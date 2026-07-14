<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('bot_name');
            $table->text('description')->nullable();
            $table->string('primary_language', 10)->default('id');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('domain_packs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('engine_type', 100);
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('business_domain_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_pack_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(10);
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['business_profile_id', 'domain_pack_id']);
        });

        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('legal_name')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('full_description')->nullable();
            $table->longText('history')->nullable();
            $table->longText('vision')->nullable();
            $table->longText('mission')->nullable();
            $table->longText('legal_information')->nullable();
            $table->text('operational_hours')->nullable();
            $table->timestamps();
        });

        Schema::create('company_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->string('label');
            $table->string('value', 2048);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_public')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(10);
            $table->timestamps();
        });

        Schema::create('company_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('address');
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('maps_url', 2048)->nullable();
            $table->text('operational_hours')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('company_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50)->default('company')->index();
            $table->text('question');
            $table->longText('answer');
            $table->json('keywords')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(10);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('domain_pack_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role', 50)->index();
            $table->string('name');
            $table->longText('default_content');
            $table->boolean('is_protected')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['business_profile_id', 'domain_pack_id', 'role'], 'prompt_template_scope_unique');
        });

        Schema::create('prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('custom_content');
            $table->string('status', 20)->default('draft')->index();
            $table->text('change_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tested_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['prompt_template_id', 'version']);
        });

        Schema::table('bot_settings', function (Blueprint $table) {
            $table->foreignId('business_profile_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
            $table->boolean('allow_domain_switching')->default(true)->after('history_limit');
            $table->string('ambiguous_domain_behavior', 30)->default('clarify')->after('allow_domain_switching');
        });

        Schema::table('chatbot_conversations', function (Blueprint $table) {
            $table->string('domain_code', 50)->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_conversations', fn (Blueprint $table) => $table->dropColumn('domain_code'));
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_profile_id');
            $table->dropColumn(['allow_domain_switching', 'ambiguous_domain_behavior']);
        });
        Schema::dropIfExists('prompt_versions');
        Schema::dropIfExists('prompt_templates');
        Schema::dropIfExists('company_faqs');
        Schema::dropIfExists('company_locations');
        Schema::dropIfExists('company_contacts');
        Schema::dropIfExists('company_profiles');
        Schema::dropIfExists('business_domain_packs');
        Schema::dropIfExists('domain_packs');
        Schema::dropIfExists('business_profiles');
    }
};
