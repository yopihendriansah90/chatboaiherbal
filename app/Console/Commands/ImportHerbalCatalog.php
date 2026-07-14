<?php

namespace App\Console\Commands;

use App\Services\HerbalCatalogImporter;
use Illuminate\Console\Command;

class ImportHerbalCatalog extends Command
{
    protected $signature = 'herbal:import-catalog {--dry-run : Validasi tanpa menulis data} {--update : Timpa produk yang sudah ada dari JSON} {--path= : Lokasi file JSON}';

    protected $description = 'Import katalog herbal tervalidasi ke database';

    public function handle(HerbalCatalogImporter $importer): int
    {
        $result = $importer->import(
            $this->option('path') ?: null,
            (bool) $this->option('dry-run'),
            (bool) $this->option('update'),
        );
        $this->table(['Data', 'Jumlah'], [
            ['Produk', $result['products']],
            ['Baris komposisi', $result['ingredients']],
            ['Kategori', $result['categories']],
        ]);
        $this->info($this->option('dry-run') ? 'Validasi katalog berhasil.' : 'Katalog herbal berhasil diimpor.');

        return self::SUCCESS;
    }
}
