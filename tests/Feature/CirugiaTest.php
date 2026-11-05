<?php

namespace Tests\Feature;

use App\Http\Middleware\AvisarSinCambios;
use App\Models\AltaMedica;
use App\Models\Cirugia;
use App\Models\DocumentoClinico;
use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Models\NotaEnfermeria;
use App\Models\SignoVital;
use App\Services\DatosReportes;
use App\Services\OrdenesMedicas;
use App\Services\PdfReportes;
use App\Services\ReportesAmpliados;
use App\Support\EdicionClinica;
use Database\Factories\IngresoFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class CirugiaTest extends TestCase
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
        (require database_path('migrations/2026_10_26_010043_add_notas_to_tbl_atencion_enfermeria.php'))->up();
        (require database_path('migrations/2026_11_02_053000_add_medico_avisado_to_tbl_atencion_enfermeria.php'))->up();
        (require database_path('migrations/2026_11_03_155847_create_tbl_atencion_enfermeria_orden_table.php'))->up();
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(18, 0));
        DB::table('tbl_cargo')->insert([['id_cargo' => 1, 'nombre_cargo' => 'Médico general'], ['id_cargo' => 6, 'nombre_cargo' => 'Enfermera']]);
        DB::table('tbl_personal')->insert([
            ['id_personal' => 1, 'id_cargo' => 1, 'nombres' => 'Rosa', 'apellidos' => 'Médica', 'dpi' => '1234567890123'],
            ['id_personal' => 2, 'id_cargo' => 6, 'nombres' => 'Laura', 'apellidos' => 'Enfermera', 'dpi' => '1234567890124'],
        ]);
        DB::table('tbl_rol')->insert(['id_rol' => 1, 'nombre_rol' => 'Administrador']);
        DB::table('tbl_usuario')->insert(['id_usuario' => 1, 'id_personal' => 2, 'id_rol' => 1, 'nombre_usuario' => 'enfermera', 'contrasena' => 'solo-pruebas']);
        DB::table('tbl_habitacion')->insert([['id_habitacion' => 1, 'numero_habitacion' => '110'], ['id_habitacion' => 2, 'numero_habitacion' => '102']]);
    }

    private function internar(Ingreso $ingreso): void
    {
        $id = DB::table('tbl_hospitalizacion')->insertGetId(['id_ingreso' => $ingreso->getKey(), 'fecha_inicio' => '2026-10-10 12:00:00', 'estado' => 'Activa', 'id_usuario_creacion' => 1]);
        DB::table('tbl_hospitalizacion_habitacion')->insert(['id_hospitalizacion' => $id, 'id_habitacion' => 1, 'fecha_hora_inicio' => '2026-10-10 12:00:00', 'id_usuario_creacion' => 1]);
    }

    private function nota(array $cambios = []): array
    {
        return array_replace(['fecha_hora' => '2026-10-11 11:30:00', 'estado_paciente' => 'Sin cambios', 'frecuencia_cardiaca' => 80], $cambios);
    }

    private function datos(array $cambios = []): array
    {
        return array_replace(['modo' => 'agendar', 'tipo_operacion' => 'Cirugía de prueba', 'fecha_programada' => '2026-10-12 10:00:00', 'personal' => [1, 2]], $cambios);
    }

    public function test_consulta_permite_agendar_sin_valoracion_pero_realizada_requiere_internamiento(): void
    {
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA, 'fecha_ingreso' => '2026-10-11 10:00:00']);
        $this->withSession(['id_usuario' => 1]);
        $this->get(route('atencion.show', $ingreso))->assertOk()
            ->assertSee('Agendar cirugía')->assertDontSee('> Registrar cirugía ya realizada</a>', false)
            ->assertSee('data-borrador', false)->assertSee('visibilitychange');
        $this->get(route('cirugias.formulario', $ingreso))->assertOk();
        $this->post(route('cirugias.guardar', $ingreso), $this->datos())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
        $this->get(route('cirugias.formulario', [$ingreso, 'modo' => 'realizada']))->assertStatus(422);
        $datos = ['modo' => 'realizada', 'tipo_operacion' => 'Urgencia de prueba', 'fecha_inicio_real' => '2026-10-11T10:30', 'fecha_fin_real' => '2026-10-11T11:00', 'motivo_registro_posterior' => 'Prueba'];
        $this->post(route('cirugias.guardar', $ingreso), $datos)->assertSessionHasErrors('cirugia');
        $this->assertDatabaseCount('tbl_cirugia', 1);
        $this->internar($ingreso);
        $this->get(route('cirugias.formulario', [$ingreso, 'modo' => 'realizada']))->assertOk();
        $this->post(route('cirugias.guardar', $ingreso), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tbl_cirugia', 2);
    }

    public function test_agendar_desde_valoracion_abre_detalles_en_expediente_sin_borrar_borrador(): void
    {
        $this->withSession(['id_usuario' => 1]);
        foreach ([Ingreso::TIPO_CONSULTA_EXTERNA, Ingreso::TIPO_HOSPITALIZACION] as $tipo) {
            $ingreso = IngresoFactory::new()->create(['tipo_atencion' => $tipo]);
            $vista = $this->get(route('atencion.show', $ingreso))->assertOk();
            if ($ingreso->esConsultaExterna()) {
                $vista->assertSee('id="modalProgramarConsulta"', false);
            } else {
                $vista->assertSee('Agendar cirugía');
            }
            $this->get(route('cirugias.formulario', [$ingreso, 'volver_valoracion' => 1]))->assertOk()
                ->assertSee('name="volver_valoracion" value="1"', false);
            $this->post(route('cirugias.guardar', $ingreso), $this->datos(['volver_valoracion' => 1, 'personal' => []]))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('pacientes.expediente', $ingreso->id_paciente))
                ->assertSessionMissing('borrador_clinico_guardado')->assertSessionHas('abrir_cirugia', Cirugia::orderByDesc('id_cirugia')->first()->getKey());
            $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
                ->assertSee('cirugia-detalle-'.Cirugia::orderByDesc('id_cirugia')->first()->getKey(), false)
                ->assertSee('Corregir cirugía');
            if ($ingreso->esConsultaExterna()) {
                $this->get(route('atencion.show', $ingreso))->assertOk()
                    ->assertSee('Valoración inicial pendiente')->assertSee('Complete la primera evaluación')
                    ->assertDontSee('> Consulta externa</span>', false);
                $this->assertSame(Ingreso::TIPO_CONSULTA_EXTERNA, $ingreso->fresh()->tipo_atencion);
            }
            $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('sanatorio:borrador:', false);
        }
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
    }

    public function test_programada_conservada_tras_alta_es_visible_editable_y_cancelable(): void
    {
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA, 'estado' => 'Dado de alta']);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Regreso para cirugía', 'estado' => 'Programada', 'fecha_programada' => '2026-10-15 10:00:00', 'id_usuario_creacion' => 1]);
        $cirugia->equipo()->attach(1, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
        $this->travel(25)->hours();
        $this->withSession(['id_usuario' => 1]);
        $this->get(route('pacientes.index'))->assertOk()->assertSee('Cirugía programada')->assertSee('cirugia-detalle-'.$cirugia->getKey());
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('data-editar-cirugia', false);
        $this->put(route('cirugias.actualizar', $cirugia), ['tipo_operacion' => 'Cirugía corregida', 'observaciones' => 'Nueva observación', 'personal' => [1]])->assertSessionHasNoErrors();
        $this->assertSame('Cirugía corregida', $cirugia->fresh()->tipo_operacion);
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'reprogramar', 'fecha_hora' => '2026-10-16 11:00:00'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-16 11:00:00', $cirugia->fresh()->fecha_programada->format('Y-m-d H:i:s'));
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'cancelar', 'fecha_hora' => now('America/Guatemala')->format('Y-m-d H:i:s'), 'motivo_cancelacion' => 'Ya no se requiere'])->assertForbidden();
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('Cancelar cirugía');
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'cancelar', 'fecha_hora' => now('America/Guatemala')->format('Y-m-d H:i:s'), 'motivo_cancelacion' => 'Ya no se requiere'])->assertSessionHasNoErrors();
        $this->assertSame('Cancelada', $cirugia->fresh()->estado);
        $this->assertSame('Dado de alta', $ingreso->fresh()->estado);
        $this->get(route('pacientes.index'))->assertOk()->assertDontSee('cirugia-detalle-'.$cirugia->getKey());
    }

    public function test_cancelar_usa_hora_del_servidor_sin_pedir_fecha(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Programada', 'estado' => 'Programada', 'fecha_programada' => '2026-10-15 10:00:00', 'id_usuario_creacion' => 1]);
        $cirugia->equipo()->attach(2, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.estado', $cirugia), ['accion' => 'cancelar', 'motivo_cancelacion' => 'No se requiere'])->assertSessionHasNoErrors();
        $this->assertSame('Cancelada', $cirugia->fresh()->estado);
        $this->assertSame(now('UTC')->format('Y-m-d H:i:s'), $cirugia->fresh()->getRawOriginal('fecha_actualizacion'));
        $this->assertSame('2026-10-15 10:00:00', $cirugia->fresh()->fecha_programada->format('Y-m-d H:i:s'));
    }

    public function test_iniciar_cirugia_conservada_registra_hora_actual_y_sigue_visible(): void
    {
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA, 'estado' => 'Dado de alta']);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Cirugía conservada', 'estado' => 'Programada', 'fecha_programada' => '2026-10-15 10:00:00', 'id_usuario_creacion' => 1]);
        $cirugia->equipo()->attach(1, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.estado', $cirugia), ['accion' => 'iniciar'])->assertForbidden();
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('Iniciar cirugía');
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'iniciar', 'fecha_hora' => '2099-01-01 00:00:00'])->assertSessionHasNoErrors()->assertSessionMissing('abrir_cirugia');
        $this->assertSame('En cirugía', $cirugia->fresh()->estado);
        $this->assertSame(now('America/Guatemala')->format('Y-m-d H:i:s'), $cirugia->fresh()->fecha_inicio_real->format('Y-m-d H:i:s'));
        $this->get(route('pacientes.index'))->assertOk()->assertSee('En cirugía')->assertSee('cirugia-detalle-'.$cirugia->getKey());
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('Finalizar cirugía');
        $this->travel(20)->minutes();
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'terminar'])->assertSessionHasNoErrors();
        $this->assertSame('Realizada', $cirugia->fresh()->estado);
        $this->assertSame(now('America/Guatemala')->format('Y-m-d H:i:s'), $cirugia->fresh()->fecha_fin_real->format('Y-m-d H:i:s'));
        $this->assertSame('Dado de alta', $ingreso->fresh()->estado);
    }

    public function test_cirugia_sin_internamiento_bloquea_seguimiento_y_hospitalizacion_hasta_finalizar(): void
    {
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA, 'estado' => 'Dado de alta']);
        AltaMedica::create(['id_ingreso' => $ingreso->getKey(), 'id_personal_medico' => 1, 'fecha_hora' => '2026-10-11 10:00:00', 'diagnostico_final' => 'Prueba', 'condicion_egreso' => 'Estable', 'id_usuario_creacion' => 1]);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Operación sin internamiento', 'estado' => 'En cirugía', 'fecha_inicio_real' => '2026-10-11 11:00:00', 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1]);
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('En cirugía · Operación sin internamiento')
            ->assertSee('Paciente en cirugía.')->assertDontSee('<strong>Nuevo seguimiento</strong>', false)->assertDontSee('<strong>Hospitalizar paciente</strong>', false);
        $this->get(route('pacientes.index'))->assertOk()->assertSee('Paciente en cirugía. Espere a que finalice.')->assertDontSee('> Iniciar consulta</button>', false);
        $this->get(route('seguimientos.crear', $ingreso))->assertSessionHasErrors('cirugia');
        $this->post('/ingresos', ['id_paciente' => $ingreso->id_paciente, 'tipo_atencion' => Ingreso::TIPO_HOSPITALIZACION, 'fecha_ingreso' => '2026-10-11 12:00:00', 'motivo_ingreso' => 'Prueba'])->assertSessionHasErrors('cirugia');
        $this->assertSame(1, Ingreso::where('id_paciente', $ingreso->id_paciente)->count());
        $cirugia->update(['estado' => 'Realizada', 'fecha_fin_real' => '2026-10-11 12:00:00']);
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('<strong>Nuevo seguimiento</strong>', false)->assertSee('<strong>Hospitalizar paciente</strong>', false);
        $this->get(route('seguimientos.crear', $ingreso))->assertOk();
    }

    public function test_notas_nuevas_se_bloquean_durante_cirugia_y_se_habilitan_al_finalizar(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->internar($ingreso);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Operación de prueba', 'estado' => 'En cirugía', 'fecha_inicio_real' => '2026-10-11 11:00:00', 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1])->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('Paciente en cirugía. Podrá registrar una nueva nota cuando finalice.')->assertDontSee('> Nueva nota</button>', false);
        $this->post(route('notas-enfermeria.guardar', $ingreso), $this->nota())->assertSessionHasErrors('cirugia');
        $this->assertDatabaseCount('tbl_atencion_enfermeria', 0);
        $cirugia->update(['estado' => 'Realizada', 'fecha_fin_real' => '2026-10-11 11:20:00']);
        $this->get(route('atencion.enfermeria', $ingreso))->assertOk()->assertSee('> Nueva nota</button>', false);
        $this->post(route('notas-enfermeria.guardar', $ingreso), $this->nota())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tbl_atencion_enfermeria', 1);
    }

    public function test_finalizar_cierra_agrega_personal_y_recuperacion_sin_internamiento(): void
    {
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA, 'estado' => 'Dado de alta']);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Ambulatoria', 'estado' => 'En cirugía', 'fecha_inicio_real' => '2026-10-11 11:00:00', 'id_usuario_creacion' => 1]);
        $cirugia->equipo()->attach(1, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
        $this->withSession(['id_usuario' => 1]);
        $this->post(route('cirugias.recuperacion', $cirugia), ['ordenes' => ['dieta' => 'Líquida']])->assertForbidden();
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('Editar el personal que participó');
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'terminar', 'personal_final' => [2], 'internar_al_finalizar' => 1, 'habitacion_recuperacion' => 1])->assertSessionHasNoErrors()->assertSessionMissing('abrir_cirugia');
        $this->assertNotSame($ingreso->getKey(), $cirugia->fresh()->id_ingreso);
        $this->assertSame('Dado de alta', $ingreso->fresh()->estado);
        $this->assertSame('Activa', $cirugia->fresh()->ingreso->hospitalizacion->estado);
        $this->assertCount(2, $cirugia->fresh()->equipo);
        $this->assertSame('Participó', $cirugia->fresh()->equipo->firstWhere('id_personal', 2)->pivot->estado);
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('Agregar orden posoperatoria')->assertSee('data-form-recuperacion', false)->assertDontSee('Guardar orden de recuperación');
        $this->post(route('cirugias.recuperacion', $cirugia), ['ordenes' => ['dieta' => 'Líquida', 'indicaciones' => 'PRUEBA de recuperación']])->assertSessionHasNoErrors();
        $orden = $cirugia->fresh()->ordenRecuperacion();
        $this->assertNotNull($orden);
        $this->assertSame(1, $orden->id_personal);
        $this->post(route('cirugias.recuperacion', $cirugia), ['ordenes' => ['dieta' => 'Líquida']])->assertSessionHasErrors('ordenes');
        $this->post(route('cirugias.recuperacion', $cirugia), ['editar_recuperacion' => 1, 'ordenes' => ['dieta' => 'Blanda']])->assertSessionHasNoErrors();
        $this->assertSame('Blanda', $orden->fresh()->ordenes_medicas['dieta']);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 1);
        $this->travel(13)->hours();
        $this->post(route('cirugias.recuperacion', $cirugia), ['editar_recuperacion' => 1, 'ordenes' => ['dieta' => 'Líquida']])->assertForbidden();
        $this->postJson(route('evolucion.cancelar-ordenes', $orden), ['cirugia_posoperatoria' => $cirugia->getKey()])->assertOk();
        $this->assertNotNull(OrdenesMedicas::cancelacion($orden->fresh()->ordenes_medicas));
    }

    public function test_finalizar_sin_internar_no_permite_orden_de_recuperacion(): void
    {
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA, 'estado' => 'Dado de alta']);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Ambulatoria sin internar', 'estado' => 'En cirugía', 'fecha_inicio_real' => '2026-10-11 11:00:00', 'id_usuario_creacion' => 1]);
        $cirugia->equipo()->attach(1, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->withSession(['id_usuario' => 1]);
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertSee('Internar después de la cirugía');
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'terminar'])->assertSessionHasNoErrors()->assertSessionMissing('abrir_cirugia');
        $this->assertSame('Realizada', $cirugia->fresh()->estado);
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()->assertDontSee('Agregar orden posoperatoria')->assertSee('Las órdenes de recuperación se habilitan cuando el paciente queda internado.');
        $this->post(route('cirugias.recuperacion', $cirugia), ['ordenes' => ['dieta' => 'Líquida']])->assertForbidden();
        $this->assertDatabaseCount('tbl_hospitalizacion', 0);
    }

    public function test_internamiento_al_final_exige_habitacion_disponible_y_no_finaliza_si_falla(): void
    {
        $ocupante = IngresoFactory::new()->create();
        $this->internar($ocupante);
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA, 'estado' => 'Dado de alta']);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Para recuperación', 'estado' => 'En cirugía', 'fecha_inicio_real' => '2026-10-11 11:00:00', 'id_usuario_creacion' => 1]);
        $cirugia->equipo()->attach(1, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->withSession(['id_usuario' => 1]);
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'terminar', 'internar_al_finalizar' => 1])->assertSessionHasErrors('habitacion_recuperacion');
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'terminar', 'internar_al_finalizar' => 1, 'habitacion_recuperacion' => 1])->assertSessionHasErrors('habitacion_recuperacion');
        $this->assertSame('En cirugía', $cirugia->fresh()->estado);
        $this->assertNull($cirugia->fresh()->fecha_fin_real);
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'terminar', 'internar_al_finalizar' => 1, 'habitacion_recuperacion' => 2])->assertSessionHasNoErrors();
        $this->assertSame('Realizada', $cirugia->fresh()->estado);
        $this->assertSame(2, $cirugia->fresh()->ingreso->hospitalizacion->habitacionActual->id_habitacion);
        $this->assertSame('Dado de alta', $ingreso->fresh()->estado);
    }

    public function test_programacion_equipo_y_vistas(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $ingreso), $this->datos())->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $this->assertCount(2, $c->equipo);
        $this->assertNull($c->id_personal_indica);
        $this->get(route('cirugias.show', $c))->assertRedirect(route('atencion.show', $ingreso))->assertSessionHas('abrir_cirugia', $c->getKey());
        $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('id="cirugia-detalle-'.$c->getKey().'"', false)->assertSee('Laura Enfermera')->assertSee('Controles de recuperación');
        $this->get(route('cirugias.formulario', $ingreso))->assertOk()->assertSee('Personal participante');
        $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('Cirugía de prueba');
        $this->get(route('pacientes.expediente', $ingreso->paciente))->assertOk()->assertSee('Cirugía de prueba');
    }

    public function test_indicar_cirugia_no_esta_disponible_ni_por_peticion_directa(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->get(route('atencion.show', $ingreso))->assertOk()->assertDontSee('Indicar cirugía');
        $this->get(route('cirugias.formulario', [$ingreso, 'modo' => 'indicar']))->assertForbidden();
        $this->post(route('cirugias.guardar', $ingreso), ['modo' => 'indicar', 'tipo_operacion' => 'Prueba'])->assertForbidden();
        $this->assertDatabaseCount('tbl_cirugia', 0);
    }

    public function test_rechaza_horarios_personal_duplicado_y_solapamientos(): void
    {
        $i = IngresoFactory::new()->create();
        $ruta = route('cirugias.guardar', $i);
        $this->withSession(['id_usuario' => 1]);
        $this->post($ruta, $this->datos(['fecha_programada' => '']))->assertSessionHasErrors(['fecha_programada' => 'Indique la fecha y hora de la cirugía.']);
        $this->post($ruta, $this->datos(['personal' => [1, 1]]))->assertSessionHasErrors('personal.0');
        $this->post($ruta, $this->datos(['fecha_programada' => '2026-10-10 10:00:00']))->assertSessionHasErrors('fecha_programada');
        $this->assertDatabaseCount('tbl_cirugia', 0);
        $this->post($ruta, $this->datos())->assertSessionHasNoErrors();
        $this->post($ruta, $this->datos())->assertSessionHasErrors('fecha_programada');
        $otro = IngresoFactory::new()->create();
        $this->post(route('cirugias.guardar', $otro), $this->datos())->assertSessionHasErrors('fecha_programada');
        $this->assertDatabaseCount('tbl_cirugia', 1);
    }

    public function test_foto_de_cirugia_se_sube_desde_su_ventana_sin_cambiar_de_pantalla(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $this->internar($i);
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Traqueotomía', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 09:00:00', 'fecha_fin_real' => '2026-10-11 10:15:00', 'id_usuario_creacion' => 1]);
        $atencion = route('atencion.show', $i);
        $clave = 'adjunto-cirugia-'.$c->getKey();

        // La ventana de la cirugía trae su propio campo para subir, con la fecha en que terminó la cirugía.
        $this->withSession(['id_usuario' => 1])->get($atencion)->assertOk()
            ->assertSee('Subir una foto o PDF')->assertSee('value="'.$clave.'"', false)->assertSee('name="fecha_documento" value="2026-10-11 10:15:00"', false);

        // Sin archivo: vuelve a la misma pantalla, con el error y la ventana abierta.
        $datos = ['_formulario' => $clave, 'en_linea' => '1', 'tipo' => 'Cirugía', 'referencia' => $c->getKey(), 'fecha_documento' => '2026-10-11 10:15:00'];
        $this->from($atencion)->post(route('archivos.guardar', $i), $datos)->assertRedirect($atencion)
            ->assertSessionHasErrors('archivo')->assertSessionHas('abrir_cirugia', $c->getKey());
        $this->assertDatabaseCount('tbl_documento_clinico', 0);

        // Con la foto: se guarda vinculada a la cirugía y se regresa a la misma pantalla.
        $this->from($atencion)->post(route('archivos.guardar', $i), $datos + ['archivo' => UploadedFile::fake()->image('cirugia.jpg')])
            ->assertSessionHasNoErrors()->assertRedirect($atencion)
            ->assertSessionHas('abrir_cirugia', $c->getKey())->assertSessionHas('adjunto_subido', $clave);
        $documento = DocumentoClinico::sole();
        $this->assertSame(['tbl_cirugia', $c->getKey(), '2026-10-11 10:15:00'], [$documento->tabla_origen, (int) $documento->id_registro_origen, $documento->fecha_documento->format('Y-m-d H:i:s')]);
        $this->get($atencion)->assertSee('Archivo agregado.')->assertSee('Agregar otra foto o PDF')
            // «Ver archivo» abre directamente la imagen o el PDF, en otra pestaña.
            ->assertSee('href="'.route('archivos.contenido', $documento).'" target="_blank"', false)->assertDontSee('href="'.route('archivos.show', $documento).'"', false);

        // En el expediente la cirugía no va en un recuadro aparte: aparece en la lista de documentos, en el día en que se operó.
        $otra = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Curación quirúrgica', 'estado' => 'Programada', 'fecha_programada' => '2026-10-12 08:00:00', 'id_usuario_creacion' => 1]);
        $this->get(route('pacientes.expediente', $i->paciente))->assertOk()
            ->assertDontSee('Cirugías y recuperación')
            ->assertSeeInOrder(['12 de octubre de 2026', 'Cirugía: Curación quirúrgica', 'Programada', '11 de octubre de 2026', 'Cirugía: Traqueotomía', 'Realizada'])
            ->assertSee('data-bs-target="#cirugia-detalle-'.$c->getKey().'"', false)->assertSee('id="cirugia-detalle-'.$c->getKey().'"', false)
            ->assertSee('id="cirugia-detalle-'.$otra->getKey().'"', false)
            // La foto es parte de la cirugía: no aparece como documento aparte, la cirugía indica que tiene un archivo.
            ->assertSee('Cirugías (1)')->assertDontSee('Cirugías (2)')->assertSee('1 archivo');

        // En los PDF la foto va dentro del mismo documento de la cirugía, al final.
        $paginas = fn (string $bytes) => (new Fpdi)->setSourceFile(StreamReader::createByString($bytes));
        $pdf = $this->get(route('reportes.documento', ['cirugias', $c->getKey()]))->assertOk()->getContent();
        $soloCirugia = app(PdfReportes::class)->generar('Cirugía', [], [], app(DatosReportes::class)->documento('cirugias', app(DatosReportes::class)->consulta('cirugias')->findOrFail($c->getKey())));
        $foto = $this->get(route('reportes.archivo', $documento))->assertOk()->getContent();
        $this->assertSame(1, $paginas($foto), 'La foto y sus datos caben en una página.');
        $this->assertSame($paginas($soloCirugia) + 1, $paginas($pdf));
        $documentos = app(ReportesAmpliados::class)->documentos($i->paciente, []);
        $this->assertSame([], $documentos->where('tipo', 'archivo')->values()->all());
        $this->assertSame([$documento->getKey()], collect($documentos->firstWhere('tipo', 'cirugias')['adjuntos'])->map->getKey()->all());
        $zip = $this->get(route('reportes.expediente.zip', $i->paciente))->assertOk()->getContent();
        $this->assertStringNotContainsString(' Cirugia.pdf', $zip);
    }

    public function test_controles_de_recuperacion_solo_para_la_cirugia_mas_reciente(): void
    {
        $i = IngresoFactory::new()->create();
        $this->internar($i);
        $anterior = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Cirugía anterior', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 07:00:00', 'fecha_fin_real' => '2026-10-11 08:00:00', 'id_usuario_creacion' => 1]);
        $reciente = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Cirugía reciente', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 10:00:00', 'fecha_fin_real' => '2026-10-11 11:00:00', 'id_usuario_creacion' => 1]);
        Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Cirugía programada', 'estado' => 'Programada', 'fecha_programada' => '2026-10-12 08:00:00', 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1]);

        // En la hoja de enfermería solo se ofrece la más reciente; no hay lista para elegir.
        $this->get(route('atencion.enfermeria', $i))->assertOk()
            ->assertSee('Cirugía reciente · terminó el 11/10/2026 11:00')->assertDontSee('Cirugía anterior · terminó')
            ->assertDontSee('value="'.$reciente->getKey().'" checked', false);
        // Desde la cirugía anterior no se abre marcada.
        $this->get(route('atencion.enfermeria', [$i, 'control_cirugia' => $anterior->getKey()]))->assertDontSee('value="'.$reciente->getKey().'" checked', false);

        $ruta = route('notas-enfermeria.guardar', $i);
        $this->post($ruta, $this->nota(['id_cirugia' => $anterior->getKey()]))->assertSessionHasErrors(['id_cirugia' => 'Los controles de recuperación se registran solo para la cirugía terminada más reciente de este paciente.']);
        $this->assertDatabaseCount('tbl_signos_vitales', 0);
        $this->post($ruta, $this->nota(['id_cirugia' => $reciente->getKey()]))->assertSessionHasNoErrors();
        $this->assertSame($reciente->getKey(), SignoVital::sole()->id_cirugia);

        // El botón «Registrar control» aparece solo en la ventana de la cirugía más reciente.
        $html = $this->get(route('atencion.show', $i))->assertOk()->getContent();
        $this->assertStringContainsString('control_cirugia='.$reciente->getKey(), $html);
        $this->assertStringNotContainsString('control_cirugia='.$anterior->getKey(), $html);
    }

    public function test_transiciones_y_control_recuperacion_desde_nota_de_enfermeria(): void
    {
        $i = IngresoFactory::new()->create();
        $this->internar($i);
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $i), $this->datos())->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $ruta = route('notas-enfermeria.guardar', $i);
        $this->post($ruta, $this->nota(['id_cirugia' => $c->getKey()]))->assertSessionHasErrors('id_cirugia');
        $this->post(route('cirugias.estado', $c), ['accion' => 'iniciar', 'fecha_hora' => '2026-10-11 10:00:00'])->assertSessionHasNoErrors();
        $this->get('/pacientes')->assertSee('En cirugía');
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->post(route('cirugias.estado', $c), ['accion' => 'terminar', 'fecha_hora' => '2026-10-11T09:00'])->assertSessionHasErrors('fecha_hora');
        $this->post(route('cirugias.estado', $c), ['accion' => 'terminar', 'fecha_hora' => '2026-10-11 11:00:00'])->assertSessionHasNoErrors()->assertSessionHas('abrir_cirugia', $c->getKey());
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 2]);
        $this->get(route('atencion.enfermeria', [$i, 'control_cirugia' => $c->getKey()]))->assertOk()->assertSee('Es un control de recuperación de la cirugía')->assertSee('Cirugía de prueba · terminó el 11/10/2026 11:00')->assertSee('value="'.$c->getKey().'" checked', false);
        $this->post($ruta, $this->nota(['id_cirugia' => $c->getKey(), 'fecha_hora' => '2026-10-11T10:30']))->assertSessionHasErrors('fecha_hora');
        $this->post($ruta, $this->nota(['id_cirugia' => $c->getKey(), 'frecuencia_cardiaca' => null, 'observaciones' => 'Sin signos']))->assertSessionHasErrors('id_cirugia');
        $otro = IngresoFactory::new()->create();
        $ajena = Cirugia::create(['id_ingreso' => $otro->getKey(), 'tipo_operacion' => 'Ajena', 'estado' => 'Realizada', 'fecha_fin_real' => '2026-10-11 09:00:00', 'id_usuario_creacion' => 1]);
        $this->post($ruta, $this->nota(['id_cirugia' => $ajena->getKey()]))->assertSessionHasErrors('id_cirugia');
        $this->assertDatabaseCount('tbl_signos_vitales', 0);
        $this->assertDatabaseCount('tbl_atencion_enfermeria', 0);

        $this->post($ruta, $this->nota(['id_cirugia' => $c->getKey(), 'observaciones' => 'Despierto y sin dolor']))->assertSessionHasNoErrors();
        $signo = SignoVital::sole();
        $this->assertSame('Recuperación', $signo->tipo_control);
        $this->assertSame($c->getKey(), $signo->id_cirugia);
        $this->assertSame($i->getKey(), $signo->id_ingreso);
        $this->assertSame($signo->getKey(), NotaEnfermeria::sole()->id_signos_vitales);
        $this->post($ruta, $this->nota(['fecha_hora' => '2026-10-11 11:45:00']))->assertSessionHasNoErrors();
        $this->assertSame('Rutina', SignoVital::orderByDesc('id_signos_vitales')->first()->tipo_control);
        $this->assertNull(SignoVital::orderByDesc('id_signos_vitales')->first()->id_cirugia);

        $this->get(route('atencion.show', $i))->assertOk()->assertSee('Controles de recuperación (1)')->assertSee('Despierto y sin dolor');
        $this->get(route('atencion.enfermeria', $i))->assertOk()->assertSee('Control de recuperación · Cirugía de prueba');
        $this->get(route('pacientes.expediente', $i->paciente))->assertOk()->assertSee('Control de recuperación · Cirugía de prueba');
    }

    public function test_controles_antiguos_sin_nota_salen_en_el_expediente(): void
    {
        $i = IngresoFactory::new()->create();
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Apendicectomía', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 08:00:00', 'fecha_fin_real' => '2026-10-11 09:00:00', 'id_usuario_creacion' => 1]);
        $control = SignoVital::create(['id_ingreso' => $i->getKey(), 'id_cirugia' => $c->getKey(), 'id_personal' => 2, 'tipo_control' => 'Recuperación', 'fecha_hora' => '2026-10-11 10:00:00', 'frecuencia_cardiaca' => 92, 'observaciones' => 'Control antiguo', 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1])->get(route('pacientes.expediente', $i->paciente))->assertOk()
            ->assertSee('id="documento-control-'.$control->getKey().'"', false)->assertSee('Control antiguo')->assertSee('Apendicectomía · terminó el 11/10/2026 09:00');
    }

    public function test_quien_registro_la_cirugia_puede_corregirla_durante_12_horas(): void
    {
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $i), ['modo' => 'realizada', 'tipo_operacion' => 'Apendicetomía', 'fecha_inicio_real' => '2026-10-11T08:00', 'fecha_fin_real' => '2026-10-11T09:00', 'motivo_registro_posterior' => 'Urgencia', 'personal' => [2]])->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $this->get(route('atencion.show', $i))->assertOk()->assertSee('Corregir cirugía')->assertSee('Confirmar cambios');
        $datos = ['_formulario' => 'cirugia-'.$c->getKey(), 'tipo_operacion' => 'Apendicectomía laparoscópica', 'fecha_inicio_real' => '2026-10-11T08:10', 'fecha_fin_real' => '2026-10-11T09:20', 'motivo_registro_posterior' => 'Urgencia quirúrgica', 'personal' => [1, 2], 'observaciones' => 'Sin complicaciones'];
        $this->put(route('cirugias.actualizar', $c), array_replace($datos, ['fecha_fin_real' => '2026-10-11T08:00']))->assertSessionHasErrors('fecha_fin_real');
        $this->put(route('cirugias.actualizar', $c), array_replace($datos, ['tipo_operacion' => '']))->assertSessionHasErrors('tipo_operacion');
        $this->put(route('cirugias.actualizar', $c), $datos)->assertSessionHasNoErrors()->assertSessionHas('abrir_cirugia', $c->getKey());
        $this->put(route('cirugias.actualizar', $c), $datos)->assertSessionHasNoErrors()->assertSessionHas('exito', AvisarSinCambios::MENSAJE);
        $c->refresh();
        $this->assertSame('Apendicectomía laparoscópica', $c->tipo_operacion);
        $this->assertSame('2026-10-11 09:20:00', $c->fecha_fin_real->format('Y-m-d H:i:s'));
        $this->assertSame('Urgencia quirúrgica', $c->motivo_registro_posterior);
        $this->assertSame([1, 2], $c->equipo()->orderBy('tbl_personal.id_personal')->pluck('tbl_personal.id_personal')->all());
        $this->assertSame('Participó', $c->equipo->first()->pivot->estado);

        SignoVital::create(['id_ingreso' => $i->getKey(), 'id_cirugia' => $c->getKey(), 'id_personal' => 2, 'tipo_control' => 'Recuperación', 'fecha_hora' => '2026-10-11 09:30:00', 'frecuencia_cardiaca' => 90, 'id_usuario_creacion' => 1]);
        $this->put(route('cirugias.actualizar', $c), array_replace($datos, ['fecha_fin_real' => '2026-10-11T09:45']))->assertSessionHasErrors('fecha_fin_real');

        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 1, 'id_rol' => 1, 'nombre_usuario' => 'otra', 'contrasena' => 'solo-pruebas']);
        $this->withSession(['id_usuario' => 2])->put(route('cirugias.actualizar', $c), $datos)->assertSessionHasErrors('cirugia');
        $this->get(route('atencion.show', $i))->assertOk()->assertDontSee('Corregir cirugía');

        $this->travel(13)->hours();
        $this->withSession(['id_usuario' => 1])->put(route('cirugias.actualizar', $c), array_replace($datos, ['tipo_operacion' => 'Tarde']))->assertSessionHasErrors('cirugia');
        $this->assertSame('Apendicectomía laparoscópica', $c->fresh()->tipo_operacion);
        $this->get(route('atencion.show', $i))->assertOk()->assertDontSee('Corregir cirugía');
    }

    public function test_permisos_y_atencion_cerrada(): void
    {
        $i = IngresoFactory::new()->create();
        $this->get(route('cirugias.formulario', $i))->assertRedirect('/');
        DB::table('tbl_rol')->where('id_rol', 1)->update(['nombre_rol' => 'Enfermería']);
        $this->asignarPermisosDeRol(1, 'Enfermería');
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $i), ['modo' => 'indicar', 'tipo_operacion' => 'Prueba'])->assertForbidden();
        $this->post(route('cirugias.guardar', $i), $this->datos())->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $i->update(['estado' => 'Dado de alta']);
        $this->post(route('cirugias.estado', $c), ['accion' => 'iniciar', 'fecha_hora' => '2026-10-11 11:00:00'])->assertSessionHasErrors('cirugia');
        $this->get(route('cirugias.show', $c))->assertRedirect(route('pacientes.expediente', $i->id_paciente));
        $this->get(route('pacientes.expediente', $i->id_paciente))->assertOk()->assertSee('Cirugía de prueba')->assertDontSee('Guardar estado')->assertDontSee('Corregir cirugía');
    }

    public function test_no_permite_reprogramar_cirugia_de_otro_ingreso(): void
    {
        $i = IngresoFactory::new()->create();
        $otro = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $i), $this->datos())->assertSessionHasNoErrors();
        $this->post(route('cirugias.guardar', $otro), $this->datos(['cirugia' => Cirugia::sole()->getKey()]))->assertNotFound();
    }

    public function test_cancelacion_requiere_motivo_y_no_admite_iniciar_despues(): void
    {
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $i), $this->datos())->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $this->post(route('cirugias.estado', $c), ['accion' => 'cancelar', 'fecha_hora' => '2026-10-11 11:00:00'])->assertSessionHasErrors('motivo_cancelacion');
        $this->post(route('cirugias.estado', $c), ['accion' => 'cancelar', 'fecha_hora' => '2026-10-11 11:00:00', 'motivo_cancelacion' => 'Prueba'])->assertSessionHasNoErrors();
        $this->post(route('cirugias.estado', $c), ['accion' => 'iniciar', 'fecha_hora' => '2026-10-11 11:00:00'])->assertSessionHasErrors('accion');
    }

    public function test_medico_solo_consulta_no_agenda_ni_corrige(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $ingreso), $this->datos())->assertSessionHasNoErrors();
        DB::table('tbl_rol')->where('id_rol', 1)->update(['nombre_rol' => 'Médico']);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->asignarPermisosDeRol(1, 'Médico');
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $ingreso), $this->datos())->assertSessionHas('permiso_denegado');
        $this->post(route('cirugias.guardar', $ingreso), ['modo' => 'indicar', 'tipo_operacion' => 'Indicación de prueba'])->assertSessionHas('permiso_denegado');
        $this->put(route('cirugias.actualizar', Cirugia::sole()), ['tipo_operacion' => 'Cambio'])->assertSessionHas('permiso_denegado');
        $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('Cirugía de prueba')->assertDontSee('Guardar estado')->assertDontSee('Corregir cirugía');
        $this->assertDatabaseCount('tbl_cirugia', 1);
    }

    public function test_migracion_repetida_conserva_los_registros(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $ingreso), $this->datos())->assertSessionHasNoErrors();
        $migracion = require database_path('migrations/2026_10_28_172129_ampliar_cirugias_y_recuperacion.php');
        $migracion->up();
        $migracion->up();
        $this->assertDatabaseCount('tbl_cirugia', 1);
        $this->assertCount(2, Cirugia::sole()->equipo);
    }

    public function test_registro_posterior_completo_identificado_y_no_identificado(): void
    {
        $this->withSession(['id_usuario' => 1]);
        foreach (['Identificado', 'No identificado'] as $identificacion) {
            $datos = ['estado_identificacion' => $identificacion, 'hospitalizar' => 1, 'fecha_ingreso' => '2026-10-11T10:00', 'motivo_ingreso' => 'Atención quirúrgica inmediata'];
            if ($identificacion === 'Identificado') {
                $datos += ['nombres' => 'Paciente', 'apellidos' => 'Prueba'];
            }
            $this->withSession(['id_usuario' => 1])->post('/pacientes', $datos)->assertSessionHasNoErrors()->assertRedirectContains('/atencion/');
            $ingreso = Ingreso::orderByDesc('id_ingreso')->firstOrFail();
            $this->assertNull($ingreso->paciente->telefono);
            if ($identificacion === 'No identificado') {
                $this->assertNotEmpty($ingreso->paciente->codigo_temporal);
            }
            $this->assertNotNull($ingreso->hospitalizacion);
            $this->assertNull($ingreso->hospitalizacion->habitacionActual);
            $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('Asignar habitación')->assertSee('Registrar cirugía ya realizada');
            $this->get(route('atencion.enfermeria', $ingreso))->assertOk();
            $this->get(route('cirugias.formulario', [$ingreso, 'modo' => 'realizada']))->assertOk()->assertSee('¿Por qué se registró después?');
            $cirugia = ['modo' => 'realizada', 'tipo_operacion' => 'Cirugía urgente de prueba', 'fecha_inicio_real' => '2026-10-11T10:30', 'fecha_fin_real' => '2026-10-11T11:00', 'motivo_registro_posterior' => 'Se priorizó la atención inmediata.', 'personal' => [1, 2]];
            $this->post(route('cirugias.guardar', $ingreso), $cirugia)->assertSessionHasNoErrors();
            $c = Cirugia::where('id_ingreso', $ingreso->getKey())->sole();
            $this->assertSame('Realizada', $c->estado);
            $this->assertNull($c->fecha_programada);
            $this->assertSame('2026-10-11 10:30:00', $c->fecha_inicio_real->format('Y-m-d H:i:s'));
            $this->assertSame('Participó', $c->equipo->first()->pivot->estado);
            $this->get(route('atencion.show', $ingreso))->assertOk()->assertSee('Registrada después de operar')->assertSee('Registrar control en nota de enfermería');
            $this->get(route('pacientes.expediente', $ingreso->paciente))->assertOk()->assertSee('Cirugía urgente de prueba');
            $this->post(route('notas-enfermeria.guardar', $ingreso), $this->nota(['id_cirugia' => $c->getKey()]))->assertSessionHasNoErrors();
            $this->assertSame($c->getKey(), SignoVital::orderByDesc('id_signos_vitales')->first()->id_cirugia);
            $this->post(route('cirugias.guardar', $ingreso), $cirugia)->assertSessionHasErrors('tipo_operacion');
        }
    }

    public function test_cirugia_realizada_rechaza_fechas_y_motivo_invalidos(): void
    {
        $ingreso = IngresoFactory::new()->create(['fecha_ingreso' => '2026-10-11 10:00:00']);
        $datos = ['modo' => 'realizada', 'tipo_operacion' => 'Prueba', 'fecha_inicio_real' => '2026-10-11T10:30', 'fecha_fin_real' => '2026-10-11T11:00', 'motivo_registro_posterior' => 'Atención inmediata'];
        $this->withSession(['id_usuario' => 1]);
        foreach ([['motivo_registro_posterior' => ''], ['fecha_inicio_real' => '2026-10-11T09:30'], ['fecha_fin_real' => '2026-10-11T10:00'], ['fecha_fin_real' => '2026-10-12T11:00']] as $cambios) {
            $this->post(route('cirugias.guardar', $ingreso), array_replace($datos, $cambios))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('tbl_cirugia', 0);
    }

    public function test_habitacion_pendiente_puede_asignarse_despues(): void
    {
        $this->withSession(['id_usuario' => 1])->post('/pacientes', ['estado_identificacion' => 'No identificado', 'hospitalizar' => 1, 'fecha_ingreso' => '2026-10-11T10:00'])->assertSessionHasNoErrors();
        $ingreso = Ingreso::sole();
        $this->patch(route('hospitalizacion.cambiar-habitacion', $ingreso->hospitalizacion), ['id_habitacion' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_hospitalizacion_habitacion', ['id_hospitalizacion' => $ingreso->hospitalizacion->getKey(), 'id_habitacion' => 1]);
    }

    public function test_alta_requiere_confirmar_y_cancela_solo_cirugias_pendientes(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $pendiente = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Pendiente de prueba', 'estado' => 'Programada', 'fecha_programada' => '2026-10-12 10:00:00', 'id_usuario_creacion' => 1]);
        $pendiente->equipo()->attach(2, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
        $realizada = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Anterior', 'estado' => 'Realizada', 'id_usuario_creacion' => 1]);
        $datos = ['id_personal_medico' => 1, 'fecha_hora' => now('America/Guatemala')->format('Y-m-d H:i:s'), 'diagnostico_final' => 'Prueba', 'condicion_egreso' => 'Prueba'];
        $this->withSession(['id_usuario' => 1]);
        $this->view('atencion.partials.aviso-cirugias-alta', ['ingreso' => $ingreso, 'errors' => new ViewErrorBag])->assertSee('Confirmo dar de alta y cancelar las cirugías pendientes.');
        $this->post(route('alta.guardar', $ingreso), $datos)->assertSessionHasErrors('cancelar_cirugias');
        self::assertSame('Activo', $ingreso->fresh()->estado);
        self::assertSame('Programada', $pendiente->fresh()->estado);
        $this->assertDatabaseCount('tbl_alta_medica', 0);
        $this->post(route('alta.guardar', $ingreso), $datos + ['cancelar_cirugias' => '1'])->assertSessionHasNoErrors();
        self::assertSame('Dado de alta', $ingreso->fresh()->estado);
        self::assertSame('Cancelada', $pendiente->fresh()->estado);
        self::assertSame('Realizada', $realizada->fresh()->estado);
        $this->assertDatabaseHas('tbl_cirugia_equipo', ['id_cirugia' => $pendiente->getKey(), 'estado' => 'Cancelado']);
        $this->assertDatabaseHas('tbl_bitacora', ['tabla' => 'tbl_cirugia', 'id_registro' => $pendiente->getKey(), 'accion' => 'Cancelación de cirugía']);
    }

    public function test_alta_no_cancela_cirugia_en_curso_ni_deja_cancelaciones_parciales(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $pendiente = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Pendiente', 'estado' => 'Programada', 'id_usuario_creacion' => 1]);
        $curso = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'En curso', 'estado' => 'En cirugía', 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1])->post(route('alta.guardar', $ingreso), ['id_personal_medico' => 1, 'fecha_hora' => now('America/Guatemala')->format('Y-m-d H:i:s'), 'diagnostico_final' => 'Prueba', 'condicion_egreso' => 'Prueba', 'cancelar_cirugias' => '1'])->assertSessionHasErrors('cancelar_cirugias');
        self::assertSame('Activo', $ingreso->fresh()->estado);
        self::assertSame('Programada', $pendiente->fresh()->estado);
        self::assertSame('En cirugía', $curso->fresh()->estado);
        $this->assertDatabaseCount('tbl_alta_medica', 0);
    }

    public function test_alta_usa_personal_de_sesion_e_ignora_medico_enviado(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('alta.guardar', $ingreso), ['id_personal_medico' => 999999, 'fecha_hora' => now('America/Guatemala')->format('Y-m-d H:i:s'), 'diagnostico_final' => 'Prueba', 'condicion_egreso' => 'Prueba'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_alta_medica', ['id_ingreso' => $ingreso->getKey(), 'id_personal_medico' => 2, 'id_usuario_creacion' => 1]);
    }

    public function test_equipo_ocupado_solo_a_la_misma_fecha_hora_y_muestra_nombres(): void
    {
        $a = IngresoFactory::new()->create();
        $b = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1]);
        $this->post(route('cirugias.guardar', $a), $this->datos(['tipo_operacion' => 'Operación anterior']))->assertSessionHasNoErrors();
        $primera = Cirugia::sole();
        $primera->update(['fecha_fin_programada' => '2026-10-12 15:00:00', 'estado' => 'En cirugía']);
        $this->post(route('cirugias.guardar', $b), $this->datos())->assertSessionHasErrors('personal');
        $mensaje = session('errors')->first('personal');
        $this->assertStringContainsString('Rosa Médica', $mensaje);
        $this->assertStringContainsString('Operación anterior', $mensaje);
        $this->post(route('cirugias.guardar', $b), $this->datos(['fecha_programada' => '2026-10-12 11:00:00']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tbl_cirugia', 2);
    }

    public function test_solo_medico_participante_finaliza_y_estado_visible_en_las_tres_vistas(): void
    {
        $i = IngresoFactory::new()->create();
        $this->internar($i);
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Operación en curso', 'estado' => 'En cirugía', 'fecha_inicio_real' => '2026-10-11 10:00:00', 'id_usuario_creacion' => 1]);
        $c->equipo()->syncWithPivotValues([1, 2], ['estado' => 'Asignado', 'rol_quirurgico' => 'Otro']);
        $this->withSession(['id_usuario' => 1]);
        foreach ([route('pacientes.index'), route('pacientes.expediente', $i->paciente), route('atencion.enfermeria', $i)] as $vista) {
            $this->get($vista)->assertOk()->assertSee('En cirugía')->assertSee('id="cirugia-detalle-'.$c->getKey().'"', false)->assertSee('11/10/2026')->assertSee('10:00')->assertDontSee('>Finalizar cirugía</button>', false);
        }
        $datos = ['accion' => 'terminar', 'fecha_hora' => '2026-10-11 11:00:00'];
        $this->post(route('cirugias.estado', $c), $datos)->assertForbidden();
        $this->assertSame('En cirugía', $c->fresh()->estado);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $c->equipo()->updateExistingPivot(1, ['estado' => 'Cancelado']);
        $this->post(route('cirugias.estado', $c), $datos)->assertForbidden();
        $c->equipo()->updateExistingPivot(1, ['estado' => 'Asignado']);
        $this->get(route('pacientes.expediente', $i->paciente))->assertOk()->assertSee('Finalizar cirugía');
        $this->post(route('cirugias.estado', $c), $datos + ['_formulario' => 'cirugia-'.$c->getKey()])->assertSessionHasNoErrors();
        $this->assertSame('Realizada', $c->fresh()->estado);
        $this->assertSame('2026-10-11 10:00:00', $c->fresh()->fecha_inicio_real->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-11 11:00:00', $c->fresh()->fecha_fin_real->format('Y-m-d H:i:s'));
        $this->assertSame(['Participó'], $c->fresh()->equipo->pluck('pivot.estado')->unique()->values()->all());
        $this->post(route('cirugias.estado', $c), $datos)->assertSessionHasErrors('accion');
        $this->get(route('pacientes.index'))->assertOk()->assertDontSee('> En cirugía</span>', false);
        $this->get(route('pacientes.expediente', $i->paciente))->assertOk()->assertSee('Realizada')->assertSee('11:00');
    }

    public function test_cancelacion_manual_exige_personal_asignado(): void
    {
        $i = IngresoFactory::new()->create();
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Pendiente', 'estado' => 'Programada', 'fecha_programada' => '2026-10-12 10:00:00', 'id_usuario_creacion' => 1]);
        $datos = ['accion' => 'cancelar', 'fecha_hora' => '2026-10-11 11:00:00', 'motivo_cancelacion' => 'Cambio de programación'];
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.estado', $c), $datos)->assertForbidden();
        $c->equipo()->syncWithPivotValues([2], ['estado' => 'Asignado', 'rol_quirurgico' => 'Otro']);
        $this->post(route('cirugias.estado', $c), $datos)->assertSessionHasNoErrors();
        $this->assertSame('Cancelada', $c->fresh()->estado);
    }

    public function test_cancelar_orden_posoperatoria_no_pide_motivo_y_valida_la_cirugia(): void
    {
        $ingreso = IngresoFactory::new()->create();
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->withSession(['id_usuario' => 1]);
        $hoja = EvolucionClinica::create(['id_ingreso' => $ingreso->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-11 09:00:00', 'diagnostico' => 'Prueba', 'ordenes_medicas' => ['dieta' => 'Blanda'], 'estado' => 'Activo', 'id_usuario_creacion' => 1]);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Operación de prueba', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 10:00:00', 'fecha_fin_real' => '2026-10-11 11:00:00', 'id_usuario_creacion' => 1]);
        $cirugia->equipo()->syncWithPivotValues([1], ['estado' => 'Participó', 'rol_quirurgico' => 'Otro']);
        $ruta = route('evolucion.cancelar-ordenes', $hoja);
        $this->post($ruta, [])->assertSessionHasErrors('motivo_cancelacion');
        $cirugia->update(['estado' => 'Programada']);
        $this->post($ruta, ['cirugia_posoperatoria' => $cirugia->getKey()])->assertNotFound();
        $cirugia->update(['estado' => 'Realizada']);
        $cirugia->equipo()->detach();
        $this->post($ruta, ['cirugia_posoperatoria' => $cirugia->getKey()])->assertForbidden();
        $cirugia->equipo()->syncWithPivotValues([1], ['estado' => 'Participó', 'rol_quirurgico' => 'Otro']);
        $this->post($ruta, ['cirugia_posoperatoria' => $cirugia->getKey()])->assertSessionHas('exito');
        $this->assertStringContainsString('después de la cirugía #'.$cirugia->getKey(), $hoja->fresh()->ordenes_medicas['cancelacion']['motivo']);
    }

    public function test_reprogramar_desde_detalle_solo_medicos_y_sin_cruzar_equipo(): void
    {
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $i), $this->datos())->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $this->get(route('pacientes.index'))->assertOk()->assertSee('Cirugía programada')->assertSee('data-bs-target="#cirugia-detalle-'.$c->getKey().'"', false)->assertDontSee('data-cambiar-horario', false);
        $ruta = route('cirugias.estado', $c);
        $datos = ['accion' => 'reprogramar', 'fecha_hora' => '2026-10-12 11:00:00'];
        $this->post($ruta, $datos)->assertSessionHasNoErrors();
        $c->refresh()->update(['fecha_programada' => '2026-10-12 10:00:00']);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $otro = IngresoFactory::new()->create();
        $ocupada = Cirugia::create(['id_ingreso' => $otro->getKey(), 'tipo_operacion' => 'Otra operación', 'estado' => 'Programada', 'fecha_programada' => $datos['fecha_hora'], 'id_usuario_creacion' => 1]);
        $ocupada->equipo()->syncWithPivotValues([1], ['estado' => 'Asignado', 'rol_quirurgico' => 'Otro']);
        $this->post($ruta, $datos)->assertSessionHasErrors('fecha_programada');
        $this->assertSame('2026-10-12 10:00:00', $c->fresh()->fecha_programada->format('Y-m-d H:i:s'));
        $this->post($ruta, array_replace($datos, ['fecha_hora' => '2026-10-12 12:00:00']))->assertSessionHas('exito')->assertSessionHas('abrir_cirugia', $c->getKey());
        $this->assertSame('2026-10-12 12:00:00', $c->fresh()->fecha_programada->format('Y-m-d H:i:s'));
        $this->assertSame('Programada', $c->fresh()->estado);
        $this->get(route('pacientes.index'))->assertOk()->assertSee('Cirugía programada')->assertDontSee('<small class="d-block">12/10/2026 12:00</small>', false);
        $this->post($ruta, array_replace($datos, ['fecha_hora' => '2026-10-10 12:00:00']))->assertSessionHasErrors('fecha_hora');
    }

    public function test_personal_ajeno_no_puede_iniciar_aunque_pueda_editar_la_cirugia(): void
    {
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $i), $this->datos(['personal' => [1]]))->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $this->assertSame('Programada', $c->estado);
        $this->assertNull($c->fecha_inicio_real);
        $this->get(route('atencion.show', $i))->assertOk()->assertSee('data-permite-estado="0"', false);
        $datos = ['accion' => 'iniciar', 'fecha_hora' => '2026-10-11 11:00:00'];
        $this->post(route('cirugias.estado', $c), $datos)->assertForbidden();
        $this->assertSame('Programada', $c->fresh()->estado);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->get(route('atencion.show', $i))->assertOk()->assertSee('data-permite-estado="1"', false);
        $this->post(route('cirugias.estado', $c), $datos)->assertSessionHasNoErrors();
        $this->assertSame('En cirugía', $c->fresh()->estado);
    }

    public function test_fecha_futura_reprograma_y_finalizar_registra_la_hora_actual(): void
    {
        $i = IngresoFactory::new()->create();
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $i), $this->datos())->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $ruta = route('cirugias.estado', $c);
        $this->post($ruta, ['fecha_hora' => '2026-10-13 14:00:00'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-13 14:00:00', $c->fresh()->fecha_programada->format('Y-m-d H:i:s'));
        $this->assertSame('Programada', $c->fresh()->estado);
        $this->post($ruta, ['accion' => 'iniciar', 'fecha_hora' => '2026-10-11 10:00:00'])->assertSessionHasNoErrors();
        $this->post($ruta, ['accion' => 'terminar', 'fecha_hora' => '2099-01-01 10:00:00'])->assertSessionHasNoErrors();
        $this->assertSame('Realizada', $c->fresh()->estado);
        $this->assertSame(now('America/Guatemala')->format('Y-m-d H:i:s'), $c->fresh()->fecha_fin_real->format('Y-m-d H:i:s'));
    }

    public function test_en_curso_no_se_edita_y_finalizada_solo_se_corrige_personal(): void
    {
        $i = IngresoFactory::new()->create();
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Nombre original', 'estado' => 'En cirugía', 'fecha_inicio_real' => '2026-10-11 09:00:00', 'observaciones' => 'Original', 'id_usuario_creacion' => 1]);
        $c->equipo()->syncWithPivotValues([1], ['estado' => 'Asignado', 'rol_quirurgico' => 'Otro']);
        $datos = ['tipo_operacion' => 'Cambio', 'observaciones' => 'Cambio', 'fecha_inicio_real' => '2026-10-11 08:00:00', 'fecha_fin_real' => '2026-10-11 08:30:00', 'personal' => [1, 2]];
        $this->withSession(['id_usuario' => 1])->put(route('cirugias.actualizar', $c), $datos)->assertSessionHasErrors('cirugia');
        $this->assertSame([1], $c->fresh()->equipo->modelKeys());
        $this->get(route('atencion.show', $i))->assertOk()->assertDontSee('data-correccion-cirugia', false);
        $c->update(['estado' => 'Realizada', 'fecha_fin_real' => '2026-10-11 10:00:00']);
        $this->put(route('cirugias.actualizar', $c), $datos)->assertSessionHasNoErrors();
        $actual = $c->fresh();
        $this->assertSame('Nombre original', $actual->tipo_operacion);
        $this->assertSame('Original', $actual->observaciones);
        $this->assertSame('2026-10-11 09:00:00', $actual->fecha_inicio_real->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-11 10:00:00', $actual->fecha_fin_real->format('Y-m-d H:i:s'));
        $this->assertSame([1, 2], $actual->equipo->modelKeys());
        $this->get(route('atencion.show', $i))->assertOk()->assertSee('data-correccion-cirugia', false)->assertDontSee('name="tipo_operacion"', false);
    }

    public function test_otros_editores_reprograman_y_solo_participantes_agregan_equipo_finalizado(): void
    {
        $i = IngresoFactory::new()->create();
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Prueba de permisos', 'estado' => 'Programada', 'fecha_programada' => '2026-10-12 10:00:00', 'id_usuario_creacion' => 1]);
        $c->equipo()->syncWithPivotValues([1], ['estado' => 'Asignado', 'rol_quirurgico' => 'Otro']);
        DB::table('tbl_personal')->insert(['id_personal' => 3, 'id_cargo' => 6, 'nombres' => 'Otra', 'apellidos' => 'Enfermera', 'dpi' => '1234567890125']);
        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 3, 'id_rol' => 1, 'nombre_usuario' => 'otro-editor', 'contrasena' => 'solo-pruebas']);
        $this->withSession(['id_usuario' => 2])->post(route('cirugias.estado', $c), ['fecha_hora' => '2026-10-12 11:00:00'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-12 11:00:00', $c->fresh()->fecha_programada->format('Y-m-d H:i:s'));
        $c->refresh()->update(['estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 09:00:00', 'fecha_fin_real' => '2026-10-11 10:00:00']);
        $c->equipo()->updateExistingPivot(1, ['estado' => 'Participó']);
        $ruta = route('cirugias.actualizar', $c);
        $this->withSession(['id_usuario' => 1])->put($ruta, ['personal' => [1, 2]])->assertSessionHasErrors('cirugia');
        DB::table('tbl_usuario')->where('id_usuario', 2)->update(['id_personal' => 1]);
        $this->withSession(['id_usuario' => 2])->get(route('atencion.show', $i))->assertOk()->assertSee('Agregar personal participante');
        $this->put($ruta, ['personal' => [2]])->assertSessionHasNoErrors();
        $this->assertSame([1, 2], $c->fresh()->equipo->modelKeys());
        $this->put($ruta, ['personal' => []])->assertSessionHasNoErrors();
        $this->assertSame([1, 2], $c->fresh()->equipo->modelKeys());
    }

    public function test_solo_programadas_se_editan_despues_de_doce_horas(): void
    {
        $i = IngresoFactory::new()->create();
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Programada antigua', 'estado' => 'Programada', 'fecha_programada' => '2026-10-14 10:00:00', 'id_usuario_creacion' => 1]);
        $c->equipo()->syncWithPivotValues([2], ['estado' => 'Asignado', 'rol_quirurgico' => 'Otro']);
        $this->travel(13)->hours();
        $this->withSession(['id_usuario' => 1])->get(route('atencion.show', $i))->assertOk()->assertSee('Editable mientras siga programada.');
        $this->put(route('cirugias.actualizar', $c), ['tipo_operacion' => 'Corrección permitida', 'personal' => [2]])->assertSessionHasNoErrors();
        $this->assertSame('Corrección permitida', $c->fresh()->tipo_operacion);
        $c->refresh()->update(['estado' => 'En cirugía']);
        $this->put(route('cirugias.actualizar', $c), ['personal' => [1, 2]])->assertSessionHasErrors('cirugia');
        $c->update(['estado' => 'Realizada']);
        $this->put(route('cirugias.actualizar', $c), ['personal' => [1, 2]])->assertSessionHasErrors('cirugia');
    }

    public function test_orden_recuperacion_unica_solo_edicion_vence_y_cancelacion_no_tiene_plazo(): void
    {
        $i = IngresoFactory::new()->create();
        $this->internar($i);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Prueba recuperación', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 09:00:00', 'fecha_fin_real' => '2026-10-11 10:00:00', 'id_usuario_creacion' => 1]);
        $c->equipo()->syncWithPivotValues([1], ['estado' => 'Participó', 'rol_quirurgico' => 'Otro']);
        $datos = ['id_ingreso' => $i->getKey(), '_formulario' => 'valoracion', 'cirugia_recuperacion' => $c->getKey(), 'fecha_hora' => '2026-10-11 11:00:00', 'motivo_atencion' => 'Recuperación', 'diagnostico' => 'Prueba', 'con_ordenes' => '1', 'ordenes' => ['dieta' => 'Blanda']];
        $this->withSession(['id_usuario' => 1])->post(route('evolucion.guardar'), $datos)->assertSessionHasNoErrors();
        $id = $c->fresh()->id_evolucion;
        $this->assertNotNull($id);
        $this->post(route('evolucion.guardar'), $datos + ['estado_mejoria' => 'Sin cambios'])->assertSessionHasErrors('cirugia_recuperacion');
        $this->assertDatabaseCount('tbl_evolucion_clinica', 1);
        $this->get(route('atencion.show', $i))->assertOk()->assertDontSee('Agregar orden posoperatoria')->assertSee('Editar orden de recuperación');
        $this->travel(25)->hours();
        $this->get(route('atencion.show', $i))->assertOk()->assertSee('Cancelar orden vigente');
        $this->assertFalse(EdicionClinica::puedeEditar($c->fresh()->evolucion));
        $this->post(route('evolucion.cancelar-ordenes', $id), ['cirugia_posoperatoria' => $c->getKey()])->assertSessionHas('exito');
        $this->assertNotNull(OrdenesMedicas::cancelacion($c->fresh()->evolucion->ordenes_medicas));
        $this->get(route('atencion.show', $i))->assertOk()->assertDontSee('Agregar orden posoperatoria')->assertDontSee('Cancelar orden vigente');
    }

    public function test_nueva_cirugia_muestra_orden_vigente_desplegable_y_formulario_vacio_sin_copiar_otra_cirugia(): void
    {
        $i = IngresoFactory::new()->create();
        $this->internar($i);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $ordenes = ['dieta' => 'Líquida', 'indicaciones' => 'Indicaciones exclusivas de la primera cirugía', 'soluciones' => [['tipo' => 'Salina', 'volumen' => 100, 'velocidad' => 40, 'unidad' => 'gotas/min']], 'medicamentos' => [['nombre' => 'Medicamento de prueba anterior', 'dosis' => 20, 'via' => 'Oral', 'frecuencia' => 8]]];
        $orden = EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-11 08:00:00', 'diagnostico' => 'Prueba', 'ordenes_medicas' => $ordenes, 'estado' => 'Activo', 'id_usuario_creacion' => 1]);
        $primera = Cirugia::create(['id_ingreso' => $i->getKey(), 'id_evolucion' => $orden->getKey(), 'tipo_operacion' => 'Primera cirugía', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 06:00:00', 'fecha_fin_real' => '2026-10-11 07:00:00', 'id_usuario_creacion' => 1]);
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Segunda cirugía', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 09:00:00', 'fecha_fin_real' => '2026-10-11 10:00:00', 'id_usuario_creacion' => 1]);
        $c->equipo()->syncWithPivotValues([1], ['estado' => 'Participó', 'rol_quirurgico' => 'Otro']);
        $this->assertSame($orden->getKey(), $primera->ordenRecuperacion()->getKey());
        $this->assertNull($c->ordenRecuperacion());
        $html = $this->withSession(['id_usuario' => 1])->get(route('pacientes.expediente', $i->id_paciente))->assertOk()->getContent();
        $modal = substr($html, strpos($html, 'id="cirugia-detalle-'.$c->getKey().'"'));
        $modal = substr($modal, 0, strpos($modal, 'data-form-recuperacion'));
        $this->assertStringContainsString('data-orden-vigente-recuperacion', $modal);
        $this->assertStringContainsString('Hay una orden vigente · Ver detalles', $modal);
        $this->assertStringContainsString('Cancelar orden vigente', $modal);
        $this->assertStringContainsString('Indicaciones exclusivas de la primera cirugía', $modal);
        $this->assertStringContainsString('Agregar orden posoperatoria', $modal);
        preg_match('~<form[^>]*data-form-recuperacion[^>]*>(.*?)</form>~s', substr($html, strpos($html, 'id="cirugia-detalle-'.$c->getKey().'"')), $form);
        $this->assertNotEmpty($form);
        $this->assertStringContainsString('name="editar_recuperacion" value="0"', $form[1]);
        $this->assertStringNotContainsString('Indicaciones exclusivas de la primera cirugía', $form[1]);
        $this->assertStringNotContainsString('Medicamento de prueba anterior', $form[1]);
        $this->assertDoesNotMatchRegularExpression('~<option[^>]*selected[^>]*>Líquida</option>~', $form[1]);
        $this->post(route('cirugias.recuperacion', $c), ['ordenes' => ['dieta' => '']])->assertSessionHasErrors('ordenes.dieta');
        $this->post(route('cirugias.recuperacion', $c), ['ordenes' => ['dieta' => 'Blanda', 'medicamentos' => [['nombre' => 'Prueba', 'dosis' => -1, 'via' => 'Oral', 'frecuencia' => 0]]]])->assertSessionHasErrors(['ordenes.medicamentos.0.dosis', 'ordenes.medicamentos.0.frecuencia']);
        $this->assertNull($c->fresh()->id_evolucion);
        $this->postJson(route('evolucion.cancelar-ordenes', $orden), ['cirugia_posoperatoria' => $c->getKey()])->assertOk()->assertJsonPath('vista', route('pacientes.expediente', $i->id_paciente));
        $this->assertNotNull(OrdenesMedicas::cancelacion($orden->fresh()->ordenes_medicas));
        $this->post(route('cirugias.recuperacion', $c), ['ordenes' => ['dieta' => 'Blanda']])->assertSessionHasNoErrors();
        $this->assertNotSame($orden->getKey(), $c->fresh()->id_evolucion);
        $this->assertSame('Blanda', $c->fresh()->ordenRecuperacion()->ordenes_medicas['dieta']);
        $this->assertSame('Líquida', $orden->fresh()->ordenes_medicas['dieta']);
        $this->assertSame($orden->getKey(), $primera->fresh()->id_evolucion);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 2);
    }

    public function test_cirugia_registrada_despues_muestra_recuperacion_sin_editar_fechas(): void
    {
        $i = IngresoFactory::new()->create();
        $this->internar($i);
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Herniorrafia', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 09:00:00', 'fecha_fin_real' => '2026-10-11 10:00:00', 'motivo_registro_posterior' => 'Registro posterior', 'id_usuario_creacion' => 1]);
        $c->equipo()->syncWithPivotValues([1], ['estado' => 'Participó', 'rol_quirurgico' => 'Otro']);
        $this->withSession(['id_usuario' => 1])->get(route('pacientes.expediente', $i->paciente))->assertOk()->assertSee('Órdenes para recuperación')->assertSee('Sin orden médica vigente.')->assertDontSee('Sin agendar')->assertDontSee('Agregar orden posoperatoria');
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->get(route('pacientes.expediente', $i->paciente))->assertOk()->assertSee('Agregar orden posoperatoria')->assertSee('Agregar personal participante')->assertSee('09:00')->assertSee('10:00')->assertDontSee('name="fecha_inicio_real"', false)->assertDontSee('data-estado-cirugia', false);
    }

    public function test_cirugia_previa_permite_cancelar_orden_antigua_de_otro_medico_y_agregar_recuperacion(): void
    {
        $i = IngresoFactory::new()->create();
        $this->internar($i);
        DB::table('tbl_personal')->insert(['id_personal' => 3, 'id_cargo' => 1, 'nombres' => 'Otro', 'apellidos' => 'Médico', 'dpi' => '1234567890125']);
        $orden = EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 3, 'fecha_hora' => '2026-10-11 08:00:00', 'diagnostico' => 'Original', 'ordenes_medicas' => ['dieta' => 'Líquida'], 'estado' => 'Activo', 'id_usuario_creacion' => 1]);
        $this->travel(25)->hours();
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Cirugía previa', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 09:00:00', 'fecha_fin_real' => '2026-10-11 10:00:00', 'motivo_registro_posterior' => 'Registro después de operar', 'id_usuario_creacion' => 1]);
        $c->equipo()->syncWithPivotValues([1], ['estado' => 'Participó', 'rol_quirurgico' => 'Otro']);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->assertNull($c->ordenRecuperacion());
        $this->withSession(['id_usuario' => 1])->get(route('atencion.show', $i))->assertOk()->assertSee('Agregar orden posoperatoria')->assertSee('Cancelar orden vigente');
        $this->postJson(route('evolucion.cancelar-ordenes', $orden), ['cirugia_posoperatoria' => $c->getKey()])->assertOk()->assertJsonPath('mensaje', 'Orden médica cancelada.')->assertJsonPath('vista', route('pacientes.expediente', $i->id_paciente));
        $this->assertSame(3, (int) $orden->fresh()->id_personal);
        $this->assertSame('Líquida', $orden->fresh()->ordenes_medicas['dieta']);
        $this->assertNotNull(OrdenesMedicas::cancelacion($orden->fresh()->ordenes_medicas));
        $datos = ['id_ingreso' => $i->getKey(), '_formulario' => 'evolucion-nueva', 'cirugia_recuperacion' => $c->getKey(), 'fecha_hora' => now('America/Guatemala')->format('Y-m-d H:i:s'), 'motivo_atencion' => 'Recuperación', 'diagnostico' => 'Seguimiento', 'estado_mejoria' => 'Mejoró', 'ordenes' => ['dieta' => 'Blanda']];
        $this->post(route('evolucion.guardar'), $datos)->assertSessionHasNoErrors()->assertRedirect(route('pacientes.expediente', $i->id_paciente));
        $this->assertNotNull($c->fresh()->id_evolucion);
        $this->get(route('atencion.show', $i))->assertOk()->assertDontSee('Agregar orden posoperatoria')->assertSee('Editar orden de recuperación');
        $this->assertDatabaseCount('tbl_evolucion_clinica', 2);
    }

    public function test_finalizar_con_orden_de_recuperacion_muestra_y_cancela_la_orden_vigente(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->internar($ingreso);
        $anterior = EvolucionClinica::create([
            'id_ingreso' => $ingreso->getKey(), 'id_personal' => 2, 'fecha_hora' => '2026-10-11 08:00:00', 'motivo_atencion' => 'Control',
            'diagnostico' => 'Fractura de rodilla', 'ordenes_medicas' => ['dieta' => 'Blanda', 'indicaciones' => 'Reposo en cama'], 'estado' => 'Activo', 'id_usuario_creacion' => 1,
        ]);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Acomodamiento de rodilla', 'estado' => 'En cirugía', 'fecha_inicio_real' => '2026-10-11 11:00:00', 'id_usuario_creacion' => 1]);
        $cirugia->equipo()->attach(1, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->withSession(['id_usuario' => 1]);

        // Al finalizar: el personal se edita solo si se elige; la orden de recuperación muestra la orden vigente y permite cancelarla.
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
            ->assertSee('Editar el personal que participó')->assertSee('Agregar orden de recuperación')
            ->assertSee('Hay una orden médica vigente')->assertSee('Reposo en cama')->assertSee('Cancelar esta orden al guardar la de recuperación')
            ->assertDontSee('Órdenes para recuperación');

        // Una orden incompleta no deja la cirugía a medias.
        $this->post(route('cirugias.estado', $cirugia), ['accion' => 'terminar', 'agregar_orden_recuperacion' => 1, 'ordenes' => ['dieta' => '']])->assertSessionHasErrors('ordenes.dieta');
        $this->assertSame('En cirugía', $cirugia->fresh()->estado);

        $this->post(route('cirugias.estado', $cirugia), [
            'accion' => 'terminar', 'agregar_orden_recuperacion' => 1, 'cancelar_orden_vigente' => 1, 'id_orden_vigente' => $anterior->getKey(),
            'ordenes' => ['dieta' => 'Líquida', 'indicaciones' => 'Vigilar la herida'],
        ])->assertSessionHasNoErrors()->assertSessionHas('exito', 'Cirugía finalizada. Enfermería ya ve la orden de recuperación.');

        $cirugia = $cirugia->fresh();
        $this->assertSame('Realizada', $cirugia->estado);
        $orden = $cirugia->ordenRecuperacion();
        $this->assertNotNull($orden);
        $this->assertSame('Líquida', $orden->ordenes_medicas['dieta']);
        $this->assertSame(1, (int) $orden->id_personal);
        $this->assertSame('Fractura de rodilla', $orden->diagnostico);
        $this->assertSame($orden->getKey(), OrdenesMedicas::vigentes($ingreso->fresh())->getKey());
        $this->assertStringContainsString('Reemplazada por la orden de recuperación de la cirugía #'.$cirugia->getKey(), OrdenesMedicas::cancelacion($anterior->fresh()->ordenes_medicas)['motivo']);
    }

    public function test_orden_de_recuperacion_al_finalizar_exige_internamiento_y_respeta_la_orden_anterior(): void
    {
        $ingreso = IngresoFactory::new()->create(['tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA]);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Ambulatoria', 'estado' => 'En cirugía', 'fecha_inicio_real' => '2026-10-11 11:00:00', 'id_usuario_creacion' => 1]);
        $cirugia->equipo()->attach(1, ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->withSession(['id_usuario' => 1]);
        $orden = ['accion' => 'terminar', 'agregar_orden_recuperacion' => 1, 'ordenes' => ['dieta' => 'Líquida']];

        $this->post(route('cirugias.estado', $cirugia), $orden)->assertSessionHasErrors('agregar_orden_recuperacion');
        $this->assertSame('En cirugía', $cirugia->fresh()->estado);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);

        // Internando al finalizar sí se guarda; sin marcar «cancelar», no se toca ninguna otra orden.
        $this->post(route('cirugias.estado', $cirugia), $orden + ['internar_al_finalizar' => 1, 'habitacion_recuperacion' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Realizada', $cirugia->fresh()->estado);
        $this->assertNotNull($cirugia->fresh()->ordenRecuperacion());
        $this->assertDatabaseCount('tbl_evolucion_clinica', 1);
    }

    public function test_cirugia_ya_realizada_acepta_fechas_pasadas_aunque_sean_antes_del_ingreso(): void
    {
        $ingreso = IngresoFactory::new()->create(['fecha_ingreso' => '2026-10-11 10:00:00']);
        $this->internar($ingreso);
        $this->withSession(['id_usuario' => 1]);
        $this->get(route('cirugias.formulario', [$ingreso, 'modo' => 'realizada']))->assertOk()
            ->assertDontSee('Para atenciones de urgencia que se registran después de operar')
            ->assertSee('Puede poner una fecha y hora pasada')->assertDontSee('min="2026-10-11T10:00"', false);
        $datos = ['modo' => 'realizada', 'tipo_operacion' => 'Extracción de tumor', 'fecha_inicio_real' => '2026-10-04T13:18', 'fecha_fin_real' => '2026-10-04T18:14', 'motivo_registro_posterior' => 'Se registró después.', 'personal' => [1, 2]];
        $this->post(route('cirugias.guardar', $ingreso), array_replace($datos, ['fecha_fin_real' => '2026-10-12T11:00']))->assertSessionHasErrors('fecha_fin_real');
        $this->post(route('cirugias.guardar', $ingreso), $datos)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-04 13:18:00', Cirugia::sole()->fecha_inicio_real->format('Y-m-d H:i:s'));
    }

    public function test_editar_programada_guarda_datos_equipo_y_horario_juntos(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $ingreso), $this->datos())->assertSessionHasNoErrors();
        $cirugia = Cirugia::sole();
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
            ->assertSee('data-filtro-personal-casillas', false)->assertSee('Todos los cargos')->assertSee('Buscar personal');
        $ruta = route('cirugias.actualizar', $cirugia);
        $datos = ['tipo_operacion' => 'Cirugía corregida', 'observaciones' => 'Observaciones corregidas', 'personal' => [2], 'accion' => '', 'fecha_hora' => '2026-10-13T11:00'];
        $this->put($ruta, $datos)->assertSessionHasNoErrors()->assertSessionHas('abrir_cirugia', $cirugia->getKey());
        $this->assertSame('Cirugía corregida', $cirugia->fresh()->tipo_operacion);
        $this->assertSame('Observaciones corregidas', $cirugia->fresh()->observaciones);
        $this->assertSame('2026-10-13 11:00', $cirugia->fresh()->fecha_programada->format('Y-m-d H:i'));
        $this->assertSame([2], $cirugia->equipo()->pluck('tbl_personal.id_personal')->all());

        $this->put($ruta, array_replace($datos, ['tipo_operacion' => 'No debe guardarse', 'fecha_hora' => '2026-10-01T11:00']))->assertSessionHasErrors('fecha_hora');
        $this->assertSame('Cirugía corregida', $cirugia->fresh()->tipo_operacion);
        $this->assertSame('2026-10-13 11:00', $cirugia->fresh()->fecha_programada->format('Y-m-d H:i'));

        $this->put($ruta, array_replace($datos, ['tipo_operacion' => 'Cirugía cancelada corregida', 'accion' => 'cancelar', 'motivo_cancelacion' => 'Ya no es necesaria']))->assertSessionHasNoErrors();
        $this->assertSame('Cancelada', $cirugia->fresh()->estado);
        $this->assertSame('Cirugía cancelada corregida', $cirugia->fresh()->tipo_operacion);
        $this->assertSame('Ya no es necesaria', $cirugia->fresh()->motivo_cancelacion);
    }

    public function test_recuperacion_usa_confirmacion_del_pie_sin_boton_interno(): void
    {
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $ingreso = IngresoFactory::new()->create();
        $this->internar($ingreso);
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Cirugía de prueba', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 09:00:00', 'fecha_fin_real' => '2026-10-11 10:00:00', 'id_usuario_creacion' => 1]);
        $cirugia->equipo()->attach(1, ['rol_quirurgico' => 'Otro', 'estado' => 'Participó']);
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.recuperacion', $cirugia), ['ordenes' => ['dieta' => 'Líquida']])->assertSessionHasNoErrors();
        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
            ->assertSee('Editar orden de recuperación')->assertSee('data-form-recuperacion', false)
            ->assertSee('data-editar-cirugia', false)->assertDontSee('class="btn btn-primary mt-3"', false);
        $this->post(route('cirugias.recuperacion', $cirugia), ['editar_recuperacion' => 1, 'ordenes' => ['dieta' => 'Blanda']])->assertSessionHasNoErrors();
        $this->assertSame('Blanda', $cirugia->fresh()->ordenRecuperacion()->ordenes_medicas['dieta']);
        $this->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('Orden de recuperación vigente')->assertSee('Después de la cirugía: Cirugía de prueba')
            ->assertDontSee('Órdenes médicas vigentes');
        EvolucionClinica::create(['id_ingreso' => $ingreso->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-11 12:01:00', 'motivo_atencion' => 'Seguimiento', 'diagnostico' => 'Prueba', 'estado_mejoria' => 'Mejoró', 'ordenes_medicas' => ['dieta' => 'Libre'], 'id_usuario_creacion' => 1]);
        $this->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('Órdenes médicas vigentes')->assertSee('Orden de recuperación · Cirugía de prueba')
            ->assertDontSee('Orden de recuperación vigente');
    }
}
