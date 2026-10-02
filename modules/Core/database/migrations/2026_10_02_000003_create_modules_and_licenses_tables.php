<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Product catalogue: modules that exist or will be sold.
        Schema::create('modules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->boolean('is_licensable')->default(true);
            // Whether the module already exists in the product.
            $table->boolean('is_available')->default(false);
            $table->timestamps();
        });

        Schema::create('module_licenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->string('module_code', 40);
            $table->date('starts_at');
            // Last valid day; NULL = no expiry.
            $table->date('ends_at')->nullable();
            // NULL = unlimited.
            $table->unsignedInteger('max_vehicles')->nullable();
            $table->unsignedInteger('max_people')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('module_code')->references('code')->on('modules')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['company_id', 'module_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_licenses');
        Schema::dropIfExists('modules');
    }
};
