<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\HerbalCatalogImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class HerbalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $sourceDocument = 'Katalog Produk Walatra.pdf';
        $replaceLegacyCatalog = Schema::hasTable('products')
            && Product::query()->exists()
            && Product::query()->where(fn ($query) => $query
                ->whereNull('source_document')
                ->orWhere('source_document', '!=', $sourceDocument))
                ->exists();

        app(HerbalCatalogImporter::class)->import(
            updateExisting: true,
            replaceExisting: $replaceLegacyCatalog,
        );
    }
}
