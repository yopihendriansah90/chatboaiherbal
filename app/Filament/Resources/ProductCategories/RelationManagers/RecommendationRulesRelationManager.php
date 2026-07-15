<?php

namespace App\Filament\Resources\ProductCategories\RelationManagers;

use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecommendationRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'recommendationRules';

    protected static ?string $title = 'Urutan Rekomendasi Produk';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')->label('Produk')->options(Product::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
            TextInput::make('priority')->label('Prioritas')->numeric()->minValue(1)->default(10)->required(),
            TextInput::make('minimum_age')->label('Usia minimum')->numeric()->minValue(0),
            TextInput::make('maximum_age')->label('Usia maksimum')->numeric()->minValue(0)->gte('minimum_age'),
            Select::make('subject_type')->label('Subjek')->options(['adult' => 'Dewasa', 'child' => 'Anak', 'senior' => 'Lansia'])->nullable(),
            Toggle::make('is_fallback')->label('Produk fallback'), Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('priority')->columns([
            TextColumn::make('priority')->label('Urutan')->sortable(), TextColumn::make('product.code')->label('Kode')->badge(), TextColumn::make('product.name')->label('Produk')->weight('bold'),
            TextColumn::make('minimum_age')->label('Usia min')->placeholder('-'), TextColumn::make('maximum_age')->label('Usia maks')->placeholder('-'),
            IconColumn::make('is_fallback')->label('Fallback')->boolean(), IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make()]);
    }
}
