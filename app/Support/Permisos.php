<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Permisos por módulo de cada usuario: Ver, Guardar, Editar y Eliminar.
 * El administrador siempre puede todo, aunque no tenga permisos marcados.
 */
class Permisos
{
    public const VER = 'Ver';

    public const GUARDAR = 'Ingresar';

    public const EDITAR = 'Editar';

    public const ELIMINAR = 'Desactivar';

    /**
     * Nombre que ve el usuario para cada acción guardada en tbl_permiso.
     *
     * @var array<string, string>
     */
    public const ACCIONES = [self::VER => 'Ver', self::GUARDAR => 'Guardar', self::EDITAR => 'Editar', self::ELIMINAR => 'Eliminar'];

    /**
     * Módulos que se pueden asignar, con el nombre que se muestra, qué incluye y las acciones que aplican.
     * La clave es el nombre en tbl_modulo.
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public const MODULOS = [
        'Pacientes' => ['Pacientes', 'Registrar pacientes, editar sus datos y contactos, desactivarlos.', [self::VER, self::GUARDAR, self::EDITAR, self::ELIMINAR]],
        'Expedientes' => ['Expedientes', 'Consultar el expediente completo del paciente.', [self::VER]],
        'Documentos clínicos' => ['Archivos médicos', 'Ver, subir y corregir archivos médicos y hojas en papel.', [self::VER, self::GUARDAR, self::EDITAR]],
        'Ingresos' => ['Atención del paciente', 'Ver atenciones, iniciar consultas, hospitalizar, editar y anular atenciones.', [self::VER, self::GUARDAR, self::EDITAR, self::ELIMINAR]],
        'Evolución clínica' => ['Consulta y evolución clínica', 'Consulta externa, valoración inicial, evoluciones y órdenes médicas.', [self::GUARDAR, self::EDITAR, self::ELIMINAR]],
        'Signos vitales' => ['Signos vitales', 'Registrar y corregir signos vitales.', [self::GUARDAR, self::EDITAR]],
        'Tratamientos' => ['Tratamientos', 'Registrar, corregir y cancelar tratamientos.', [self::GUARDAR, self::EDITAR, self::ELIMINAR]],
        'Enfermería' => ['Hoja de enfermería', 'Ver la hoja de enfermería y registrar notas.', [self::VER, self::GUARDAR]],
        'Hospitalización' => ['Habitación del paciente', 'Internar, asignar habitación (Guardar) y cambiarla (Editar).', [self::GUARDAR, self::EDITAR]],
        'Alta médica' => ['Alta médica', 'Dar de alta al paciente, también en consulta externa.', [self::GUARDAR]],
        'Cirugías' => ['Cirugías', 'Ver, agendar o registrar cirugías y sus controles.', [self::VER, self::GUARDAR, self::EDITAR]],
        'Habitaciones' => ['Habitaciones', 'Ver, crear y editar habitaciones.', [self::VER, self::GUARDAR, self::EDITAR]],
        'Personal' => ['Personal', 'Ver, registrar, editar y desactivar al personal.', [self::VER, self::GUARDAR, self::EDITAR, self::ELIMINAR]],
        'Bitácora' => ['Bitácora', 'Consultar la actividad del sistema.', [self::VER]],
    ];

    /**
     * Permisos que se marcan solos al elegir el rol de un usuario nuevo.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const SUGERIDOS = [
        'Médico' => [
            'Pacientes' => [self::VER, self::GUARDAR, self::EDITAR],
            'Expedientes' => [self::VER],
            'Documentos clínicos' => [self::VER, self::GUARDAR, self::EDITAR],
            'Ingresos' => [self::VER, self::GUARDAR, self::EDITAR, self::ELIMINAR],
            'Evolución clínica' => [self::GUARDAR, self::EDITAR, self::ELIMINAR],
            'Signos vitales' => [self::GUARDAR, self::EDITAR],
            'Tratamientos' => [self::GUARDAR, self::EDITAR, self::ELIMINAR],
            'Enfermería' => [self::VER],
            'Hospitalización' => [self::GUARDAR, self::EDITAR],
            'Alta médica' => [self::GUARDAR],
            'Cirugías' => [self::VER],
            'Habitaciones' => [self::VER],
        ],
        'Enfermería' => [
            'Pacientes' => [self::VER],
            'Expedientes' => [self::VER],
            'Documentos clínicos' => [self::VER, self::GUARDAR, self::EDITAR],
            'Ingresos' => [self::VER],
            'Signos vitales' => [self::GUARDAR, self::EDITAR],
            'Enfermería' => [self::VER, self::GUARDAR],
            'Hospitalización' => [self::EDITAR],
            'Cirugías' => [self::VER, self::GUARDAR, self::EDITAR],
            'Habitaciones' => [self::VER],
        ],
        'Secretaria' => [
            'Pacientes' => [self::VER, self::GUARDAR, self::EDITAR, self::ELIMINAR],
            'Expedientes' => [self::VER],
            'Documentos clínicos' => [self::VER, self::GUARDAR, self::EDITAR],
            'Ingresos' => [self::VER, self::GUARDAR, self::EDITAR],
            'Hospitalización' => [self::GUARDAR],
            'Cirugías' => [self::VER, self::GUARDAR],
            'Habitaciones' => [self::VER, self::GUARDAR, self::EDITAR],
        ],
    ];

    /**
     * Permiso que exige cada ruta del sistema, por su nombre.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const RUTAS = [
        'pacientes.index' => ['Pacientes', self::VER],
        'pacientes.guardar' => ['Pacientes', self::GUARDAR],
        'pacientes.actualizar' => ['Pacientes', self::EDITAR],
        'pacientes.desactivar' => ['Pacientes', self::ELIMINAR],
        'pacientes.activar' => ['Pacientes', self::ELIMINAR],
        'pacientes.contactos.guardar' => ['Pacientes', self::EDITAR],
        'pacientes.contactos.actualizar' => ['Pacientes', self::EDITAR],
        'pacientes.contactos.eliminar' => ['Pacientes', self::ELIMINAR],
        'expedientes.index' => ['Expedientes', self::VER],
        'pacientes.expediente' => ['Expedientes', self::VER],
        'archivos.show' => ['Documentos clínicos', self::VER],
        'archivos.contenido' => ['Documentos clínicos', self::VER],
        'archivos.formulario' => ['Documentos clínicos', self::GUARDAR],
        'archivos.guardar' => ['Documentos clínicos', self::GUARDAR],
        'archivos.paciente.guardar' => ['Documentos clínicos', self::GUARDAR],
        'archivos.editar' => ['Documentos clínicos', self::EDITAR],
        'archivos.actualizar' => ['Documentos clínicos', self::EDITAR],
        'atencion.index' => ['Ingresos', self::VER],
        'atencion.show' => ['Ingresos', self::VER],
        'consultas.iniciar' => ['Ingresos', self::GUARDAR],
        'ingresos.guardar' => ['Ingresos', self::GUARDAR],
        'seguimientos.crear' => ['Ingresos', self::GUARDAR],
        'seguimientos.guardar' => ['Ingresos', self::GUARDAR],
        'ingresos.actualizar' => ['Ingresos', self::EDITAR],
        'ingresos.cancelar' => ['Ingresos', self::ELIMINAR],
        'emergencia.guardar' => ['Evolución clínica', self::GUARDAR],
        'evolucion.guardar' => ['Evolución clínica', self::GUARDAR],
        'evolucion.actualizar' => ['Evolución clínica', self::EDITAR],
        'evolucion.eliminar' => ['Evolución clínica', self::ELIMINAR],
        'signos.guardar' => ['Signos vitales', self::GUARDAR],
        'signos.actualizar' => ['Signos vitales', self::EDITAR],
        'tratamientos.guardar' => ['Tratamientos', self::GUARDAR],
        'tratamientos.actualizar' => ['Tratamientos', self::EDITAR],
        'tratamientos.eliminar' => ['Tratamientos', self::ELIMINAR],
        'atencion.enfermeria' => ['Enfermería', self::VER],
        'notas-enfermeria.guardar' => ['Enfermería', self::GUARDAR],
        'hospitalizacion.guardar' => ['Hospitalización', self::GUARDAR],
        'hospitalizacion.cambiar-habitacion' => ['Hospitalización', self::EDITAR],
        'alta.guardar' => ['Alta médica', self::GUARDAR],
        'cirugias.show' => ['Cirugías', self::VER],
        'cirugias.formulario' => ['Cirugías', self::GUARDAR],
        'cirugias.guardar' => ['Cirugías', self::GUARDAR],
        'cirugias.estado' => ['Cirugías', self::EDITAR],
        'cirugias.control' => ['Cirugías', self::EDITAR],
        'habitaciones.index' => ['Habitaciones', self::VER],
        'habitaciones.guardar' => ['Habitaciones', self::GUARDAR],
        'habitaciones.actualizar' => ['Habitaciones', self::EDITAR],
        'personal.index' => ['Personal', self::VER],
        'personal.guardar' => ['Personal', self::GUARDAR],
        'personal.actualizar' => ['Personal', self::EDITAR],
        'personal.desactivar' => ['Personal', self::ELIMINAR],
        'personal.activar' => ['Personal', self::ELIMINAR],
        'bitacora.index' => ['Bitácora', self::VER],
        'usuarios.index' => ['Usuarios', self::VER],
        'usuarios.crear' => ['Usuarios', self::GUARDAR],
        'usuarios.guardar' => ['Usuarios', self::GUARDAR],
        'usuarios.editar' => ['Usuarios', self::EDITAR],
        'usuarios.actualizar' => ['Usuarios', self::EDITAR],
        'usuarios.contrasena' => ['Usuarios', self::EDITAR],
        'usuarios.estado' => ['Usuarios', self::ELIMINAR],
    ];

    /**
     * Rutas que cualquier usuario con sesión puede usar: su perfil, el inicio y avisos internos.
     * Las rutas antiguas que solo redirigen a otra pantalla también quedan libres.
     *
     * @var list<string>
     */
    public const RUTAS_LIBRES = [
        'inicio', 'sesion.salida-pagina', 'personal.foto',
        'perfil.show', 'perfil.foto.editar', 'perfil.contrasena.editar', 'perfil.contacto', 'perfil.foto', 'perfil.quitar-foto', 'perfil.contrasena',
        'evolucion.index', 'signos.index', 'tratamientos.index', 'ingresos.index',
    ];

    /**
     * Si el usuario (por defecto, el de la sesión) tiene un rol de administrador.
     */
    public function esAdministrador(?int $idUsuario = null): bool
    {
        return $this->datos($idUsuario)['administrador'];
    }

    /**
     * Si el usuario de la sesión puede hacer la acción en el módulo.
     */
    public function puede(string $modulo, string $accion, ?int $idUsuario = null): bool
    {
        $datos = $this->datos($idUsuario);
        if ($datos['administrador']) {
            return true;
        }

        // Los usuarios y sus permisos solo los administra el administrador.
        return $modulo !== 'Usuarios' && in_array($modulo.'|'.$accion, $datos['permisos'], true);
    }

    /**
     * Nombre visible de un módulo y acción, por ejemplo «Guardar en Pacientes».
     */
    public static function descripcion(string $modulo, string $accion): string
    {
        $nombres = [self::VER => 'ver', self::GUARDAR => 'guardar', self::EDITAR => 'editar', self::ELIMINAR => 'eliminar'];

        return ($nombres[$accion] ?? mb_strtolower($accion)).' en «'.(self::MODULOS[$modulo][0] ?? $modulo).'»';
    }

    /**
     * Permisos marcados para el usuario, como «Módulo|Acción».
     *
     * @return list<string>
     */
    public function deUsuario(int $idUsuario): array
    {
        return DB::table('tbl_usuario_permiso as up')
            ->join('tbl_permiso as pe', 'pe.id_permiso', '=', 'up.id_permiso')
            ->join('tbl_modulo as m', 'm.id_modulo', '=', 'pe.id_modulo')
            ->where('up.id_usuario', $idUsuario)->where('pe.estado', 'Activo')->where('m.estado', 'Activo')
            ->get(['m.nombre_modulo', 'pe.accion'])
            ->map(fn ($fila) => $fila->nombre_modulo.'|'.$fila->accion)->all();
    }

    /**
     * Se calcula una vez por solicitud para no consultar la base en cada botón.
     *
     * @return array{administrador: bool, permisos: list<string>}
     */
    private function datos(?int $idUsuario): array
    {
        $idUsuario ??= (int) session('id_usuario');
        $clave = 'permisos_usuario_'.$idUsuario;
        $request = request();
        if ($request->attributes->has($clave)) {
            return $request->attributes->get($clave);
        }
        $rol = DB::table('tbl_usuario as u')->join('tbl_rol as r', 'r.id_rol', '=', 'u.id_rol')
            ->where('u.id_usuario', $idUsuario)->value('r.nombre_rol');
        $administrador = self::rolEsAdministrador($rol);
        $datos = ['administrador' => $administrador, 'permisos' => $administrador || ! $idUsuario ? [] : $this->deUsuario($idUsuario)];
        $request->attributes->set($clave, $datos);

        return $datos;
    }

    public static function rolEsAdministrador(?string $nombreRol): bool
    {
        return str_contains(mb_strtolower((string) $nombreRol), 'admin');
    }

    /**
     * Borra lo calculado en esta solicitud, después de cambiar permisos.
     */
    public static function olvidar(int $idUsuario): void
    {
        request()->attributes->remove('permisos_usuario_'.$idUsuario);
    }
}
