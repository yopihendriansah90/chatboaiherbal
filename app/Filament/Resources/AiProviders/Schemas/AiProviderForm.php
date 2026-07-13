<?php

namespace App\Filament\Resources\AiProviders\Schemas;

use App\Models\AiProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AiProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Koneksi provider')
                    ->description('API key lama tidak pernah ditampilkan. Kosongkan untuk mempertahankan key yang tersimpan.')
                    ->columns(2)
                    ->schema([
                        Select::make('provider')
                            ->options(array_combine(AiProvider::TYPES, array_map('ucfirst', AiProvider::TYPES)))
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        TextInput::make('name')->required()->maxLength(100),
                        TextInput::make('api_key')
                            ->label('API key baru')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->placeholder('API key sudah tersimpan atau belum diisi')
                            ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(1024)
                            ->columnSpanFull(),
                        Toggle::make('is_enabled')->label('Provider aktif')->default(true),
                        TextInput::make('priority')->numeric()->minValue(1)->maxValue(10)->required(),
                    ]),
                Section::make('Timeout lanjutan')
                    ->description('Daftar model dan harga dikelola melalui tab Model setelah koneksi provider disimpan.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('parser_timeout')->numeric()->suffix('detik')->minValue(5)->maxValue(120)->required(),
                        TextInput::make('renderer_timeout')->numeric()->suffix('detik')->minValue(3)->maxValue(60)->required(),
                    ]),
            ]);
    }
}
