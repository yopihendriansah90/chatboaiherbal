<?php

namespace App\Services;

use App\Models\BotSetting;
use App\Models\BusinessProfile;
use App\Models\DomainPack;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BusinessProfileResolver
{
    public function current(): ?BusinessProfile
    {
        try {
            if (! Schema::hasTable('business_profiles')) {
                return null;
            }
            $businessId = Schema::hasTable('bot_settings')
                ? BotSetting::query()->where('is_active', true)->value('business_profile_id')
                : null;

            return BusinessProfile::query()
                ->where('is_active', true)
                ->when($businessId, fn ($query) => $query->whereKey($businessId))
                ->with(['domainPacks' => fn ($query) => $query->where('domain_packs.is_active', true)])
                ->first()
                ?? BusinessProfile::query()->where('slug', 'walatra-herbal')->where('is_active', true)->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function currentOrFail(): BusinessProfile
    {
        return $this->current() ?? throw new \RuntimeException('Business profile aktif belum tersedia.');
    }

    public function enabledDomains(): array
    {
        $business = $this->current();
        if (! $business) {
            return ['health_herbal'];
        }

        return $business->domainPacks
            ->filter(fn ($domain) => (bool) $domain->pivot->is_enabled)
            ->sortBy(fn ($domain) => (int) $domain->pivot->priority)
            ->pluck('code')->values()->all();
    }

    public function defaultDomain(): string
    {
        $business = $this->current();
        $default = $business?->domainPacks
            ->first(fn ($domain) => (bool) $domain->pivot->is_enabled && (bool) $domain->pivot->is_default);

        return $default?->code ?? 'health_herbal';
    }

    public function domainOptions(): array
    {
        try {
            if (! Schema::hasTable('domain_packs')) {
                return ['health_herbal' => 'AI Asisten Herbal'];
            }

            return DomainPack::query()->where('is_active', true)->orderBy('id')->pluck('name', 'code')->all();
        } catch (Throwable) {
            return ['health_herbal' => 'AI Asisten Herbal'];
        }
    }
}
