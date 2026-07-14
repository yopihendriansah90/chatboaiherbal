<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('full_description')->nullable();
            $table->longText('usage_instruction')->nullable();
            $table->longText('additional_notes')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('source_checksum', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(10);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'product_category_id']);
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ingredient_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('amount')->nullable();
            $table->string('unit', 30)->nullable();
            $table->longText('main_content')->nullable();
            $table->longText('symptom_context')->nullable();
            $table->longText('approved_narrative')->nullable();
            $table->longText('legacy_warning')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'ingredient_id']);
        });

        Schema::create('product_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->longText('claim_text');
            $table->text('source')->nullable();
            $table->string('version', 30)->default('1.0');
            $table->string('approval_status', 20)->default('approved')->index();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_contraindications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->string('code', 100)->index();
            $table->string('label');
            $table->string('severity', 20)->default('caution')->index();
            $table->longText('guidance')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('available_quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->boolean('track_stock')->default(false);
            $table->timestamps();
        });

        Schema::create('product_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30)->default('marketplace')->index();
            $table->string('label')->default('Link produk');
            $table->string('url', 2048);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_recommendation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(10);
            $table->unsignedSmallInteger('minimum_age')->nullable();
            $table->unsignedSmallInteger('maximum_age')->nullable();
            $table->string('subject_type', 30)->nullable();
            $table->boolean('is_fallback')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['product_category_id', 'product_id'], 'product_recommendation_category_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recommendation_rules');
        Schema::dropIfExists('product_links');
        Schema::dropIfExists('product_inventories');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('product_contraindications');
        Schema::dropIfExists('product_claims');
        Schema::dropIfExists('ingredient_product');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('product_category_product');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('products');
    }
};
