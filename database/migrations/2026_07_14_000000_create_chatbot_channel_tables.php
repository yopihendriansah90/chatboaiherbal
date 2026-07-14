<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('driver', 30)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('chatbot_contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('display_name');
            $table->string('status', 20)->default('active')->index();
            $table->text('admin_notes')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chatbot_channel_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_integration_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30)->index();
            $table->string('external_user_id', 191);
            $table->string('external_chat_id', 191)->nullable()->index();
            $table->string('username')->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name');
            $table->string('language_code', 20)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('description')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['channel_integration_id', 'external_user_id'], 'channel_identity_external_unique');
        });

        Schema::create('chatbot_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('chatbot_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chatbot_channel_identity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_integration_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30)->index();
            $table->string('external_conversation_id', 191);
            $table->string('status', 20)->default('active')->index();
            $table->string('category', 50)->nullable()->index();
            $table->string('product_code', 50)->nullable()->index();
            $table->boolean('is_emergency')->default(false)->index();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['channel_integration_id', 'external_conversation_id', 'status'],
                'chatbot_conversation_lookup'
            );
        });

        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('chatbot_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('chatbot_messages')->nullOnDelete();
            $table->foreignId('chatbot_channel_identity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_integration_id')->constrained()->cascadeOnDelete();
            $table->string('external_event_id', 191)->nullable();
            $table->string('external_message_id', 191)->nullable();
            $table->string('direction', 20)->index();
            $table->string('message_type', 30)->default('text');
            $table->longText('content');
            $table->string('processing_status', 20)->default('pending')->index();
            $table->string('delivery_status', 20)->nullable()->index();
            $table->string('error_code', 100)->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['channel_integration_id', 'external_event_id'],
                'chatbot_message_event_unique'
            );
        });

        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->foreignId('chatbot_conversation_id')->nullable()->after('ai_model_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('chatbot_message_id')->nullable()->after('chatbot_conversation_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chatbot_message_id');
            $table->dropConstrainedForeignId('chatbot_conversation_id');
        });

        Schema::dropIfExists('chatbot_messages');
        Schema::dropIfExists('chatbot_conversations');
        Schema::dropIfExists('chatbot_channel_identities');
        Schema::dropIfExists('chatbot_contacts');
        Schema::dropIfExists('channel_integrations');
    }
};
