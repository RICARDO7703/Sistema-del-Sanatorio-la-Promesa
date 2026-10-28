<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_documento_clinico', 'tabla_origen')) {
            Schema::table('tbl_documento_clinico', fn (Blueprint $t) => $t->string('tabla_origen', 50)->nullable());
        }
        if (! Schema::hasColumn('tbl_documento_clinico', 'id_registro_origen')) {
            Schema::table('tbl_documento_clinico', fn (Blueprint $t) => $t->bigInteger('id_registro_origen')->nullable());
        }
    }

    public function down(): void
    {
        // Preservar los vínculos de las hojas archivadas.
    }
};
