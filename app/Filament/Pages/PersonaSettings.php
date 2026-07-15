<?php

namespace App\Filament\Pages;

use App\Services\PersonaConfiguration;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class PersonaSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-face-smile';

    protected static string|UnitEnum|null $navigationGroup = 'Chatbot';

    protected static ?string $navigationLabel = 'Persona Chatbot';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.persona-settings';

    public array $data = [];

    public function mount(PersonaConfiguration $personas): void
    {
        $data = $personas->current();
        $data['tone_rules_text'] = implode("\n", $data['tone_rules'] ?? []);
        $this->form->fill($data);
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('supervisor', 'content_reviewer');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Karakter asisten')->columns(2)->schema([
                TextInput::make('name')->label('Nama asisten')->required()->maxLength(100),
                Select::make('formality')->label('Gaya bahasa')->options([
                    'friendly' => 'Ramah sehari-hari',
                    'professional' => 'Profesional hangat',
                    'formal' => 'Formal',
                ])->required(),
                Select::make('empathy_style')->label('Gaya empati')->options([
                    'brief_relevant' => 'Singkat dan relevan',
                    'supportive' => 'Lebih suportif',
                    'neutral' => 'Netral',
                ])->required(),
                Select::make('emoji_policy')->label('Penggunaan emoji')->options([
                    'none' => 'Tanpa emoji',
                    'minimal' => 'Minimal',
                    'friendly' => 'Ramah',
                ])->required(),
                TextInput::make('max_words')
                    ->label('Batas kata')
                    ->helperText('Berlaku langsung sebagai batas respons persona. Natural renderer tetap mengikuti batas global bila nilainya lebih kecil.')
                    ->numeric()->minValue(20)->maxValue(250)->required(),
                Textarea::make('tone_rules_text')->label('Aturan gaya, satu per baris')->rows(8)->maxLength(5000)->columnSpanFull(),
            ]),
        ]);
    }

    public function save(PersonaConfiguration $personas): void
    {
        $personas->save($this->form->getState(), auth()->id());
        Notification::make()->title('Persona chatbot disimpan')->success()->send();
    }
}
