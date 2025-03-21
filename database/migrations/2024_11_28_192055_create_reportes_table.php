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
                  ->on('users')  // Referencia a la tabla 'users'
                  ->onDelete('cascade');
            $table->foreignId('categoria_id')
                  ->nullable()
                  ->constrained('categorias')
                  ->nullOnDelete();
            $table->text('descripcion');
            $table->json('ubicacion');
            $table->enum('estado', ['pendiente', 'en_proceso', 'completado'])->default('pendiente');
            $table->enum('urgencia', ['baja', 'normal', 'alta'])->default('normal');
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
