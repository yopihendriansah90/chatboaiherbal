<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('correlation_id')->index();
            $table->string('channel', 30)->index();
            $table->string('integration_key', 100);
            $table->string('external_event_id', 191);
            $table->string('event_type', 30)->default('message');
            $table->longText('payload');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('error_code', 100)->nullable();
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['integration_key', 'external_event_id'], 'channel_event_external_unique');
        });

        Schema::create('chatbot_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('active_domain', 50)->nullable()->index();
            $table->string('phase', 50)->default('complaint')->index();
            $table->longText('facts');
            $table->longText('domain_states');
            $table->longText('missing_fields');
            $table->longText('offered_products');
            $table->longText('preferences');
            $table->longText('catalog_context');
            $table->longText('history');
            $table->longText('safety_state')->nullable();
            $table->longText('last_decision')->nullable();
            $table->longText('summary')->nullable();
            $table->timestamps();
        });

        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->uuid('correlation_id')->nullable()->after('uuid')->index();
            $table->unsignedSmallInteger('delivery_attempt_count')->default(0)->after('delivery_status');
            $table->timestamp('next_delivery_attempt_at')->nullable()->after('failed_at')->index();
        });

        Schema::table('chatbot_conversations', function (Blueprint $table) {
            $table->string('service_status', 30)->default('bot_active')->after('status')->index();
            $table->string('bot_mode', 20)->default('automatic')->after('status')->index();
            $table->string('priority', 20)->default('normal')->after('bot_mode')->index();
            $table->foreignId('assigned_to')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            $table->text('handoff_reason')->nullable()->after('is_emergency');
            $table->timestamp('waiting_since')->nullable()->after('last_message_at')->index();
            $table->timestamp('sla_due_at')->nullable()->after('waiting_since')->index();
            $table->timestamp('resolved_at')->nullable()->after('closed_at');
            $table->string('resolution_code', 50)->nullable()->after('resolved_at');
            $table->json('tags')->nullable()->after('resolution_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('agent')->after('is_admin')->index();
        });
        DB::table('users')->where('is_admin', true)->update(['role' => 'super_admin']);

        Schema::table('chatbot_contacts', function (Blueprint $table) {
            $table->timestamp('memory_consented_at')->nullable()->after('last_seen_at');
            $table->timestamp('memory_consent_revoked_at')->nullable()->after('memory_consented_at');
        });

        Schema::create('conversation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('content');
            $table->timestamps();
        });

        Schema::create('conversation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50)->index();
            $table->longText('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('conversation_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->string('source', 30)->default('customer');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_contact_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->longText('value');
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('source_message_id')->nullable()->constrained('chatbot_messages')->nullOnDelete();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['chatbot_contact_id', 'key']);
        });

        Schema::create('chatbot_personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('formality', 20)->default('friendly');
            $table->string('empathy_style', 30)->default('brief_relevant');
            $table->string('emoji_policy', 20)->default('minimal');
            $table->unsignedSmallInteger('max_words')->default(80);
            $table->json('tone_rules')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('product_claims', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('approved_by');
        });

        Schema::table('product_contraindications', function (Blueprint $table) {
            $table->text('source')->nullable()->after('guidance');
            $table->foreignId('reviewed_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('product_contraindications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['source', 'reviewed_at']);
        });
        Schema::table('product_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('reviewed_at');
        });
        Schema::dropIfExists('chatbot_personas');
        Schema::dropIfExists('customer_memories');
        Schema::dropIfExists('conversation_feedback');
        Schema::dropIfExists('conversation_events');
        Schema::dropIfExists('conversation_notes');
        Schema::table('chatbot_contacts', fn (Blueprint $table) => $table->dropColumn(['memory_consented_at', 'memory_consent_revoked_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
        Schema::table('chatbot_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['service_status', 'bot_mode', 'priority', 'handoff_reason', 'waiting_since', 'sla_due_at', 'resolved_at', 'resolution_code', 'tags']);
        });
        Schema::table('chatbot_messages', fn (Blueprint $table) => $table->dropColumn(['correlation_id', 'delivery_attempt_count', 'next_delivery_attempt_at']));
        Schema::dropIfExists('chatbot_conversation_states');
        Schema::dropIfExists('channel_events');
    }
};
