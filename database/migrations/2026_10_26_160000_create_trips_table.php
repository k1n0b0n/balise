<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trips of the static catalogue, for the destination their vehicles display (E8.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('network')->index();
            $table->string('line_id');
            $table->string('headsign')->nullable();
            $table->uuid('sync_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
