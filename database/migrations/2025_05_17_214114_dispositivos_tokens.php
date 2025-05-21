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
        Schema::create('dispositivos_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->string('fcm_token');
            $table->string('dispositivo_id')->nullable(); // Identificador único del dispositivo
            $table->string('dispositivo_nombre')->nullable(); // Nombre del dispositivo (opcional)
            $table->timestamp('ultimo_uso')->nullable(); // Última vez que se usó
            $table->timestamps();
            
            $table->foreign('usuario_id')->references('id')->on('usuario')->onDelete('cascade');
            // Asegurar que no haya tokens duplicados
            $table->unique(['usuario_id', 'fcm_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispositivos_tokens');
    }
};
