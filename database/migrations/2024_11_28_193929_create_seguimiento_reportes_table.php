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
        Schema::create('seguimiento_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('reportes')->onDelete('cascade'); // Reporte asociado
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade'); // Trabajador del GAD responsable
            $table->text('comentario'); // Detalle del seguimiento (e.g., acciones realizadas)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seguimiento_reportes');
    }
};
