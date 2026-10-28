<?php

namespace Tests\Feature;

use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Models\Paciente;
use App\Models\Personal;
use Database\Factories\IngresoFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SistemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('testing', app()->environment());
        self::assertSame('sqlite', config('database.default'));
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('regexp', fn ($pattern, $value) => $value !== null && preg_match('~'.$pattern.'~u', (string) $value) === 1, 2);
        DB::unprepared(file_get_contents(base_path('tests/Fixtures/v3.sqlite.sql')));
        (require database_path('migrations/2026_10_12_224836_add_examen_fisico_to_tbl_evolucion_clinica.php'))->up();
        (require database_path('migrations/2026_10_14_011905_add_ordenes_to_tbl_evolucion_clinica.php'))->up();
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(12, 0));
        DB::table('tbl_cargo')->insert(['id_cargo' => 1, 'nombre_cargo' => 'Médico general']);
        DB::table('tbl_personal')->insert(['id_personal' => 1, 'id_cargo' => 1, 'nombres' => 'Médico', 'apellidos' => 'Prueba', 'dpi' => '1234567890123']);
        DB::table('tbl_rol')->insert(['id_rol' => 1, 'nombre_rol' => 'Administrador']);
        DB::table('tbl_usuario')->insert(['id_usuario' => 1, 'id_personal' => 1, 'id_rol' => 1, 'nombre_usuario' => 'prueba', 'contrasena' => 'solo-pruebas']);
        DB::table('tbl_habitacion')->insert([['id_habitacion' => 1, 'numero_habitacion' => '101'], ['id_habitacion' => 2, 'numero_habitacion' => '102']]);
        DB::table('tbl_medicamento')->insert(['id_medicamento' => 1, 'nombre_medicamento' => 'Medicamento de prueba']);
    }

    #[DataProvider('pantallas')]
    public function test_pantallas_implementadas_abren_sin_excepciones(string $ruta): void
    {
        $this->withSession(['id_usuario' => 1])->get($ruta)->assertOk();
    }

    public static function pantallas(): array
    {
        return array_map(fn ($ruta) => [$ruta], ['/', '/inicio', '/pacientes', '/personal', '/habitaciones', '/atencion', '/bitacora']);
    }

    private function pacienteValido(): array
    {
        return ['estado_identificacion' => 'Identificado', 'nombres' => 'Ana María', 'apellidos' => 'López Pérez', 'dpi' => '2234567890123', 'telefono' => '55555555', 'sexo' => 'Femenino', 'tipo_sangre' => 'O+', 'fecha_nacimiento' => '1990-06-14'];
    }

    #[DataProvider('pacientesInvalidos')]
    public function test_paciente_rechaza_datos_invalidos(string $campo, mixed $valor): void
    {
        $this->withSession(['id_usuario' => 1])->post('/pacientes', array_replace($this->pacienteValido(), [$campo => $valor]))->assertSessionHasErrors($campo);
        $this->assertDatabaseCount('tbl_paciente', 0);
    }

    public static function pacientesInvalidos(): array
    {
        return [['nombres', 'Ana123'], ['apellidos', '123'], ['dpi', '123'], ['telefono', 'abc'], ['tipo_sangre', '12'], ['sexo', 'Otro'], ['fecha_nacimiento', '2027-01-01'], ['nombres', []], ['contacto', 'texto']];
    }

    public function test_paciente_contacto_edicion_y_expediente(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/pacientes', $this->pacienteValido() + ['contacto' => ['nombre' => 'María López', 'parentesco' => 'Madre', 'telefono' => '44444444']])->assertSessionHasNoErrors();
        $paciente = Paciente::firstOrFail();
        $this->get('/pacientes')->assertOk()->assertSee('Iniciar consulta')->assertSee('Hospitalizar')->assertSee('data-iniciar-atencion="'.$paciente->getKey().'"', false);
        $this->get('/pacientes/'.$paciente->getKey().'/expediente')->assertOk()->assertSee('Primera consulta')->assertSee('Hospitalizar paciente')->assertDontSee('Información clínica general');
        DB::table('tbl_paciente')->where('id_paciente', $paciente->getKey())->update(['alergias' => 'Alergia de prueba']);
        $this->get('/pacientes/'.$paciente->getKey().'/expediente')->assertOk()->assertSee('Información clínica general')->assertSee('Alergia de prueba')
            ->assertSeeInOrder(['id="datos-paciente"', 'id="contactos-paciente"', 'Información clínica general', 'id="historial-evoluciones"'], false);
        $this->put('/pacientes/'.$paciente->getKey(), array_replace($this->pacienteValido(), ['tipo_sangre' => 'AB-']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_paciente', ['tipo_sangre' => 'AB-', 'telefono' => '5555-5555']);
        $this->post('/pacientes', $this->pacienteValido())->assertSessionHasErrors('dpi');
        $this->patch('/pacientes/'.$paciente->getKey().'/desactivar')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_paciente', ['estado' => 'Inactivo']);
        $this->patch('/pacientes/'.$paciente->getKey().'/activar')->assertSessionHasNoErrors();
        $contacto = DB::table('tbl_contacto_paciente')->first();
        $this->put('/pacientes/contactos/'.$contacto->id_contacto, ['nombre' => '123', 'telefono' => 'abc'])->assertSessionHasErrors(['nombre', 'telefono']);
    }

    public function test_paciente_no_identificado_puede_guardarse_sin_telefono(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/pacientes', ['estado_identificacion' => 'No identificado'])->assertSessionHasNoErrors();
        $paciente = Paciente::firstOrFail();
        self::assertNotEmpty($paciente->codigo_temporal);
        $this->get('/pacientes/'.$paciente->getKey().'/expediente')->assertOk()->assertDontSee('id="contactos-paciente"', false);
    }

    private function personalValido(): array
    {
        return ['nombres' => 'María', 'apellidos' => 'García', 'dpi' => '3234567890123', 'telefono' => '44444444', 'correo' => 'prueba@example.com', 'id_cargo' => 1];
    }

    #[DataProvider('personalInvalido')]
    public function test_personal_rechaza_datos_invalidos(string $campo, mixed $valor): void
    {
        $this->withSession(['id_usuario' => 1])->post('/personal', array_replace($this->personalValido(), [$campo => $valor]))->assertSessionHasErrors($campo);
        $this->assertDatabaseCount('tbl_personal', 1);
    }

    public static function personalInvalido(): array
    {
        return [['nombres', '123'], ['dpi', '123'], ['telefono', 'abc'], ['correo', 'invalido'], ['correo', ['x']], ['numero_colegiado', ['x']], ['id_cargo', 999], ['id_cargo', [1]], ['id_especialidad', 999], ['es_externo', 'otro']];
    }

    public function test_personal_creacion_edicion_duplicados_y_especialidad(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/personal', $this->personalValido())->assertSessionHasNoErrors();
        $personal = Personal::where('dpi', '3234567890123')->firstOrFail();
        $this->put('/personal/'.$personal->getKey(), $this->personalValido())->assertSessionHasNoErrors();
        $this->post('/personal', $this->personalValido())->assertSessionHasErrors(['dpi', 'correo']);
        DB::table('tbl_especialidad')->insert(['id_especialidad' => 1, 'nombre_especialidad' => 'Prueba']);
        $this->put('/personal/'.$personal->getKey(), $this->personalValido() + ['id_especialidad' => 1])->assertSessionHasErrors('id_especialidad');
        DB::table('tbl_cargo_especialidad')->insert(['id_cargo' => 1, 'id_especialidad' => 1]);
        $this->put('/personal/'.$personal->getKey(), $this->personalValido() + ['id_especialidad' => 1])->assertSessionHasNoErrors();
        $this->patch('/personal/'.$personal->getKey().'/desactivar')->assertSessionHasNoErrors();
        $this->patch('/personal/'.$personal->getKey().'/activar')->assertSessionHasNoErrors();
    }

    public function test_habitacion_guarda_estado_operativo_y_rechaza_duplicados(): void
    {
        $datos = ['numero_habitacion' => '103', 'tipo' => 'Privada', 'estado_operativo' => 'Mantenimiento'];
        $this->withSession(['id_usuario' => 1])->post('/habitaciones', $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_habitacion', ['numero_habitacion' => '103', 'estado_operativo' => 'Mantenimiento']);
        $this->post('/habitaciones', $datos)->assertSessionHasErrors('numero_habitacion');
        $this->put('/habitaciones/1', array_replace($datos, ['numero_habitacion' => '101', 'estado_operativo' => 'Activa']))->assertSessionHasNoErrors();
        $this->post('/habitaciones', array_replace($datos, ['numero_habitacion' => '104', 'estado_operativo' => 'Ocupada']))->assertSessionHasErrors('estado_operativo');
    }

    public function test_habitacion_ocupada_no_se_desactiva_ni_marca_mantenimiento(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post('/atencion/'.$ingreso->getKey().'/hospitalizacion', ['id_habitacion' => 1])->assertSessionHasNoErrors();
        $this->get('/habitaciones')->assertOk()->assertSee('Ocupada');
        $this->put('/habitaciones/1', ['numero_habitacion' => '101', 'tipo' => 'Privada', 'estado_operativo' => 'Mantenimiento'])->assertSessionHasErrors('estado_operativo');
        $this->assertDatabaseHas('tbl_habitacion', ['id_habitacion' => 1, 'estado_operativo' => 'Activa']);
    }

    #[DataProvider('busquedas')]
    public function test_busquedas_rechazan_estructuras_invalidas(string $ruta): void
    {
        $this->withSession(['id_usuario' => 1])->get($ruta.'?buscar[]=x')->assertSessionHasErrors('buscar');
    }

    public static function busquedas(): array
    {
        return [['/pacientes'], ['/personal'], ['/habitaciones'], ['/atencion']];
    }

    public function test_login_correcto_incorrecto_y_logout(): void
    {
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['contrasena' => Hash::make('ClaveDePrueba')]);
        $this->post('/login', ['usuario' => 'prueba', 'contrasena' => 'Incorrecta'])->assertSessionHas('error');
        $this->post('/login', ['usuario' => 'prueba', 'contrasena' => 'ClaveDePrueba'])->assertRedirect('/inicio')->assertSessionHas('id_usuario', 1);
        $this->post('/logout')->assertRedirect('/')->assertSessionMissing('id_usuario');
        $this->get('/pacientes')->assertRedirect('/');
    }

    public function test_login_hash_invalido_no_produce_excepcion(): void
    {
        $this->post('/login', ['usuario' => 'prueba', 'contrasena' => 'ClaveDePrueba'])->assertSessionHas('error');
    }

    public function test_personal_inactivo_no_conserva_acceso(): void
    {
        DB::table('tbl_personal')->where('id_personal', 1)->update(['estado' => 'Inactivo']);
        $this->withSession(['id_usuario' => 1])->get('/inicio')->assertRedirect('/')->assertSessionMissing('id_usuario');
    }

    public function test_ingreso_rechaza_identificadores_en_arreglo(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/ingresos', ['id_paciente' => [1], 'id_habitacion' => [1], 'fecha_ingreso' => '2026-10-11 10:00:00'])->assertSessionHasErrors(['id_paciente', 'id_habitacion']);
    }

    public function test_error_de_validacion_permite_volver_al_formulario(): void
    {
        $this->withSession(['id_usuario' => 1])->from('/personal')->post('/personal', array_replace($this->personalValido(), ['correo' => ['incorrecto']]))->assertSessionHasErrors('correo');
        $this->get('/personal')->assertOk();
        $ingreso = IngresoFactory::new()->create();
        $datos = ['id_ingreso' => $ingreso->getKey(), 'fecha_hora' => '2026-10-11 10:00:00', 'motivo_atencion' => 'Control', 'diagnostico' => 'Prueba', '_formulario' => 'evolucion-nueva', 'examen_fisico' => ['presion_arterial' => ['120/80']]];
        $this->from('/atencion/'.$ingreso->getKey())->post('/evolucion', $datos)->assertSessionHasErrors('examen_fisico.presion_arterial');
        $this->get('/atencion/'.$ingreso->getKey())->assertOk();
    }

    public function test_contactos_permiten_crear_editar_y_eliminar(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post('/pacientes/'.$ingreso->id_paciente.'/contactos', ['nombre' => 'Marta', 'telefono' => '44444444', 'parentesco' => 'Madre'])->assertSessionHasNoErrors();
        $id = DB::table('tbl_contacto_paciente')->value('id_contacto');
        $this->put('/pacientes/contactos/'.$id, ['nombre' => 'Marta Pérez', 'telefono' => '33333333'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_contacto_paciente', ['nombre' => 'Marta Pérez', 'telefono' => '3333-3333']);
        $this->delete('/pacientes/contactos/'.$id)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tbl_contacto_paciente', 0);
    }

    public function test_formularios_escapan_texto_y_conservan_datos_invalidos(): void
    {
        $datos = $this->pacienteValido();
        $datos['alergias'] = '<script>alert(1)</script>';
        $this->withSession(['id_usuario' => 1])->post('/pacientes', $datos)->assertSessionHasNoErrors();
        $id = DB::table('tbl_paciente')->value('id_paciente');
        $this->get('/pacientes/'.$id.'/expediente')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_limite_de_intentos_de_login(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['usuario' => 'noexiste', 'contrasena' => 'Incorrecta'])->assertRedirect();
        }
        $this->post('/login', ['usuario' => 'noexiste', 'contrasena' => 'Incorrecta'])->assertStatus(429);
    }

    public function test_reingreso_muestra_hospitalizacion_actual(): void
    {
        $anterior = IngresoFactory::new()->create(['estado' => 'Dado de alta']);
        DB::table('tbl_alta_medica')->insert(['id_ingreso' => $anterior->getKey(), 'id_personal_medico' => 1, 'fecha_hora' => '2026-10-10 12:00:00', 'diagnostico_final' => 'Anterior', 'condicion_egreso' => 'Estable', 'id_usuario_creacion' => 1]);
        $actual = Ingreso::create(['id_paciente' => $anterior->id_paciente, 'id_personal_registra' => 1, 'fecha_ingreso' => '2026-10-11 09:00:00', 'estado' => 'Activo', 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1])->post('/atencion/'.$actual->getKey().'/hospitalizacion', ['id_habitacion' => 1])->assertSessionHasNoErrors();
        $this->get('/pacientes')->assertOk()->assertViewHas('pacientes', fn ($lista) => $lista->first()->situacion_actual === 'Hospitalizado');
    }

    public function test_ciclo_completo_desde_paciente_hasta_alta(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/pacientes', $this->pacienteValido())->assertSessionHasNoErrors();
        $paciente = Paciente::firstOrFail();
        $this->post('/ingresos', ['id_paciente' => $paciente->getKey(), 'id_habitacion' => 1, 'fecha_ingreso' => '2026-10-11 08:00:00', 'motivo_ingreso' => 'Control de prueba'])->assertSessionHasNoErrors();
        $ingreso = Ingreso::firstOrFail();
        $this->get('/pacientes')->assertOk()->assertSee('href="'.route('atencion.show', $ingreso).'"', false);
        $this->get(route('pacientes.expediente', $paciente))->assertOk()->assertSee('Habitación actual')->assertDontSee('Cambios de habitación');
        $datos = ['id_ingreso' => $ingreso->getKey(), 'fecha_hora' => '2026-10-11 09:00:00', 'motivo_atencion' => 'Control de prueba', 'diagnostico' => 'Diagnóstico de prueba', 'examen_fisico' => ['presion_arterial' => '120/80', 'frecuencia_cardiaca' => 80], 'estado_mejoria' => 'Sin cambios', 'id_personal_medico' => 1, 'ordenes' => ['dieta' => 'Libre', 'medicamentos' => [['nombre' => 'Medicina escrita libremente', 'dosis' => 75, 'via' => 'Intramuscular', 'frecuencia' => 8]]]];
        $this->post('/evolucion', $datos)->assertSessionHasNoErrors();
        $id = EvolucionClinica::firstOrFail()->getKey();
        $this->get('/atencion/'.$ingreso->getKey().'?registro='.$id)->assertOk()->assertSee('Medicina escrita libremente');
        $this->patch('/hospitalizacion/'.$ingreso->hospitalizacion->getKey().'/cambiar-habitacion', ['id_habitacion' => 2])->assertSessionHasNoErrors();
        $this->get(route('pacientes.expediente', $paciente))->assertOk()->assertSee('Habitación actual')->assertSee('Cambios de habitación')->assertSee('101')->assertSee('102');
        $this->travel(2)->minutes();
        $this->post('/atencion/'.$ingreso->getKey().'/alta', ['id_personal_medico' => 1, 'fecha_hora' => now()->format('Y-m-d H:i:s'), 'diagnostico_final' => 'De prueba', 'condicion_egreso' => 'Estable'])->assertSessionHasNoErrors();
        $this->get('/atencion/'.$ingreso->getKey().'?registro='.$id)->assertOk()->assertSee('modo consulta');
        $this->get(route('pacientes.expediente', $paciente))->assertOk()->assertSee('Última habitación ocupada')->assertSee('Cambios de habitación')->assertSee('102');
        self::assertSame('Dado de alta', $ingreso->fresh()->estado);
        $this->get('/pacientes')->assertOk()->assertSee('Iniciar consulta')->assertSee('Hospitalizar')->assertSee('data-iniciar-atencion="'.$paciente->getKey().'"', false)
            ->assertDontSee('href="'.route('atencion.show', $ingreso).'"', false);
        self::assertSame(0, DB::table('tbl_hospitalizacion_habitacion')->whereNull('fecha_hora_fin')->count());
    }

    public function test_varios_contactos_se_crean_y_editan_con_el_paciente(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/pacientes', $this->pacienteValido() + ['contactos' => [
            ['nombre' => 'María López', 'parentesco' => 'Madre', 'telefono' => '44444444'],
            ['nombre' => 'Juan López', 'parentesco' => 'Padre', 'telefono' => '33333333'],
            ['nombre' => null, 'telefono' => null],
        ]])->assertSessionHasNoErrors();
        $paciente = Paciente::firstOrFail();
        $this->assertDatabaseCount('tbl_contacto_paciente', 2);
        $contacto = $paciente->contactos()->firstOrFail();
        $this->put('/pacientes/'.$paciente->getKey(), $this->pacienteValido() + ['contactos' => [
            ['id_contacto' => $contacto->getKey(), 'nombre' => 'María Pérez', 'telefono' => '22222222'],
            ['nombre' => 'Ana López', 'parentesco' => 'Hermana'],
        ]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tbl_contacto_paciente', 3);
        $this->assertDatabaseHas('tbl_contacto_paciente', ['id_contacto' => $contacto->getKey(), 'nombre' => 'María Pérez', 'telefono' => '2222-2222']);
        $this->get('/pacientes')->assertOk()->assertSee('Agregar otro contacto')->assertSee('contactos[0][id_contacto]', false);
    }

    public function test_contactos_invalidos_no_guardan_parcialmente_el_paciente(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/pacientes', $this->pacienteValido() + ['contactos' => [
            ['nombre' => 'María López'], ['telefono' => '44444444'],
        ]])->assertSessionHasErrors('contactos.1.nombre');
        $this->assertDatabaseCount('tbl_paciente', 0);
        $this->assertDatabaseCount('tbl_contacto_paciente', 0);
    }

    public function test_edicion_rechaza_contactos_de_otro_paciente(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/pacientes', $this->pacienteValido() + ['contactos' => [['nombre' => 'María López']]])->assertSessionHasNoErrors();
        $primero = Paciente::firstOrFail();
        $contacto = $primero->contactos()->firstOrFail();
        $datos = array_replace($this->pacienteValido(), ['dpi' => '3234567890123']);
        $this->post('/pacientes', $datos)->assertSessionHasNoErrors();
        $segundo = Paciente::orderByDesc('id_paciente')->firstOrFail();
        $this->put('/pacientes/'.$segundo->getKey(), array_replace($datos, ['nombres' => 'Otro', 'contactos' => [
            ['id_contacto' => $contacto->getKey(), 'nombre' => 'Modificado'],
        ]]))->assertSessionHasErrors('contactos.0.id_contacto');
        $this->assertDatabaseHas('tbl_contacto_paciente', ['id_contacto' => $contacto->getKey(), 'nombre' => 'María López']);
        $this->assertDatabaseHas('tbl_paciente', ['id_paciente' => $segundo->getKey(), 'nombres' => 'Ana María']);
    }
}
