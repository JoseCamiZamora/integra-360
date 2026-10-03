<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            // Normalised: uppercase, no spaces or hyphens.
            $table->string('plate', 10);
            $table->string('vehicle_type', 20);
            $table->string('brand', 60);
            $table->string('model_line', 60);
            $table->unsignedSmallInteger('model_year');
            $table->string('color', 40)->nullable();
            $table->string('vin', 30)->nullable();
            $table->unsignedInteger('load_capacity_kg')->nullable();
            $table->string('ownership', 20);
            $table->string('service_type', 20);
            $table->unsignedInteger('odometer_km')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            // The plate while the vehicle is not deleted, NULL otherwise: a
            // deleted vehicle frees its plate (unique ignores NULLs).
            $table->string('live_plate', 10)->nullable()->virtualAs('if(deleted_at is null, plate, null)');
            $table->unique(['company_id', 'live_plate']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
