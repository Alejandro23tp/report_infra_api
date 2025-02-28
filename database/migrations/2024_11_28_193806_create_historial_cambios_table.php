<?php

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
        Schema::create('historial_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('reportes')->onDelete('cascade'); // Reporte al que se refiere el historial
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade'); // Usuario que hizo el cambio
            $table->string('estado_anterior');
            $table->string('estado_nuevo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_cambios');
    }
};
