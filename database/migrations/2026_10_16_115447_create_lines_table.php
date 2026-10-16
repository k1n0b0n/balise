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
        Schema::create('lines', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('network')->index();
            $table->string('short_name');
            $table->string('long_name')->nullable();
            $table->string('mode');
            $table->char('color', 6)->nullable();
            $table->char('text_color', 6)->nullable();
            $table->uuid('sync_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lines');
    }
};
