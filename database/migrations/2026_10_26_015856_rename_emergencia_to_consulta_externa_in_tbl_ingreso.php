<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La atención sin internamiento deja de llamarse "Emergencia" y pasa a "Consulta externa".
 * Es idempotente porque el mismo cambio también se puede aplicar con SQL manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            DB::table('tbl_ingreso')->where('tipo_atencion', 'Emergencia')->update(['tipo_atencion' => 'Consulta externa']);

            return;
        }

        $tipo = (string) DB::selectOne("SHOW COLUMNS FROM tbl_ingreso LIKE 'tipo_atencion'")->Type;

        if (! str_contains($tipo, 'Consulta externa')) {
            DB::statement("ALTER TABLE tbl_ingreso MODIFY tipo_atencion ENUM('Hospitalización','Emergencia','Consulta externa') NOT NULL DEFAULT 'Hospitalización'");
        }

        DB::table('tbl_ingreso')->where('tipo_atencion', 'Emergencia')->update(['tipo_atencion' => 'Consulta externa']);

        if (str_contains($tipo, 'Emergencia') || ! str_contains($tipo, 'Consulta externa')) {
            DB::statement("ALTER TABLE tbl_ingreso MODIFY tipo_atencion ENUM('Hospitalización','Consulta externa') NOT NULL DEFAULT 'Hospitalización'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE tbl_ingreso MODIFY tipo_atencion ENUM('Hospitalización','Emergencia','Consulta externa') NOT NULL DEFAULT 'Hospitalización'");
        DB::table('tbl_ingreso')->where('tipo_atencion', 'Consulta externa')->update(['tipo_atencion' => 'Emergencia']);
        DB::statement("ALTER TABLE tbl_ingreso MODIFY tipo_atencion ENUM('Hospitalización','Emergencia') NOT NULL DEFAULT 'Hospitalización'");
    }
};
