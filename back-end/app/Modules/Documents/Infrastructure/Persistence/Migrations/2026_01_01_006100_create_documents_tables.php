<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One generic file store instead of radiology_images + lab_files +
 * patient_files. `file_links` is what ties a stored file to any entity in the
 * system, which is why a new department needs no new file table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk', 50);
            $table->string('path', 1000);
            $table->string('mime_type', 150)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('checksum', 'idx_file_checksum');
        });

        Schema::create('file_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->string('category', 100)->nullable();

            // Denormalised so the patient file can list every document in one
            // query without joining through each owning module.
            $table->foreignId('patient_id')->nullable()->constrained('patients')->cascadeOnDelete();
            $table->unsignedBigInteger('encounter_id')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id'], 'idx_file_entity');
            $table->index(['patient_id', 'category'], 'idx_file_patient_category');
            $table->index('encounter_id', 'idx_file_encounter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_links');
        Schema::dropIfExists('files');
    }
};
