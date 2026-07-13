<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('name');
            $table->text('api_key')->nullable();
            $table->string('parser_model');
            $table->string('renderer_model');
            $table->unsignedSmallInteger('parser_timeout')->default(25);
            $table->unsignedSmallInteger('renderer_timeout')->default(12);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedTinyInteger('priority')->default(1);
            $table->string('last_test_status')->nullable();
            $table->string('last_error_code')->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
