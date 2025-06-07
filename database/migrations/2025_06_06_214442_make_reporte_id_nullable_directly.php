<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar la columna para que sea nullable
        DB::statement('ALTER TABLE notificaciones MODIFY reporte_id BIGINT UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir el cambio, hacer que la columna sea NOT NULL
        DB::statement('ALTER TABLE notificaciones MODIFY reporte_id BIGINT UNSIGNED NOT NULL');
    }
};
