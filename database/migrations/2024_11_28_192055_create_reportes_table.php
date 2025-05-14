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
        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')
                  ->references('id')
                  ->on('usuario')  // Referencia a la tabla 'users'
                  ->onDelete('cascade');
            $table->foreignId('categoria_id')
                  ->nullable()
                  ->constrained('categorias')
                  ->nullOnDelete();
            $table->text('descripcion');
            $table->json('ubicacion');
            $table->enum('estado', ['Pendiente', 'En Proceso', 'Completado', 'Cancelado'])->default('Pendiente');
            $table->enum('urgencia', ['bajo', 'medio', 'alto', 'crítico'])->default('bajo');
            $table->string('imagen_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};
