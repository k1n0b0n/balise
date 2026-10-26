<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stations, the groups of platforms that riders know by one name (E7.1), and the station of each platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('network')->index();
            $table->string('name');
            $table->double('latitude');
            $table->double('longitude');
            $table->uuid('sync_id');
            $table->timestamps();
        });

        Schema::table('stops', function (Blueprint $table) {
            $table->string('station_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->dropColumn('station_id');
        });

        Schema::dropIfExists('stations');
    }
};
