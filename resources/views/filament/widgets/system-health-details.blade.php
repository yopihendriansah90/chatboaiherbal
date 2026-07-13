<x-filament-widgets::widget wire:poll.30s>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem">
        @foreach ([
            'Runtime aplikasi' => [
                'Environment' => data_get($health, 'runtime.environment', '-'),
                'PHP' => data_get($health, 'runtime.php', '-'),
                'Laravel' => data_get($health, 'runtime.laravel', '-'),
                'Timezone' => data_get($health, 'runtime.timezone', '-'),
                'Memory' => data_get($health, 'runtime.memory_mb', 0).' MB',
            ],
            'Telegram webhook' => [
                'Status' => data_get($health, 'telegram.configured') ? 'Terkonfigurasi' : 'Belum lengkap',
                'Host' => data_get($health, 'telegram.webhook.host', '-'),
                'Path' => data_get($health, 'telegram.webhook.path', '-'),
                'Timeout' => data_get($health, 'telegram.timeout_seconds', 0).' detik',
            ],
            'Model AI' => [
                'Parser provider' => data_get($health, 'ai.parser.provider', '-'),
                'API key' => data_get($health, 'ai.api_key_configured') ? 'Tersedia' : 'Belum tersedia',
                'Parser' => data_get($health, 'ai.parser.model', '-'),
                'Renderer provider' => data_get($health, 'ai.renderer.provider', '-'),
                'Renderer' => data_get($health, 'ai.renderer.model', '-'),
                'Renderer aktif' => data_get($health, 'ai.renderer.enabled') ? 'Ya' : 'Tidak',
            ],
            'Data chatbot' => [
                'Sumber konfigurasi' => data_get($health, 'configuration.source', 'environment'),
                'Versi state' => data_get($health, 'conversation.state_version', '-'),
                'Cache store' => data_get($health, 'conversation.cache_store', '-'),
                'Masa ingatan' => data_get($health, 'conversation.memory_ttl_hours', 0).' jam',
                'Produk' => data_get($health, 'catalog.products', 0),
                'Komposisi' => data_get($health, 'catalog.composition_rows', 0).' baris',
            ],
        ] as $title => $items)
            <x-filament::section :heading="$title" compact>
                <div style="display:grid;gap:.75rem">
                    @foreach ($items as $label => $value)
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
                            <span style="color:rgb(156 163 175);font-size:.875rem">{{ $label }}</span>
                            <strong style="font-size:.875rem;text-align:right;overflow-wrap:anywhere">{{ $value }}</strong>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <div style="margin-top:1rem">
        <x-filament::section heading="Pemeriksaan komponen" description="Diperbarui otomatis setiap 30 detik." compact>
            <div style="display:flex;flex-wrap:wrap;gap:.6rem">
                @foreach (($health['checks'] ?? []) as $name => $status)
                    <x-filament::badge :color="match ($status) { 'ok' => 'success', 'disabled' => 'gray', 'degraded' => 'warning', default => 'danger' }" size="lg">
                        {{ str($name)->replace('_', ' ')->title() }}: {{ strtoupper($status) }}
                    </x-filament::badge>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
