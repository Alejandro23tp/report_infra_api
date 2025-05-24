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
            $table->foreignId('reporte_id')->constrained('reportes')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('usuario')->onDelete('cascade');
            $table->string('tipo', 50)->default('seguimiento'); // Tipo de seguimiento: 'cambio_estado', 'asignacion', 'comentario', etc.
            $table->text('comentario')->nullable(); // Detalle del seguimiento o cambios realizados (en formato JSON para cambios)
            $table->timestamps();
            
            // Índices para mejorar el rendimiento de las consultas
            $table->index(['reporte_id', 'created_at']);
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
