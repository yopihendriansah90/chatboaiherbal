<?php

namespace App\Filament\Pages;

use App\Services\AiProviderResolver;
use App\Services\BotConfiguration;
use App\Services\BusinessProfileResolver;
use App\Services\TelegramClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnitEnum;

class BotSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?string $navigationLabel = 'Pengaturan Bot';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.bot-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(BotConfiguration $configuration): void
    {
        $this->form->fill($configuration->formData());
    }

    public function getTitle(): string
    {
        return 'Pengaturan Bot';
    }

    public function getSubheading(): string
    {
        return 'Kelola Telegram, strategi model AI, dan perilaku percakapan tanpa mengubah file .env.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Pengaturan')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Telegram')
                            ->icon('heroicon-o-paper-airplane')
                            ->schema([
                                Section::make('Kredensial Telegram')
                                    ->description('Kosongkan input secret bila tidak ingin mengganti nilai yang sudah tersimpan.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('telegram_bot_token')
                                            ->label('Bot token baru')
                                            ->password()
                                            ->revealable()
                                            ->autocomplete('new-password')
                                            ->placeholder(fn (): string => filled(config('services.telegram.token')) ? 'Token sudah tersimpan' : '123456:ABC...')
                                            ->maxLength(512),
                                        TextInput::make('telegram_webhook_secret')
                                            ->label('Webhook secret baru')
                                            ->password()
                                            ->revealable()
                                            ->autocomplete('new-password')
                                            ->placeholder(fn (): string => filled(config('services.telegram.webhook_secret')) ? 'Secret sudah tersimpan' : 'Gunakan nilai acak')
                                            ->rules(['nullable', 'regex:/^[A-Za-z0-9_-]+$/', 'max:256'])
                                            ->validationMessages([
                                                'regex' => 'Webhook secret hanya boleh berisi huruf, angka, tanda hubung (-), dan garis bawah (_).',
                                                'max' => 'Webhook secret terlalu panjang. Gunakan maksimal 256 karakter ya.',
                                            ]),
                                        TextInput::make('telegram_webhook_url')
                                            ->label('Webhook URL')
                                            ->url()
                                            ->rules(['nullable', 'starts_with:https://'])
                                            ->placeholder('https://domain.example/api/telegram/webhook')
                                            ->helperText('Gunakan alamat HTTPS lengkap yang menuju ke /api/telegram/webhook.')
                                            ->mutateStateForValidationUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null)
                                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null)
                                            ->validationMessages([
                                                'url' => 'Alamat webhook belum sesuai. Masukkan URL lengkap, contohnya https://domain.example/api/telegram/webhook.',
                                                'starts_with' => 'Webhook Telegram harus memakai HTTPS agar koneksinya aman.',
                                            ])
                                            ->columnSpanFull(),
                                        TextInput::make('telegram_timeout')
                                            ->label('Timeout Telegram')
                                            ->numeric()
                                            ->suffix('detik')
                                            ->required()
                                            ->minValue(3)
                                            ->maxValue(60)
                                            ->validationMessages([
                                                'required' => 'Waktu tunggu Telegram perlu diisi terlebih dahulu.',
                                                'numeric' => 'Waktu tunggu Telegram harus berupa angka.',
                                                'min' => 'Waktu tunggu Telegram minimal 3 detik ya.',
                                                'max' => 'Waktu tunggu Telegram maksimal 60 detik ya.',
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Strategi AI')
                            ->icon('heroicon-o-cpu-chip')
                            ->schema([
                                Section::make('Model utama')
                                    ->description('Pilih model aktif yang sudah ditambahkan pada AI Provider. Parser wajib mendukung structured output.')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('parser_ai_model_id')
                                            ->label('Model parser utama')
                                            ->options(fn (): array => app(AiProviderResolver::class)->parserModelOptions())
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Mengubah pesan menjadi fakta JSON terstruktur.'),
                                        Select::make('renderer_ai_model_id')
                                            ->label('Model natural renderer')
                                            ->options(fn (): array => app(AiProviderResolver::class)->rendererModelOptions())
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Memperhalus pembuka respons tanpa memilih produk.'),
                                        Toggle::make('parser_fallback_enabled')
                                            ->label('Aktifkan fallback model')
                                            ->helperText('Model berikutnya digunakan saat model utama timeout, rate limit, atau respons invalid.')
                                            ->live()
                                            ->columnSpanFull(),
                                        Select::make('fallback_ai_model_ids')
                                            ->label('Urutan model fallback')
                                            ->options(fn (): array => app(AiProviderResolver::class)->parserModelOptions())
                                            ->multiple()
                                            ->reorderable()
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn ($get): bool => (bool) $get('parser_fallback_enabled'))
                                            ->columnSpanFull(),
                                        Toggle::make('natural_renderer_enabled')
                                            ->label('Aktifkan natural renderer')
                                            ->helperText('Jika dimatikan atau gagal, bot langsung memakai template Laravel.')
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Cara pengaturan')
                                    ->description('API key dan daftar model dikelola melalui menu Operasional → AI Providers. Harga token dikelola pada model masing-masing.')
                                    ->compact(),
                            ]),
                        Tab::make('Domain Pack')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Section::make('Layanan chatbot')
                                    ->description('Walatra dapat melayani informasi perusahaan dan konsultasi herbal dalam satu chatbot.')
                                    ->schema([
                                        CheckboxList::make('enabled_domain_codes')
                                            ->label('Domain aktif')
                                            ->options(fn (): array => app(BusinessProfileResolver::class)->domainOptions())
                                            ->required()
                                            ->live()
                                            ->columns(2),
                                        Select::make('default_domain_code')
                                            ->label('Domain utama')
                                            ->options(fn (): array => app(BusinessProfileResolver::class)->domainOptions())
                                            ->required()
                                            ->in(fn ($get): array => $get('enabled_domain_codes') ?: ['health_herbal']),
                                        Toggle::make('allow_domain_switching')
                                            ->label('Izinkan perpindahan domain dalam percakapan')
                                            ->helperText('Pengguna dapat beralih dari pertanyaan perusahaan ke konsultasi herbal tanpa /reset.'),
                                        Select::make('ambiguous_domain_behavior')
                                            ->label('Jika domain tidak jelas')
                                            ->options(['clarify' => 'Tanyakan klarifikasi'])
                                            ->required(),
                                    ]),
                            ]),
                        Tab::make('Percakapan')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make('Memori dan respons')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('memory_ttl_hours')
                                            ->label('Masa ingatan')
                                            ->numeric()
                                            ->suffix('jam')
                                            ->required()
                                            ->minValue(1)
                                            ->maxValue(168),
                                        TextInput::make('history_limit')
                                            ->label('Batas riwayat')
                                            ->numeric()
                                            ->suffix('pesan')
                                            ->required()
                                            ->minValue(2)
                                            ->maxValue(30),
                                        TextInput::make('renderer_max_words')
                                            ->label('Batas kata renderer')
                                            ->numeric()
                                            ->suffix('kata')
                                            ->required()
                                            ->minValue(15)
                                            ->maxValue(100),
                                    ]),
                                Section::make('Penyimpanan riwayat')
                                    ->description('Atur pencatatan percakapan untuk monitoring internal dan masa penyimpanannya.')
                                    ->columns(3)
                                    ->schema([
                                        Toggle::make('chat_history_enabled')
                                            ->label('Simpan riwayat chat')
                                            ->helperText('Jika dimatikan, bot tetap berjalan tetapi pengguna dan pesan baru tidak dicatat.')
                                            ->columnSpanFull(),
                                        TextInput::make('chat_history_retention_days')
                                            ->label('Retensi pesan')
                                            ->numeric()
                                            ->suffix('hari')
                                            ->required()
                                            ->minValue(1)
                                            ->maxValue(3650),
                                        TextInput::make('inactive_contact_days')
                                            ->label('Pengguna dianggap tidak aktif')
                                            ->numeric()
                                            ->suffix('hari')
                                            ->required()
                                            ->minValue(1)
                                            ->maxValue(3650),
                                    ]),
                            ]),
                        Tab::make('Status')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Sumber konfigurasi')
                                    ->description('Saat aktif, nilai database menggantikan .env. Nilai .env tetap menjadi fallback untuk secret yang belum diisi.')
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->label('Gunakan konfigurasi panel')
                                            ->default(true),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(BotConfiguration $configuration): void
    {
        $values = $this->form->getState();
        unset($values['telegram_token_configured'], $values['webhook_secret_configured'], $values['groq_key_configured']);

        $resolver = app(AiProviderResolver::class);
        $parserModel = $resolver->model((int) ($values['parser_ai_model_id'] ?? 0));
        $rendererModel = $resolver->model((int) ($values['renderer_ai_model_id'] ?? 0));
        if ($parserModel) {
            $values['parser_provider'] = $parserModel->provider->provider;
            $values['parser_model'] = $parserModel->model_id;
        }
        if ($rendererModel) {
            $values['renderer_provider'] = $rendererModel->provider->provider;
            $values['renderer_model'] = $rendererModel->model_id;
        }
        $values['parser_fallback_order'] = collect($values['fallback_ai_model_ids'] ?? [])
            ->reject(fn ($id) => (int) $id === (int) ($values['parser_ai_model_id'] ?? 0))
            ->map(fn ($id) => $resolver->model((int) $id)?->provider?->provider)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $values['fallback_ai_model_ids'] = collect($values['fallback_ai_model_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) ($values['parser_ai_model_id'] ?? 0))
            ->unique()
            ->values()
            ->all();

        $configuration->save($values, auth()->id());
        $this->form->fill($configuration->formData());

        Notification::make()
            ->title('Pengaturan bot disimpan')
            ->body('Konfigurasi baru langsung digunakan oleh aplikasi.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testTelegram')
                ->label('Uji Telegram')
                ->icon('heroicon-o-paper-airplane')
                ->action(fn () => $this->telegramAction('getMe', successTitle: 'Telegram terhubung')),
            ActionGroup::make([
                Action::make('webhookInfo')
                    ->label('Periksa webhook')
                    ->action(fn () => $this->telegramAction('getWebhookInfo', successTitle: 'Informasi webhook diterima', showWebhook: true)),
                Action::make('setWebhook')
                    ->label('Pasang webhook')
                    ->requiresConfirmation()
                    ->action(fn () => $this->setWebhook()),
                Action::make('deleteWebhook')
                    ->label('Hapus webhook')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn () => $this->telegramAction('deleteWebhook', successTitle: 'Webhook dihapus')),
            ])
                ->label('Webhook')
                ->icon('heroicon-o-link')
                ->button(),
        ];
    }

    private function setWebhook(): void
    {
        $configuration = app(BotConfiguration::class);
        $url = $configuration->telegramWebhookUrl();
        $secret = $configuration->telegramWebhookSecret();
        if (blank($url) || blank($secret)) {
            Notification::make()->title('Webhook belum lengkap')->body('Simpan Webhook URL dan secret terlebih dahulu.')->danger()->send();

            return;
        }

        $this->telegramAction('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'my_chat_member'],
        ], 'Webhook berhasil dipasang');
    }

    private function telegramAction(string $method, array $payload = [], string $successTitle = 'Telegram berhasil dihubungi', bool $showWebhook = false): void
    {
        try {
            $result = app(TelegramClient::class)->call($method, $payload);
            $body = null;
            if ($showWebhook) {
                $body = 'URL: '.($result['result']['url'] ?? 'belum terpasang').' · Pending: '.($result['result']['pending_update_count'] ?? 0);
            } elseif ($method === 'getMe') {
                $body = 'Bot: @'.($result['result']['username'] ?? '-');
            }

            Notification::make()->title($successTitle)->body($body)->success()->send();
        } catch (Throwable $exception) {
            Log::warning('Admin Telegram connectivity test failed', [
                'method' => $method,
                'exception' => $exception::class,
            ]);
            Notification::make()->title('Telegram tidak dapat dihubungi')->body('Periksa token, URL, dan koneksi server.')->danger()->send();
        }
    }
}
