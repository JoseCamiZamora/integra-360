<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Workers of a company. Only the data the operation needs (Ley 1581):
        // no health data, photos or relatives.
        Schema::create('people', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            // The account the person signs in with, if any.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('document_type', 5);
            // Normalised: no dots, spaces or hyphens.
            $table->string('document_number', 20);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('birth_date')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('position', 100);
            $table->string('area', 100)->nullable();
            $table->date('hired_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            // The document while the person is not deleted, NULL otherwise.
            $table->string('live_document_number', 20)->nullable()->virtualAs('if(deleted_at is null, document_number, null)');
            $table->unique(['company_id', 'document_type', 'live_document_number'], 'people_document_unique');
            $table->index(['company_id', 'status']);
        });

        // Driver profile of a person (1:1). Soft deleted when the person stops
        // driving, so assignments keep their history; restored if they drive again.
        Schema::create('drivers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('person_id')->unique()->constrained('people')->cascadeOnDelete();
            $table->string('license_number', 30);
            $table->string('license_category', 5);
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('people');
    }
};
