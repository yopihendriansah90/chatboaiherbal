<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
