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
        Schema::create('notificacions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade'); // Usuario que recibe la notificación
            $table->foreignId('reporte_id')->nullable()->constrained('reportes')->onDelete('cascade'); // Relacionada a un reporte (opcional)
            $table->string('titulo');
            $table->text('mensaje');
            $table->boolean('leido')->default(false); // Indica si el usuario ya vio la notificación

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificacions');
    }
};
