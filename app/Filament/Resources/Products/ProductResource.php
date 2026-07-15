<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\ClaimsRelationManager;
use App\Filament\Resources\Products\RelationManagers\ContraindicationsRelationManager;
use App\Filament\Resources\Products\RelationManagers\IngredientsRelationManager;
use App\Filament\Resources\Products\RelationManagers\LinksRelationManager;
use App\Filament\Resources\Products\RelationManagers\PricesRelationManager;
use App\Models\BusinessProfile;
use App\Models\Product;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|\UnitEnum|null $navigationGroup = 'Walatra Herbal';

    protected static ?string $navigationLabel = 'Produk Herbal';

    protected static ?string $modelLabel = 'produk herbal';

    protected static ?string $pluralModelLabel = 'Produk Herbal';

    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('content_reviewer') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make()
                ->schema([
                    Group::make([
                        Section::make('Identitas & status')
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                Select::make('business_profile_id')->label('Bisnis')->options(BusinessProfile::query()->pluck('name', 'id'))->required(),
                                TextInput::make('code')->label('Kode')->required()->maxLength(50)->unique(ignoreRecord: true),
                                TextInput::make('name')->label('Nama produk')->required()->maxLength(255),
                                TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true)->readonly()->disabled(),
                                Select::make('status')->options(['active' => 'Aktif', 'archived' => 'Diarsipkan'])->required(),
                                Toggle::make('is_active')->label('Dapat direkomendasikan'),
                            ]),
                        Section::make('Stok')
                            ->relationship('inventory')
                            ->description('Matikan pelacakan jika chatbot tidak boleh menyebut status stok.')
                            ->columns(['default' => 1, 'md' => 3])
                            ->schema([
                                Toggle::make('track_stock')->label('Lacak stok'),
                                TextInput::make('available_quantity')->label('Tersedia')->numeric()->minValue(0)->default(0)->required(),
                                TextInput::make('reserved_quantity')->label('Dipesan')->numeric()->minValue(0)->default(0)->required()->lte('available_quantity'),
                            ]),
                    ])->columnSpanFull(),

                        // ->columnSpan(['default' => 1, 'xl' => 5]),
                    Group::make([
                        Section::make('Deskripsi produk')
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                Textarea::make('short_description')->label('Deskripsi singkat')->rows(3),
                                Textarea::make('full_description')->label('Deskripsi lengkap')->rows(3),
                            ]),
                        Section::make('Detail produk')
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('package_content')->label('Isi / kemasan')->maxLength(255),
                                TextInput::make('dosage_form')->label('Bentuk sediaan')->maxLength(80),
                                TextInput::make('manufacturer')->label('Produsen')->maxLength(255),
                                Textarea::make('usage_instruction')->label('Aturan konsumsi')->rows(2),
                            ]),
                        Section::make('Legalitas & sumber')
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('registration_number')->label('Nomor registrasi')->maxLength(100),
                                TextInput::make('halal_status')->label('Status halal')->maxLength(100),
                                TextInput::make('source_document')->label('Dokumen sumber')->maxLength(255),
                                TextInput::make('source_page')->label('Halaman sumber')->numeric()->minValue(1),
                                Textarea::make('additional_notes')->label('Catatan tambahan')->rows(2)->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
                        // ->columnSpan(['default' => 1, 'xl' => 7]),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('code')->columns([
            TextColumn::make('code')->label('Kode')->badge()->searchable()->sortable(),
            TextColumn::make('name')->label('Produk')->weight('bold')->searchable()->sortable(),
            TextColumn::make('categories.name')->label('Kategori')->badge()->limitList(3),
            TextColumn::make('ingredients_count')->label('Komposisi')->counts('ingredients')->numeric(),
            TextColumn::make('claims_count')->label('Klaim')->counts('claims')->numeric(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
            TextColumn::make('updated_at')->label('Diperbarui')->since(),
        ])->recordUrl(fn (Product $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [IngredientsRelationManager::class, ClaimsRelationManager::class, ContraindicationsRelationManager::class, PricesRelationManager::class, LinksRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListProducts::route('/'), 'edit' => EditProduct::route('/{record}/edit')];
    }
}
