<?php

namespace App\Filament\Resources\ExchangeRates\Pages;

use App\Filament\Resources\ExchangeRates\ExchangeRateResource;
use App\Filament\Widgets\DollarValueOverview;
use App\Services\CurrencyFreaksService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ListExchangeRates extends ListRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configureCurrencyFreaks')
                ->label('Konfigurasi API')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->modalHeading('Konfigurasi CurrencyFreaks')
                ->modalDescription('API key disimpan terenkripsi di database dan tidak pernah ditampilkan kembali.')
                ->schema([
                    Section::make('Sumber kurs otomatis')
                        ->columns(2)
                        ->schema([
                            TextInput::make('api_key')
                                ->label('API key baru')
                                ->password()
                                ->revealable()
                                ->autocomplete(false)
                                ->helperText('Kosongkan jika tidak ingin mengganti API key yang sudah tersimpan.')
                                ->columnSpanFull(),
                            TextInput::make('api_key_status')
                                ->label('Status API key')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('last_sync_status')
                                ->label('Sinkronisasi terakhir')
                                ->disabled()
                                ->dehydrated(false),
                            Toggle::make('is_enabled')
                                ->label('Aktifkan CurrencyFreaks'),
                            Toggle::make('auto_sync')
                                ->label('Sinkronisasi otomatis harian')
                                ->helperText('Dijalankan pukul 09.00 WIB.'),
                            TextInput::make('warning_percent')
                                ->label('Batas perubahan yang perlu diperiksa')
                                ->numeric()
                                ->minValue(0.1)
                                ->maxValue(100)
                                ->step(0.01)
                                ->suffix('%')
                                ->required(),
                        ]),
                ])
                ->mountUsing(function (Schema $schema): void {
                    $source = app(CurrencyFreaksService::class)->source();
                    $schema->fill([
                        'api_key' => null,
                        'api_key_status' => filled($source->api_key) || filled(config('services.currencyfreaks.api_key'))
                            ? 'Sudah dikonfigurasi'
                            : 'Belum dikonfigurasi',
                        'last_sync_status' => $source->last_success_at
                            ? $source->last_success_at->timezone(config('app.timezone'))->format('d M Y H:i').' WIB'
                            : 'Belum pernah berhasil',
                        'is_enabled' => $source->is_enabled,
                        'auto_sync' => $source->auto_sync,
                        'warning_percent' => $source->warning_percent,
                    ]);
                })
                ->action(function (Action $action, array $data): void {
                    $source = app(CurrencyFreaksService::class)->source();
                    $enabled = (bool) ($data['is_enabled'] ?? false);
                    $hasKey = filled($data['api_key'] ?? null)
                        || filled($source->api_key)
                        || filled(config('services.currencyfreaks.api_key'));
                    if ($enabled && ! $hasKey) {
                        Notification::make()
                            ->title('API key wajib diisi')
                            ->body('Masukkan API key CurrencyFreaks sebelum mengaktifkan sumber kurs.')
                            ->danger()
                            ->send();
                        $action->halt();

                        return;
                    }
                    $values = [
                        'is_enabled' => $enabled,
                        'auto_sync' => $enabled && (bool) ($data['auto_sync'] ?? false),
                        'warning_percent' => $data['warning_percent'],
                        'updated_by' => auth()->id(),
                    ];
                    if (filled($data['api_key'] ?? null)) {
                        $values['api_key'] = trim((string) $data['api_key']);
                    }
                    $source->update($values);

                    Notification::make()
                        ->title('Konfigurasi CurrencyFreaks disimpan')
                        ->body('API key lama tetap dipakai jika kolom API key dibiarkan kosong.')
                        ->success()
                        ->send();
                }),
            Action::make('fetchCurrencyFreaks')
                ->label('Ambil kurs API')
                ->icon(Heroicon::OutlinedArrowPath)
                ->modalHeading('Periksa kurs CurrencyFreaks')
                ->modalDescription('Bandingkan data API dengan kurs aktif sebelum menyimpannya sebagai acuan baru.')
                ->modalSubmitActionLabel('Simpan sebagai kurs terbaru')
                ->schema([
                    Hidden::make('preview_token')->required(),
                    TextInput::make('api_rate')->label('Kurs dari API')->prefix('Rp')->disabled(),
                    TextInput::make('current_rate')->label('Kurs aktif saat ini')->prefix('Rp')->disabled(),
                    TextInput::make('difference')->label('Perubahan')->disabled(),
                    TextInput::make('response_at')->label('Waktu data API')->disabled(),
                    TextInput::make('warning')->label('Hasil pemeriksaan')->disabled()->columnSpanFull(),
                    Checkbox::make('confirmation')
                        ->label('Saya sudah memeriksa nilai di atas dan setuju menjadikannya acuan terbaru.')
                        ->accepted()
                        ->required()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])
                ->mountUsing(function (Action $action, Schema $schema): void {
                    try {
                        $preview = app(CurrencyFreaksService::class)->createPreview(auth()->id());
                        $schema->fill([
                            'preview_token' => $preview['token'],
                            'api_rate' => number_format($preview['rate'], 2, ',', '.'),
                            'current_rate' => $preview['current_rate'] === null
                                ? 'Belum ada'
                                : number_format($preview['current_rate'], 2, ',', '.'),
                            'difference' => $preview['difference_percent'] === null
                                ? 'Belum dapat dibandingkan'
                                : number_format($preview['difference_percent'], 2, ',', '.').'%',
                            'response_at' => $preview['response_at'],
                            'warning' => $preview['warning']
                                ? 'Perubahan melewati batas. Pastikan nilainya benar sebelum menyimpan.'
                                : 'Nilai berada dalam batas perubahan yang dikonfigurasi.',
                        ]);
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Kurs API belum dapat diambil')
                            ->body('Periksa status konfigurasi, API key, kuota, dan koneksi server. Kurs aktif lama tetap digunakan.')
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                })
                ->action(function (Action $action, array $data): void {
                    try {
                        $rate = app(CurrencyFreaksService::class)->savePreview(
                            (string) $data['preview_token'],
                            auth()->id(),
                        );
                        Notification::make()
                            ->title('Kurs terbaru berhasil disimpan')
                            ->body('1 USD = Rp '.number_format((float) $rate->rate, 2, ',', '.'))
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Kurs tidak dapat disimpan')
                            ->body('Preview mungkin sudah kedaluwarsa. Ambil kembali data API dan ulangi pemeriksaan.')
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
            CreateAction::make()
                ->label('Tambah Nilai Dolar Terbaru')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [DollarValueOverview::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
