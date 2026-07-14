<?php

namespace Database\Seeders;

use App\Services\HerbalCatalogImporter;
use Illuminate\Database\Seeder;

class HerbalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(HerbalCatalogImporter::class)->import();
    }
}
