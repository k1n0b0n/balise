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
        Schema::table('catalog_imports', function (Blueprint $table) {
            $table->string('shapes_fingerprint')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_imports', function (Blueprint $table) {
            $table->dropColumn('shapes_fingerprint');
        });
    }
};
