<?php

namespace Tests\Feature;

use App\Http\Middleware\RegistrarOperaciones;
use App\Http\Middleware\SesionClinica;
use App\Models\EvolucionClinica;
use App\Models\Paciente;
use Database\Factories\IngresoFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditoriaSistemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::connection()->getPdo()->sqliteCreateFunction('regexp', fn ($pattern, $value) => $value !== null && preg_match('~'.$pattern.'~u', (string) $value) === 1, 2);
        DB::unprepared(file_get_contents(base_path('tests/Fixtures/v3.sqlite.sql')));
        (require database_path('migrations/2026_10_15_053133_create_recuperacion_contrasenas_table.php'))->up();
        (require database_path('migrations/2026_10_15_112918_add_verificacion_to_recuperacion_contrasenas_table.php'))->up();
        DB::table('tbl_cargo')->insert(['id_cargo' => 1, 'nombre_cargo' => 'Administrador']);
        DB::table('tbl_personal')->insert(['id_personal' => 1, 'id_cargo' => 1, 'nombres' => 'Persona', 'apellidos' => 'Prueba', 'dpi' => '1234567890123', 'correo' => 'persona@example.com']);
        DB::table('tbl_rol')->insert(['id_rol' => 1, 'nombre_rol' => 'Administrador']);
        DB::table('tbl_usuario')->insert(['id_usuario' => 1, 'id_personal' => 1, 'id_rol' => 1, 'nombre_usuario' => 'prueba', 'contrasena' => Hash::make('Anterior-12345')]);
        Mail::fake();
    }

    public function test_registra_paciente_y_desactivacion_con_actor_tabla_id_y_contexto(): void
    {
        $this->withSession(['id_usuario' => 1])->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0')->post('/pacientes', ['estado_identificacion' => 'No identificado'])->assertSessionHasNoErrors();
        $id = Paciente::firstOrFail()->getKey();
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Registro de paciente', 'id_usuario' => 1, 'tabla' => 'tbl_paciente', 'id_registro' => $id, 'navegador' => 'Chrome 120.0', 'sistema_operativo' => 'Windows 10 / 11']);
        $this->patch('/pacientes/'.$id.'/desactivar')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Desactivación de paciente', 'id_registro' => $id]);
        $total = DB::table('tbl_bitacora')->count();
        $this->patch('/pacientes/'.$id.'/desactivar');
        self::assertSame($total, DB::table('tbl_bitacora')->count());
    }

    public function test_validacion_rechazada_no_registra_operacion_exitosa(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/pacientes', ['estado_identificacion' => 'inválido'])->assertSessionHasErrors();
        $this->assertDatabaseCount('tbl_bitacora', 0);
    }

    public function test_cambio_de_contrasena_audita_sin_guardar_secretos(): void
    {
        $datos = ['contrasena_actual' => 'Anterior-12345', 'nueva_contrasena' => 'Otra-Clave12345!', 'nueva_contrasena_confirmation' => 'Otra-Clave12345!'];
        $this->withSession(['id_usuario' => 1])->post(route('perfil.contrasena'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Cambio de contraseña', 'id_usuario' => 1, 'tabla' => 'tbl_usuario', 'id_registro' => 1]);
        $texto = DB::table('tbl_bitacora')->get()->toJson();
        self::assertStringNotContainsString($datos['contrasena_actual'], $texto);
        self::assertStringNotContainsString($datos['nueva_contrasena'], $texto);
        $this->post(route('perfil.contrasena'), $datos)->assertSessionHasErrors('contrasena_actual');
        $this->assertDatabaseCount('tbl_bitacora', 1);
    }

    public function test_logout_registra_antes_de_invalidar_y_no_duplica_sin_sesion(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/logout')->assertRedirect('/')->assertSessionMissing('id_usuario');
        $this->post('/logout');
        $this->assertDatabaseCount('tbl_bitacora', 1);
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Cierre de sesión', 'id_usuario' => 1]);
    }

    public function test_salida_pagina_es_aviso_no_logout_y_se_deduplica(): void
    {
        $datos = ['pagina' => (string) Str::uuid(), 'id_usuario' => 999, 'accion' => 'Cierre de sesión'];
        $this->post(route('sesion.salida-pagina'), $datos)->assertRedirect('/');
        $this->withSession(['id_usuario' => 1])->post(route('sesion.salida-pagina'), $datos)->assertNoContent()->assertSessionHas('id_usuario', 1);
        $this->post(route('sesion.salida-pagina'), $datos)->assertNoContent();
        $this->assertDatabaseCount('tbl_bitacora', 1);
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Posible cierre de página', 'id_usuario' => 1]);
        $this->post(route('sesion.salida-pagina'), ['pagina' => []])->assertSessionHasErrors('pagina');
    }

    public function test_rollback_revierte_tambien_la_bitacora(): void
    {
        Route::post('/prueba-auditoria-rollback', function () {
            Paciente::create(['numero_expediente' => 'TEST-ROLLBACK', 'estado_identificacion' => 'No identificado', 'codigo_temporal' => 'TMP-ROLLBACK', 'id_usuario_creacion' => 1]);
            throw ValidationException::withMessages(['prueba' => 'Revertir operación']);
        })->middleware(['web', SesionClinica::class, RegistrarOperaciones::class]);
        $this->withSession(['id_usuario' => 1])->post('/prueba-auditoria-rollback')->assertSessionHasErrors('prueba');
        $this->assertDatabaseCount('tbl_paciente', 0);
        $this->assertDatabaseCount('tbl_bitacora', 0);
    }

    public function test_registro_de_evolucion_tiene_id_real_sin_copiar_diagnostico(): void
    {
        (require database_path('migrations/2026_10_12_224836_add_examen_fisico_to_tbl_evolucion_clinica.php'))->up();
        (require database_path('migrations/2026_10_14_011905_add_ordenes_to_tbl_evolucion_clinica.php'))->up();
        DB::table('tbl_cargo')->where('id_cargo', 1)->update(['nombre_cargo' => 'Médico general']);
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post('/evolucion', ['id_ingreso' => $ingreso->getKey(), 'fecha_hora' => now('America/Guatemala')->format('Y-m-d H:i:s'), 'motivo_atencion' => 'Prueba', 'diagnostico' => 'DATO CLINICO PRIVADO', 'ordenes' => ['dieta' => 'Libre'], 'estado_mejoria' => 'Sin cambios', 'id_personal_medico' => 1])->assertSessionHasNoErrors();
        $registro = EvolucionClinica::firstOrFail();
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Registro de evolución clínica', 'id_registro' => $registro->getKey(), 'tabla' => 'tbl_evolucion_clinica']);
        self::assertStringNotContainsString('DATO CLINICO PRIVADO', DB::table('tbl_bitacora')->get()->toJson());
    }
}
