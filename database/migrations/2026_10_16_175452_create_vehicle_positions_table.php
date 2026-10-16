<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Latest position per vehicle only (ADR-0002, ADR-0013); no foreign key on line_id.
        Schema::create('vehicle_positions', function (Blueprint $table) {
            $table->string('vehicle_id')->primary();
            $table->string('network')->index();
            $table->double('latitude');
            $table->double('longitude');
            $table->string('line_id')->nullable();
            $table->string('trip_id')->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampTz('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_positions');
    }
};
