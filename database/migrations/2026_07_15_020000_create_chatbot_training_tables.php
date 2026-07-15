<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_training_candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('fingerprint', 64)->unique();
            $table->foreignId('chatbot_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('incoming_message_id')->nullable()->constrained('chatbot_messages')->nullOnDelete();
            $table->foreignId('outgoing_message_id')->nullable()->constrained('chatbot_messages')->nullOnDelete();
            $table->string('source', 30)->default('system')->index();
            $table->string('issue_type', 50)->index();
            $table->string('status', 30)->default('new')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('risk_level', 20)->default('low')->index();
            $table->longText('user_message');
            $table->longText('bot_response')->nullable();
            $table->string('detected_intent', 80)->nullable()->index();
            $table->string('detected_decision', 40)->nullable();
            $table->decimal('detected_confidence', 5, 4)->nullable();
            $table->longText('detected_facts')->nullable();
            $table->string('expected_intent', 80)->nullable()->index();
            $table->string('expected_decision', 40)->nullable();
            $table->longText('expected_response')->nullable();
            $table->json('patterns')->nullable();
            $table->longText('expected_facts')->nullable();
            $table->string('product_code', 50)->nullable()->index();
            $table->boolean('requires_health_context')->default(false);
            $table->longText('review_notes')->nullable();
            $table->string('test_status', 20)->default('not_tested')->index();
            $table->json('test_result')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chatbot_training_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_training_candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 120)->unique();
            $table->unsignedInteger('version')->default(1);
            $table->string('intent', 80)->index();
            $table->string('decision', 40)->index();
            $table->json('patterns');
            $table->longText('response_template');
            $table->unsignedSmallInteger('priority')->default(100)->index();
            $table->boolean('requires_health_context')->default(false);
            $table->string('product_code', 50)->nullable()->index();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tested_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('chatbot_training_candidates', function (Blueprint $table) {
            $table->foreignId('published_rule_id')->nullable()->after('approved_by')
                ->constrained('chatbot_training_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_training_candidates', fn (Blueprint $table) => $table->dropConstrainedForeignId('published_rule_id'));
        Schema::dropIfExists('chatbot_training_rules');
        Schema::dropIfExists('chatbot_training_candidates');
    }
};
