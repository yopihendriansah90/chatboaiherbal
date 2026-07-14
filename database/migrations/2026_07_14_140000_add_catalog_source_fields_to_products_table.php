<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('package_content')->nullable()->after('full_description');
            $table->string('dosage_form', 80)->nullable()->after('package_content');
            $table->string('manufacturer')->nullable()->after('usage_instruction');
            $table->string('registration_number', 100)->nullable()->after('manufacturer');
            $table->string('halal_status', 100)->nullable()->after('registration_number');
            $table->string('source_document')->nullable()->after('additional_notes');
            $table->unsignedSmallInteger('source_page')->nullable()->after('source_document');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'package_content',
                'dosage_form',
                'manufacturer',
                'registration_number',
                'halal_status',
                'source_document',
                'source_page',
            ]);
        });
    }
};
