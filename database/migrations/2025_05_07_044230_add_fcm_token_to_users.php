<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFcmTokenToUsers extends Migration
{
    public function up()
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->string('fcm_token')->nullable();
        });
    }

    public function down()
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }
};
