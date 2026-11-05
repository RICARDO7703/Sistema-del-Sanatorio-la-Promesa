<?php

namespace Tests\Feature;

use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Services\ExamenFisico;
use Database\Factories\IngresoFactory;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AtencionTest extends TestCase
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
        // 18:00 UTC = 12:00 en Guatemala, la hora que usan las fechas clínicas.
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(18, 0));
        DB::table('tbl_cargo')->insert(['id_cargo' => 1, 'nombre_cargo' => 'Médico general']);
        DB::table('tbl_personal')->insert(['id_personal' => 1, 'id_cargo' => 1, 'nombres' => 'Médico', 'apellidos' => 'Prueba', 'dpi' => '1234567890123']);
        DB::table('tbl_rol')->insert(['id_rol' => 1, 'nombre_rol' => 'Administrador']);
        DB::table('tbl_usuario')->insert(['id_usuario' => 1, 'id_personal' => 1, 'id_rol' => 1, 'nombre_usuario' => 'prueba', 'contrasena' => 'solo-pruebas']);
        DB::table('tbl_habitacion')->insert([['id_habitacion' => 1, 'numero_habitacion' => '101'], ['id_habitacion' => 2, 'numero_habitacion' => '102']]);
        DB::table('tbl_medicamento')->insert(['id_medicamento' => 1, 'nombre_medicamento' => 'Medicamento de prueba']);
    }

    private function ingreso(array $atributos = []): Ingreso
    {
        return IngresoFactory::new()->create($atributos);
    }

    private function sesion(): static
    {
        return $this->withSession(['id_usuario' => 1]);
    }

    private function evolucion(Ingreso $ingreso): array
    {
        return ['id_ingreso' => $ingreso->getKey(), 'fecha_hora' => '2026-10-11 10:00:00', 'motivo_atencion' => 'Control', 'diagnostico' => 'Diagnóstico de prueba'];
    }

    private function tratamiento(Ingreso $ingreso): array
    {
        return ['id_ingreso' => $ingreso->getKey(), 'tipo_tratamiento' => 'Medicamento', 'id_medicamento' => 1, 'fecha_indicacion' => '2026-10-11 10:00:00', 'dosis' => 'Según indicación'];
    }

    private function alta(): array
    {
        return ['id_personal_medico' => 1, 'fecha_hora' => '2026-10-11 11:59:00', 'diagnostico_final' => 'Diagnóstico final de prueba', 'condicion_egreso' => 'Estable', 'indicaciones_alta' => 'Seguimiento'];
    }

    private function hospitalizar(Ingreso $ingreso): int
    {
        $id = DB::table('tbl_hospitalizacion')->insertGetId(['id_ingreso' => $ingreso->getKey(), 'fecha_inicio' => '2026-10-10 12:00:00', 'id_usuario_creacion' => 1]);
        DB::table('tbl_hospitalizacion_habitacion')->insert(['id_hospitalizacion' => $id, 'id_habitacion' => 1, 'fecha_hora_inicio' => '2026-10-10 12:00:00', 'id_usuario_creacion' => 1]);

        return $id;
    }

    public function test_pantalla_del_medico_muestra_alergias_donde_se_receta(): void
    {
        $ingreso = $this->ingreso();
        $this->hospitalizar($ingreso);
        DB::table('tbl_paciente')->where('id_paciente', $ingreso->id_paciente)->update(['alergias' => 'Penicilina <b>']);
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();

        $pagina = $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('Penicilina &lt;b&gt;', false)->assertDontSee('Penicilina <b>', false)
            ->assertDontSee('Sin alergias registradas')->getContent();
        $this->assertSame(4, substr_count($pagina, 'aviso-alergias '), 'Ficha del paciente, valoración inicial, órdenes médicas y medicamentos para casa.');
    }

    public function test_paciente_sin_alergias_lo_indica_en_su_ficha(): void
    {
        $ingreso = $this->ingreso();

        $pagina = $this->sesion()->get(route('atencion.show', $ingreso))->assertOk()->assertSee('Sin alergias registradas')->getContent();
        $this->assertSame(0, substr_count($pagina, 'aviso-alergias '));
    }

    public function test_visitante_no_puede_consultar_ni_modificar_atencion(): void
    {
        $ingreso = $this->ingreso();
        $this->get('/atencion')->assertRedirect('/');
        $this->get('/atencion/'.$ingreso->getKey())->assertRedirect('/');
        $this->post('/tratamientos', $this->tratamiento($ingreso))->assertRedirect('/');
        $this->post('/atencion/'.$ingreso->getKey().'/alta', $this->alta())->assertRedirect('/');
        $this->assertDatabaseCount('tbl_tratamiento', 0);
        $this->assertDatabaseCount('tbl_alta_medica', 0);
    }

    public function test_usuario_bloqueado_pierde_acceso(): void
    {
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['estado' => 'Bloqueado']);
        $this->sesion()->get('/atencion')->assertRedirect('/')->assertSessionMissing('id_usuario');
    }

    public function test_listado_solo_muestra_ingresos_activos_y_filtra(): void
    {
        $activo = $this->ingreso();
        $this->hospitalizar($activo);
        $this->ingreso(['estado' => 'Dado de alta']);
        $this->ingreso(['tipo_atencion' => 'Consulta externa']);
        $this->sesion()->get('/atencion')->assertOk()->assertViewHas('ingresos', fn ($lista) => $lista->modelKeys() === [$activo->getKey()]);
        $this->get('/atencion?buscar=NO-EXISTE')->assertOk()->assertViewHas('ingresos', fn ($lista) => $lista->isEmpty());
        $this->get('/atencion?buscar='.$activo->paciente->numero_expediente)->assertOk()->assertViewHas('ingresos', fn ($lista) => $lista->modelKeys() === [$activo->getKey()]);
    }

    public function test_busqueda_cero_no_se_ignora(): void
    {
        $ingreso = $this->ingreso();
        DB::table('tbl_paciente')->where('id_paciente', $ingreso->id_paciente)->update(['numero_expediente' => 'ABC', 'nombres' => 'Ana', 'apellidos' => 'Perez']);
        $this->sesion()->get('/atencion?buscar=0')->assertOk()->assertViewHas('ingresos', fn ($lista) => $lista->isEmpty());
    }

    public function test_busqueda_demasiado_larga_se_rechaza(): void
    {
        $this->sesion()->get('/atencion?buscar='.str_repeat('x', 101))->assertSessionHasErrors('buscar');
    }

    public function test_detalle_renderiza_todos_los_formularios(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->get('/atencion/'.$ingreso->getKey())->assertOk()->assertSee('Síntomas')->assertSee('Presión arterial <span class="text-muted fw-normal">(mmHg)</span>', false)->assertDontSee('id="signos-tab"', false)->assertDontSee('id="form-evolucion"', false)->assertDontSee('Registrar alta médica');
        $this->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        $this->get('/atencion/'.$ingreso->getKey())->assertOk()->assertSee('id="form-evolucion"', false)->assertSee('Órdenes médicas')->assertSee('Registrar alta médica');
    }

    public function test_detalle_inexistente_o_cerrado(): void
    {
        $ingreso = $this->ingreso(['estado' => 'Cancelado']);
        $this->sesion()->get('/atencion/99999')->assertNotFound();
        $this->get('/atencion/'.$ingreso->getKey())->assertRedirect(route('atencion.index'));
    }

    public function test_registra_evolucion_con_autor_de_sesion(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + ['id_personal' => 999])->assertRedirect(route('atencion.show', $ingreso))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_evolucion_clinica', ['id_ingreso' => $ingreso->getKey(), 'id_personal' => 1, 'id_usuario_creacion' => 1]);
        $this->get('/atencion/'.$ingreso->getKey())->assertOk()->assertSee('Diagnóstico de prueba');
    }

    #[DataProvider('fechasInvalidas')]
    public function test_evolucion_rechaza_fecha_fuera_del_ingreso(string $fecha): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', array_replace($this->evolucion($ingreso), ['fecha_hora' => $fecha]))->assertSessionHasErrors('fecha_hora');
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
    }

    public static function fechasInvalidas(): array
    {
        return [['2026-10-09 10:00:00'], ['2026-10-12 10:00:00']];
    }

    public function test_evolucion_requiere_diagnostico(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', array_replace($this->evolucion($ingreso), ['diagnostico' => '']))->assertSessionHasErrors('diagnostico');
    }

    public function test_expediente_indica_cuando_se_edito_un_documento_y_no_permite_subir_archivos(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        $id = DB::table('tbl_evolucion_clinica')->value('id_evolucion');

        $expediente = route('pacientes.expediente', $ingreso->id_paciente);
        $this->get($expediente)->assertOk()->assertDontSee('Se editó el')
            ->assertDontSee('Subir hoja en papel')->assertDontSee('Subir una foto o PDF');

        $this->travel(5)->minutes();
        $this->put('/evolucion/'.$id, array_replace($this->evolucion($ingreso), ['diagnostico' => 'Revisado']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Edición de evolución clínica', 'tabla' => 'tbl_evolucion_clinica', 'id_registro' => $id]);
        $this->get($expediente)->assertOk()->assertSee('Se editó el 11/10/2026 a las 12:05');

        $this->travel(5)->minutes();
        $this->put('/ingresos/'.$ingreso->getKey(), ['fecha_ingreso' => '2026-10-10 12:00:00', 'motivo_ingreso' => 'Motivo corregido'])->assertSessionHasNoErrors();
        $this->get($expediente)->assertOk()->assertSee('Se editó el 11/10/2026 a las 12:10')->assertSee('Se editó el 11/10/2026 a las 12:05');
    }

    public function test_evolucion_se_actualiza_y_desactiva_sin_borrarse(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        $id = DB::table('tbl_evolucion_clinica')->value('id_evolucion');
        $this->put('/evolucion/'.$id, array_replace($this->evolucion($ingreso), ['diagnostico' => 'Revisado']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_evolucion_clinica', ['id_evolucion' => $id, 'diagnostico' => 'Revisado', 'id_usuario_modificacion' => 1]);
        $this->delete('/evolucion/'.$id)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_evolucion_clinica', ['id_evolucion' => $id, 'estado' => 'Inactivo']);
    }

    #[DataProvider('signosInvalidos')]
    public function test_signos_rechazan_valores_invalidos(array $valores, string $campo): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/signos-vitales', $valores + ['id_ingreso' => $ingreso->getKey(), 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 10:00:00'])->assertSessionHasErrors($campo);
        $this->assertDatabaseCount('tbl_signos_vitales', 0);
    }

    public static function signosInvalidos(): array
    {
        return [[['presion_sistolica' => 120], 'presion_diastolica'], [['temperatura' => 51], 'temperatura'], [['saturacion_oxigeno' => 101], 'saturacion_oxigeno'], [['frecuencia_cardiaca' => 0], 'frecuencia_cardiaca'], [['tipo_control' => 'Otro'], 'tipo_control'], [['fecha_hora' => '2026-10-09 10:00:00'], 'fecha_hora']];
    }

    public function test_signos_se_guardan_y_actualizan(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        $datos = ['id_ingreso' => $ingreso->getKey(), 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 10:00:00', 'temperatura' => 37, 'presion_sistolica' => 120, 'presion_diastolica' => 80];
        $this->sesion()->post('/signos-vitales', $datos)->assertSessionHasNoErrors();
        $id = DB::table('tbl_signos_vitales')->value('id_signos_vitales');
        $this->put('/signos-vitales/'.$id, array_replace($datos, ['temperatura' => 36.5]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_signos_vitales', ['id_signos_vitales' => $id, 'temperatura' => 36.5, 'id_usuario_modificacion' => 1]);
        $this->get('/atencion/'.$ingreso->getKey())->assertOk()->assertSee('Historial de controles de signos vitales anteriores');
    }

    public function test_asigna_habitacion_y_rechaza_duplicados(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/atencion/'.$ingreso->getKey().'/hospitalizacion', ['id_habitacion' => 1])->assertSessionHasNoErrors();
        $this->post('/atencion/'.$ingreso->getKey().'/hospitalizacion', ['id_habitacion' => 2])->assertSessionHasErrors('id_habitacion');
        $this->assertDatabaseCount('tbl_hospitalizacion', 1);
        $this->assertDatabaseCount('tbl_hospitalizacion_habitacion', 1);
    }

    public function test_habitacion_ocupada_o_en_mantenimiento_no_se_asigna(): void
    {
        $primero = $this->ingreso();
        $segundo = $this->ingreso();
        $this->hospitalizar($primero);
        $this->sesion()->post('/atencion/'.$segundo->getKey().'/hospitalizacion', ['id_habitacion' => 1])->assertSessionHasErrors('id_habitacion');
        DB::table('tbl_habitacion')->where('id_habitacion', 2)->update(['estado_operativo' => 'Mantenimiento']);
        $this->post('/atencion/'.$segundo->getKey().'/hospitalizacion', ['id_habitacion' => 2])->assertSessionHasErrors('id_habitacion');
        $this->assertDatabaseCount('tbl_hospitalizacion', 1);
    }

    public function test_traslado_conserva_historial_y_libera_habitacion_anterior(): void
    {
        $id = $this->hospitalizar($this->ingreso());
        $this->sesion()->patch('/hospitalizacion/'.$id.'/cambiar-habitacion', ['id_habitacion' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 1, 'fecha_hora_fin' => '2026-10-11 12:00:00']);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 2, 'fecha_hora_fin' => null]);
    }

    public function test_traslado_a_la_misma_habitacion_no_altera_historial(): void
    {
        $id = $this->hospitalizar($this->ingreso());
        $this->sesion()->patch('/hospitalizacion/'.$id.'/cambiar-habitacion', ['id_habitacion' => 1])->assertSessionHasErrors('id_habitacion');
        $this->assertDatabaseCount('tbl_hospitalizacion_habitacion', 1);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 1, 'fecha_hora_fin' => null]);
    }

    public function test_tratamiento_v3_se_registra_actualiza_y_cancela(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        $datos = $this->tratamiento($ingreso);
        $this->sesion()->post('/tratamientos', $datos)->assertSessionHasNoErrors();
        $id = DB::table('tbl_tratamiento')->value('id_tratamiento');
        $this->assertDatabaseHas('tbl_tratamiento', ['id_ingreso' => $ingreso->getKey(), 'id_personal_indica' => 1, 'id_usuario_creacion' => 1, 'id_medicamento' => 1]);
        $this->get('/atencion/'.$ingreso->getKey())->assertOk()->assertSee('Editar tratamiento');
        $this->put('/tratamientos/'.$id, array_replace($datos, ['indicaciones' => 'Revisado']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_tratamiento', ['id_tratamiento' => $id, 'indicaciones' => 'Revisado']);
        $this->delete('/tratamientos/'.$id)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_tratamiento', ['id_tratamiento' => $id, 'estado' => 'Cancelado']);
    }

    public function test_tratamiento_no_farmacologico_requiere_descripcion(): void
    {
        $ingreso = $this->ingreso();
        $datos = array_replace($this->tratamiento($ingreso), ['tipo_tratamiento' => 'No farmacológico', 'id_medicamento' => null]);
        $this->sesion()->post('/tratamientos', $datos)->assertSessionHasErrors('descripcion_tratamiento');
        $this->post('/tratamientos', $datos + ['descripcion_tratamiento' => 'Reposo'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_tratamiento', ['tipo_tratamiento' => 'No farmacológico', 'descripcion_tratamiento' => 'Reposo', 'id_medicamento' => null]);
    }

    public function test_tratamiento_rechaza_evolucion_de_otro_ingreso(): void
    {
        $ingreso = $this->ingreso();
        $otro = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($otro))->assertSessionHasNoErrors();
        $id = DB::table('tbl_evolucion_clinica')->value('id_evolucion');
        $this->post('/tratamientos', $this->tratamiento($ingreso) + ['id_evolucion' => $id])->assertSessionHasErrors('id_evolucion');
        $this->assertDatabaseCount('tbl_tratamiento', 0);
    }

    public function test_tratamiento_rechaza_medicamento_inactivo_y_fechas_invertidas(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/tratamientos', $this->tratamiento($ingreso) + ['fecha_inicio' => '2026-10-11 11:00:00', 'fecha_fin' => '2026-10-11 10:00:00'])->assertSessionHasErrors('fecha_fin');
        DB::table('tbl_medicamento')->where('id_medicamento', 1)->update(['estado' => 'Inactivo']);
        $this->post('/tratamientos', $this->tratamiento($ingreso))->assertSessionHasErrors('id_medicamento');
        $this->assertDatabaseCount('tbl_tratamiento', 0);
    }

    public function test_alta_cierra_el_flujo_y_se_consulta_en_expediente(): void
    {
        $ingreso = $this->ingreso();
        $this->hospitalizar($ingreso);
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        $this->post('/evolucion', $this->evolucion($ingreso) + $this->ordenesNuevas())->assertSessionHasNoErrors();
        $this->post('/tratamientos', $this->tratamiento($ingreso))->assertSessionHasNoErrors();
        $this->post('/atencion/'.$ingreso->getKey().'/alta', $this->alta())->assertRedirect(route('atencion.show', $ingreso))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Dado de alta']);
        $this->assertDatabaseHas('tbl_hospitalizacion', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Finalizada']);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 1, 'fecha_hora_fin' => '2026-10-11 11:59:00']);
        $this->assertDatabaseHas('tbl_tratamiento', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Finalizado']);
        $respuesta = $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertDontSee('Ver evoluciones')
            ->assertSee('Fecha de ingreso')->assertSee('Fecha de egreso')->assertSee('11/10/2026 11:59')->assertSee('Ver documentos')->assertSee('id="documentos-atencion-'.$ingreso->getKey().'"', false)->assertSee('Última habitación ocupada')->assertDontSee('Cambios de habitación')
            ->assertSee('Hoja de ingreso')->assertSee('Valoración inicial')->assertSee('Evolución clínica #')->assertSee('Diagnóstico final de prueba')
            ->assertSee('data-bs-target="#documento-alta-'.$ingreso->getKey().'"', false)
            ->assertDontSee('Ver expediente')->assertDontSee('consulta=valoracion', false)->assertDontSee('consulta=alta', false)
            ->assertDontSee('Alta médica — ingreso');
        $documento = new \DOMDocument;
        $documento->loadHTML($respuesta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($documento);
        $ventanas = $xpath->query('//div[starts-with(@id,"documento-")]');
        self::assertCount(4, $ventanas);
        foreach ($ventanas as $ventana) {
            self::assertSame(0, $xpath->query('.//form | .//input | .//select | .//textarea', $ventana)->length);
        }
        $this->get(route('atencion.show', ['ingreso' => $ingreso->getKey(), 'consulta' => 'alta']))->assertOk()->assertSee('getElementById("modalAlta")', false)->assertSee('Diagnóstico final de prueba');
        $this->get(route('atencion.show', ['ingreso' => $ingreso->getKey(), 'consulta' => 'valoracion']))->assertOk()->assertSee('getElementById("modalValoracion")', false);
        $this->post('/atencion/'.$ingreso->getKey().'/alta', $this->alta())->assertSessionHasErrors('ingreso');
        $this->assertDatabaseCount('tbl_alta_medica', 1);
    }

    public function test_alta_invalida_revierte_todos_los_cambios(): void
    {
        $ingreso = $this->ingreso();
        $this->hospitalizar($ingreso);
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        $this->post('/atencion/'.$ingreso->getKey().'/alta', array_replace($this->alta(), ['fecha_hora' => '2026-10-11 09:00:00']))->assertSessionHasErrors('fecha_hora');
        $this->assertDatabaseCount('tbl_alta_medica', 0);
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Activo']);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 1, 'fecha_hora_fin' => null]);
    }

    public function test_ingreso_cerrado_rechaza_registros_y_traslados(): void
    {
        $ingreso = $this->ingreso();
        $hospitalizacion = $this->hospitalizar($ingreso);
        $ingreso->update(['estado' => 'Dado de alta']);
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasErrors('ingreso');
        $this->post('/signos-vitales', ['id_ingreso' => $ingreso->getKey(), 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 10:00:00', 'temperatura' => 37])->assertSessionHasErrors('ingreso');
        $this->post('/tratamientos', $this->tratamiento($ingreso))->assertSessionHasErrors('ingreso');
        $this->patch('/hospitalizacion/'.$hospitalizacion.'/cambiar-habitacion', ['id_habitacion' => 2])->assertSessionHasErrors('ingreso');
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
        $this->assertDatabaseCount('tbl_signos_vitales', 0);
        $this->assertDatabaseCount('tbl_tratamiento', 0);
    }

    public function test_fecha_de_ingreso_no_puede_invalidar_la_atencion_existente(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        $this->put('/ingresos/'.$ingreso->getKey(), ['fecha_ingreso' => '2026-10-11 11:00:00'])->assertSessionHasErrors('fecha_ingreso');
        self::assertSame('2026-10-10 12:00:00', $ingreso->fresh()->fecha_ingreso->format('Y-m-d H:i:s'));
    }

    public function test_fallo_al_guardar_alta_revierte_la_liberacion_de_habitacion(): void
    {
        $ingreso = $this->ingreso();
        $this->hospitalizar($ingreso);
        DB::unprepared("CREATE TRIGGER falla_alta BEFORE INSERT ON tbl_alta_medica BEGIN SELECT RAISE(ABORT, 'Fallo simulado'); END;");
        $this->sesion()->post('/atencion/'.$ingreso->getKey().'/alta', $this->alta())->assertStatus(500);
        $this->assertDatabaseCount('tbl_alta_medica', 0);
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Activo']);
        $this->assertDatabaseHas('tbl_hospitalizacion', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Activa', 'fecha_fin' => null]);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 1, 'fecha_hora_fin' => null]);
    }

    public function test_alta_rechaza_personal_sin_cargo_medico(): void
    {
        $ingreso = $this->ingreso();
        DB::table('tbl_cargo')->where('id_cargo', 1)->update(['nombre_cargo' => 'Secretaria']);
        $this->sesion()->post('/atencion/'.$ingreso->getKey().'/alta', $this->alta())->assertSessionHasErrors('id_personal_medico');
        $this->assertDatabaseCount('tbl_alta_medica', 0);
    }

    public function test_alta_sin_hospitalizacion_tambien_cierra_el_ingreso(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/atencion/'.$ingreso->getKey().'/alta', $this->alta())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Dado de alta']);
    }

    public function test_ingreso_cerrado_bloquea_ediciones_y_bajas_de_registros(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        $this->post('/tratamientos', $this->tratamiento($ingreso))->assertSessionHasNoErrors();
        $this->post('/signos-vitales', ['id_ingreso' => $ingreso->getKey(), 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 10:00:00', 'temperatura' => 37])->assertSessionHasNoErrors();
        $ingreso->update(['estado' => 'Dado de alta']);
        $this->put('/evolucion/1', $this->evolucion($ingreso))->assertSessionHasErrors('ingreso');
        $this->delete('/evolucion/1')->assertSessionHasErrors('ingreso');
        $this->put('/signos-vitales/1', [])->assertSessionHasErrors('ingreso');
        $this->put('/tratamientos/1', $this->tratamiento($ingreso))->assertSessionHasErrors('ingreso');
        $this->delete('/tratamientos/1')->assertSessionHasErrors('ingreso');
        $this->assertDatabaseHas('tbl_tratamiento', ['id_tratamiento' => 1, 'estado' => 'Activo']);
        $this->assertDatabaseHas('tbl_evolucion_clinica', ['id_evolucion' => 1, 'estado' => 'Activo']);
    }

    public function test_ingreso_registra_hospitalizacion_y_rechaza_segundo_ingreso_activo(): void
    {
        $previo = $this->ingreso(['estado' => 'Dado de alta']);
        $datos = ['id_paciente' => $previo->id_paciente, 'id_habitacion' => 1, 'fecha_ingreso' => '2026-10-11 10:00:00'];
        $this->sesion()->post('/ingresos', $datos)->assertSessionHasNoErrors()->assertSessionHas('exito');
        $this->post('/ingresos', $datos)->assertSessionHas('error');
        $this->assertDatabaseCount('tbl_ingreso', 2);
        $this->assertDatabaseCount('tbl_hospitalizacion', 1);
        $this->assertDatabaseCount('tbl_hospitalizacion_habitacion', 1);
    }

    public function test_cancelar_ingreso_libera_la_habitacion(): void
    {
        $ingreso = $this->ingreso();
        $this->hospitalizar($ingreso);
        $this->sesion()->patch('/ingresos/'.$ingreso->getKey().'/cancelar', ['motivo_anulacion' => 'Se abrió con el paciente equivocado'])
            ->assertSessionHasNoErrors()->assertRedirect(route('atencion.index'));
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Cancelado']);
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Anulación de atención', 'descripcion' => 'Motivo: Se abrió con el paciente equivocado', 'id_usuario' => 1, 'tabla' => 'tbl_ingreso', 'id_registro' => $ingreso->getKey()]);
        $this->assertDatabaseHas('tbl_hospitalizacion', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Cancelada']);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 1, 'fecha_hora_fin' => '2026-10-11 12:00:00']);
    }

    public function test_anular_atencion_pide_motivo_y_conserva_la_atencion_si_falta(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('Anular atención')->assertSee('id="modalAnularAtencion"', false)
            ->assertSee('action="'.route('ingresos.cancelar', $ingreso).'"', false)->assertDontSee('confirm(', false);

        $this->from(route('atencion.show', $ingreso))->patch(route('ingresos.cancelar', $ingreso), ['_formulario' => 'anular-atencion', 'motivo_anulacion' => ''])
            ->assertRedirect(route('atencion.show', $ingreso))->assertSessionHasErrors(['motivo_anulacion' => 'Escriba por qué se anula la atención.']);
        $this->assertSame('Activo', $ingreso->fresh()->estado);
        $this->get(route('atencion.show', $ingreso))->assertSee('document.getElementById("modalAnularAtencion")', false);
        $this->assertDatabaseMissing('tbl_bitacora', ['accion' => 'Anulación de atención']);
    }

    public function test_consulta_anulada_vuelve_a_pacientes_y_aparece_anulada_en_el_expediente(): void
    {
        $ingreso = $this->ingreso(['tipo_atencion' => 'Consulta externa']);

        $this->sesion()->patch(route('ingresos.cancelar', $ingreso), ['motivo_anulacion' => 'Paciente equivocado'])
            ->assertRedirect(route('pacientes.index'))->assertSessionHas('exito', 'Atención anulada. Los registros se conservan y el motivo quedó en la bitácora.');
        $this->get(route('atencion.show', $ingreso))->assertRedirect(route('atencion.index'));
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('Anulada')->assertSee('Atención anulada');
        $this->patch(route('ingresos.cancelar', $ingreso), ['motivo_anulacion' => 'Otra vez'])->assertSessionHasErrors(['ingreso' => 'El ingreso ya no se encuentra activo.']);
        $this->assertSame(1, DB::table('tbl_bitacora')->where('accion', 'Anulación de atención')->count());
    }

    public function test_cancelacion_en_el_mismo_instante_no_rompe_restriccion_v3(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/atencion/'.$ingreso->getKey().'/hospitalizacion', ['id_habitacion' => 1])->assertSessionHasNoErrors();
        $this->patch('/ingresos/'.$ingreso->getKey().'/cancelar', ['motivo_anulacion' => 'Internado por error'])->assertSessionHasErrors('fecha_hora');
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso' => $ingreso->getKey(), 'estado' => 'Activo']);
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_habitacion' => 1, 'fecha_hora_fin' => null]);
    }

    public function test_valoracion_precarga_motivo_y_muestra_examen_fisico_sin_talla_ni_peso(): void
    {
        $ingreso = $this->ingreso(['motivo_ingreso' => 'Dolor de prueba']);
        $this->sesion()->get('/atencion/'.$ingreso->getKey())->assertOk()
            ->assertSee('value="Dolor de prueba"', false)->assertSee('Síntomas')
            ->assertDontSee('Talla <span class="text-muted fw-normal">(cm)</span>', false)->assertDontSee('Peso <span class="text-muted fw-normal">(lb)</span>', false)->assertSee('Cabeza')->assertSee('Tronco')->assertSee('Extremidades')
            ->assertDontSee('Presión sistólica')->assertDontSee('Presión diastólica')->assertDontSee('data-bs-target="#signos"', false);
    }

    public function test_examen_fisico_se_guarda_y_se_edita_con_su_evolucion(): void
    {
        $ingreso = $this->ingreso();
        $examen = ['frecuencia_cardiaca' => 86, 'presion_arterial' => '120 / 80', 'frecuencia_respiratoria' => 18,
            'talla' => 165.5, 'peso' => 62.3, 'temperatura' => 37.3, 'saturacion_oxigeno' => 98,
            'cabeza' => 'Descripción de cabeza', 'tronco' => 'Descripción de tronco', 'extremidades' => 'Descripción de extremidades'];
        $datos = $this->evolucion($ingreso) + ['_formulario' => 'valoracion', 'examen_fisico' => $examen];
        $this->sesion()->post('/evolucion', $datos)->assertSessionHasNoErrors();
        $registro = EvolucionClinica::firstOrFail();
        self::assertSame('120/80', $registro->examen_fisico['presion_arterial']);
        self::assertEquals(165.5, $registro->examen_fisico['talla']);
        self::assertSame('Descripción de cabeza', $registro->examen_fisico['cabeza']);
        $this->get('/atencion/'.$ingreso->getKey())->assertOk()->assertSee('120/80')->assertSee('Descripción de tronco')->assertSee('Editar valoración inicial')
            ->assertViewHas('historial', fn ($historial) => $historial->total() === 1)->assertSee('Tipo de registro')->assertSee('Valoración inicial');
        $this->get('/atencion/'.$ingreso->getKey().'?registro='.$registro->getKey())->assertOk()
            ->assertSee('getElementById("modalValoracion")', false);
        $this->post('/evolucion', $this->evolucion($ingreso) + $this->ordenesNuevas())->assertSessionHasNoErrors();
        $seguimiento = EvolucionClinica::orderByDesc('id_evolucion')->firstOrFail();
        $this->get('/atencion/'.$ingreso->getKey().'?registro='.$seguimiento->getKey())->assertOk()
            ->assertViewHas('historial', fn ($historial) => $historial->total() === 2)
            ->assertSee('getElementById("modalEvolucion")', false)->assertSee('Guardar cambios de evolución y órdenes');
        $datos['examen_fisico']['peso'] = 63;
        $datos['examen_fisico']['presion_arterial'] = '118/78';
        $this->put('/evolucion/'.$registro->getKey(), $datos)->assertSessionHasNoErrors();
        self::assertSame('118/78', $registro->fresh()->examen_fisico['presion_arterial']);
        self::assertEquals(63, $registro->fresh()->examen_fisico['peso']);
        self::assertSame('lb', $registro->fresh()->examen_fisico['unidad_peso']);
        self::assertEquals(132.28, ExamenFisico::pesoEnLibras(['peso' => 60]));
        self::assertEquals(150, ExamenFisico::pesoEnLibras(['peso' => 150, 'unidad_peso' => 'lb']));
        $this->assertDatabaseCount('tbl_evolucion_clinica', 2);
    }

    #[DataProvider('examenesInvalidos')]
    public function test_examen_fisico_rechaza_datos_invalidos(string $campo, mixed $valor): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + ['examen_fisico' => [$campo => $valor]])
            ->assertSessionHasErrors('examen_fisico.'.$campo);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
    }

    public static function examenesInvalidos(): array
    {
        return [
            ['presion_arterial', ['120', '80']], ['presion_arterial', '120'], ['presion_arterial', 'abc/80'], ['presion_arterial', '301/80'],
            ['presion_arterial', '120/201'], ['presion_arterial', '120/0'], ['talla', -10], ['peso', 0],
            ['peso', 'abc'], ['frecuencia_cardiaca', 301], ['frecuencia_respiratoria', 0],
            ['temperatura', 51], ['saturacion_oxigeno', 101], ['cabeza', str_repeat('a', 2001)],
        ];
    }

    public function test_edicion_invalida_preserva_examen_anterior_y_datos_del_formulario(): void
    {
        $ingreso = $this->ingreso();
        $datos = $this->evolucion($ingreso) + ['examen_fisico' => ['presion_arterial' => '120/80']];
        $this->sesion()->post('/evolucion', $datos)->assertSessionHasNoErrors();
        $registro = EvolucionClinica::firstOrFail();
        $datos['examen_fisico']['presion_arterial'] = 'dato inválido';
        $datos['_formulario'] = 'valoracion-editar';
        $this->from('/atencion/'.$ingreso->getKey())->put('/evolucion/'.$registro->getKey(), $datos)
            ->assertSessionHasErrors('examen_fisico.presion_arterial')->assertSessionHasInput('examen_fisico.presion_arterial', 'dato inválido');
        self::assertSame('120/80', $registro->fresh()->examen_fisico['presion_arterial']);
        $this->get('/atencion/'.$ingreso->getKey())->assertOk()->assertSee('value="dato inválido"', false);
    }

    public function test_edicion_sin_campos_nuevos_conserva_el_examen_existente(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + ['examen_fisico' => ['cabeza' => 'Hallazgo conservado']])->assertSessionHasNoErrors();
        $registro = EvolucionClinica::firstOrFail();
        $this->put('/evolucion/'.$registro->getKey(), $this->evolucion($ingreso))->assertSessionHasNoErrors();
        self::assertSame('Hallazgo conservado', $registro->fresh()->examen_fisico['cabeza']);
    }

    public function test_examen_nuevo_no_altera_controles_historicos(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/signos-vitales', ['id_ingreso' => $ingreso->getKey(), 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 10:00:00', 'temperatura' => 37])->assertSessionHasNoErrors();
        $this->post('/evolucion', $this->evolucion($ingreso) + ['examen_fisico' => ['temperatura' => 36.5]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_signos_vitales', ['temperatura' => 37]);
        $this->get('/atencion/'.$ingreso->getKey())->assertOk()->assertSee('Historial de controles de signos vitales anteriores');
    }

    private function ordenesNuevas(): array
    {
        return ['id_personal_medico' => 1, 'estado_mejoria' => 'Mejoró', 'ordenes' => [
            'dieta' => 'Blanda', 'soluciones' => [['tipo' => 'Salina', 'volumen' => 500, 'velocidad' => 40, 'unidad' => 'gotas/min']],
            'medicamentos' => [['nombre' => 'Medicamento de prueba', 'dosis' => 500, 'via' => 'Oral', 'frecuencia' => 8]],
            'indicaciones' => 'Vigilar según indicación de prueba',
        ]];
    }

    public function test_evolucion_y_ordenes_se_guardan_juntas(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $this->ordenesNuevas())->assertSessionHasNoErrors();
        $registro = EvolucionClinica::firstOrFail();
        self::assertSame('Blanda', $registro->ordenes_medicas['dieta']);
        self::assertSame('Medicamento de prueba', $registro->ordenes_medicas['medicamentos'][0]['nombre']);
        self::assertSame('Mejoró', $registro->estado_mejoria);
        $this->get('/atencion/'.$ingreso->getKey().'?registro='.$registro->getKey())->assertOk()->assertSee('Guardar cambios de evolución y órdenes')->assertSee('Registrar alta médica')->assertDontSee('Diagnóstico anterior');
    }

    public function test_ordenes_de_cada_evolucion_son_independientes(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $this->ordenesNuevas())->assertSessionHasNoErrors();
        $primera = EvolucionClinica::firstOrFail();
        $siguiente = array_replace($this->evolucion($ingreso), ['fecha_hora' => '2026-10-11 11:00:00']);
        $this->post('/evolucion', $siguiente + $this->ordenesNuevas())->assertSessionHasNoErrors();
        $segunda = EvolucionClinica::orderByDesc('id_evolucion')->firstOrFail();
        $cambio = $this->ordenesNuevas();
        $cambio['ordenes']['dieta'] = 'Líquida';
        $this->put('/evolucion/'.$primera->getKey(), $this->evolucion($ingreso) + $cambio)->assertSessionHasNoErrors();
        self::assertSame('Blanda', $segunda->fresh()->ordenes_medicas['dieta']);
        self::assertArrayNotHasKey('modo', $segunda->ordenes_medicas);
        self::assertArrayNotHasKey('origen_evolucion', $segunda->ordenes_medicas);
    }

    public function test_migracion_quita_opcion_de_copia_y_conserva_indicaciones(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $this->ordenesNuevas())->assertSessionHasNoErrors();
        $registro = EvolucionClinica::firstOrFail();
        $original = $registro->ordenes_medicas;
        $registro->update(['ordenes_medicas' => $original + ['modo' => 'igual', 'origen_evolucion' => 99]]);
        (require database_path('migrations/2026_10_15_022136_remove_order_copy_metadata_from_evoluciones.php'))->up();
        self::assertSame($original, $registro->fresh()->ordenes_medicas);
        $this->get('/atencion/'.$ingreso->getKey().'?registro='.$registro->getKey())->assertOk()
            ->assertDontSee('Órdenes de este registro')->assertDontSee('name="modo_ordenes"', false);
    }

    public function test_evolucion_no_se_guarda_sin_ordenes_medicas(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + ['_formulario' => 'valoracion'])->assertSessionHasNoErrors();
        $this->post('/evolucion', $this->evolucion($ingreso) + ['_formulario' => 'evolucion-nueva'])->assertSessionHasErrors('ordenes');
        $this->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasErrors('ordenes');
        $datos = $this->evolucion($ingreso) + $this->ordenesNuevas();
        unset($datos['ordenes']);
        $this->post('/evolucion', $datos)->assertSessionHasErrors('ordenes');
        $datos['ordenes'] = ['dieta' => ''];
        $this->post('/evolucion', $datos)->assertSessionHasErrors('ordenes.dieta');
        $this->assertDatabaseCount('tbl_evolucion_clinica', 1);
        $datos['ordenes'] = ['dieta' => 'Blanda'];
        $this->post('/evolucion', $datos)->assertSessionHasNoErrors();
        $registro = EvolucionClinica::orderByDesc('id_evolucion')->firstOrFail();
        $this->put('/evolucion/'.$registro->getKey(), $this->evolucion($ingreso))->assertSessionHasErrors('ordenes');
        self::assertSame('Blanda', $registro->fresh()->ordenes_medicas['dieta']);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 2);
    }

    public function test_solo_admite_una_solucion_y_varios_medicamentos(): void
    {
        $ingreso = $this->ingreso();
        $datos = $this->evolucion($ingreso) + $this->ordenesNuevas();
        $datos['ordenes']['soluciones'][] = $datos['ordenes']['soluciones'][0];
        $this->sesion()->post('/evolucion', $datos)->assertSessionHasErrors('ordenes.soluciones');
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
        array_pop($datos['ordenes']['soluciones']);
        $datos['ordenes']['medicamentos'][] = ['nombre' => 'Segundo medicamento de prueba', 'dosis' => 250, 'via' => 'Intravenosa', 'frecuencia' => 12];
        $this->post('/evolucion', $datos)->assertSessionHasNoErrors();
        $registro = EvolucionClinica::firstOrFail();
        self::assertCount(1, $registro->ordenes_medicas['soluciones']);
        self::assertCount(2, $registro->ordenes_medicas['medicamentos']);
        $datos['ordenes']['soluciones'][] = $datos['ordenes']['soluciones'][0];
        $this->put('/evolucion/'.$registro->getKey(), $datos)->assertSessionHasErrors('ordenes.soluciones');
        self::assertCount(1, $registro->fresh()->ordenes_medicas['soluciones']);
    }

    public function test_orden_invalida_no_guarda_evolucion_parcial(): void
    {
        $ingreso = $this->ingreso();
        $datos = $this->ordenesNuevas();
        $datos['ordenes']['medicamentos'][0]['nombre'] = '   ';
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $datos)->assertSessionHasErrors('ordenes.medicamentos.0.nombre');
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
    }

    public function test_ordenes_rechazan_dieta_volumen_y_dosis_invalidos(): void
    {
        $ingreso = $this->ingreso();
        $datos = $this->ordenesNuevas();
        $datos['ordenes']['dieta'] = 'Incorrecta';
        $datos['ordenes']['soluciones'][0]['volumen'] = 0;
        $datos['ordenes']['medicamentos'][0]['dosis'] = '';
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $datos)->assertSessionHasErrors(['ordenes.dieta', 'ordenes.soluciones.0.volumen', 'ordenes.medicamentos.0.dosis']);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
    }

    public function test_evolucion_usa_el_personal_del_usuario_y_exige_estado_mejoria(): void
    {
        $ingreso = $this->ingreso();
        $datos = $this->ordenesNuevas();
        $datos['estado_mejoria'] = 'Otro';
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $datos)->assertSessionHasErrors('estado_mejoria');
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);

        // Aunque llegue otro médico en la petición, el responsable es quien inició sesión.
        $datos['estado_mejoria'] = 'Mejoró';
        $datos['id_personal_medico'] = 999;
        $this->post('/evolucion', $this->evolucion($ingreso) + $datos)->assertSessionHasNoErrors();
        $idPersonalSesion = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');
        $this->assertSame((int) $idPersonalSesion, (int) EvolucionClinica::sole()->id_personal);
        $this->get('/atencion/'.$ingreso->getKey().'?nuevo=1')->assertOk()->assertDontSee('id="evo-medico"', false)->assertSee('Médico responsable');
    }

    public function test_historial_paginado_solo_abre_registros_del_ingreso(): void
    {
        $ingreso = $this->ingreso();
        for ($i = 0; $i < 12; $i++) {
            $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $this->ordenesNuevas())->assertSessionHasNoErrors();
        }
        $this->get('/atencion/'.$ingreso->getKey())->assertOk()->assertViewHas('historial', fn ($historial) => $historial->count() === 10 && $historial->total() === 12)->assertSee('id="alta"', false)->assertDontSee('role="tabpanel"', false);
        $this->get('/atencion/'.$ingreso->getKey().'?page=2')->assertOk()->assertViewHas('historial', fn ($historial) => $historial->count() === 2);
        $otro = $this->ingreso();
        $this->get('/atencion/'.$otro->getKey().'?registro=1')->assertNotFound();
    }

    public function test_edicion_invalida_conserva_ordenes_y_diagnostico(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $this->ordenesNuevas())->assertSessionHasNoErrors();
        $registro = EvolucionClinica::firstOrFail();
        $datos = $this->ordenesNuevas();
        $datos['ordenes']['dieta'] = 'Inválida';
        $this->put('/evolucion/'.$registro->getKey(), array_replace($this->evolucion($ingreso), ['diagnostico' => 'No guardar']) + $datos)->assertSessionHasErrors('ordenes.dieta');
        self::assertSame('Diagnóstico de prueba', $registro->fresh()->diagnostico);
        self::assertSame('Blanda', $registro->fresh()->ordenes_medicas['dieta']);
    }

    public function test_alta_permanece_en_atencion_con_historial_de_solo_consulta(): void
    {
        $ingreso = $this->ingreso();
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $this->ordenesNuevas())->assertSessionHasNoErrors();
        $this->post('/atencion/'.$ingreso->getKey().'/alta', $this->alta())->assertRedirect(route('atencion.show', $ingreso))->assertSessionHasNoErrors();
        $this->get('/atencion/'.$ingreso->getKey().'?registro=1')->assertOk()->assertSee('modo consulta')->assertSee('Alta médica registrada')->assertSee('Blanda')->assertSee('<fieldset class="atencion-contenido" disabled>', false)->assertSee('href="#modalAlta"', false)->assertSee('Ver alta médica');
        $this->put('/evolucion/1', $this->evolucion($ingreso) + $this->ordenesNuevas())->assertSessionHasErrors('ingreso');
    }

    public function test_medicamento_se_escribe_sin_catalogo_y_se_conserva_al_editar(): void
    {
        DB::table('tbl_medicamento')->delete();
        $ingreso = $this->ingreso();
        $datos = $this->ordenesNuevas();
        $datos['ordenes']['medicamentos'][0]['nombre'] = 'Nombre libre 500 mg / 5 ml';
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $datos)->assertSessionHasNoErrors();
        $registro = EvolucionClinica::firstOrFail();
        self::assertSame('Nombre libre 500 mg / 5 ml', $registro->ordenes_medicas['medicamentos'][0]['nombre']);
        $this->get('/atencion/'.$ingreso->getKey().'?registro='.$registro->getKey())->assertOk()->assertSee('Nombre libre 500 mg / 5 ml')->assertSee('placeholder="Ej. Diclofenaco"', false);
        $datos['ordenes']['medicamentos'][0]['nombre'] = 'Otro nombre escrito';
        $this->put('/evolucion/'.$registro->getKey(), $this->evolucion($ingreso) + $datos)->assertSessionHasNoErrors();
        self::assertSame('Otro nombre escrito', $registro->fresh()->ordenes_medicas['medicamentos'][0]['nombre']);
        $this->assertDatabaseCount('tbl_medicamento', 0);
    }

    public function test_nombre_de_medicamento_rechaza_exceso_de_longitud(): void
    {
        $ingreso = $this->ingreso();
        $datos = $this->ordenesNuevas();
        $datos['ordenes']['medicamentos'][0]['nombre'] = str_repeat('x', 256);
        $this->sesion()->post('/evolucion', $this->evolucion($ingreso) + $datos)->assertSessionHasErrors('ordenes.medicamentos.0.nombre');
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
    }
}
