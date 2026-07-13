<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->decimal('input_price_per_million_usd', 16, 8)->default(0);
            $table->decimal('cached_input_price_per_million_usd', 16, 8)->nullable();
            $table->decimal('output_price_per_million_usd', 16, 8)->default(0);
            $table->timestamp('effective_at');
            $table->string('source_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ai_provider_id', 'model', 'is_active', 'effective_at'], 'ai_prices_lookup_index');
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3)->default('USD');
            $table->char('quote_currency', 3)->default('IDR');
            $table->decimal('rate', 18, 6);
            $table->date('rate_date');
            $table->string('source_name')->default('Manual');
            $table->string('source_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['base_currency', 'quote_currency', 'is_active', 'rate_date'], 'exchange_rates_lookup_index');
        });

        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('role', 30);
            $table->string('model');
            $table->string('request_id')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->boolean('successful')->default(false);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('error_code')->nullable();
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('reasoning_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->foreignId('ai_model_price_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('input_price_per_million_usd', 16, 8)->nullable();
            $table->decimal('cached_input_price_per_million_usd', 16, 8)->nullable();
            $table->decimal('output_price_per_million_usd', 16, 8)->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('usd_idr_rate', 18, 6)->nullable();
            $table->decimal('input_cost_usd', 20, 10)->nullable();
            $table->decimal('output_cost_usd', 20, 10)->nullable();
            $table->decimal('total_cost_usd', 20, 10)->nullable();
            $table->decimal('total_cost_idr', 20, 4)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['provider', 'occurred_at']);
            $table->index(['role', 'occurred_at']);
            $table->index(['model', 'occurred_at']);
            $table->index(['successful', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('ai_model_prices');
    }
};
