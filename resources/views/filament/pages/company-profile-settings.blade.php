<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        <div style="margin-top: 1.5rem">
            <x-filament::button type="submit" icon="heroicon-o-check">Simpan profil perusahaan</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
