<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('comentarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('reporte_id');
            $table->text('contenido');
            $table->unsignedBigInteger('padre_id')->nullable();
            $table->timestamps();
            
            $table->foreign('usuario_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
                
            $table->foreign('reporte_id')
                ->references('id')
                ->on('reportes')
                ->onDelete('cascade');
                
            $table->foreign('padre_id')
                ->references('id')
                ->on('comentarios')
                ->onDelete('cascade');
            
            $table->index(['reporte_id', 'padre_id']);
        });
    }

    public function down()
    {
        Schema::table('comentarios', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->dropForeign(['reporte_id']);
            $table->dropForeign(['padre_id']);
        });
        Schema::dropIfExists('comentarios');
    }
};
