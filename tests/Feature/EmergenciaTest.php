<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Paciente;
use Database\Factories\IngresoFactory;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmergenciaTest extends TestCase
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
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(18, 0));
        DB::table('tbl_cargo')->insert(['id_cargo' => 1, 'nombre_cargo' => 'Médico general']);
        DB::table('tbl_personal')->insert(['id_personal' => 1, 'id_cargo' => 1, 'nombres' => 'Médico', 'apellidos' => 'Prueba', 'dpi' => '1234567890123']);
        DB::table('tbl_rol')->insert(['id_rol' => 1, 'nombre_rol' => 'Administrador']);
        DB::table('tbl_usuario')->insert(['id_usuario' => 1, 'id_personal' => 1, 'id_rol' => 1, 'nombre_usuario' => 'prueba', 'contrasena' => 'solo-pruebas']);
        DB::table('tbl_habitacion')->insert([['id_habitacion' => 1, 'numero_habitacion' => '101'], ['id_habitacion' => 2, 'numero_habitacion' => '102']]);
    }

    private function sesion(): static
    {
        return $this->withSession(['id_usuario' => 1]);
    }

    private function pacienteSinIngreso(): int
    {
        $previo = IngresoFactory::new()->create(['estado' => 'Cancelado']);

        return $previo->id_paciente;
    }

    private function emergencia(int $idPaciente, array $extra = []): array
    {
        return array_replace(['id_paciente' => $idPaciente, 'tipo_atencion' => 'Consulta externa', 'fecha_ingreso' => '2026-10-11 08:30:00', 'motivo_ingreso' => 'Dolor abdominal'], $extra);
    }

    private function emergenciaConValoracion(): Ingreso
    {
        $this->sesion()->post('/ingresos', $this->emergencia($this->pacienteSinIngreso()))->assertSessionHasNoErrors();
        $ingreso = Ingreso::where('tipo_atencion', 'Consulta externa')->firstOrFail();
        DB::table('tbl_evolucion_clinica')->insert(['id_ingreso' => $ingreso->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-11 09:00:00', 'motivo_atencion' => 'Dolor abdominal', 'diagnostico' => 'Gastritis', 'id_usuario_creacion' => 1]);

        return $ingreso;
    }

    private function cierre(array $extra = []): array
    {
        return array_replace(['id_personal_medico' => 1, 'fecha_hora' => '2026-10-11 10:00:00', 'diagnostico_final' => 'Gastritis aguda', 'condicion_egreso' => 'Estable'], $extra);
    }

    private function consulta(array $extra = []): array
    {
        return array_replace(['_formulario' => 'consulta-emergencia', 'accion' => 'alta', 'fecha_hora' => '2026-10-11 09:00:00', 'motivo_atencion' => 'Dolor abdominal', 'diagnostico' => 'Gastritis aguda'], $extra);
    }

    private function emergenciaActiva(): Ingreso
    {
        $this->sesion()->post('/ingresos', $this->emergencia($this->pacienteSinIngreso()))->assertSessionHasNoErrors();

        return Ingreso::where('tipo_atencion', 'Consulta externa')->where('estado', 'Activo')->firstOrFail();
    }

    public function test_migracion_es_idempotente_si_la_base_ya_tiene_los_cambios(): void
    {
        (require database_path('migrations/2026_10_18_045848_add_tipo_atencion_y_medicamentos_alta.php'))->up();

        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('tbl_ingreso', 'tipo_atencion'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('tbl_alta_medicamento'));
    }

    public function test_migracion_vuelve_opcional_la_via_si_la_tabla_la_exigia(): void
    {
        DB::statement('DROP TABLE tbl_alta_medicamento');
        DB::statement('CREATE TABLE tbl_alta_medicamento (id_alta_medicamento INTEGER PRIMARY KEY AUTOINCREMENT, id_alta INT NOT NULL, medicamento VARCHAR(255) NOT NULL, dosis VARCHAR(100) NOT NULL, via_administracion VARCHAR(80) NOT NULL, frecuencia VARCHAR(100) NOT NULL, duracion VARCHAR(100) NULL)');

        $migracion = require database_path('migrations/2026_10_18_054906_hacer_opcional_via_en_tbl_alta_medicamento.php');
        $migracion->up();
        $migracion->up();

        $columna = collect(DB::getSchemaBuilder()->getColumns('tbl_alta_medicamento'))->firstWhere('name', 'via_administracion');
        $this->assertTrue($columna['nullable']);
    }

    public function test_pantalla_de_emergencia_es_un_solo_formulario(): void
    {
        $ingreso = $this->emergenciaActiva();

        $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('Consulta externa')
            ->assertSee('action="'.route('emergencia.guardar', $ingreso).'"', false)
            ->assertSee('value="alta"', false)->assertSee('Dar de alta')
            ->assertSee('value="internar"', false)->assertSee('Internar paciente')
            ->assertSee('novalidate data-validar-en-linea', false)
            ->assertSee('id="modalConfirmarAlta"', false)->assertSee('Sí, dar de alta')
            ->assertDontSee('confirm(', false)
            ->assertSee('href="'.route('pacientes.index').'"', false)->assertSee('Volver a pacientes')->assertDontSee('Volver a pacientes en atención')
            ->assertSee('placeholder="Ej. 50 mg"', false)
            ->assertSee('Ambulatorio')->assertDontSee('Para la casa')->assertSee('Agregar medicamento')->assertSee('name="indicaciones_casa"', false)
            ->assertSee('data-elegir-signo="presion_arterial"', false)
            ->assertSee('Examen por regiones (opcional)')
            ->assertSeeInOrder(['Examen por regiones (opcional)', 'name="examen_fisico[cabeza]"', 'Signos vitales (opcional)', 'data-elegir-signo="frecuencia_cardiaca"'], false)
            ->assertDontSee('Valoración inicial')
            ->assertDontSee('id="modalAlta"', false)
            ->assertDontSee('Finalizar atención')
            ->assertDontSee('Evolución clínica y órdenes médicas')
            ->assertDontSee('ordenes[dieta]', false)
            ->assertDontSee('<strong>Habitación</strong>', false);
    }

    public function test_signos_ya_tomados_aparecen_visibles_y_los_demas_ocultos(): void
    {
        $ingreso = $this->emergenciaConValoracion();
        DB::table('tbl_evolucion_clinica')->update(['examen_fisico' => json_encode(['temperatura' => 38.5])]);

        $html = $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('value="38.5"', false)->getContent();

        $this->assertMatchesRegularExpression('/data-signo="temperatura"\s*>/', $html);
        $this->assertMatchesRegularExpression('/data-signo="presion_arterial"\s+hidden/', $html);
    }

    public function test_dar_de_alta_guarda_consulta_medicamentos_y_cierra_la_atencion(): void
    {
        $ingreso = $this->emergenciaActiva();

        $this->post(route('emergencia.guardar', $ingreso), $this->consulta([
            'sintomas' => 'Ardor en el estómago',
            'examen_fisico' => ['temperatura' => '37.8', 'presion_arterial' => '120/80'],
            'indicaciones' => 'Se aplicó omeprazol IV',
            'medicamentos_casa' => [
                ['medicamento' => 'Omeprazol 20 mg', 'dosis' => '1 cápsula', 'frecuencia' => 'Cada 24 horas', 'duracion' => '14 días'],
                ['medicamento' => '', 'dosis' => '', 'frecuencia' => '', 'duracion' => ''],
                ['medicamento' => ' Acetaminofén 500 mg ', 'dosis' => '1 tableta', 'frecuencia' => 'Cada 8 horas'],
            ],
            'indicaciones_casa' => 'Dieta blanda, no correr',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('atencion.show', $ingreso))
            ->assertSessionHas('exito', 'Paciente dado de alta. La consulta externa quedó cerrada.');

        $ingreso->refresh();
        $this->assertSame('Dado de alta', $ingreso->estado);
        $consulta = $ingreso->evoluciones()->sole();
        $this->assertSame('Gastritis aguda', $consulta->diagnostico);
        $this->assertNull($consulta->ordenes_medicas);
        $this->assertEquals(37.8, $consulta->examen_fisico['temperatura']);
        $this->assertArrayNotHasKey('frecuencia_cardiaca', array_filter($consulta->examen_fisico, fn ($valor) => $valor !== null));
        $this->assertDatabaseHas('tbl_alta_medica', ['id_ingreso' => $ingreso->getKey(), 'id_personal_medico' => 1, 'diagnostico_final' => 'Gastritis aguda', 'condicion_egreso' => 'Alta a casa', 'indicaciones_alta' => 'Dieta blanda, no correr', 'fecha_hora' => '2026-10-11 12:00:00']);
        $this->assertDatabaseCount('tbl_alta_medicamento', 2);
        $this->assertDatabaseHas('tbl_alta_medicamento', ['medicamento' => 'Acetaminofén 500 mg', 'duracion' => null, 'via_administracion' => null]);

        $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('Dado de alta el 11/10/2026 12:00')->assertSee('Temp. (°C): 37.8')->assertDontSee('FC (lpm)')
            ->assertSee('Omeprazol 20 mg')->assertSee('Dieta blanda, no correr')
            ->assertDontSee('value="alta"', false);
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
            ->assertSee('Consulta externa del '.$ingreso->fecha_ingreso->format('d/m/Y'))->assertSee('Atención finalizada')->assertSee('Omeprazol 20 mg');
    }

    public function test_internar_guarda_la_consulta_y_abre_la_seleccion_de_habitacion(): void
    {
        $ingreso = $this->emergenciaActiva();

        $this->post(route('emergencia.guardar', $ingreso), $this->consulta([
            'accion' => 'internar',
            'medicamentos_casa' => [['medicamento' => 'Incompleto', 'dosis' => '', 'frecuencia' => '']],
        ]))->assertSessionHasNoErrors()->assertSessionHas('abrir_internamiento', true);

        $this->assertSame('Activo', $ingreso->fresh()->estado);
        $this->assertDatabaseCount('tbl_alta_medica', 0);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 1);
        $this->get(route('atencion.show', $ingreso))->assertSee('document.getElementById("modalAsignarHabitacion")', false);
        $this->get(route('atencion.show', $ingreso))->assertSee('maxlength="255">Gastritis aguda</textarea>', false);

        $this->post(route('hospitalizacion.guardar', $ingreso), ['id_habitacion' => 2])->assertSessionHasNoErrors();
        $this->assertSame('Hospitalización', $ingreso->fresh()->tipo_atencion);
        $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('Valoración inicial registrada')->assertDontSee('Consulta externa');
    }

    public function test_guardar_otra_vez_actualiza_la_misma_consulta(): void
    {
        $ingreso = $this->emergenciaConValoracion();

        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['accion' => 'internar', 'diagnostico' => 'Apendicitis probable']))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tbl_evolucion_clinica', 1);
        $this->assertSame('Apendicitis probable', $ingreso->evoluciones()->sole()->diagnostico);
    }

    public function test_consulta_invalida_no_da_de_alta(): void
    {
        $ingreso = $this->emergenciaActiva();

        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['diagnostico' => '']))->assertSessionHasErrors('diagnostico');
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['accion' => 'otra']))->assertSessionHasErrors('accion');
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['fecha_hora' => '2026-10-11 07:00:00']))->assertSessionHasErrors('fecha_hora');
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['fecha_hora' => '2026-10-11 12:30:00']))
            ->assertSessionHasErrors(['fecha_hora' => 'La fecha y hora no pueden ser futuras.']);
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['medicamentos_casa' => [['medicamento' => 'Omeprazol', 'dosis' => '', 'frecuencia' => '']]]))
            ->assertSessionHasErrors(['medicamentos_casa.0.dosis', 'medicamentos_casa.0.frecuencia']);
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['medicamentos_casa' => 'texto']))->assertSessionHasErrors('medicamentos_casa');

        $this->assertSame('Activo', $ingreso->fresh()->estado);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
        $this->assertDatabaseCount('tbl_alta_medica', 0);
    }

    public function test_errores_del_servidor_se_muestran_junto_a_cada_campo(): void
    {
        $ingreso = $this->emergenciaActiva();

        $this->from(route('atencion.show', $ingreso))->post(route('emergencia.guardar', $ingreso), $this->consulta([
            'diagnostico' => '',
            'medicamentos_casa' => [['medicamento' => 'Ibuprofeno', 'dosis' => '', 'frecuencia' => 'Cada 8 horas']],
        ]))->assertRedirect(route('atencion.show', $ingreso));

        $html = $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('El diagnóstico es obligatorio.')
            ->assertSee('Indique la dosis de cada medicamento ambulatorio.')
            ->assertSee('value="Ibuprofeno"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/name="diagnostico" class="form-control\s+is-invalid/', $html);
    }

    public function test_signo_vital_fuera_de_rango_se_marca_en_su_campo(): void
    {
        $ingreso = $this->emergenciaActiva();

        $this->from(route('atencion.show', $ingreso))->post(route('emergencia.guardar', $ingreso), $this->consulta(['examen_fisico' => ['temperatura' => '80']]))
            ->assertRedirect(route('atencion.show', $ingreso));

        $html = $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('El campo temperatura debe estar entre 25 y 50.')->getContent();
        $this->assertMatchesRegularExpression('/data-signo="temperatura"\s*>/', $html);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
    }

    public function test_no_se_puede_modificar_una_emergencia_cerrada_ni_una_hospitalizacion(): void
    {
        $ingreso = $this->emergenciaActiva();
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta())->assertSessionHasNoErrors();

        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['diagnostico' => 'Otro']))->assertSessionHasErrors('ingreso');
        $this->assertSame('Gastritis aguda', $ingreso->evoluciones()->sole()->diagnostico);
        $this->assertDatabaseCount('tbl_alta_medica', 1);

        $hospitalizado = IngresoFactory::new()->create();
        $this->post(route('emergencia.guardar', $hospitalizado), $this->consulta())->assertSessionHas('error', 'Esta atención no es una consulta externa.');
        $this->assertSame('Activo', $hospitalizado->fresh()->estado);
    }

    public function test_hospitalizacion_conserva_evoluciones_y_examen_completo(): void
    {
        $idPaciente = $this->pacienteSinIngreso();
        $this->sesion()->post('/ingresos', $this->emergencia($idPaciente, ['tipo_atencion' => 'Hospitalización', 'id_habitacion' => 1]))->assertSessionHasNoErrors();
        $ingreso = Ingreso::where('id_paciente', $idPaciente)->where('estado', 'Activo')->firstOrFail();
        DB::table('tbl_evolucion_clinica')->insert(['id_ingreso' => $ingreso->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-11 09:00:00', 'motivo_atencion' => 'Control', 'diagnostico' => 'Estable', 'id_usuario_creacion' => 1]);

        $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('Valoración inicial registrada')
            ->assertSee('Evolución clínica y órdenes médicas')
            ->assertSee('<strong>Habitación</strong>', false)
            ->assertDontSee('data-elegir-signo', false)
            ->assertDontSee('Consulta externa');
    }

    public function test_emergencia_se_registra_sin_habitacion_ni_hospitalizacion(): void
    {
        $idPaciente = $this->pacienteSinIngreso();

        $respuesta = $this->sesion()->post('/ingresos', $this->emergencia($idPaciente, ['id_habitacion' => 1]));

        $ingreso = Ingreso::where('id_paciente', $idPaciente)->where('estado', 'Activo')->firstOrFail();
        $respuesta->assertSessionHasNoErrors()->assertRedirect(route('atencion.show', $ingreso))->assertSessionHas('exito', 'Consulta externa registrada correctamente.');
        $this->assertSame('Consulta externa', $ingreso->tipo_atencion);
        $this->assertDatabaseCount('tbl_hospitalizacion', 0);
        $this->assertDatabaseCount('tbl_hospitalizacion_habitacion', 0);

        $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('Consulta externa')->assertSee('bi-person-walking', false)->assertDontSee('bi-heart-pulse', false)->assertSee('Atención sin internamiento')->assertSee('Internar paciente')->assertSee('Editar atención')->assertSee('Consulta externa');
    }

    public function test_hospitalizacion_sigue_exigiendo_habitacion(): void
    {
        $idPaciente = $this->pacienteSinIngreso();

        $this->sesion()->post('/ingresos', $this->emergencia($idPaciente, ['tipo_atencion' => 'Hospitalización']))->assertSessionHasErrors('id_habitacion');
        $this->post('/ingresos', $this->emergencia($idPaciente, ['tipo_atencion' => 'Consulta']))->assertSessionHasErrors('tipo_atencion');
        $this->assertDatabaseCount('tbl_ingreso', 1);

        $this->post('/ingresos', $this->emergencia($idPaciente, ['tipo_atencion' => 'Hospitalización', 'id_habitacion' => 1]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_ingreso', ['id_paciente' => $idPaciente, 'estado' => 'Activo', 'tipo_atencion' => 'Hospitalización']);
        $this->assertDatabaseCount('tbl_hospitalizacion_habitacion', 1);
    }

    public function test_paciente_con_atencion_activa_no_puede_abrir_otra(): void
    {
        $idPaciente = $this->pacienteSinIngreso();
        $this->sesion()->post('/ingresos', $this->emergencia($idPaciente))->assertSessionHasNoErrors();

        $this->post('/ingresos', $this->emergencia($idPaciente))->assertSessionHas('error', 'El paciente ya tiene un ingreso activo.');

        $this->assertSame(1, Ingreso::where('id_paciente', $idPaciente)->where('estado', 'Activo')->count());
    }

    public function test_alta_de_hospitalizacion_tambien_acepta_medicamentos_para_casa(): void
    {
        $idPaciente = $this->pacienteSinIngreso();
        $this->sesion()->post('/ingresos', $this->emergencia($idPaciente, ['tipo_atencion' => 'Hospitalización', 'id_habitacion' => 1]))->assertSessionHasNoErrors();
        $ingreso = Ingreso::where('id_paciente', $idPaciente)->where('estado', 'Activo')->firstOrFail();

        $this->post(route('alta.guardar', $ingreso), $this->cierre([
            'medicamentos_casa' => [['medicamento' => 'Amoxicilina 500 mg', 'dosis' => '1 cápsula', 'frecuencia' => 'Cada 8 horas', 'duracion' => '7 días']],
        ]))->assertSessionHasNoErrors()->assertSessionHas('exito', 'Alta médica registrada; habitación liberada.');

        $this->assertDatabaseHas('tbl_alta_medicamento', ['medicamento' => 'Amoxicilina 500 mg']);
    }

    public function test_internar_emergencia_la_convierte_en_hospitalizacion(): void
    {
        $ingreso = $this->emergenciaConValoracion();

        $this->post(route('hospitalizacion.guardar', $ingreso), ['id_habitacion' => 2])
            ->assertSessionHasNoErrors()->assertSessionHas('exito', 'Paciente internado: la consulta externa ahora es una hospitalización.');

        $this->assertSame('Hospitalización', $ingreso->fresh()->tipo_atencion);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 2, 'fecha_hora_fin' => null]);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 1);
    }

    public function test_registrar_paciente_puede_hospitalizarlo_al_guardar(): void
    {
        $datos = ['estado_identificacion' => 'Identificado', 'nombres' => 'Luis', 'apellidos' => 'García', 'telefono' => '55555555', 'form_context' => 'nuevo',
            'hospitalizar' => '1', 'fecha_ingreso' => '2026-10-11 11:30:00', 'id_habitacion' => 2, 'motivo_ingreso' => 'Neumonía', 'documento_firmado' => '1'];

        $respuesta = $this->sesion()->post('/pacientes', $datos);

        $ingreso = Ingreso::firstOrFail();
        $respuesta->assertSessionHasNoErrors()->assertRedirect(route('atencion.show', $ingreso))->assertSessionHas('exito', 'Paciente registrado y hospitalizado.');
        $this->assertSame('Hospitalización', $ingreso->tipo_atencion);
        $this->assertSame('Neumonía', $ingreso->motivo_ingreso);
        $this->assertTrue($ingreso->documento_firmado);
        $this->assertSame('2026-10-11 11:30', $ingreso->fecha_ingreso->format('Y-m-d H:i'));
        $this->assertDatabaseHas('tbl_hospitalizacion', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Activa']);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 2, 'fecha_hora_fin' => null]);
    }

    public function test_hospitalizar_al_registrar_exige_habitacion_libre(): void
    {
        $datos = ['estado_identificacion' => 'Identificado', 'nombres' => 'Luis', 'apellidos' => 'García', 'telefono' => '55555555', 'form_context' => 'nuevo', 'hospitalizar' => '1', 'fecha_ingreso' => '2026-10-11 11:30:00'];

        $this->sesion()->post('/pacientes', $datos)->assertSessionHasErrors(['id_habitacion' => 'Seleccione la habitación donde se internará.']);
        $this->post('/pacientes', array_replace($datos, ['id_habitacion' => 1, 'fecha_ingreso' => '2026-10-11 13:00:00']))->assertSessionHasErrors('fecha_ingreso');
        $this->assertDatabaseCount('tbl_paciente', 0);

        $this->post('/pacientes', array_replace($datos, ['nombres' => 'Ana', 'id_habitacion' => 1]))->assertSessionHasNoErrors();
        $this->post('/pacientes', array_replace($datos, ['nombres' => 'Pedro', 'dpi' => null, 'id_habitacion' => 1]))->assertSessionHasErrors('id_habitacion');
        $this->assertDatabaseCount('tbl_paciente', 1);
    }

    public function test_iniciar_consulta_lleva_directo_a_la_consulta(): void
    {
        $idPaciente = $this->pacienteSinIngreso();

        $this->sesion()->post(route('consultas.iniciar', $idPaciente))
            ->assertRedirect(route('atencion.show', Ingreso::where('estado', 'Activo')->firstOrFail()))
            ->assertSessionHas('exito', 'Consulta externa iniciada. Registre la consulta del paciente.');

        $ingreso = Ingreso::where('estado', 'Activo')->firstOrFail();
        $this->assertSame('Consulta externa', $ingreso->tipo_atencion);
        $this->assertSame('2026-10-11 12:00', $ingreso->fecha_ingreso->format('Y-m-d H:i'));
        $this->assertDatabaseCount('tbl_hospitalizacion', 0);

        $this->from('/pacientes')->post(route('consultas.iniciar', $idPaciente))->assertRedirect('/pacientes')
            ->assertSessionHas('error', 'El paciente ya tiene un ingreso activo.');
        $this->assertSame(1, Ingreso::where('estado', 'Activo')->count());

        $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('Se anota en la consulta, abajo');
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['motivo_atencion' => 'Dolor de cabeza', 'fecha_hora' => '2026-10-11 12:00:00']))->assertSessionHasNoErrors();
        $this->assertSame('Dolor de cabeza', $ingreso->fresh()->motivo_ingreso);
    }

    public function test_registrar_paciente_sin_casilla_no_abre_atencion(): void
    {
        $this->sesion()->post('/pacientes', ['estado_identificacion' => 'Identificado', 'nombres' => 'Luis', 'apellidos' => 'García', 'telefono' => '55555555', 'motivo_emergencia' => 'No aplica'])
            ->assertSessionHasNoErrors()->assertRedirect(route('pacientes.index'));

        $this->assertDatabaseCount('tbl_paciente', 1);
        $this->assertDatabaseCount('tbl_ingreso', 0);
    }

    public function test_paciente_que_regresa_otro_dia_tiene_atencion_nueva(): void
    {
        $ingreso = $this->emergenciaActiva();
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta())->assertSessionHasNoErrors();

        $this->post('/ingresos', $this->emergencia($ingreso->id_paciente, ['fecha_ingreso' => '2026-10-11 11:00:00', 'motivo_ingreso' => 'Control']))->assertSessionHasNoErrors();

        $this->assertSame(2, Ingreso::where('id_paciente', $ingreso->id_paciente)->where('tipo_atencion', 'Consulta externa')->count());
        $this->get('/pacientes')->assertOk()->assertSee('En consulta');
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
            ->assertSee('Consulta externa actual')->assertSee('Consulta externa del '.$ingreso->fecha_ingreso->format('d/m/Y'))->assertSee('Historial de atenciones');
    }

    public function test_listado_de_enfermeria_no_muestra_emergencias(): void
    {
        $emergencia = IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa', 'motivo_ingreso' => 'Emergencia de prueba']);
        $idPaciente = $this->pacienteSinIngreso();
        $this->sesion()->post('/ingresos', $this->emergencia($idPaciente, ['tipo_atencion' => 'Hospitalización', 'id_habitacion' => 1]))->assertSessionHasNoErrors();
        $hospitalizado = Ingreso::where('id_paciente', $idPaciente)->where('estado', 'Activo')->firstOrFail();

        $this->get('/atencion')->assertOk()
            ->assertSee(route('atencion.enfermeria', $hospitalizado))
            ->assertDontSee(route('atencion.enfermeria', $emergencia))
            ->assertDontSee(route('atencion.show', $emergencia))
            ->assertDontSee('name="tipo"', false);
        $this->get(route('atencion.enfermeria', $emergencia))->assertRedirect(route('atencion.index'));
    }

    public function test_inicio_avisa_emergencias_abiertas_de_dias_anteriores(): void
    {
        $antigua = IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa', 'fecha_ingreso' => '2026-10-10 20:00:00']);
        $deHoy = IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa', 'fecha_ingreso' => '2026-10-11 07:00:00']);
        IngresoFactory::new()->create(['fecha_ingreso' => '2026-10-09 07:00:00']);

        $this->sesion()->get('/inicio')->assertOk()
            ->assertSee('Consultas externas abiertas de días anteriores')
            ->assertSee(route('atencion.show', $antigua))
            ->assertDontSee(route('atencion.show', $deHoy));
    }

    public function test_inicio_sin_emergencias_pendientes_no_muestra_aviso(): void
    {
        $this->sesion()->get('/inicio')->assertOk()->assertDontSee('Consultas externas abiertas de días anteriores');
    }

    public function test_pacientes_y_expediente_ofrecen_consulta_y_hospitalizacion(): void
    {
        $idPaciente = $this->pacienteSinIngreso();

        $this->sesion()->get('/pacientes')->assertOk()
            ->assertSee('action="'.route('consultas.iniciar', $idPaciente).'"', false)->assertSee('Iniciar consulta')
            ->assertSee('data-iniciar-atencion="'.$idPaciente.'"', false)->assertSee('Hospitalizar paciente')
            ->assertSee('Hospitalizar al guardar')->assertDontSee('Iniciar consulta externa al guardar')
            ->assertDontSee('Se va el mismo día');
        $this->get(route('pacientes.expediente', $idPaciente))->assertOk()
            ->assertSee('action="'.route('consultas.iniciar', $idPaciente).'"', false)
            ->assertSee('Hospitalizar paciente')->assertSee('name="id_habitacion"', false)
            ->assertSee('name="tipo_atencion" value="Hospitalización"', false)->assertDontSee('Nueva atención');
    }

    public function test_error_al_iniciar_desde_pacientes_reabre_la_ventana(): void
    {
        $idPaciente = $this->pacienteSinIngreso();
        $this->sesion()->post('/ingresos', $this->emergencia($idPaciente))->assertSessionHasNoErrors();

        $this->from('/pacientes')->post('/ingresos', $this->emergencia($idPaciente, ['form_context' => 'iniciar-atencion', 'nombre_paciente' => 'Paciente de prueba']))
            ->assertRedirect('/pacientes');

        $this->get('/pacientes')->assertOk()->assertSee('new bootstrap.Modal(modalIniciarAtencion).show()', false);
    }

    public function test_visitante_no_puede_iniciar_emergencia(): void
    {
        $idPaciente = $this->pacienteSinIngreso();

        $this->post('/ingresos', $this->emergencia($idPaciente))->assertRedirect('/');

        $this->assertSame(0, Paciente::findOrFail($idPaciente)->ingresos()->where('estado', 'Activo')->count());
    }

    public function test_expediente_de_emergencia_usa_nombres_de_consulta_y_muestra_medicamentos(): void
    {
        $ingreso = $this->emergenciaActiva();
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta([
            'examen_fisico' => ['temperatura' => '36.8'],
            'medicamentos_casa' => [['medicamento' => 'Omeprazol', 'dosis' => '20 mg', 'frecuencia' => 'Cada 24 horas', 'duracion' => '14 días']],
            'indicaciones_casa' => 'Dieta blanda',
        ]))->assertSessionHasNoErrors();

        $expediente = $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
            ->assertSee('Documentos de la atención')
            ->assertSee('Hoja de llegada')->assertSee('Consulta externa')->assertSee('Alta e indicaciones para casa')
            ->assertDontSee('Notas de enfermería (')->assertDontSee('Evoluciones (')
            ->assertSee('Consulta externa del 11/10/2026')->assertSee('Fecha y hora de salida')->assertSee('Medicamentos para casa')
            ->assertSee('<td class="fw-semibold text-break">Omeprazol</td>', false)->assertSee('14 días')
            ->assertSee('Temperatura (°C)')->assertDontSee('Frecuencia cardíaca (FC, lpm)');
        $this->assertStringNotContainsString('Saturación de oxígeno (%)', $expediente->getContent());

        $this->get(route('pacientes.index'))->assertOk()->assertSee('Atención finalizada');
        $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('Nuevo seguimiento (el paciente regresó)')->assertDontSee('data-bs-target="#modalEditarIngreso"', false);
    }

    public function test_formulario_de_paciente_valida_con_mensajes_del_sistema(): void
    {
        $this->sesion()->get(route('pacientes.index'))->assertOk()
            ->assertSee('action="'.route('pacientes.guardar').'"'."\n                method=\"POST\" novalidate data-validar-en-linea data-validar-al-enviar", false)
            ->assertSee('data-mensaje-requerido="Escriba los nombres del paciente."', false)
            ->assertSee('data-mensaje-formato="El teléfono debe tener 8 números, por ejemplo 5555-1234."', false)
            ->assertSee('data-mensaje-maximo="La fecha de nacimiento no puede ser futura."', false);
    }

    public function test_consulta_externa_con_nombre_anterior_se_sigue_reconociendo(): void
    {
        $ingreso = new Ingreso(['tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA_ANTERIOR]);

        $this->assertTrue($ingreso->esConsultaExterna());
        $this->assertSame('Consulta externa', $ingreso->nombreTipoAtencion());
        $this->assertSame('Hospitalización', (new Ingreso(['tipo_atencion' => 'Hospitalización']))->nombreTipoAtencion());
    }

    public function test_migracion_cambia_emergencia_por_consulta_externa(): void
    {
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => 'Hospitalización']);
        DB::statement('PRAGMA ignore_check_constraints = ON');
        DB::table('tbl_ingreso')->where('id_ingreso', $ingreso->getKey())->update(['tipo_atencion' => 'Emergencia']);
        DB::statement('PRAGMA ignore_check_constraints = OFF');

        (require database_path('migrations/2026_10_26_015856_rename_emergencia_to_consulta_externa_in_tbl_ingreso.php'))->up();

        $this->assertSame('Consulta externa', DB::table('tbl_ingreso')->where('id_ingreso', $ingreso->getKey())->value('tipo_atencion'));
    }
}
