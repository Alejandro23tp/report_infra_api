<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reacciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('reporte_id');
            $table->tinyInteger('tipo_reaccion')->comment('1: like, 2: love, 3: angry, etc.');
            $table->timestamps();
            
            $table->foreign('usuario_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
                
            $table->foreign('reporte_id')
                ->references('id')
                ->on('reportes')
                ->onDelete('cascade');
            
            $table->unique(['usuario_id', 'reporte_id']);
        });
    }

    public function down()
    {
        Schema::table('reacciones', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->dropForeign(['reporte_id']);
        });
        Schema::dropIfExists('reacciones');
    }
};
