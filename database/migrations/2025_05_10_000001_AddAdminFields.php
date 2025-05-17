<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Agregar campos a la tabla de usuarios
        Schema::table('usuario', function (Blueprint $table) {
            // Ya existe el campo 'rol', no es necesario agregarlo nuevamente
            
            if (!Schema::hasColumn('usuario', 'activo')) {
                $table->boolean('activo')->default(true)->after('rol');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar solo el campo agregado
        Schema::table('usuario', function (Blueprint $table) {
            // No eliminamos 'rol' porque ya existía antes de esta migración
            
            if (Schema::hasColumn('usuario', 'activo')) {
                $table->dropColumn('activo');
            }
        });
    }
};