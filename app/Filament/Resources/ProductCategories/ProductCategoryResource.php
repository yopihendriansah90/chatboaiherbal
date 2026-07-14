<?php

namespace App\Filament\Resources\ProductCategories;

use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\ProductCategories\RelationManagers\RecommendationRulesRelationManager;
use App\Models\ProductCategory;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Walatra Herbal';

    protected static ?string $navigationLabel = 'Kategori & Rekomendasi';

    protected static ?string $modelLabel = 'kategori keluhan';

    protected static ?string $pluralModelLabel = 'Kategori & Rekomendasi';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make('Kategori keluhan')->schema([
            TextInput::make('code')->label('Kode')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->label('Nama')->required(),
            Textarea::make('description')->label('Label manfaat yang diizinkan')->required()->columnSpanFull(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ])->columns(2)]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('code')->columns([
            TextColumn::make('code')->label('Kode')->badge(), TextColumn::make('name')->label('Kategori')->weight('bold'),
            TextColumn::make('description')->label('Manfaat')->limit(80),
            TextColumn::make('recommendation_rules_count')->label('Produk')->counts('recommendationRules')->numeric(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->recordUrl(fn ($record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [RecommendationRulesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListProductCategories::route('/'), 'edit' => EditProductCategory::route('/{record}/edit')];
    }
}
