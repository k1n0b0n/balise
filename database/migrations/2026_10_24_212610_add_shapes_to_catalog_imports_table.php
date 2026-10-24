<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The shapes' GeoJSON moves from a file, which each container kept apart, to the import's row (ADR-0016).
        Schema::table('catalog_imports', function (Blueprint $table) {
            $table->text('shapes')->nullable();
        });

        // Without its fingerprint, the next import downloads the archive again and fills the new column.
        DB::table('catalog_imports')->update(['shapes_fingerprint' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_imports', function (Blueprint $table) {
            $table->dropColumn('shapes');
        });
    }
};
