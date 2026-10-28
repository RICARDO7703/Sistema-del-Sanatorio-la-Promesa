<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\Ingreso;
use App\Models\SignoVital;
use Database\Factories\IngresoFactory;
use Illuminate\Support\Facades\DB;
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

    private function datos(array $cambios = []): array
    {
        return array_replace(['modo' => 'agendar', 'tipo_operacion' => 'Cirugía de prueba', 'fecha_programada' => '2026-10-12 10:00:00', 'fecha_fin_programada' => '2026-10-12 11:00:00', 'personal' => [1, 2]], $cambios);
    }

    public function test_programacion_equipo_y_vistas(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $ingreso), $this->datos())->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $this->assertCount(2, $c->equipo);
        $this->assertNull($c->id_personal_indica);
        $this->get(route('cirugias.show', $c))->assertOk()->assertSee('Laura Enfermera')->assertSee('Controles de recuperación');
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
        $this->post($ruta, $this->datos(['fecha_fin_programada' => '2026-10-12 09:00:00']))->assertSessionHasErrors('fecha_fin_programada');
        $this->post($ruta, $this->datos(['personal' => [1, 1]]))->assertSessionHasErrors('personal.0');
        $this->post($ruta, $this->datos(['fecha_programada' => '2026-10-10 10:00:00']))->assertSessionHasErrors('fecha_programada');
        $this->assertDatabaseCount('tbl_cirugia', 0);
        $this->post($ruta, $this->datos())->assertSessionHasNoErrors();
        $this->post($ruta, $this->datos())->assertSessionHasErrors('fecha_programada');
        $otro = IngresoFactory::new()->create();
        $this->post(route('cirugias.guardar', $otro), $this->datos())->assertSessionHasErrors('fecha_programada');
        $this->assertDatabaseCount('tbl_cirugia', 1);
    }

    public function test_transiciones_y_control_recuperacion(): void
    {
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $i), $this->datos())->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $this->post(route('cirugias.control', $c), ['fecha_hora' => '2026-10-11 11:00:00', 'frecuencia_cardiaca' => 80])->assertSessionHasErrors('signos');
        $this->post(route('cirugias.estado', $c), ['accion' => 'iniciar', 'fecha_hora' => '2026-10-11 10:00:00'])->assertSessionHasNoErrors();
        $this->get('/pacientes')->assertSee('En cirugía');
        $this->post(route('cirugias.estado', $c), ['accion' => 'terminar', 'fecha_hora' => '2026-10-11T09:00'])->assertSessionHasErrors('fecha_hora');
        $this->post(route('cirugias.estado', $c), ['accion' => 'terminar', 'fecha_hora' => '2026-10-11 11:00:00'])->assertSessionHasNoErrors();
        $this->post(route('cirugias.control', $c), ['fecha_hora' => '2026-10-11T10:30', 'frecuencia_cardiaca' => 80])->assertSessionHasErrors('fecha_hora');
        $this->post(route('cirugias.control', $c), ['fecha_hora' => '2026-10-11 11:30:00'])->assertSessionHasErrors('signos');
        $this->post(route('cirugias.control', $c), ['fecha_hora' => '2026-10-11 11:30:00', 'presion_sistolica' => 120])->assertSessionHasErrors('presion_diastolica');
        $this->post(route('cirugias.control', $c), ['fecha_hora' => '2026-10-11 11:30:00', 'saturacion_oxigeno' => 101])->assertSessionHasErrors('saturacion_oxigeno');
        $this->post(route('cirugias.control', $c), ['fecha_hora' => '2026-10-11 11:30:00', 'frecuencia_cardiaca' => 80])->assertSessionHasNoErrors();
        $this->assertSame('Recuperación', SignoVital::sole()->tipo_control);
        $this->assertSame($i->getKey(), SignoVital::sole()->id_ingreso);
        $this->get(route('cirugias.show', $c))->assertOk()->assertSee('80');
    }

    public function test_permisos_y_atencion_cerrada(): void
    {
        $i = IngresoFactory::new()->create();
        $this->get(route('cirugias.formulario', $i))->assertRedirect('/');
        DB::table('tbl_rol')->where('id_rol', 1)->update(['nombre_rol' => 'Enfermería']);
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $i), ['modo' => 'indicar', 'tipo_operacion' => 'Prueba'])->assertForbidden();
        $this->post(route('cirugias.guardar', $i), $this->datos())->assertSessionHasNoErrors();
        $c = Cirugia::sole();
        $i->update(['estado' => 'Dado de alta']);
        $this->post(route('cirugias.estado', $c), ['accion' => 'iniciar', 'fecha_hora' => '2026-10-11 11:00:00'])->assertSessionHasErrors('cirugia');
        $this->get(route('cirugias.show', $c))->assertOk()->assertDontSee('Guardar estado');
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

    public function test_medico_solo_consulta_no_agenda_ni_registra_controles(): void
    {
        $ingreso = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $ingreso), $this->datos())->assertSessionHasNoErrors();
        DB::table('tbl_rol')->where('id_rol', 1)->update(['nombre_rol' => 'Médico']);
        DB::table('tbl_usuario')->where('id_usuario', 1)->update(['id_personal' => 1]);
        $this->withSession(['id_usuario' => 1])->post(route('cirugias.guardar', $ingreso), $this->datos())->assertForbidden();
        $this->post(route('cirugias.guardar', $ingreso), ['modo' => 'indicar', 'tipo_operacion' => 'Indicación de prueba'])->assertForbidden();
        $this->post(route('cirugias.control', Cirugia::sole()), ['fecha_hora' => '2026-10-11 11:30:00', 'frecuencia_cardiaca' => 80])->assertForbidden();
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
            $this->get(route('cirugias.show', $c))->assertOk()->assertSee('Registro posterior a la cirugía')->assertSee('Registrar signos vitales');
            $this->get(route('pacientes.expediente', $ingreso->paciente))->assertOk()->assertSee('Cirugía urgente de prueba');
            $this->post(route('cirugias.control', $c), ['fecha_hora' => '2026-10-11T11:30', 'frecuencia_cardiaca' => 80])->assertSessionHasNoErrors();
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
}
