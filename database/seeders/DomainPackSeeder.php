<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use App\Models\DomainPack;
use Illuminate\Database\Seeder;

class DomainPackSeeder extends Seeder
{
    public function run(): void
    {
        $business = BusinessProfile::query()->where('slug', 'walatra-herbal')->firstOrFail();
        $packs = [
            'health_herbal' => ['name' => 'AI Asisten Herbal', 'description' => 'Memahami keluhan, melakukan screening, dan merekomendasikan produk herbal.', 'engine_type' => 'HerbalDecisionEngine', 'priority' => 10, 'default' => true],
            'company_profile' => ['name' => 'Profile Company', 'description' => 'Menjawab informasi perusahaan, lokasi, kontak, pemesanan, dan FAQ.', 'engine_type' => 'CompanyProfileEngine', 'priority' => 20, 'default' => false],
        ];

        foreach ($packs as $code => $data) {
            $pack = DomainPack::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $data['name'], 'description' => $data['description'], 'engine_type' => $data['engine_type'], 'is_system' => true, 'is_active' => true],
            );
            if (! $business->domainPacks()->whereKey($pack->id)->exists()) {
                $business->domainPacks()->attach($pack->id, [
                    'priority' => $data['priority'], 'is_enabled' => true,
                    'is_default' => $data['default'], 'configuration' => null,
                ]);
            }
        }
    }
}
