<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configurable catalog: company_id NULL = global type of the platform.
        Schema::create('document_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->string('applies_to', 20);
            $table->boolean('requires_expiry')->default(true);
            $table->boolean('is_required')->default(false);
            // Vehicle types it applies to; NULL = all.
            $table->json('vehicle_types')->nullable();
            $table->boolean('blocks_operation')->default(false);
            $table->unsignedSmallInteger('warning_days')->default(30);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Codes are unique among the global types and within each company
            // (a NULL company_id would escape a plain unique index).
            $table->string('owner_key', 26)->virtualAs("ifnull(company_id, 'global')");
            $table->unique(['owner_key', 'code']);
        });

        Schema::create('expiring_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            // Morph map aliases: person, vehicle.
            $table->string('documentable_type', 30);
            $table->ulid('documentable_id');
            $table->foreignUlid('document_type_id')->constrained()->restrictOnDelete();
            $table->string('number', 60)->nullable();
            $table->string('issuer', 120)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // 1 for the current, not deleted document; NULL otherwise. With the
            // unique index: one current document per entity and type.
            $table->tinyInteger('current_marker')->nullable()->virtualAs('if(is_current and deleted_at is null, 1, null)');
            $table->unique(['documentable_type', 'documentable_id', 'document_type_id', 'current_marker'], 'expiring_documents_one_current');
            $table->index(['documentable_type', 'documentable_id']);
            $table->index(['company_id', 'is_current', 'expires_at']);
        });

        // Up to 4 files per document (front and back of a license...). Never
        // public: served through temporary signed URLs after authorization.
        Schema::create('expiring_document_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('expiring_document_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiring_document_files');
        Schema::dropIfExists('expiring_documents');
        Schema::dropIfExists('document_types');
    }
};
