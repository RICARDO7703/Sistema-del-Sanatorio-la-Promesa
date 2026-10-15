<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_evolucion_clinica', function (Blueprint $table): void {
            $table->json('ordenes_medicas')->nullable();
            $table->string('estado_mejoria', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_evolucion_clinica', function (Blueprint $table): void {
            $table->dropColumn(['ordenes_medicas', 'estado_mejoria']);
        });
    }
};
