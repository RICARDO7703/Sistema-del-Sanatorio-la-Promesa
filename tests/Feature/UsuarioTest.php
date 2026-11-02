<?php

namespace Tests\Feature;

use App\Http\Middleware\PermisoModulo;
use App\Support\Permisos;
use Database\Factories\IngresoFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    private const CLAVE = 'Clave-Segura#2026';

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::connection()->getPdo()->sqliteCreateFunction('regexp', fn ($pattern, $value) => $value !== null && preg_match('~'.$pattern.'~u', (string) $value) === 1, 2);
        DB::unprepared(file_get_contents(base_path('tests/Fixtures/v3.sqlite.sql')));
        (require database_path('migrations/2026_10_12_224836_add_examen_fisico_to_tbl_evolucion_clinica.php'))->up();
        (require database_path('migrations/2026_10_14_011905_add_ordenes_to_tbl_evolucion_clinica.php'))->up();
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(18, 0));
        DB::table('tbl_cargo')->insert([['id_cargo' => 1, 'nombre_cargo' => 'Administrador'], ['id_cargo' => 2, 'nombre_cargo' => 'Médico general'], ['id_cargo' => 3, 'nombre_cargo' => 'Enfermera']]);
        DB::table('tbl_personal')->insert([
            ['id_personal' => 1, 'id_cargo' => 1, 'nombres' => 'Ana', 'apellidos' => 'Admin', 'dpi' => '1234567890101'],
            ['id_personal' => 2, 'id_cargo' => 2, 'nombres' => 'Rosa', 'apellidos' => 'Médica', 'dpi' => '1234567890102'],
            ['id_personal' => 3, 'id_cargo' => 3, 'nombres' => 'Laura', 'apellidos' => 'Enfermera', 'dpi' => '1234567890103'],
        ]);
        DB::table('tbl_rol')->insert([['id_rol' => 1, 'nombre_rol' => 'Administrador'], ['id_rol' => 2, 'nombre_rol' => 'Médico'], ['id_rol' => 3, 'nombre_rol' => 'Enfermería'], ['id_rol' => 4, 'nombre_rol' => 'Secretaria']]);
        DB::table('tbl_usuario')->insert(['id_usuario' => 1, 'id_personal' => 1, 'id_rol' => 1, 'nombre_usuario' => 'admin', 'contrasena' => Hash::make(self::CLAVE)]);
        (require database_path('migrations/2026_11_01_031622_create_tbl_usuario_permiso_table.php'))->up();
    }

    private function admin(): static
    {
        return $this->withSession(['id_usuario' => 1]);
    }

    private function nuevoUsuario(array $cambios = []): array
    {
        return array_replace(['id_personal' => 2, 'nombre_usuario' => 'dra.medica', 'id_rol' => 2, 'contrasena' => self::CLAVE, 'contrasena_confirmation' => self::CLAVE,
            'permisos' => ['Pacientes|Ingresar', 'Expedientes|Ver', 'Evolución clínica|Ingresar']], $cambios);
    }

    private function permisosDe(int $idUsuario): array
    {
        return app(Permisos::class)->deUsuario($idUsuario);
    }

    public function test_cada_ruta_con_sesion_tiene_un_permiso_asignado(): void
    {
        $sinPermiso = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $ruta) => in_array(PermisoModulo::class, $ruta->gatherMiddleware(), true))
            ->map(fn (Route $ruta) => $ruta->getName() ?? $ruta->uri())
            ->reject(fn ($nombre) => isset(Permisos::RUTAS[$nombre]) || in_array($nombre, Permisos::RUTAS_LIBRES, true))
            ->values()->all();

        $this->assertSame([], $sinPermiso);
    }

    public function test_administrador_crea_un_usuario_con_sus_permisos_y_este_puede_entrar(): void
    {
        $this->admin()->get('/inicio')->assertOk()->assertSee(route('usuarios.index'), false);
        $this->get(route('usuarios.crear'))->assertOk()->assertSee('Rosa Médica')->assertDontSee('Ana Admin · ')
            ->assertSee('Marcar los sugeridos del rol')->assertSee('name="permisos[]" value="Pacientes|Ver"', false);

        $this->post(route('usuarios.guardar'), $this->nuevoUsuario())->assertSessionHasNoErrors();
        $id = (int) DB::table('tbl_usuario')->where('nombre_usuario', 'dra.medica')->value('id_usuario');
        $this->assertTrue(Hash::check(self::CLAVE, DB::table('tbl_usuario')->where('id_usuario', $id)->value('contrasena')));
        $this->assertEqualsCanonicalizing(['Pacientes|Ingresar', 'Pacientes|Ver', 'Expedientes|Ver', 'Evolución clínica|Ingresar'], $this->permisosDe($id), 'Guardar agrega Ver.');
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Creación de usuario', 'id_registro' => $id]);

        $this->post('/logout');
        $this->post('/login', ['usuario' => 'dra.medica', 'contrasena' => self::CLAVE])->assertRedirect('/inicio');
        $this->assertSame('2026-10-11 18:00:00', DB::table('tbl_usuario')->where('id_usuario', $id)->value('ultimo_acceso'));
        $this->get('/inicio')->assertOk()->assertSee(url('/pacientes'), false)->assertDontSee(route('usuarios.index'), false)->assertDontSee(route('personal.index'), false);
    }

    public function test_usuario_sin_permiso_no_ve_ni_usa_lo_que_no_tiene(): void
    {
        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 3, 'id_rol' => 3, 'nombre_usuario' => 'enfermera', 'contrasena' => Hash::make(self::CLAVE)]);
        $this->asignarPermisos(2, ['Pacientes' => [Permisos::VER]]);
        $this->withSession(['id_usuario' => 2]);

        $this->get('/pacientes')->assertOk()->assertDontSee('data-bs-target="#modalPaciente"', false)
            ->assertDontSee(url('/pacientes/'.DB::table('tbl_paciente')->value('id_paciente').'/consulta'), false);
        $this->post('/pacientes', ['nombres' => 'No debe', 'apellidos' => 'Guardarse'])->assertRedirect()
            ->assertSessionHas('permiso_denegado', 'No tiene permiso para guardar en «Pacientes». Pida al administrador que se lo asigne.');
        $this->assertDatabaseMissing('tbl_paciente', ['nombres' => 'No debe']);
        $this->get('/personal')->assertRedirect('/inicio')->assertSessionHas('permiso_denegado');
        $this->get('/inicio')->assertSee('No tiene permiso para ver en «Personal»');
        $this->get(route('usuarios.index'))->assertRedirect('/inicio');
        $this->get('/bitacora')->assertRedirect('/inicio');
        $this->get(route('perfil.show'))->assertOk();
    }

    public function test_alta_desde_consulta_externa_exige_permiso_de_alta(): void
    {
        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 3, 'id_rol' => 3, 'nombre_usuario' => 'enfermera', 'contrasena' => Hash::make(self::CLAVE)]);
        $this->asignarPermisos(2, ['Ingresos' => [Permisos::VER], 'Evolución clínica' => [Permisos::GUARDAR]]);
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa', 'id_personal_registra' => 1]);

        $this->withSession(['id_usuario' => 2])->get(route('atencion.show', $ingreso))->assertOk()
            ->assertDontSee('data-pedir-alta><i', false)->assertDontSee('value="internar"', false)->assertSee('No tiene permiso para guardar la consulta');
        $this->post(route('emergencia.guardar', $ingreso), ['_formulario' => 'consulta-emergencia', 'accion' => 'alta', 'fecha_hora' => '2026-10-11 12:00:00', 'motivo_atencion' => 'Dolor', 'diagnostico' => 'Gastritis'])
            ->assertSessionHas('permiso_denegado');
        $this->assertSame('Activo', $ingreso->fresh()->estado);
    }

    public function test_administrador_siempre_tiene_acceso_aunque_no_tenga_permisos_marcados(): void
    {
        $this->assertSame(0, DB::table('tbl_usuario_permiso')->where('id_usuario', 1)->count());
        $this->admin();
        foreach (['/pacientes', '/personal', '/habitaciones', '/bitacora', '/expedientes', '/atencion', '/usuarios'] as $pantalla) {
            $this->get($pantalla)->assertOk();
        }
    }

    public function test_editar_rol_permisos_y_aplicar_sin_cerrar_sesion(): void
    {
        $this->admin()->post(route('usuarios.guardar'), $this->nuevoUsuario())->assertSessionHasNoErrors();
        $id = (int) DB::table('tbl_usuario')->where('nombre_usuario', 'dra.medica')->value('id_usuario');

        $this->get(route('usuarios.editar', $id))->assertOk()->assertSee('value="Pacientes|Ingresar" id', false)->assertSee('Restablecer contraseña');
        $this->put(route('usuarios.actualizar', $id), ['nombre_usuario' => 'dra.rosa', 'id_rol' => 3, 'permisos' => ['Personal|Editar']])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Personal|Editar', 'Personal|Ver'], $this->permisosDe($id));
        $this->assertSame(['dra.rosa', 3], [DB::table('tbl_usuario')->where('id_usuario', $id)->value('nombre_usuario'), (int) DB::table('tbl_usuario')->where('id_usuario', $id)->value('id_rol')]);

        $this->put(route('usuarios.actualizar', $id), ['nombre_usuario' => 'dra.rosa', 'id_rol' => 1, 'permisos' => ['Personal|Editar']])->assertSessionHasNoErrors();
        $this->assertSame([], $this->permisosDe($id), 'Un administrador no necesita permisos guardados.');
        $this->withSession(['id_usuario' => $id])->get('/usuarios')->assertOk();
    }

    public function test_validaciones_al_crear_usuario(): void
    {
        $this->admin()->from(route('usuarios.crear'))->post(route('usuarios.guardar'), $this->nuevoUsuario(['id_personal' => 1, 'nombre_usuario' => 'Admin', 'contrasena' => 'corta', 'contrasena_confirmation' => 'otra', 'permisos' => ['Usuarios|Ver']]))
            ->assertRedirect(route('usuarios.crear'))
            ->assertSessionHasErrors(['id_personal' => 'Esa persona ya tiene una cuenta.', 'contrasena', 'permisos.0']);
        $this->post(route('usuarios.guardar'), $this->nuevoUsuario(['nombre_usuario' => 'dra médica']))->assertSessionHasErrors('nombre_usuario');
        $this->post(route('usuarios.guardar'), $this->nuevoUsuario(['nombre_usuario' => 'ADMIN']))->assertSessionHasErrors(['nombre_usuario' => 'Ese nombre de usuario ya existe. Elija otro.']);
        $this->assertSame(1, DB::table('tbl_usuario')->count());
    }

    public function test_no_se_puede_quedar_sin_administrador(): void
    {
        $this->admin()->put(route('usuarios.actualizar', 1), ['nombre_usuario' => 'admin', 'id_rol' => 2])->assertSessionHasErrors(['id_rol' => 'No puede quitarse a sí mismo el rol de administrador.']);
        $this->patch(route('usuarios.estado', 1))->assertSessionHas('error', 'No puede desactivar su propia cuenta.');
        $this->assertSame('Activo', DB::table('tbl_usuario')->where('id_usuario', 1)->value('estado'));

        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 2, 'id_rol' => 1, 'nombre_usuario' => 'segundo', 'contrasena' => Hash::make(self::CLAVE)]);
        $this->patch(route('usuarios.estado', 2))->assertSessionHas('exito');
        $this->withSession(['id_usuario' => 1])->put(route('usuarios.actualizar', 2), ['nombre_usuario' => 'segundo', 'id_rol' => 1])->assertSessionHasNoErrors();
    }

    public function test_restablecer_contrasena_y_desactivar_impide_entrar(): void
    {
        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 3, 'id_rol' => 3, 'nombre_usuario' => 'enfermera', 'contrasena' => Hash::make('Vieja-Clave#2026'), 'estado' => 'Bloqueado', 'intentos_fallidos' => 5]);
        $this->admin()->patch(route('usuarios.contrasena', 2), ['contrasena' => self::CLAVE, 'contrasena_confirmation' => self::CLAVE])->assertSessionHasNoErrors();
        $cuenta = DB::table('tbl_usuario')->where('id_usuario', 2)->first();
        $this->assertTrue(Hash::check(self::CLAVE, $cuenta->contrasena));
        $this->assertSame(['Activo', 0], [$cuenta->estado, (int) $cuenta->intentos_fallidos]);
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Restablecimiento de contraseña', 'id_registro' => 2]);

        $this->patch(route('usuarios.estado', 2))->assertSessionHas('exito', 'Usuario desactivado. Ya no podrá iniciar sesión.');
        $this->post('/logout');
        $this->post('/login', ['usuario' => 'enfermera', 'contrasena' => self::CLAVE])->assertSessionHas('error');
    }

    public function test_migracion_da_permisos_del_rol_a_usuarios_existentes_y_se_puede_repetir(): void
    {
        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 3, 'id_rol' => 3, 'nombre_usuario' => 'enfermera', 'contrasena' => Hash::make(self::CLAVE)]);
        $migracion = require database_path('migrations/2026_11_01_031622_create_tbl_usuario_permiso_table.php');
        $migracion->up();
        $migracion->up();

        $esperados = collect(Permisos::SUGERIDOS['Enfermería'])->flatMap(fn ($acciones, $modulo) => array_map(fn ($accion) => $modulo.'|'.$accion, $acciones))->all();
        $this->assertEqualsCanonicalizing($esperados, $this->permisosDe(2));
        $this->assertSame(0, DB::table('tbl_usuario_permiso')->where('id_usuario', 1)->count());
        $this->assertSame(1, DB::table('tbl_modulo')->where('nombre_modulo', 'Alta médica')->count());
    }

    public function test_foto_de_perfil_al_crear_y_editar_un_usuario(): void
    {
        Storage::fake('documentos_clinicos');
        $this->admin()->get(route('usuarios.crear'))->assertOk()->assertSee('name="foto"', false)->assertSee('enctype="multipart/form-data"', false);

        $this->post(route('usuarios.guardar'), $this->nuevoUsuario(['foto' => UploadedFile::fake()->create('virus.php', 5, 'application/x-php')]))->assertSessionHasErrors('foto');
        $this->assertDatabaseMissing('tbl_usuario', ['nombre_usuario' => 'dra.medica']);

        $this->post(route('usuarios.guardar'), $this->nuevoUsuario(['foto' => UploadedFile::fake()->image('rosa.jpg', 200, 200)]))->assertSessionHasNoErrors();
        $id = (int) DB::table('tbl_usuario')->where('nombre_usuario', 'dra.medica')->value('id_usuario');
        $primera = DB::table('tbl_personal')->where('id_personal', 2)->value('foto_perfil');
        $this->assertStringStartsWith('fotos-personal/2/', $primera);
        Storage::disk('documentos_clinicos')->assertExists($primera);
        $this->get(route('usuarios.editar', $id))->assertOk()->assertSee(route('personal.foto', ['personaFoto' => 2], false), false)->assertSee('name="quitar_foto"', false);

        // Cambiarla reemplaza el archivo anterior.
        $datos = ['nombre_usuario' => 'dra.medica', 'id_rol' => 2, 'permisos' => ['Pacientes|Ver']];
        $this->put(route('usuarios.actualizar', $id), $datos + ['foto' => UploadedFile::fake()->image('nueva.png', 300, 300)])->assertSessionHasNoErrors();
        $segunda = DB::table('tbl_personal')->where('id_personal', 2)->value('foto_perfil');
        $this->assertNotSame($primera, $segunda);
        Storage::disk('documentos_clinicos')->assertMissing($primera);
        Storage::disk('documentos_clinicos')->assertExists($segunda);

        // Quitarla deja el icono de usuario.
        $this->put(route('usuarios.actualizar', $id), $datos + ['quitar_foto' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(DB::table('tbl_personal')->where('id_personal', 2)->value('foto_perfil'));
        Storage::disk('documentos_clinicos')->assertMissing($segunda);
    }

    public function test_apariencia_permite_elegir_modo_claro_u_oscuro(): void
    {
        $this->admin()->get('/inicio')->assertOk()
            ->assertSee(route('perfil.apariencia.editar'), false)
            ->assertSee("localStorage.getItem('sanatorio:tema') === 'oscuro'", false);
        $this->get(route('perfil.apariencia.editar'))->assertOk()
            ->assertSee('Modo de color')->assertSee('id="tema-claro"', false)->assertSee('id="tema-oscuro"', false);
        $this->post('/logout');
        $this->get('/')->assertOk()->assertSee("localStorage.getItem('sanatorio:tema') === 'oscuro'", false);
    }
}
