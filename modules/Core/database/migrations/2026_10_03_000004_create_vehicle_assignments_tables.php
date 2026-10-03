<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who drives which vehicle. ends_at NULL = current assignment. The
        // history is never deleted.
        Schema::create('vehicle_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('driver_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            // At most one current assignment per vehicle and per driver.
            $table->char('current_vehicle_id', 26)->nullable()->virtualAs('if(ends_at is null, vehicle_id, null)');
            $table->char('current_driver_id', 26)->nullable()->virtualAs('if(ends_at is null, driver_id, null)');
            $table->unique('current_vehicle_id');
            $table->unique('current_driver_id');
            $table->index(['vehicle_id', 'starts_at']);
            $table->index(['driver_id', 'starts_at']);
        });

        // Editable equivalences (platform-wide data, not code): license
        // categories allowed for each vehicle type. Seeded with initial values
        // to be validated with the pilot company. A category outside the list
        // only produces a warning when assigning.
        Schema::create('vehicle_type_license_categories', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_type', 20);
            $table->string('license_category', 5);
            $table->timestamps();

            $table->unique(['vehicle_type', 'license_category'], 'vehicle_type_license_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_type_license_categories');
        Schema::dropIfExists('vehicle_assignments');
    }
};
