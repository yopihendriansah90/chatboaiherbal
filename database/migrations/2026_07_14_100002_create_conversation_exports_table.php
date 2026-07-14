<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 30);
            $table->string('format', 20)->default('json');
            $table->string('filename');
            $table->boolean('included_identity')->default(false);
            $table->unsignedInteger('conversation_count')->default(0);
            $table->json('filters')->nullable();
            $table->timestamp('exported_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_exports');
    }
};
