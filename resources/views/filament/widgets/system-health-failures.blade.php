<x-filament-widgets::widget wire:poll.30s>
    <x-filament::section heading="Kegagalan AI terbaru" description="Hanya metadata aman yang ditampilkan; token dan isi percakapan tidak disertakan." icon="heroicon-o-exclamation-triangle">
        @forelse ($failures as $failure)
            <div style="padding:.85rem 0;border-bottom:1px solid rgba(156,163,175,.2)">
                <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                    <strong>{{ $failure['event'] ?? 'AI failure' }}</strong>
                    <span style="color:rgb(156 163 175);font-size:.8rem">{{ $failure['timestamp'] ?? '-' }}</span>
                </div>
                <div style="margin-top:.35rem;display:flex;gap:.5rem;flex-wrap:wrap">
                    @foreach (collect($failure)->except(['event', 'timestamp']) as $key => $value)
                        <x-filament::badge color="gray">{{ str($key)->replace('_', ' ')->title() }}: {{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}</x-filament::badge>
                    @endforeach
                </div>
            </div>
        @empty
            <div style="padding:2rem;text-align:center">
                <x-filament::icon icon="heroicon-o-check-circle" style="width:2.5rem;height:2.5rem;margin:auto;color:rgb(34 197 94)" />
                <p style="margin-top:.75rem;font-weight:600">Tidak ada kegagalan AI terbaru</p>
                <p style="margin-top:.25rem;color:rgb(156 163 175);font-size:.875rem">Layanan berjalan tanpa error yang tercatat.</p>
            </div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
