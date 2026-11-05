<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $id = DB::table('tbl_modulo')->where('nombre_modulo', 'Reportes')->value('id_modulo');
            if (! $id) {
                $id = DB::table('tbl_modulo')->insertGetId(['nombre_modulo' => 'Reportes', 'descripcion' => 'Consultar y generar reportes PDF.', 'estado' => 'Activo']);
            }
            if (! DB::table('tbl_permiso')->where('id_modulo', $id)->where('accion', 'Ver')->exists()) {
                DB::table('tbl_permiso')->insert(['id_modulo' => $id, 'accion' => 'Ver', 'descripcion' => 'Consultar y generar reportes PDF.', 'estado' => 'Activo']);
            }
        });
    }

    public function down(): void
    {
        // Se conservan los permisos y sus asignaciones existentes para evitar pérdida de configuración.
    }
};
