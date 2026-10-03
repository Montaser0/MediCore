<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime configuration an administrator can change without a deployment.
 * A null hospital_id means the value applies system-wide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->nullable()->constrained('hospitals')->cascadeOnDelete();
            $table->string('group', 100)->default('general');
            $table->string('key', 150);
            $table->json('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'group', 'key'], 'uq_setting_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
