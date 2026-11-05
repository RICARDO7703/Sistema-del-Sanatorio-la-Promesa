<?php

namespace Tests\Feature;

use App\Models\AltaMedica;
use App\Models\Cirugia;
use App\Models\Ingreso;
use App\Models\Paciente;
use App\Support\DocumentosExpediente;
use Database\Factories\IngresoFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function test_alta_permite_elegir_mantener_o_cancelar_cirugias(): void
    {
        foreach (['mantener', 'cancelar'] as $decision) {
            $ingreso = $this->emergenciaActiva();
            $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Cirugía futura', 'fecha_programada' => '2026-10-12 10:00:00', 'estado' => 'Programada', 'id_usuario_creacion' => 1]);
            $cirugia->equipo()->attach(1, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
            $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('Mantener programadas')->assertDontSee('Confirmo dar de alta y cancelar las cirugías pendientes.');
            $this->post(route('emergencia.guardar', $ingreso), $this->consulta())->assertSessionHasErrors('decision_cirugias');
            $this->assertSame('Activo', $ingreso->fresh()->estado);
            $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['decision_cirugias' => $decision]))->assertSessionHasNoErrors();
            $this->assertSame('Dado de alta', $ingreso->fresh()->estado);
            $this->assertSame($decision === 'mantener' ? 'Programada' : 'Cancelada', $cirugia->fresh()->estado);
            $this->assertSame($decision === 'mantener' ? 'Asignado' : 'Cancelado', $cirugia->fresh()->equipo->first()->pivot->estado);
            $this->assertSame('2026-10-12 10:00:00', $cirugia->fresh()->fecha_programada->format('Y-m-d H:i:s'));
        }
    }

    public function test_programacion_flotante_se_guarda_con_la_valoracion_al_alta_o_internamiento(): void
    {
        foreach (['alta', 'internar'] as $accion) {
            $ingreso = $this->emergenciaActiva();
            $vista = $this->get(route('atencion.show', $ingreso))->assertOk()
                ->assertSee('id="modalProgramarConsulta"', false)->assertSee('data-borrador-incluir', false);
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$vista->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame(0, $xpath->query('//form[@id="form-consulta-emergencia"]//form')->length);
            $this->assertSame(1, $xpath->query('//div[@id="modalProgramarConsulta"]//form[@id="form-programacion-pendiente"]')->length);
            $this->assertDatabaseCount('tbl_cirugia', $accion === 'alta' ? 0 : 1);
            $pendiente = json_encode(['tipo_operacion' => 'Cirugía '.$accion, 'fecha_programada' => $accion === 'alta' ? '2026-10-12 10:00:00' : '2026-10-12 11:00:00', 'personal' => [1], 'observaciones' => 'Prueba']);
            $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['accion' => $accion, 'cirugia_pendiente' => $pendiente]))->assertSessionHasNoErrors();
            $cirugia = $ingreso->cirugias()->sole();
            $this->assertSame('Programada', $cirugia->estado);
            $this->assertSame(1, $ingreso->evoluciones()->count());
            $this->assertSame('Asignado', $cirugia->equipo->first()->pivot->estado);
        }
    }

    public function test_nueva_cirugia_pide_resolver_la_anterior_de_otra_consulta(): void
    {
        foreach (['mantener', 'cancelar'] as $decision) {
            $ingreso = $this->emergenciaActiva();
            $previo = IngresoFactory::new()->create(['id_paciente' => $ingreso->id_paciente, 'tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA, 'estado' => 'Dado de alta']);
            $anterior = Cirugia::create(['id_ingreso' => $previo->getKey(), 'tipo_operacion' => 'Cirugía anterior', 'fecha_programada' => '2026-10-13 09:00:00', 'estado' => 'Programada', 'id_usuario_creacion' => 1]);
            $anterior->equipo()->attach(1, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
            $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('El paciente ya tiene una cirugía programada')->assertSee('Cirugía anterior');
            $programacion = ['tipo_operacion' => 'Cirugía nueva', 'fecha_programada' => '2026-10-14 11:00:00', 'personal' => []];
            $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['cirugia_pendiente' => json_encode($programacion)]))->assertSessionHasErrors('cirugia_pendiente');
            $this->assertSame(0, $ingreso->evoluciones()->count());
            $this->assertSame('Programada', $anterior->fresh()->estado);
            $invalida = array_replace($programacion, ['decision_existentes' => 'cancelar', 'tipo_operacion' => '']);
            $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['cirugia_pendiente' => json_encode($invalida)]))->assertSessionHasErrors('cirugia_pendiente');
            $this->assertSame('Programada', $anterior->fresh()->estado);
            $this->assertSame('Asignado', $anterior->fresh()->equipo->first()->pivot->estado);
            $programacion['decision_existentes'] = $decision;
            $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['cirugia_pendiente' => json_encode($programacion)]))->assertSessionHasNoErrors();
            $this->assertSame('Dado de alta', $ingreso->fresh()->estado);
            $this->assertSame('Programada', $ingreso->cirugias()->sole()->estado);
            $this->assertSame($decision === 'cancelar' ? 'Cancelada' : 'Programada', $anterior->fresh()->estado);
            $this->assertSame($decision === 'cancelar' ? 'Cancelado' : 'Asignado', $anterior->fresh()->equipo->first()->pivot->estado);
        }
    }

    public function test_programacion_invalida_no_guarda_ni_valoracion_ni_alta(): void
    {
        $ingreso = $this->emergenciaActiva();
        $pendiente = json_encode(['tipo_operacion' => '', 'fecha_programada' => '2026-10-10 10:00:00', 'personal' => [99999]]);
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['cirugia_pendiente' => $pendiente, 'decision_cirugias' => 'mantener']))->assertSessionHasErrors('cirugia_pendiente');
        $this->assertSame('Activo', $ingreso->fresh()->estado);
        $this->assertSame(0, $ingreso->evoluciones()->count());
        $this->assertSame(0, $ingreso->cirugias()->count());
        $this->assertDatabaseCount('tbl_alta_medica', 0);
    }

    public function test_primera_hoja_se_muestra_como_valoracion_inicial_en_el_expediente(): void
    {
        $ingreso = $this->emergenciaActiva();
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta())->assertSessionHasNoErrors();
        $ingreso->refresh();
        $hojas = DocumentosExpediente::deIngreso($ingreso, collect());
        $valoracion = $hojas->firstWhere('origen', 'tbl_evolucion_clinica:'.$ingreso->evoluciones->first()->getKey());
        $this->assertSame('Valoración inicial', $valoracion['titulo']);
        $this->assertSame('valoracion', $valoracion['tipo']);
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
            ->assertSee('>Valoración inicial</h5>', false);
        $this->assertSame(Ingreso::TIPO_CONSULTA_EXTERNA, $ingreso->tipo_atencion);
    }

    public function test_alta_no_puede_mantener_una_cirugia_en_curso(): void
    {
        $ingreso = $this->emergenciaActiva();
        Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'En curso', 'estado' => 'En cirugía', 'id_usuario_creacion' => 1]);
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['decision_cirugias' => 'mantener']))->assertSessionHasErrors('cancelar_cirugias');
        $this->assertSame('Activo', $ingreso->fresh()->estado);
        $this->assertDatabaseCount('tbl_alta_medica', 0);
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
            ->assertSee('Documentos de la atención')->assertSee('Cambiar habitación')->assertDontSee('<strong>Habitación</strong>', false)
            ->assertSee('data-elegir-signo', false)
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

    public function test_hospitalizacion_permite_habitacion_pendiente(): void
    {
        $idPaciente = $this->pacienteSinIngreso();
        $this->sesion()->post('/ingresos', $this->emergencia($idPaciente, ['tipo_atencion' => 'Hospitalización']))->assertSessionHasNoErrors();
        $this->post('/ingresos', $this->emergencia($idPaciente, ['tipo_atencion' => 'Consulta']))->assertSessionHasErrors('tipo_atencion');
        $this->assertDatabaseHas('tbl_ingreso', ['id_paciente' => $idPaciente, 'estado' => 'Activo', 'tipo_atencion' => 'Hospitalización']);
        $this->assertDatabaseCount('tbl_hospitalizacion_habitacion', 0);
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
        Storage::fake('documentos_clinicos');

        $this->sesion()->post('/pacientes', $datos)->assertSessionHasErrors('documento_archivo');
        $this->assertDatabaseCount('tbl_paciente', 0);

        $respuesta = $this->post('/pacientes', $datos + ['documento_archivo' => UploadedFile::fake()->createWithContent('firma.pdf', "%PDF-1.4\n%%EOF")]);

        $ingreso = Ingreso::firstOrFail();
        $respuesta->assertSessionHasNoErrors()->assertRedirect(route('atencion.show', $ingreso))->assertSessionHas('exito', 'Paciente registrado y hospitalizado.');
        $this->assertSame('Hospitalización', $ingreso->tipo_atencion);
        $this->assertSame('Neumonía', $ingreso->motivo_ingreso);
        $this->assertTrue($ingreso->documento_firmado);
        $this->assertSame('2026-10-11 11:30', $ingreso->fecha_ingreso->format('Y-m-d H:i'));
        $this->assertDatabaseHas('tbl_hospitalizacion', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Activa']);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 2, 'fecha_hora_fin' => null]);
        $this->assertDatabaseHas('tbl_documento_clinico', ['id_paciente' => $ingreso->id_paciente, 'id_ingreso' => null, 'id_registro_origen' => $ingreso->getKey(), 'tipo_documento' => 'Documento de ingreso firmado']);
    }

    public function test_hospitalizar_al_registrar_exige_habitacion_libre(): void
    {
        $datos = ['estado_identificacion' => 'Identificado', 'nombres' => 'Luis', 'apellidos' => 'García', 'telefono' => '55555555', 'form_context' => 'nuevo', 'hospitalizar' => '1', 'fecha_ingreso' => '2026-10-11 11:30:00'];

        $this->sesion()->post('/pacientes', array_replace($datos, ['id_habitacion' => 99999]))->assertSessionHasErrors('id_habitacion');
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

        $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('Internar paciente')->assertDontSee('Aún no registrado')->assertDontSee('Se anota en la consulta, abajo');
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['motivo_atencion' => 'Dolor de cabeza', 'fecha_hora' => '2026-10-11 12:00:00']))->assertSessionHasNoErrors();
        $this->assertSame('Dolor de cabeza', $ingreso->fresh()->motivo_ingreso);
    }

    public function test_consulta_iniciada_se_puede_cerrar_en_el_mismo_minuto(): void
    {
        $this->travelTo(now()->setTime(18, 3, 41));
        $idPaciente = $this->pacienteSinIngreso();

        $this->sesion()->post(route('consultas.iniciar', $idPaciente))->assertSessionHasNoErrors();
        $ingreso = Ingreso::where('estado', 'Activo')->firstOrFail();
        $this->assertSame('2026-10-11 12:03:00', $ingreso->fecha_ingreso->format('Y-m-d H:i:s'));

        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['fecha_hora' => '2026-10-11T12:03']))->assertSessionHasNoErrors();
        $this->assertSame('Dado de alta', $ingreso->fresh()->estado);
    }

    public function test_llegada_antigua_con_segundos_no_impide_la_consulta_del_mismo_minuto(): void
    {
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa', 'fecha_ingreso' => '2026-10-11 11:58:41']);

        $this->sesion()->post(route('emergencia.guardar', $ingreso), $this->consulta(['accion' => 'internar', 'fecha_hora' => '2026-10-11T11:58']))->assertSessionHasNoErrors();
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta(['accion' => 'internar', 'fecha_hora' => '2026-10-11T11:57']))
            ->assertSessionHasErrors(['fecha_hora' => 'La consulta no puede ser anterior a la llegada del paciente.']);
    }

    public function test_internar_cambiar_habitacion_y_alta_usan_la_hora_de_guatemala(): void
    {
        $ingreso = $this->emergenciaConValoracion();

        $this->post(route('hospitalizacion.guardar', $ingreso), ['id_habitacion' => 1])->assertSessionHasNoErrors();
        $hospitalizacion = $ingreso->fresh()->hospitalizacion;
        $this->assertSame('2026-10-11 12:00:00', $hospitalizacion->fecha_inicio->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 1, 'fecha_hora_inicio' => '2026-10-11 12:00:00']);

        $this->travel(5)->minutes();
        $this->patch('/hospitalizacion/'.$hospitalizacion->getKey().'/cambiar-habitacion', ['id_habitacion' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 1, 'fecha_hora_fin' => '2026-10-11 12:05:00']);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 2, 'fecha_hora_inicio' => '2026-10-11 12:05:00']);

        $this->travel(5)->minutes();
        $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('max="2026-10-11T12:10" value="2026-10-11T12:10"', false);

        $this->post('/atencion/'.$ingreso->getKey().'/alta', $this->cierre(['fecha_hora' => '2026-10-11 18:10:00']))->assertSessionHasErrors('fecha_hora');
        $this->post('/atencion/'.$ingreso->getKey().'/alta', $this->cierre(['fecha_hora' => '2026-10-11 12:10:00']))->assertSessionHasNoErrors();
        $this->assertSame('Dado de alta', $ingreso->fresh()->estado);
        $this->assertDatabaseHas('tbl_alta_medica', ['id_ingreso' => $ingreso->getKey(), 'fecha_hora' => '2026-10-11 12:10:00']);
    }

    public function test_consulta_externa_muestra_tratamiento_debajo_de_signos_con_alergias(): void
    {
        $ingreso = $this->emergenciaActiva();
        DB::table('tbl_paciente')->where('id_paciente', $ingreso->id_paciente)->update(['alergias' => 'Penicilina']);

        $pagina = $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSeeInOrder(['Alergias:', 'Penicilina', 'Signos vitales (opcional)', 'Tratamiento en el sanatorio', 'Alergias:', 'Penicilina', 'Medicamentos o procedimientos aplicados', 'Diagnóstico', 'emergencia-diagnostico', 'Ambulatorio'])->getContent();
        $this->assertSame(2, substr_count($pagina, 'aviso-alergias '));
    }

    public function test_documento_de_ingreso_firmado_solo_aparece_en_hospitalizacion(): void
    {
        $consulta = IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa']);
        $this->sesion()->get(route('atencion.show', $consulta))->assertOk()->assertDontSee('name="documento_firmado"', false);
        $this->get(route('pacientes.expediente', $consulta->id_paciente))->assertOk()
            ->assertDontSee('Documento de ingreso</h6>', false)->assertDontSee('pendiente de firma')->assertDontSee('Pendiente de firma');

        $hospitalizacion = IngresoFactory::new()->create(['tipo_atencion' => 'Hospitalización']);
        $this->get(route('atencion.show', $hospitalizacion))->assertOk()->assertSee('name="documento_firmado"', false);
        $this->get(route('pacientes.expediente', $hospitalizacion->id_paciente))->assertOk()
            ->assertSee('Documento de ingreso</h6>', false)->assertSee('Documento de ingreso pendiente de firma')->assertSee('Pendiente de firma');
    }

    public function test_registrar_paciente_sin_casilla_no_abre_atencion(): void
    {
        $this->sesion()->post('/pacientes', ['estado_identificacion' => 'Identificado', 'nombres' => 'Luis', 'apellidos' => 'García', 'telefono' => '55555555', 'motivo_emergencia' => 'No aplica'])
            ->assertSessionHasNoErrors()->assertRedirect(route('pacientes.index'));

        $this->assertDatabaseCount('tbl_paciente', 1);
        $this->assertDatabaseCount('tbl_ingreso', 0);
    }

    public function test_receta_de_la_consulta_con_medico_arriba_medicamentos_e_indicaciones(): void
    {
        $ingreso = $this->emergenciaActiva();
        DB::table('tbl_especialidad')->insert(['id_especialidad' => 1, 'nombre_especialidad' => 'Cirugía General']);
        DB::table('tbl_personal')->where('id_personal', 1)->update(['id_especialidad' => 1, 'numero_colegiado' => '12345']);
        $this->post(route('emergencia.guardar', $ingreso), $this->consulta([
            'medicamentos_casa' => [['medicamento' => 'Amoxicilina 500 mg', 'dosis' => '1 cápsula', 'frecuencia' => 'cada 8 horas', 'duracion' => '7']],
            'indicaciones_casa' => 'Tomar abundantes líquidos',
        ]))->assertSessionHasNoErrors();
        $alta = AltaMedica::where('id_ingreso', $ingreso->getKey())->sole();
        $enlace = 'href="'.e(route('alta.receta', [$alta, 'imprimir' => 1])).'"';

        $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee($enlace, false)->assertSee('Imprimir o descargar receta');
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee($enlace, false);
        $this->get(route('alta.receta', [$alta, 'imprimir' => 1]))->assertOk()
            ->assertSeeInOrder(['LA PROMESA', 'Médico Prueba', 'Especialidad en Cirugía General', 'Colegiado No. 12345', 'Paciente:', 'Rp.', 'Amoxicilina 500 mg', '1 cápsula · cada 8 horas · Por 7 días', 'Indicaciones médicas', 'Tomar abundantes líquidos', 'Firma y sello', 'FAVOR NO CAMBIAR ESTA RECETA'])
            ->assertSee('window.print()', false)->assertSee('Guardar como PDF');
        $this->assertSame(1, substr_count($this->get(route('alta.receta', $alta))->getContent(), 'Médico Prueba'), 'El nombre del médico va solo arriba.');
    }

    public function test_listado_ofrece_atender_consulta_y_marca_al_no_identificado(): void
    {
        $ingreso = $this->emergenciaActiva();
        $this->get('/pacientes')->assertOk()
            ->assertSee('href="'.route('atencion.show', $ingreso).'"', false)->assertSee('Atender consulta')->assertSee('Esperando al médico')
            ->assertDontSee('Atención al paciente</a>', false);
        $this->sesion()->post('/pacientes', ['estado_identificacion' => 'No identificado'])->assertSessionHasNoErrors();
        $this->get('/pacientes')->assertOk()->assertSee('<span class="paciente-avatar-desconocido" aria-hidden="true">?</span>', false);
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
            ->assertSee('Ver documentos')->assertSee('Consulta externa (1)')
            ->assertSee('Hoja de llegada')->assertSee('Consulta externa')->assertSee('Alta e indicaciones para casa')
            ->assertDontSee('Notas de enfermería (')->assertDontSee('Evoluciones (')
            ->assertSee('Consulta externa del 11/10/2026')->assertSee(' Salida</dt>', false)->assertSee('Medicamentos para casa')
            ->assertSee('<strong>Omeprazol</strong>', false)->assertSee('14 días')
            ->assertSee('title="Temperatura"', false)->assertDontSee('title="Frecuencia cardíaca"', false);
        $this->assertStringNotContainsString('title="Saturación de oxígeno"', $expediente->getContent());

        $this->get(route('pacientes.index'))->assertOk()->assertSee('Atención finalizada');
        $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('Nuevo seguimiento (el paciente regresó)')->assertDontSee('data-bs-target="#modalEditarIngreso"', false);
    }

    public function test_formulario_de_paciente_valida_con_mensajes_del_sistema(): void
    {
        $this->sesion()->get(route('pacientes.index'))->assertOk()
            ->assertSee('action="'.route('pacientes.guardar').'"'."\n                method=\"POST\" enctype=\"multipart/form-data\" novalidate data-validar-en-linea data-validar-al-enviar", false)
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

    public function test_consulta_vacia_admite_fecha_anterior_y_ajusta_llegada(): void
    {
        $i = $this->emergenciaActiva();
        $i->update(['motivo_ingreso' => null]);
        $this->post(route('emergencia.guardar', $i), $this->consulta(['fecha_hora' => '2026-10-10 09:00:00']))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-10 09:00:00', $i->fresh()->fecha_ingreso->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-10 09:00:00', $i->evoluciones()->firstOrFail()->fecha_hora->format('Y-m-d H:i:s'));
    }

    public function test_autor_corrige_consulta_cerrada_sin_reabrirla_y_con_plazo(): void
    {
        $i = $this->emergenciaActiva();
        $this->post(route('emergencia.guardar', $i), $this->consulta())->assertSessionHasNoErrors();
        $alta = $i->fresh()->altaMedica;
        $this->get(route('atencion.show', [$i, 'editar_consulta' => 1]))->assertOk()->assertSee('Guardar corrección');
        $this->post(route('emergencia.guardar', $i), $this->consulta(['accion' => 'corregir', 'diagnostico' => 'Diagnóstico corregido', 'medicamentos_casa' => [['medicamento' => 'Producto de prueba', 'dosis' => '10.5', 'frecuencia' => '8', 'duracion' => '3']]]))->assertSessionHasNoErrors();
        $this->assertSame('Dado de alta', $i->fresh()->estado);
        $this->assertSame('Diagnóstico corregido', $alta->fresh()->diagnostico_final);
        $this->assertSame('10.5', $alta->medicamentos()->sole()->dosis);
        $this->assertDatabaseCount('tbl_alta_medica', 1);
        $this->travel(13)->hours();
        $this->post(route('emergencia.guardar', $i), $this->consulta(['accion' => 'corregir']))->assertForbidden();
    }

    public function test_medicamentos_ambulatorios_solo_aceptan_numeros(): void
    {
        $i = $this->emergenciaActiva();
        $this->post(route('emergencia.guardar', $i), $this->consulta(['medicamentos_casa' => [['medicamento' => 'Producto de prueba', 'dosis' => 'Morfina', 'frecuencia' => '8 horas', 'duracion' => '3 días']]]))->assertSessionHasErrors(['medicamentos_casa.0.dosis', 'medicamentos_casa.0.frecuencia', 'medicamentos_casa.0.duracion']);
        $this->assertSame('Activo', $i->fresh()->estado);
        $this->post(route('emergencia.guardar', $i), $this->consulta(['medicamentos_casa' => [['medicamento' => 'Producto de prueba', 'dosis' => '10.5', 'frecuencia' => '8', 'duracion' => '3']]]))->assertSessionHasNoErrors();
        $this->assertSame('10.5', $i->fresh()->altaMedica->medicamentos()->sole()->dosis);
    }
}
