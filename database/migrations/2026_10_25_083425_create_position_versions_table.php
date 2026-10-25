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
        // The version of each network's positions, counted with each write (ADR-0021): the change log in the cache
        // can then tell when it missed one.
        Schema::create('position_versions', function (Blueprint $table) {
            $table->string('network')->primary();
            $table->unsignedBigInteger('version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_versions');
    }
};
