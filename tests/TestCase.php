<?php

namespace Tests;

use App\Support\Permisos;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Prepara la tabla de permisos (si falta) y deja al usuario solo con los permisos indicados.
     *
     * @param  array<string, list<string>>  $permisos  módulo => acciones
     */
    protected function asignarPermisos(int $idUsuario, array $permisos): void
    {
        if (! Schema::hasTable('tbl_usuario_permiso')) {
            (require database_path('migrations/2026_11_01_031622_create_tbl_usuario_permiso_table.php'))->up();
        }
        DB::table('tbl_usuario_permiso')->where('id_usuario', $idUsuario)->delete();
        foreach ($permisos as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                $idPermiso = DB::table('tbl_permiso as pe')->join('tbl_modulo as m', 'm.id_modulo', '=', 'pe.id_modulo')
                    ->where('m.nombre_modulo', $modulo)->where('pe.accion', $accion)->value('pe.id_permiso');
                DB::table('tbl_usuario_permiso')->insert(['id_usuario' => $idUsuario, 'id_permiso' => $idPermiso]);
            }
        }
    }

    /**
     * Da al usuario los permisos que se sugieren para un rol (Médico, Enfermería o Secretaria).
     */
    protected function asignarPermisosDeRol(int $idUsuario, string $rol): void
    {
        $this->asignarPermisos($idUsuario, Permisos::SUGERIDOS[$rol]);
    }
}
