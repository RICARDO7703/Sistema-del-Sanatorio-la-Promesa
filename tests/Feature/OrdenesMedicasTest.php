<?php

namespace Tests\Feature;

use App\Http\Middleware\AvisarSinCambios;
use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Services\OrdenesMedicas;
use App\Support\EdicionClinica;
use Database\Factories\IngresoFactory;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrdenesMedicasTest extends TestCase
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
    }

    private function ingreso(): Ingreso
    {
        return IngresoFactory::new()->create();
    }

    /**
     * @param  array<string, mixed>  $ordenes
     * @return array<string, mixed>
     */
    private function evolucion(Ingreso $ingreso, array $ordenes = [], string $fecha = '2026-10-11 10:00:00'): array
    {
        return [
            '_formulario' => 'evolucion-nueva',
            'id_ingreso' => $ingreso->getKey(),
            'fecha_hora' => $fecha,
            'motivo_atencion' => 'Control',
            'diagnostico' => 'Neumonía',
            'id_personal_medico' => 1,
            'estado_mejoria' => 'Mejoró',
            'ordenes' => array_replace([
                'dieta' => 'Blanda',
                'soluciones' => [['tipo' => 'Salina', 'volumen' => 500, 'velocidad' => 40, 'unidad' => 'gotas/min']],
                'medicamentos' => [['nombre' => 'Diclofenaco', 'dosis' => 75, 'via' => 'Intramuscular', 'frecuencia' => 8]],
                'indicaciones' => 'Vigilar temperatura',
            ], $ordenes),
        ];
    }

    private function valoracion(Ingreso $ingreso): void
    {
        $this->withSession(['id_usuario' => 1])->post('/evolucion', [
            '_formulario' => 'valoracion',
            'id_ingreso' => $ingreso->getKey(),
            'fecha_hora' => '2026-10-11 08:00:00',
            'motivo_atencion' => 'Tos',
            'diagnostico' => 'Neumonía adquirida en la comunidad',
            'examen_fisico' => ['talla' => '165', 'peso' => '150', 'temperatura' => '38.2'],
        ])->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function dietasDeLista(): array
    {
        return [
            'NPO' => ['NPO (nada por vía oral)'],
            'HNO' => ['HNO (hasta nueva orden)'],
            'Líquida' => ['Líquida'],
        ];
    }

    #[DataProvider('dietasDeLista')]
    public function test_acepta_las_dietas_de_la_lista(string $dieta): void
    {
        $ingreso = $this->ingreso();

        $this->withSession(['id_usuario' => 1])->post('/evolucion', $this->evolucion($ingreso, ['dieta' => $dieta]))->assertSessionHasNoErrors();

        $this->assertSame($dieta, EvolucionClinica::sole()->ordenes_medicas['dieta']);
    }

    public function test_otra_dieta_guarda_el_texto_escrito(): void
    {
        $ingreso = $this->ingreso();

        $this->withSession(['id_usuario' => 1])->post('/evolucion', $this->evolucion($ingreso, ['dieta' => 'Otra', 'dieta_otra' => '  Hiposódica  ']))->assertSessionHasNoErrors();

        $ordenes = EvolucionClinica::sole()->ordenes_medicas;
        $this->assertSame('Hiposódica', $ordenes['dieta']);
        $this->assertArrayNotHasKey('dieta_otra', $ordenes);

        $this->get(route('atencion.show', ['ingreso' => $ingreso, 'registro' => 1]))->assertOk()
            ->assertSee('value="Hiposódica"', false)
            ->assertSee('<option value="Otra" selected>', false);
    }

    public function test_dieta_invalida_u_otra_vacia_se_rechaza(): void
    {
        $ingreso = $this->ingreso();
        $this->withSession(['id_usuario' => 1]);

        $this->post('/evolucion', $this->evolucion($ingreso, ['dieta' => 'Inventada']))->assertSessionHasErrors('ordenes.dieta');
        $this->post('/evolucion', $this->evolucion($ingreso, ['dieta' => 'Otra', 'dieta_otra' => '']))->assertSessionHasErrors(['ordenes.dieta_otra' => 'Escriba la dieta indicada.']);

        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
    }

    public function test_medicamentos_en_mg_via_de_lista_y_frecuencia_en_horas(): void
    {
        $ingreso = $this->ingreso();
        $this->withSession(['id_usuario' => 1]);

        $this->post('/evolucion', $this->evolucion($ingreso, ['medicamentos' => [['nombre' => 'Diclofenaco', 'dosis' => 'mucho', 'via' => 'Oral', 'frecuencia' => 8]]]))
            ->assertSessionHasErrors(['ordenes.medicamentos.0.dosis' => 'La dosis debe ser un número en miligramos.']);
        $this->post('/evolucion', $this->evolucion($ingreso, ['medicamentos' => [['nombre' => 'Diclofenaco', 'dosis' => 75, 'via' => 'Sublingual', 'frecuencia' => 8]]]))
            ->assertSessionHasErrors('ordenes.medicamentos.0.via');
        $this->post('/evolucion', $this->evolucion($ingreso, ['medicamentos' => [['nombre' => 'Diclofenaco', 'dosis' => 75, 'via' => 'Oral', 'frecuencia' => '3 días']]]))
            ->assertSessionHasErrors(['ordenes.medicamentos.0.frecuencia' => 'La frecuencia debe indicarse en horas enteras.']);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);

        $this->post('/evolucion', $this->evolucion($ingreso, ['medicamentos' => [['nombre' => 'Diclofenaco', 'dosis' => '75', 'via' => 'Intramuscular', 'frecuencia' => '8']]]))->assertSessionHasNoErrors();
        $medicamento = EvolucionClinica::sole()->ordenes_medicas['medicamentos'][0];
        $this->assertSame(75, $medicamento['dosis']);
        $this->assertSame(8, $medicamento['frecuencia']);

        $this->get(route('atencion.show', ['ingreso' => $ingreso, 'registro' => 1]))->assertOk()
            ->assertSee('Diclofenaco · 75 mg · Intramuscular · cada 8 horas')
            ->assertSee('<option selected>Intramuscular</option>', false);
    }

    public function test_goteo_en_gotas_o_microgotas_por_minuto(): void
    {
        $ingreso = $this->ingreso();
        $this->withSession(['id_usuario' => 1]);

        $this->post('/evolucion', $this->evolucion($ingreso, ['soluciones' => [['tipo' => 'Salina', 'volumen' => 250, 'velocidad' => 20, 'unidad' => 'litros']]]))
            ->assertSessionHasErrors('ordenes.soluciones.0.unidad');
        $this->post('/evolucion', $this->evolucion($ingreso, ['soluciones' => [['tipo' => 'Salina', 'volumen' => 250, 'velocidad' => 'rápido', 'unidad' => 'gotas/min']]]))
            ->assertSessionHasErrors('ordenes.soluciones.0.velocidad');

        $this->post('/evolucion', $this->evolucion($ingreso, ['soluciones' => [['tipo' => 'Glucosada', 'volumen' => 250, 'velocidad' => 60, 'unidad' => 'microgotas/min']]]))->assertSessionHasNoErrors();
        $this->assertEquals(['tipo' => 'Glucosada', 'volumen' => 250, 'velocidad' => 60, 'unidad' => 'microgotas/min'], EvolucionClinica::sole()->ordenes_medicas['soluciones'][0]);
        $this->get(route('atencion.show', ['ingreso' => $ingreso, 'registro' => 1]))->assertOk()->assertSee('Suero Glucosada · 250 ml · 60 microgotas/min');
    }

    public function test_plan_educacional_es_opcional_y_se_muestra(): void
    {
        $ingreso = $this->ingreso();
        $this->withSession(['id_usuario' => 1]);

        $this->post('/evolucion', $this->evolucion($ingreso, ['plan_educacional' => '']))->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('plan_educacional', EvolucionClinica::sole()->ordenes_medicas);

        $this->post('/evolucion', $this->evolucion($ingreso, ['plan_educacional' => 'Lavado de manos y signos de alarma'], '2026-10-11 11:00:00'))->assertSessionHasNoErrors();
        $this->assertSame('Lavado de manos y signos de alarma', EvolucionClinica::latest('id_evolucion')->first()->ordenes_medicas['plan_educacional']);
        $this->get(route('atencion.show', ['ingreso' => $ingreso, 'registro' => 2]))->assertOk()->assertSee('Plan educacional:')->assertSee('Lavado de manos y signos de alarma');
    }

    public function test_nueva_evolucion_trae_talla_peso_y_diagnostico_anterior(): void
    {
        $ingreso = $this->ingreso();
        $this->valoracion($ingreso);

        $html = $this->get(route('atencion.show', ['ingreso' => $ingreso, 'nuevo' => 1]))->assertOk()
            ->assertSee('Síntomas')->assertDontSee('Signos y síntomas')
            ->assertSee('Mantener el anterior')->assertSee('Escribir uno nuevo')
            ->assertSee('data-diagnostico-anterior="Neumonía adquirida en la comunidad"', false)
            ->getContent();

        // La talla y el peso ya no se piden en la evolución; la hoja anterior los conserva ocultos al corregirla.
        $this->assertDoesNotMatchRegularExpression('/type="number"[^>]*name="examen_fisico\[(talla|peso)\]"/', $html);
        $this->assertMatchesRegularExpression('/type="hidden" name="examen_fisico\[talla\]" value="165"/', $html);
        $this->assertMatchesRegularExpression('/type="hidden" name="examen_fisico\[peso\]" value="150"/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="evolucion-nueva-temperatura"[^>]*value="38\.2"/', $html);
        $this->assertMatchesRegularExpression('/id="evo-diagnostico"[^>]*readonly[^>]*>Neumonía adquirida en la comunidad</', $html);
    }

    public function test_seguir_igual_ofrece_las_ordenes_de_la_evolucion_anterior(): void
    {
        $ingreso = $this->ingreso();
        $this->withSession(['id_usuario' => 1])->post('/evolucion', $this->evolucion($ingreso, ['dieta' => 'HNO (hasta nueva orden)', 'plan_educacional' => 'Reposo']))->assertSessionHasNoErrors();

        $this->get(route('atencion.show', ['ingreso' => $ingreso, 'nuevo' => 1]))->assertOk()
            ->assertSee('Seguir igual (copiar las órdenes anteriores)')
            ->assertSee('<script type="application/json" id="ordenes-anteriores">', false)
            ->assertSee('HNO (hasta nueva orden)')
            ->assertDontSee('name="modo_ordenes"', false);

        $this->post('/evolucion', $this->evolucion($ingreso, ['dieta' => 'Libre'], '2026-10-11 11:00:00'))->assertSessionHasNoErrors();
        $this->assertSame('HNO (hasta nueva orden)', EvolucionClinica::orderBy('id_evolucion')->first()->ordenes_medicas['dieta']);
    }

    public function test_primera_evolucion_sin_ordenes_previas_no_ofrece_copiarlas(): void
    {
        $ingreso = $this->ingreso();
        $this->valoracion($ingreso);

        $this->get(route('atencion.show', ['ingreso' => $ingreso, 'nuevo' => 1]))->assertOk()
            ->assertDontSee('Seguir igual (copiar las órdenes anteriores)')
            ->assertSee('NPO (nada por vía oral)')
            ->assertSee('Otra (escribirla)')
            ->assertSee('Microgotas para niños.')
            ->assertSee('<span class="input-group-text">mg</span>', false);
    }

    public function test_valoracion_inicial_de_internado_admite_ordenes_opcionales(): void
    {
        $ingreso = $this->ingreso();
        $ordenes = ['dieta' => 'Líquida', 'medicamentos' => [['nombre' => 'Acetaminofén', 'dosis' => 500, 'via' => 'Oral', 'frecuencia' => 6]], 'indicaciones' => 'Control de temperatura'];
        $valoracion = ['_formulario' => 'valoracion', 'id_ingreso' => $ingreso->getKey(), 'fecha_hora' => '2026-10-11 08:00:00', 'motivo_atencion' => 'Desmayo', 'diagnostico' => 'Síncope', 'ordenes_opcionales' => '1'];

        $this->withSession(['id_usuario' => 1])->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('Órdenes médicas (opcional)')->assertSee('name="con_ordenes"', false);
        $html = $this->get(route('atencion.show', $ingreso))->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//div[@id="modalValoracion"]//form//button[contains(., "Guardar valoración")]')->length);
        $this->assertSame(1, $xpath->query('//div[@id="modalValoracion"]//section[@id="valoracion-ordenes" and @hidden]/fieldset')->length);
        $this->assertSame(0, $xpath->query('//section[@data-valoracion-clinica and contains(@class, "col-xl-6")]')->length);
        $this->post('/evolucion', $valoracion + ['con_ordenes' => '1', 'ordenes' => ['dieta' => '']])->assertSessionHasErrors('ordenes.dieta');
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);

        $this->post('/evolucion', $valoracion + ['con_ordenes' => '1', 'ordenes' => $ordenes])->assertSessionHasNoErrors();
        $registro = EvolucionClinica::sole();
        $this->assertSame('Líquida', $registro->ordenes_medicas['dieta']);
        $this->assertNull($registro->estado_mejoria);
        $this->assertSame(1, (int) $registro->id_personal);

        // Sigue siendo la valoración inicial y sus órdenes se muestran con ella.
        $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('Valoración inicial registrada')->assertSee('Acetaminofén');
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('Valoración inicial')->assertSee('Acetaminofén');

        // Al editarla sin marcar la casilla, las órdenes se quitan.
        $this->put(route('evolucion.actualizar', $registro), array_replace($valoracion, ['_formulario' => 'valoracion-editar', 'ordenes' => $ordenes]))->assertSessionHasNoErrors();
        $this->assertNull($registro->fresh()->ordenes_medicas);
        $this->assertNull($registro->fresh()->estado_mejoria);

        // Sin la casilla tampoco se guardan al registrarla.
        $otro = $this->ingreso();
        $this->post('/evolucion', array_replace($valoracion, ['id_ingreso' => $otro->getKey(), 'ordenes' => $ordenes]))->assertSessionHasNoErrors();
        $this->assertNull(EvolucionClinica::where('id_ingreso', $otro->getKey())->sole()->ordenes_medicas);
    }

    public function test_solo_quien_registro_edita_y_durante_doce_horas(): void
    {
        $ingreso = $this->ingreso();
        DB::table('tbl_personal')->insert(['id_personal' => 2, 'id_cargo' => 1, 'nombres' => 'Otra', 'apellidos' => 'Médica', 'dpi' => '1234567890124']);
        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 2, 'id_rol' => 1, 'nombre_usuario' => 'otra', 'contrasena' => 'solo-pruebas']);
        $this->valoracion($ingreso);
        $this->withSession(['id_usuario' => 1])->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        [$valoracion, $evolucion] = EvolucionClinica::orderBy('id_evolucion')->get()->all();
        $expediente = route('pacientes.expediente', $ingreso->id_paciente);

        $this->get($expediente)->assertOk()
            ->assertSee(route('atencion.show', ['ingreso' => $ingreso->getKey(), 'editar' => 'valoracion']), false)
            ->assertSee(route('atencion.show', ['ingreso' => $ingreso->getKey(), 'registro' => $evolucion->getKey()]).'#evoluciones', false)
            ->assertSee('Puede editarla hasta');
        $this->get(route('atencion.show', ['ingreso' => $ingreso->getKey(), 'editar' => 'valoracion']))->assertOk()->assertSee('data-editar-valoracion', false);

        // Otro usuario solo puede verlos.
        $this->withSession(['id_usuario' => 2])->get($expediente)->assertOk()->assertDontSee('Puede editarla hasta');
        $this->get(route('atencion.show', ['ingreso' => $ingreso->getKey(), 'registro' => $evolucion->getKey()]))->assertOk()
            ->assertSee('Solo quien lo registró puede editarlo')->assertDontSee('Guardar cambios de evolución y órdenes');
        $this->put(route('evolucion.actualizar', $evolucion), $this->evolucion($ingreso, ['dieta' => 'Libre']) + ['_formulario' => 'evolucion-'.$evolucion->getKey()])
            ->assertSessionHas('error', EdicionClinica::MENSAJE);
        $this->put(route('ingresos.actualizar', $ingreso), ['fecha_ingreso' => $ingreso->fecha_ingreso->format('Y-m-d\TH:i'), 'motivo_ingreso' => 'Otro'])->assertSessionHas('error', EdicionClinica::MENSAJE);
        $this->assertSame('Blanda', $evolucion->fresh()->ordenes_medicas['dieta']);

        // Quien lo registró puede corregirlo dentro del plazo, pero no después de 12 horas.
        $this->withSession(['id_usuario' => 1])->put(route('evolucion.actualizar', $evolucion), $this->evolucion($ingreso, ['dieta' => 'Libre']) + ['_formulario' => 'evolucion-'.$evolucion->getKey()])->assertSessionHasNoErrors();
        $this->assertSame('Libre', $evolucion->fresh()->ordenes_medicas['dieta']);
        $this->travel(12)->hours();
        $this->travel(1)->minutes();
        $this->put(route('evolucion.actualizar', $valoracion), ['_formulario' => 'valoracion-editar', 'fecha_hora' => '2026-10-11 08:00:00', 'motivo_atencion' => 'Tos', 'diagnostico' => 'Cambio tardío'])
            ->assertSessionHas('error', EdicionClinica::MENSAJE);
        $this->assertNotSame('Cambio tardío', $valoracion->fresh()->diagnostico);
        $this->get($expediente)->assertOk()->assertDontSee('Puede editarla hasta');
    }

    public function test_volver_a_guardar_sin_tocar_nada_no_cuenta_como_edicion(): void
    {
        $ingreso = $this->ingreso();
        $this->valoracion($ingreso);
        $this->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        [$valoracion, $evolucion] = EvolucionClinica::orderBy('id_evolucion')->get()->all();
        $examenVacio = ['cabeza' => '', 'tronco' => '', 'extremidades' => '', 'presion_arterial' => '', 'frecuencia_cardiaca' => '', 'frecuencia_respiratoria' => '', 'temperatura' => '', 'saturacion_oxigeno' => ''];

        // Lo mismo que envía el formulario al abrirlo y guardarlo: campos vacíos, números como texto y la hora sin segundos.
        $this->put(route('evolucion.actualizar', $valoracion), [
            '_formulario' => 'valoracion-editar', 'fecha_hora' => '2026-10-11T08:00', 'motivo_atencion' => 'Tos', 'sintomas' => '', 'evaluacion_medica' => '',
            'diagnostico' => 'Neumonía adquirida en la comunidad', 'indicaciones' => '', 'observaciones' => '',
            'examen_fisico' => array_replace($examenVacio, ['talla' => '165', 'peso' => '150', 'temperatura' => '38.2']),
        ])->assertSessionHasNoErrors()->assertSessionHas('exito', AvisarSinCambios::MENSAJE);
        $formulario = array_replace($this->evolucion($ingreso), ['_formulario' => 'evolucion-'.$evolucion->getKey(), 'fecha_hora' => '2026-10-11T10:00', 'examen_fisico' => $examenVacio, 'observaciones' => '']);
        $formulario['ordenes']['soluciones'][0] = ['tipo' => 'Salina', 'volumen' => '500', 'velocidad' => '40', 'unidad' => 'gotas/min'];
        $formulario['ordenes']['medicamentos'][0] = ['nombre' => 'Diclofenaco', 'dosis' => '75', 'via' => 'Intramuscular', 'frecuencia' => '8'];
        $formulario['ordenes']['plan_educacional'] = '';
        $this->put(route('evolucion.actualizar', $evolucion), $formulario)->assertSessionHasNoErrors()->assertSessionHas('exito', AvisarSinCambios::MENSAJE);
        $this->assertNull($evolucion->fresh()->id_usuario_modificacion);

        // Un cambio de verdad sí se guarda.
        $formulario['ordenes']['medicamentos'][0]['dosis'] = '50';
        $this->put(route('evolucion.actualizar', $evolucion), $formulario)->assertSessionHasNoErrors()->assertSessionHas('exito', 'Evolución clínica actualizada correctamente.');
        $this->assertEquals(50, $evolucion->fresh()->ordenes_medicas['medicamentos'][0]['dosis']);
    }

    public function test_editor_bloquea_ordenes_canceladas_hasta_reactivacion_explicita(): void
    {
        $ingreso = $this->ingreso();
        $this->valoracion($ingreso);
        $this->post('/evolucion', $this->evolucion($ingreso))->assertSessionHasNoErrors();
        $registro = EvolucionClinica::orderByDesc('id_evolucion')->first();
        $this->post(route('evolucion.cancelar-ordenes', $registro), ['motivo_cancelacion' => 'Cancelación de prueba'])->assertSessionHasNoErrors();
        $canceladas = $registro->fresh()->ordenes_medicas;
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
            ->assertDontSee('Reactivar estas órdenes')->assertDontSee('data-lapiz-ordenes');
        $this->get(route('atencion.show', ['ingreso' => $ingreso->getKey(), 'registro' => $registro->getKey()]))->assertOk()
            ->assertDontSee('Órdenes canceladas y bloqueadas.')->assertSee('Reactivar y editar estas órdenes')
            ->assertSee('data-edicion-canceladas', false)->assertDontSee('Órdenes guardadas de este registro');

        $datos = $this->evolucion($ingreso, ['dieta' => 'Libre']);
        $datos['_formulario'] = 'evolucion-'.$registro->getKey();
        $datos['diagnostico'] = 'Diagnóstico corregido';
        // Ni los datos manipulados ni la ausencia de órdenes desbloquean la cancelación.
        $this->put(route('evolucion.actualizar', $registro), $datos)->assertSessionHasNoErrors();
        $this->assertSame($canceladas, $registro->fresh()->ordenes_medicas);
        unset($datos['ordenes']);
        $this->put(route('evolucion.actualizar', $registro), $datos)->assertSessionHasNoErrors();
        $this->assertSame('Diagnóstico corregido', $registro->fresh()->diagnostico);
        $this->assertSame($canceladas, $registro->fresh()->ordenes_medicas);

        $datos['reactivar_ordenes'] = 1;
        $datos['ordenes'] = ['dieta' => ''];
        $this->put(route('evolucion.actualizar', $registro), $datos)->assertSessionHasErrors('ordenes.dieta');
        $this->assertSame($canceladas, $registro->fresh()->ordenes_medicas);
        $datos['ordenes'] = ['dieta' => 'Líquida', 'indicaciones' => 'Nueva indicación'];
        $this->put(route('evolucion.actualizar', $registro), $datos)->assertSessionHasNoErrors();
        $this->assertSame('Líquida', $registro->fresh()->ordenes_medicas['dieta']);
        $this->assertNull(OrdenesMedicas::cancelacion($registro->fresh()->ordenes_medicas));

        $this->post(route('evolucion.cancelar-ordenes', $registro), ['motivo_cancelacion' => 'Nueva cancelación'])->assertSessionHasNoErrors();
        $this->post('/evolucion', $this->evolucion($ingreso, ['dieta' => 'Libre'], '2026-10-11 11:00:00'))->assertSessionHasNoErrors();
        $this->put(route('evolucion.actualizar', $registro), $datos)->assertSessionHasErrors('reactivar_ordenes');
        $this->assertNotNull(OrdenesMedicas::cancelacion($registro->fresh()->ordenes_medicas));
        $this->travel(13)->hours();
        $this->put(route('evolucion.actualizar', $registro), $datos)->assertSessionHas('error', EdicionClinica::MENSAJE);
        $this->assertNotNull(OrdenesMedicas::cancelacion($registro->fresh()->ordenes_medicas));
    }
}
