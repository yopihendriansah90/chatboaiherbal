<?php

namespace App\Services;

use App\Contracts\DecisionEngineContract;
use App\Data\ParsedMessage;
use App\Data\ResponsePlan;
use App\Models\BusinessProfile;

class CompanyProfileEngine implements DecisionEngineContract
{
    public function buildPlan(ParsedMessage $message, array $state, BusinessProfile $business): ResponsePlan
    {
        $business->loadMissing(['companyProfile', 'contacts', 'locations', 'faqs']);
        $query = mb_strtolower((string) ($message->facts['company_query'] ?? $message->facts['complaint'] ?? ''));
        $information = $this->resolveInformation($business, $query);

        return new ResponsePlan(
            action: $information === [] ? 'company_unavailable' : 'company_inform',
            fallbackText: $information === []
                ? 'Maaf, informasi tersebut belum tersedia. Anda dapat menanyakan profil, alamat, kontak, jam operasional, pemesanan, atau layanan Walatra.'
                : implode("\n", $information),
            knownFacts: $message->facts,
            domain: 'company_profile',
            companyInformation: ['facts' => $information],
        );
    }

    public function planFromText(string $message, BusinessProfile $business): ResponsePlan
    {
        return $this->buildPlan(
            new ParsedMessage('company_info', 'high', null, false, ['company_query' => $message], 'company_profile'),
            [],
            $business,
        );
    }

    private function resolveInformation(BusinessProfile $business, string $query): array
    {
        $profile = $business->companyProfile;
        if (str_contains($query, 'alamat') || str_contains($query, 'lokasi')) {
            $location = $business->locations->where('is_active', true)->sortByDesc('is_primary')->first();

            return $location ? ["{$location->name}: {$location->address}".($location->operational_hours ? "\nJam operasional: {$location->operational_hours}" : '')] : [];
        }
        if (str_contains($query, 'kontak') || str_contains($query, 'telepon') || str_contains($query, 'whatsapp')) {
            return $business->contacts->where('is_public', true)->sortBy('sort_order')
                ->map(fn ($contact): string => "{$contact->label}: {$contact->value}")->all();
        }
        if (str_contains($query, 'jam ') || str_contains($query, 'operasional') || str_contains($query, 'buka')) {
            return filled($profile?->operational_hours) ? ["Jam operasional: {$profile->operational_hours}"] : [];
        }
        if (str_contains($query, 'visi') || str_contains($query, 'misi')) {
            return array_values(array_filter([
                $profile?->vision ? "Visi: {$profile->vision}" : null,
                $profile?->mission ? "Misi: {$profile->mission}" : null,
            ]));
        }
        if (str_contains($query, 'sejarah')) {
            return filled($profile?->history) ? [$profile->history] : [];
        }
        if (str_contains($query, 'legal')) {
            return filled($profile?->legal_information) ? [$profile->legal_information] : [];
        }

        $faq = $business->faqs->where('is_active', true)->first(function ($faq) use ($query): bool {
            $needles = array_filter(
                array_merge((array) $faq->keywords, preg_split('/\s+/u', mb_strtolower($faq->question)) ?: []),
                fn ($term) => mb_strlen((string) $term) >= 4,
            );

            return collect($needles)->contains(fn ($term): bool => str_contains($query, mb_strtolower((string) $term)));
        });
        if ($faq) {
            return [$faq->answer];
        }

        $profileSignals = ['walatra', 'perusahaan', 'profil', 'tentang perusahaan', 'siapa'];
        if (! collect($profileSignals)->contains(fn (string $signal): bool => str_contains($query, $signal))) {
            return [];
        }

        $description = $profile?->full_description ?: $profile?->short_description ?: $business->description;

        return filled($description) ? [$description] : [];
    }
}
