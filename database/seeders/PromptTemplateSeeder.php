<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use App\Models\DomainPack;
use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

class PromptTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $business = BusinessProfile::query()->where('slug', 'walatra-herbal')->firstOrFail();
        $templates = [
            ['domain' => null, 'role' => 'branding', 'name' => 'Branding Walatra', 'protected' => false, 'content' => 'Gunakan Bahasa Indonesia yang hangat, ringkas, sopan, dan mudah dipahami pengguna dewasa maupun lansia. Nama asisten adalah Asisten Herbal Walatra.'],
            ['domain' => 'health_herbal', 'role' => 'parser', 'name' => 'Parser Asisten Herbal', 'protected' => true, 'content' => 'Ekstrak domain, intent, kategori keluhan, dan fakta yang benar-benar dinyatakan pengguna. Jangan menjawab, mendiagnosis, memilih produk, membuat harga, stok, link, atau klaim.'],
            ['domain' => 'health_herbal', 'role' => 'renderer', 'name' => 'Renderer Asisten Herbal', 'protected' => true, 'content' => 'Perhalus hanya pembuka atau pertanyaan pada RESPONSE PLAN. Jangan mengubah fakta, memilih atau menyebut produk, menambah link, diagnosis, maupun klaim kesehatan.'],
            ['domain' => 'company_profile', 'role' => 'parser', 'name' => 'Parser Profile Company', 'protected' => true, 'content' => 'Klasifikasikan pertanyaan tentang perusahaan, alamat, kontak, jam operasional, pemesanan, pengiriman, pembayaran, reseller, legalitas, dan FAQ. Jangan membuat fakta perusahaan.'],
            ['domain' => 'company_profile', 'role' => 'renderer', 'name' => 'Renderer Profile Company', 'protected' => true, 'content' => 'Sampaikan hanya fakta perusahaan yang tersedia pada RESPONSE PLAN dengan bahasa singkat dan natural. Jangan membuat alamat, kontak, legalitas, layanan, atau kebijakan baru.'],
        ];

        foreach ($templates as $item) {
            $domainId = $item['domain'] ? DomainPack::query()->where('code', $item['domain'])->value('id') : null;
            PromptTemplate::query()->updateOrCreate(
                ['business_profile_id' => $business->id, 'domain_pack_id' => $domainId, 'role' => $item['role']],
                ['name' => $item['name'], 'default_content' => $item['content'], 'is_protected' => $item['protected'], 'is_active' => true],
            );
        }
    }
}
