<?php

namespace App\Filament\Pages;

use App\Models\CompanyContact;
use App\Models\CompanyFaq;
use App\Models\CompanyLocation;
use App\Services\BusinessProfileResolver;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class CompanyProfileSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Walatra Herbal';

    protected static ?string $navigationLabel = 'Profil Perusahaan';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.company-profile-settings';

    public array $data = [];

    public function mount(BusinessProfileResolver $resolver): void
    {
        $business = $resolver->current()?->load(['companyProfile', 'contacts', 'locations', 'faqs']);
        abort_unless($business, 404);
        $profile = $business->companyProfile;
        $this->form->fill([
            'name' => $business->name,
            'bot_name' => $business->bot_name,
            'description' => $business->description,
            'legal_name' => $profile?->legal_name,
            'short_description' => $profile?->short_description,
            'full_description' => $profile?->full_description,
            'history' => $profile?->history,
            'vision' => $profile?->vision,
            'mission' => $profile?->mission,
            'legal_information' => $profile?->legal_information,
            'operational_hours' => $profile?->operational_hours,
            'contacts' => $business->contacts->map->only(['type', 'label', 'value', 'is_primary', 'is_public', 'sort_order'])->all(),
            'locations' => $business->locations->map->only(['name', 'address', 'city', 'province', 'postal_code', 'maps_url', 'operational_hours', 'is_primary', 'is_active'])->all(),
            'faqs' => $business->faqs->map(fn ($faq) => [
                'category' => $faq->category, 'question' => $faq->question, 'answer' => $faq->answer,
                'keywords_text' => implode(', ', $faq->keywords ?? []), 'sort_order' => $faq->sort_order, 'is_active' => $faq->is_active,
            ])->all(),
        ]);
    }

    public function getTitle(): string
    {
        return 'Profil Perusahaan Walatra';
    }

    public function getSubheading(): string
    {
        return 'Seluruh jawaban Domain Pack Profile Company diambil dari informasi tervalidasi pada halaman ini.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make('Profil')->persistTabInQueryString()->tabs([
                Tab::make('Identitas')->schema([
                    Section::make('Branding')->columns(2)->schema([
                        TextInput::make('name')->label('Nama bisnis')->required()->maxLength(255),
                        TextInput::make('bot_name')->label('Nama chatbot')->required()->maxLength(255),
                        Textarea::make('description')->label('Deskripsi internal')->rows(2)->columnSpanFull(),
                    ]),
                    Section::make('Informasi perusahaan')->schema([
                        TextInput::make('legal_name')->label('Nama legal perusahaan')->maxLength(255),
                        Textarea::make('short_description')->label('Deskripsi singkat')->rows(3),
                        Textarea::make('full_description')->label('Profil lengkap')->rows(6),
                        Textarea::make('history')->label('Sejarah')->rows(4),
                        Textarea::make('vision')->label('Visi')->rows(3),
                        Textarea::make('mission')->label('Misi')->rows(3),
                        Textarea::make('legal_information')->label('Informasi legalitas')->rows(3),
                        Textarea::make('operational_hours')->label('Jam operasional')->rows(2),
                    ])->columns(2),
                ]),
                Tab::make('Kontak')->schema([
                    Repeater::make('contacts')->label('Kontak publik')->schema([
                        Select::make('type')->options(['phone' => 'Telepon', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'website' => 'Website', 'instagram' => 'Instagram', 'marketplace' => 'Marketplace'])->required(),
                        TextInput::make('label')->required(),
                        TextInput::make('value')->required()->maxLength(2048),
                        TextInput::make('sort_order')->numeric()->default(10)->required(),
                        Toggle::make('is_primary')->label('Utama'),
                        Toggle::make('is_public')->label('Tampilkan')->default(true),
                    ])->columns(3)->defaultItems(0)->reorderable(),
                ]),
                Tab::make('Lokasi')->schema([
                    Repeater::make('locations')->label('Lokasi perusahaan')->schema([
                        TextInput::make('name')->label('Nama lokasi')->required(),
                        Textarea::make('address')->label('Alamat')->required()->columnSpan(2),
                        TextInput::make('city')->label('Kota'),
                        TextInput::make('province')->label('Provinsi'),
                        TextInput::make('postal_code')->label('Kode pos'),
                        TextInput::make('maps_url')->label('Google Maps URL')->url()->columnSpan(2),
                        Textarea::make('operational_hours')->label('Jam operasional')->columnSpan(2),
                        Toggle::make('is_primary')->label('Lokasi utama'),
                        Toggle::make('is_active')->label('Aktif')->default(true),
                    ])->columns(3)->defaultItems(0)->reorderable(),
                ]),
                Tab::make('FAQ')->schema([
                    Repeater::make('faqs')->label('Pertanyaan umum')->schema([
                        Select::make('category')->options(['company' => 'Perusahaan', 'ordering' => 'Pemesanan', 'shipping' => 'Pengiriman', 'payment' => 'Pembayaran', 'reseller' => 'Reseller', 'product' => 'Produk', 'legal' => 'Legalitas', 'contact' => 'Kontak'])->required(),
                        TextInput::make('question')->label('Pertanyaan')->required()->columnSpan(2),
                        Textarea::make('answer')->label('Jawaban tervalidasi')->required()->columnSpanFull(),
                        TextInput::make('keywords_text')->label('Kata kunci, pisahkan koma')->columnSpan(2),
                        TextInput::make('sort_order')->numeric()->default(10)->required(),
                        Toggle::make('is_active')->label('Aktif')->default(true),
                    ])->columns(3)->defaultItems(0)->reorderable(),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public function save(BusinessProfileResolver $resolver): void
    {
        $business = $resolver->current();
        abort_unless($business, 404);
        $data = $this->form->getState();
        DB::transaction(function () use ($business, $data): void {
            $business->update(['name' => $data['name'], 'bot_name' => $data['bot_name'], 'description' => $data['description'] ?? null]);
            $business->companyProfile()->updateOrCreate([], collect($data)->only([
                'legal_name', 'short_description', 'full_description', 'history', 'vision', 'mission', 'legal_information', 'operational_hours',
            ])->all());
            $business->contacts()->delete();
            foreach ($data['contacts'] ?? [] as $item) {
                CompanyContact::query()->create($item + ['business_profile_id' => $business->id]);
            }
            $business->locations()->delete();
            foreach ($data['locations'] ?? [] as $item) {
                CompanyLocation::query()->create($item + ['business_profile_id' => $business->id]);
            }
            $business->faqs()->delete();
            foreach ($data['faqs'] ?? [] as $item) {
                $keywords = array_values(array_filter(array_map('trim', explode(',', (string) ($item['keywords_text'] ?? '')))));
                unset($item['keywords_text']);
                CompanyFaq::query()->create($item + ['business_profile_id' => $business->id, 'keywords' => $keywords]);
            }
        });

        Notification::make()->title('Profil perusahaan disimpan')->body('Informasi baru langsung digunakan oleh Domain Pack Profile Company.')->success()->send();
    }
}
