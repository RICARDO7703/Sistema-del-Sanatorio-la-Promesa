<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recuperacion_contrasenas', function (Blueprint $table) {
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->boolean('verificado')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('recuperacion_contrasenas', function (Blueprint $table) {
            $table->dropColumn(['intentos', 'verificado']);
        });
    }
};
