<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_personal', 'foto_perfil')) {
            Schema::table('tbl_personal', fn (Blueprint $t) => $t->string('foto_perfil', 255)->nullable());
        }
    }

    public function down(): void
    { /* Conservar las fotos de perfil existentes. */
    }
};
