<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_cirugia', 'motivo_registro_posterior')) {
            Schema::table('tbl_cirugia', fn (Blueprint $table) => $table->string('motivo_registro_posterior', 500)->nullable());
        }
    }

    public function down(): void
    {
        // Conservar los motivos registrados para mantener la trazabilidad.
    }
};
