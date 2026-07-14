<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rate_sources', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->unique();
            $table->string('name');
            $table->longText('api_key')->nullable();
            $table->string('endpoint', 2048);
            $table->boolean('is_enabled')->default(false)->index();
            $table->boolean('auto_sync')->default(false)->index();
            $table->decimal('warning_percent', 8, 2)->default(10);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestamp('last_response_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_sources');
    }
};
