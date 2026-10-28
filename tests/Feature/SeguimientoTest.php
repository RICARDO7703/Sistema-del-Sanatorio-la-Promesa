<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use Database\Factories\IngresoFactory;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeguimientoTest extends TestCase
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

    private function anterior(array $extra = []): Ingreso
    {
        $origen = IngresoFactory::new()->create($extra + ['tipo_atencion' => 'Consulta externa', 'estado' => 'Dado de alta', 'fecha_ingreso' => '2026-10-10 08:00:00']);
        DB::table('tbl_alta_medica')->insert(['id_ingreso' => $origen->getKey(), 'id_personal_medico' => 1, 'fecha_hora' => '2026-10-10 10:00:00', 'diagnostico_final' => 'Diagnóstico anterior de prueba', 'condicion_egreso' => 'Estable', 'indicaciones_alta' => 'Control posterior', 'id_usuario_creacion' => 1]);

        return $origen;
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'fecha_ingreso' => '2026-10-11T09:00',
            'fecha_hora' => $extra['fecha_ingreso'] ?? '2026-10-11T09:00',
            'motivo_atencion' => $extra['motivo_ingreso'] ?? 'Control posterior',
            'accion' => 'internar',
            'diagnostico' => 'Diagnóstico de la nueva visita',
            '_formulario' => 'consulta-emergencia',
        ];
    }

    public function test_muestra_formulario_y_resumen_sin_copiar_datos_clinicos(): void
    {
        $origen = $this->anterior();
        $this->withSession(['id_usuario' => 1])->get(route('seguimientos.crear', $origen))->assertOk()->assertSee('Consulta externa')->assertDontSee('Datos de esta visita')->assertSee('Diagnóstico anterior de prueba')->assertSee('Control posterior');
        $this->get(route('pacientes.expediente', $origen->paciente))->assertSee(route('seguimientos.crear', $origen), false);
        $this->get(route('atencion.show', $origen))->assertSee('Nuevo seguimiento');
        $this->assertDatabaseCount('tbl_ingreso', 1);
    }

    public function test_inicia_seguimiento_sin_reabrir_origen_ni_copiar_medicacion(): void
    {
        $origen = $this->anterior();
        DB::table('tbl_alta_medicamento')->insert(['id_alta' => $origen->altaMedica->getKey(), 'medicamento' => 'Medicamento anterior', 'dosis' => 'Dosis de prueba', 'frecuencia' => 'Frecuencia de prueba']);
        $this->withSession(['id_usuario' => 1])->post(route('seguimientos.guardar', $origen), $this->datos())->assertSessionHasNoErrors();
        $nuevo = Ingreso::where('id_ingreso_anterior', $origen->getKey())->sole();
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso' => $nuevo->getKey(), 'id_paciente' => $origen->id_paciente, 'estado' => 'Activo', 'tipo_atencion' => 'Consulta externa']);
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso' => $origen->getKey(), 'estado' => 'Dado de alta']);
        $this->assertDatabaseCount('tbl_alta_medica', 1);
        $this->assertDatabaseCount('tbl_alta_medicamento', 1);
        self::assertSame(1, $nuevo->evoluciones()->count());
        $this->get(route('atencion.show', $nuevo))->assertOk()->assertSee('Seguimiento')->assertSee('Medicamento anterior')->assertSee('Diagnóstico anterior de prueba');
        $this->get(route('pacientes.expediente', $origen->paciente))->assertOk()->assertSee('El paciente regresó por la consulta del');
    }

    public function test_impide_dos_atenciones_activas_y_oculta_accion(): void
    {
        $origen = $this->anterior();
        $this->withSession(['id_usuario' => 1])->post(route('seguimientos.guardar', $origen), $this->datos());
        $this->post(route('seguimientos.guardar', $origen), $this->datos())->assertSessionHasErrors();
        $this->get(route('seguimientos.crear', $origen))->assertRedirect();
        $this->get(route('pacientes.expediente', $origen->paciente))->assertDontSee('Nuevo seguimiento');
        $this->assertDatabaseCount('tbl_ingreso', 2);
    }

    public function test_rechaza_origen_abierto_cancelado_sin_alta_y_paciente_inactivo(): void
    {
        $origen = $this->anterior();
        $this->withSession(['id_usuario' => 1]);
        foreach (['Activo', 'Cancelado'] as $estado) {
            $origen->update(['estado' => $estado]);
            $this->post(route('seguimientos.guardar', $origen), $this->datos())->assertStatus(422);
        }
        $origen->update(['estado' => 'Dado de alta']);
        $origen->paciente->update(['estado' => 'Inactivo']);
        $this->post(route('seguimientos.guardar', $origen), $this->datos())->assertStatus(422);
        $origen->paciente->update(['estado' => 'Activo']);
        $origen->altaMedica()->delete();
        $this->post(route('seguimientos.guardar', $origen), $this->datos())->assertStatus(422);
        $this->assertDatabaseCount('tbl_ingreso', 1);
    }

    public function test_valida_fecha_motivo_y_no_admite_fechas_futuras_locales(): void
    {
        $origen = $this->anterior();
        $this->withSession(['id_usuario' => 1]);
        foreach (['2026-10-10T09:00', '2026-10-11T13:00', 'invalida'] as $fecha) {
            $this->post(route('seguimientos.guardar', $origen), $this->datos(['fecha_ingreso' => $fecha]))->assertSessionHasErrors('fecha_hora');
        }
        $this->post(route('seguimientos.guardar', $origen), $this->datos(['motivo_ingreso' => '']))->assertSessionHasErrors('motivo_atencion');
        $this->assertDatabaseCount('tbl_ingreso', 1);
    }

    public function test_no_permite_cambiar_paciente_origen_o_tipo_mediante_campos_ocultos(): void
    {
        $origen = $this->anterior();
        $otro = $this->anterior();
        $this->withSession(['id_usuario' => 1])->post(route('seguimientos.guardar', $origen), $this->datos(['id_paciente' => $otro->id_paciente, 'id_ingreso_anterior' => $otro->getKey(), 'tipo_atencion' => 'Hospitalización']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso_anterior' => $origen->getKey(), 'id_paciente' => $origen->id_paciente, 'tipo_atencion' => 'Consulta externa', 'estado' => 'Activo']);
    }

    public function test_seguimiento_puede_darse_de_alta_y_generar_otro_seguimiento(): void
    {
        $origen = $this->anterior();
        $this->withSession(['id_usuario' => 1])->post(route('seguimientos.guardar', $origen), $this->datos());
        $nuevo = Ingreso::where('id_ingreso_anterior', $origen->getKey())->sole();
        $this->post(route('emergencia.guardar', $nuevo), ['accion' => 'alta', 'fecha_hora' => '2026-10-11T09:30', 'motivo_atencion' => 'Control', 'diagnostico' => 'Evolución favorable'])->assertSessionHasNoErrors();
        $this->post(route('seguimientos.guardar', $nuevo), $this->datos(['fecha_ingreso' => '2026-10-11T12:00']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso_anterior' => $nuevo->getKey(), 'estado' => 'Activo']);
        $this->assertDatabaseCount('tbl_alta_medica', 2);
    }

    public function test_puede_internar_seguimiento_conservando_su_relacion(): void
    {
        $origen = $this->anterior();
        $this->withSession(['id_usuario' => 1])->post(route('seguimientos.guardar', $origen), $this->datos());
        $nuevo = Ingreso::where('id_ingreso_anterior', $origen->getKey())->sole();
        $this->post(route('hospitalizacion.guardar', $nuevo), ['id_habitacion' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso' => $nuevo->getKey(), 'id_ingreso_anterior' => $origen->getKey(), 'tipo_atencion' => 'Hospitalización']);
        $this->assertDatabaseCount('tbl_hospitalizacion', 1);
    }

    public function test_visitante_no_puede_crear_seguimiento(): void
    {
        $origen = $this->anterior();
        $this->get(route('seguimientos.crear', $origen))->assertRedirect('/');
        $this->post(route('seguimientos.guardar', $origen), $this->datos())->assertRedirect('/');
        $this->assertDatabaseCount('tbl_ingreso', 1);
    }

    public function test_migracion_agrega_relacion_sin_borrar_ingresos_y_es_idempotente(): void
    {
        $origen = $this->anterior();
        DB::getSchemaBuilder()->table('tbl_ingreso', fn ($table) => $table->dropColumn('id_ingreso_anterior'));
        $migracion = require database_path('migrations/2026_10_18_063958_add_ingreso_anterior_to_tbl_ingreso.php');
        $migracion->up();
        $migracion->up();
        $this->assertDatabaseHas('tbl_ingreso', ['id_ingreso' => $origen->getKey(), 'id_ingreso_anterior' => null]);
        self::assertNotEmpty(DB::getSchemaBuilder()->getForeignKeys('tbl_ingreso'));
    }

    public function test_editar_seguimiento_no_permite_adelantarlo_a_la_salida_anterior(): void
    {
        $origen = $this->anterior();
        $this->withSession(['id_usuario' => 1])->post(route('seguimientos.guardar', $origen), $this->datos());
        $nuevo = Ingreso::where('id_ingreso_anterior', $origen->getKey())->sole();
        $this->put(route('ingresos.actualizar', $nuevo), $this->datos(['fecha_ingreso' => '2026-10-10T09:00']))->assertSessionHasErrors('fecha_ingreso');
        self::assertSame('2026-10-11 09:00:00', $nuevo->fresh()->fecha_ingreso->format('Y-m-d H:i:s'));
    }

    public function test_historial_tiene_un_boton_sobre_ultima_visita_y_detalles_cerrados(): void
    {
        $anterior = $this->anterior();
        $ultima = $this->anterior(['id_paciente' => $anterior->id_paciente]);
        $response = $this->withSession(['id_usuario' => 1])->get(route('pacientes.expediente', $anterior->paciente))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $botones = $xpath->query('//*[@id="acciones-expediente"]//a[contains(., "Nuevo seguimiento")]');
        self::assertSame(1, $botones->length);
        self::assertSame(route('seguimientos.crear', $ultima), $botones->item(0)->getAttribute('href'));
        $detalles = $xpath->query('//*[@id="historial-evoluciones"]//details');
        self::assertSame(2, $detalles->length);
        foreach ($detalles as $detalle) {
            self::assertFalse($detalle->hasAttribute('open'));
            $resumen = $xpath->query('./summary', $detalle)->item(0);
            self::assertStringContainsString('Atendido el', $resumen->textContent);
            self::assertStringContainsString('Diagnóstico anterior de prueba', $resumen->textContent);
            self::assertStringNotContainsString('Documento de ingreso', $resumen->textContent);
        }
    }

    public function test_consulta_invalida_no_deja_un_seguimiento_vacio(): void
    {
        $origen = $this->anterior();
        $this->withSession(['id_usuario' => 1])->from(route('seguimientos.crear', $origen))
            ->post(route('seguimientos.guardar', $origen), $this->datos(['diagnostico' => '']))->assertRedirect(route('seguimientos.crear', $origen))->assertSessionHasErrors('diagnostico');
        $this->assertDatabaseCount('tbl_ingreso', 1);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
        $this->assertDatabaseCount('tbl_alta_medica', 1);
    }

    public function test_guarda_y_da_alta_en_un_solo_envio(): void
    {
        $origen = $this->anterior();
        $this->withSession(['id_usuario' => 1])->post(route('seguimientos.guardar', $origen), $this->datos(['accion' => 'alta', 'indicaciones_casa' => 'Control de prueba']))->assertSessionHasNoErrors();
        $nuevo = Ingreso::where('id_ingreso_anterior', $origen->getKey())->sole();
        self::assertSame('Dado de alta', $nuevo->estado);
        self::assertSame('Diagnóstico de la nueva visita', $nuevo->altaMedica->diagnostico_final);
        $this->assertDatabaseCount('tbl_alta_medica', 2);
        $this->assertDatabaseCount('tbl_hospitalizacion', 0);
    }

    public function test_historial_completo_es_del_mismo_paciente_y_botones_estan_en_orden(): void
    {
        $origen = $this->anterior();
        $otra = $this->anterior(['id_paciente' => $origen->id_paciente, 'fecha_ingreso' => '2026-10-09 08:00:00']);
        $ajena = $this->anterior();
        $ajena->altaMedica()->update(['diagnostico_final' => 'Diagnóstico ajeno que no debe mostrarse']);
        $response = $this->withSession(['id_usuario' => 1])->get(route('seguimientos.crear', $origen))->assertOk()
            ->assertSee('Ver consultas anteriores')->assertSee('Última consulta')->assertDontSee('Diagnóstico ajeno que no debe mostrarse');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        self::assertSame(2, $xpath->query('//*[@id="historial-consultas"]/details')->length);
        self::assertTrue($xpath->query('//*[@id="historial-consultas"]')->item(0)->hasAttribute('hidden'));
        self::assertSame(1, $xpath->query('//form[@id="form-consulta-emergencia"]')->length);
        $contenido = $response->getContent();
        self::assertLessThan(strpos($contenido, 'value="internar"'), strpos($contenido, 'data-pedir-alta'));
        self::assertSame('', $xpath->query('//*[@id="emergencia-diagnostico"]')->item(0)->textContent);
    }
}
