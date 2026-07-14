<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            AiProviderSeeder::class,
            AiModelSeeder::class,
            AiModelPriceSeeder::class,
            BusinessProfileSeeder::class,
            DomainPackSeeder::class,
            BotSettingSeeder::class,
            ChannelIntegrationSeeder::class,
            PromptTemplateSeeder::class,
            HerbalCatalogSeeder::class,
        ]);
    }
}
