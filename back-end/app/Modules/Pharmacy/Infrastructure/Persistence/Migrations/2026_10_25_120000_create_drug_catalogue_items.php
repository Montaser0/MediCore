<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_catalogue_items', function (Blueprint $table): void {
            $table->id();
            $table->string('source_record_id', 40)->unique();
            $table->string('name', 180);
            $table->text('generic_name')->nullable();
            $table->string('strength', 80)->nullable();
            $table->string('dosage_form', 80)->nullable();
            $table->string('pack', 100)->nullable();
            $table->string('company', 80)->nullable();
            $table->string('company_ar', 80)->nullable();
            $table->string('product_type', 40)->nullable();
            $table->string('product_family', 40)->nullable();
            $table->string('registration_status', 120)->nullable();
            $table->string('dataset', 64)->nullable();
            $table->string('published_category', 150)->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('company_ar');
            $table->index('product_type');
        });

        Schema::table('medications', function (Blueprint $table): void {
            $table->string('pack', 100)->nullable()->after('dosage_form');
            $table->string('manufacturer', 80)->nullable()->after('category');
            $table->foreignId('catalogue_item_id')->nullable()->after('inventory_item_id')
                ->constrained('drug_catalogue_items')->nullOnDelete();
            $table->unique(['hospital_id', 'catalogue_item_id'], 'medications_hospital_catalogue');
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table): void {
            $table->dropUnique('medications_hospital_catalogue');
            $table->dropConstrainedForeignId('catalogue_item_id');
            $table->dropColumn(['pack', 'manufacturer']);
        });

        Schema::dropIfExists('drug_catalogue_items');
    }
};
