<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_cases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('chatbot_conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('case_number');
            $table->string('status', 30)->default('active')->index();
            $table->string('phase', 40)->default('identify_subject')->index();
            $table->string('subject_type', 50)->nullable()->index();
            $table->string('sex', 20)->nullable();
            $table->unsignedTinyInteger('age_years')->nullable();
            $table->string('category', 50)->nullable()->index();
            $table->longText('complaint')->nullable();
            $table->longText('facts');
            $table->longText('summary')->nullable();
            $table->string('safety_outcome', 20)->nullable()->index();
            $table->longText('safety_reason_codes')->nullable();
            $table->string('resolution_code', 50)->nullable()->index();
            $table->foreignId('started_by_message_id')->nullable()->constrained('chatbot_messages')->nullOnDelete();
            $table->timestamp('started_at')->index();
            $table->timestamp('last_activity_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('handed_off_at')->nullable();
            $table->timestamps();

            $table->unique(['chatbot_conversation_id', 'case_number']);
            $table->index(['chatbot_conversation_id', 'status'], 'consultation_active_lookup');
        });

        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->foreignId('consultation_case_id')->nullable()->after('chatbot_conversation_id')
                ->constrained('consultation_cases')->nullOnDelete();
        });

        Schema::table('conversation_events', function (Blueprint $table) {
            $table->foreignId('consultation_case_id')->nullable()->after('chatbot_conversation_id')
                ->constrained('consultation_cases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversation_events', fn (Blueprint $table) => $table->dropConstrainedForeignId('consultation_case_id'));
        Schema::table('chatbot_messages', fn (Blueprint $table) => $table->dropConstrainedForeignId('consultation_case_id'));
        Schema::dropIfExists('consultation_cases');
    }
};
