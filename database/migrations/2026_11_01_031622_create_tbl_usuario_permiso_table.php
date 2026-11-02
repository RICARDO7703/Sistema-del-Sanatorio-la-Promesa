<?php

use App\Support\Permisos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos por usuario y módulo. Se puede ejecutar más de una vez sin duplicar datos.
 * Los usuarios que ya existían reciben los permisos sugeridos de su rol para no quedarse sin acceso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tbl_usuario_permiso')) {
            Schema::create('tbl_usuario_permiso', function (Blueprint $table): void {
                $table->integer('id_usuario');
                $table->integer('id_permiso');
                $table->dateTime('fecha_registro')->useCurrent();
                $table->primary(['id_usuario', 'id_permiso']);
                $table->foreign('id_usuario', 'fk_usuario_permiso_usuario')->references('id_usuario')->on('tbl_usuario')->cascadeOnUpdate()->cascadeOnDelete();
                $table->foreign('id_permiso', 'fk_usuario_permiso_permiso')->references('id_permiso')->on('tbl_permiso')->cascadeOnUpdate()->cascadeOnDelete();
            });
        }

        foreach (Permisos::MODULOS + ['Usuarios' => ['Usuarios', 'Gestión de usuarios y permisos.', []]] as $nombre => [$etiqueta, $descripcion]) {
            if (! DB::table('tbl_modulo')->where('nombre_modulo', $nombre)->exists()) {
                DB::table('tbl_modulo')->insert(['nombre_modulo' => $nombre, 'descripcion' => mb_substr($descripcion, 0, 255), 'estado' => 'Activo']);
            }
        }
        $modulos = DB::table('tbl_modulo')->pluck('id_modulo', 'nombre_modulo');
        foreach (Permisos::MODULOS as $nombre => [, , $acciones]) {
            foreach ($acciones as $accion) {
                if (! DB::table('tbl_permiso')->where('id_modulo', $modulos[$nombre])->where('accion', $accion)->exists()) {
                    DB::table('tbl_permiso')->insert(['id_modulo' => $modulos[$nombre], 'accion' => $accion, 'descripcion' => 'Permite '.Permisos::descripcion($nombre, $accion), 'estado' => 'Activo']);
                }
            }
        }

        $permisos = DB::table('tbl_permiso as pe')->join('tbl_modulo as m', 'm.id_modulo', '=', 'pe.id_modulo')
            ->get(['pe.id_permiso', 'm.nombre_modulo', 'pe.accion'])
            ->mapWithKeys(fn ($fila) => [$fila->nombre_modulo.'|'.$fila->accion => $fila->id_permiso]);
        $usuarios = DB::table('tbl_usuario as u')->join('tbl_rol as r', 'r.id_rol', '=', 'u.id_rol')
            ->whereNotExists(fn ($consulta) => $consulta->select(DB::raw(1))->from('tbl_usuario_permiso as up')->whereColumn('up.id_usuario', 'u.id_usuario'))
            ->get(['u.id_usuario', 'r.nombre_rol']);
        foreach ($usuarios as $usuario) {
            foreach (Permisos::SUGERIDOS[$usuario->nombre_rol] ?? [] as $modulo => $acciones) {
                foreach ($acciones as $accion) {
                    DB::table('tbl_usuario_permiso')->insert(['id_usuario' => $usuario->id_usuario, 'id_permiso' => $permisos[$modulo.'|'.$accion]]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_usuario_permiso');
    }
};
