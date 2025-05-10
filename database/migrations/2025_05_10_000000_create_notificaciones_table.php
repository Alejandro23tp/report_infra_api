<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')
                  ->constrained('usuario')
                  ->onDelete('cascade');
            $table->foreignId('reporte_id')
                  ->constrained('reportes') // Changed from 'reporte' to 'reportes'
                  ->onDelete('cascade');
            $table->string('titulo');
            $table->text('mensaje');
            $table->boolean('leido')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notificaciones');
    }
};
