<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            // Normalised as "900123456-7" (base + verification digit).
            $table->string('nit', 15)->unique();
            $table->string('mission_type', 20);
            $table->string('city', 100);
            $table->string('department', 100);
            $table->string('address');
            $table->string('phone', 20);
            $table->string('email');
            $table->string('legal_representative');
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('city', 100);
            $table->string('address');
            $table->string('phone', 20)->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            // 1 for the main branch, NULL otherwise: with the unique index
            // below, MySQL guarantees a single main branch per company.
            $table->tinyInteger('main_marker')->nullable()->virtualAs('if(is_main, 1, null)');
            $table->timestamps();

            $table->unique(['company_id', 'main_marker']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
