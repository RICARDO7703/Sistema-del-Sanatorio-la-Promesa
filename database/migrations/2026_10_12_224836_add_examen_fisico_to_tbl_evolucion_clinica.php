<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_evolucion_clinica', function (Blueprint $table): void {
            $table->json('examen_fisico')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_evolucion_clinica', function (Blueprint $table): void {
            $table->dropColumn('examen_fisico');
        });
    }
};
