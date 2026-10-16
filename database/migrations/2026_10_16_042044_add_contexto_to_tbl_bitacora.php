<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['ip' => 45, 'navegador' => 150, 'sistema_operativo' => 100, 'agente_usuario' => 1024, 'tabla' => 100] as $nombre => $longitud) {
            if (! Schema::hasColumn('tbl_bitacora', $nombre)) {
                Schema::table('tbl_bitacora', fn (Blueprint $table) => $table->string($nombre, $longitud)->nullable());
            }
        }
        if (! Schema::hasColumn('tbl_bitacora', 'id_registro')) {
            Schema::table('tbl_bitacora', fn (Blueprint $table) => $table->unsignedBigInteger('id_registro')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('tbl_bitacora', fn (Blueprint $table) => $table->dropColumn(['ip', 'navegador', 'sistema_operativo', 'agente_usuario', 'tabla', 'id_registro']));
    }
};
