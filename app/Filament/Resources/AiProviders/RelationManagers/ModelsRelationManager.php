<?php

namespace App\Filament\Resources\AiProviders\RelationManagers;

use App\Models\AiModel;
use App\Models\ExchangeRate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ModelsRelationManager extends RelationManager
{
    protected static string $relationship = 'models';

    protected static ?string $title = 'Model';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-cube-transparent';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->models()->where('status', '!=', 'archived')->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas model')
                ->columns(2)
                ->schema([
                    TextInput::make('display_name')->label('Nama tampilan')->required()->maxLength(255),
                    TextInput::make('model_id')
                        ->label('Model ID API')
                        ->required()
                        ->maxLength(255)
                        ->rules(fn (?AiModel $record): array => [
                            Rule::unique('ai_models', 'model_id')
                                ->where('ai_provider_id', $this->getOwnerRecord()->id)
                                ->ignore($record?->id),
                        ])
                        ->helperText('Harus sama persis dengan model ID dari provider.'),
                    Select::make('status')
                        ->options(AiModel::STATUSES)
                        ->default('active')
                        ->required()
                        ->helperText('Sebelum mengarsipkan model, pindahkan assignment-nya di Pengaturan Bot.'),
                    TextInput::make('context_window')->label('Context window')->numeric()->minValue(1)->suffix('token'),
                    TextInput::make('sort_order')->label('Urutan')->numeric()->minValue(1)->default(10)->required(),
                ]),
            Section::make('Kemampuan')
                ->description('Pilihan Strategi Bot hanya menampilkan model yang sesuai dengan kemampuan ini.')
                ->columns(3)
                ->schema([
                    Toggle::make('can_parse')->label('Bisa menjadi parser')->default(true),
                    Toggle::make('supports_structured_output')->label('Structured output')->default(true),
                    Toggle::make('can_render')->label('Bisa menjadi renderer')->default(true),
                    Textarea::make('notes')->label('Catatan')->rows(3)->maxLength(1000)->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->emptyStateHeading('Belum ada model')
            ->emptyStateDescription('Tambahkan hanya model yang benar-benar akan digunakan chatbot.')
            ->columns([
                TextColumn::make('display_name')->label('Model')->weight('bold')->searchable()
                    ->description(fn (AiModel $record): string => $record->model_id),
                TextColumn::make('capabilities')
                    ->label('Kemampuan')
                    ->state(fn (AiModel $record): array => array_values(array_filter([
                        $record->can_parse ? 'Parser' : null,
                        $record->can_render ? 'Renderer' : null,
                        $record->supports_structured_output ? 'JSON' : null,
                    ])))
                    ->badge(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => AiModel::STATUSES[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'recommended' => 'success',
                        'archived' => 'gray',
                        default => 'primary',
                    }),
                TextColumn::make('assignment')
                    ->label('Dipakai sebagai')
                    ->state(fn (AiModel $record): array => array_values(array_filter([
                        (int) config('chatbot.parser_ai_model_id') === $record->id ? 'Parser utama' : null,
                        (int) config('chatbot.renderer_ai_model_id') === $record->id ? 'Renderer' : null,
                        in_array($record->id, array_map('intval', (array) config('chatbot.fallback_ai_model_ids', [])), true) ? 'Fallback' : null,
                    ])))
                    ->badge()
                    ->placeholder('Belum dipakai'),
                TextColumn::make('price_usd')
                    ->label('Harga / 1M token (USD)')
                    ->state(function (AiModel $record): ?string {
                        $price = $record->currentPrice();

                        return $price
                            ? 'Input $'.number_format((float) $price->input_price_per_million_usd, 6).' · Output $'.number_format((float) $price->output_price_per_million_usd, 6)
                            : null;
                    })
                    ->placeholder('Harga belum diatur')
                    ->wrap(),
                TextColumn::make('price_idr')
                    ->label('Estimasi / 1M token (IDR)')
                    ->state(function (AiModel $record): ?string {
                        $price = $record->currentPrice();
                        $rate = ExchangeRate::current();
                        if (! $price || ! $rate) {
                            return null;
                        }

                        return 'Input Rp'.number_format((float) $price->input_price_per_million_usd * (float) $rate->rate, 2, ',', '.')
                            .' · Output Rp'.number_format((float) $price->output_price_per_million_usd * (float) $rate->rate, 2, ',', '.');
                    })
                    ->placeholder('Harga atau nilai dolar belum diatur')
                    ->wrap(),
                TextColumn::make('context_window')->label('Context')->numeric()->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah model')->icon('heroicon-o-plus'),
            ])
            ->recordActions([
                Action::make('setPrice')
                    ->label('Perbarui harga')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->schema($this->priceSchema())
                    ->action(function (AiModel $record, array $data): void {
                        $record->prices()->create([
                            ...$data,
                            'ai_provider_id' => $record->ai_provider_id,
                            'model' => $record->model_id,
                            'is_active' => true,
                            'updated_by' => auth()->id(),
                        ]);
                    })
                    ->successNotificationTitle('Harga model baru sudah aktif'),
                Action::make('priceHistory')
                    ->label('Riwayat harga')
                    ->icon('heroicon-o-clock')
                    ->modalHeading(fn (AiModel $record): string => 'Riwayat harga '.$record->display_name)
                    ->modalContent(fn (AiModel $record) => view('filament.ai-models.price-history', [
                        'prices' => $record->prices()->latest('effective_at')->latest('id')->get(),
                        'rate' => ExchangeRate::current(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                EditAction::make(),
            ]);
    }

    private function priceSchema(): array
    {
        return [
            TextInput::make('input_price_per_million_usd')->label('Input / 1 juta token')->prefix('$')->numeric()->minValue(0)->step(0.00000001)->required(),
            TextInput::make('cached_input_price_per_million_usd')->label('Cached input / 1 juta token')->prefix('$')->numeric()->minValue(0)->step(0.00000001),
            TextInput::make('output_price_per_million_usd')->label('Output / 1 juta token')->prefix('$')->numeric()->minValue(0)->step(0.00000001)->required(),
            DateTimePicker::make('effective_at')->label('Berlaku sejak')->seconds(false)->maxDate(now())->default(now())->required(),
            TextInput::make('source_url')->label('URL harga resmi')->url()->maxLength(2048),
        ];
    }
}
