<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use App\Models\CompanyProfile;
use Illuminate\Database\Seeder;

class BusinessProfileSeeder extends Seeder
{
    public function run(): void
    {
        $business = BusinessProfile::query()->firstOrCreate(
            ['slug' => 'walatra-herbal'],
            [
                'name' => 'Walatra Herbal',
                'bot_name' => 'Asisten Herbal Walatra',
                'description' => 'Asisten informasi perusahaan dan konsultasi produk herbal Walatra.',
                'primary_language' => 'id',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
        );

        CompanyProfile::query()->firstOrCreate(
            ['business_profile_id' => $business->id],
            ['legal_name' => 'Walatra Herbal', 'short_description' => 'Perusahaan produk herbal dan layanan informasi herbal.'],
        );
    }
}
