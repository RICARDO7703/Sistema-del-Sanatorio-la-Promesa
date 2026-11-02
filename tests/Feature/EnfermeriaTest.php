<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\NotaEnfermeria;
use Database\Factories\IngresoFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnfermeriaTest extends TestCase
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

    private function sesion(): static
    {
        return $this->withSession(['id_usuario' => 1]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function internado(int $habitacion = 1, array $atributos = []): Ingreso
    {
        $ingreso = IngresoFactory::new()->create($atributos);
        $id = DB::table('tbl_hospitalizacion')->insertGetId(['id_ingreso' => $ingreso->getKey(), 'fecha_inicio' => '2026-10-10 12:00:00', 'estado' => 'Activa', 'id_usuario_creacion' => 1]);
        DB::table('tbl_hospitalizacion_habitacion')->insert(['id_hospitalizacion' => $id, 'id_habitacion' => $habitacion, 'fecha_hora_inicio' => '2026-10-10 12:00:00', 'id_usuario_creacion' => 1]);

        return $ingreso;
    }

    /**
     * @param  array<string, mixed>|null  $ordenes
     */
    private function evolucion(Ingreso $ingreso, string $fecha, string $diagnostico, ?array $ordenes): void
    {
        DB::table('tbl_evolucion_clinica')->insert([
            'id_ingreso' => $ingreso->getKey(), 'id_personal' => 1, 'fecha_hora' => $fecha, 'motivo_atencion' => 'Control',
            'diagnostico' => $diagnostico, 'ordenes_medicas' => $ordenes ? json_encode($ordenes) : null, 'id_usuario_creacion' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ordenes(string $dieta, string $medicamento): array
    {
        return [
            'dieta' => $dieta,
            'soluciones' => [['tipo' => 'Salina', 'volumen' => 500, 'velocidad' => 40, 'unidad' => 'gotas/min']],
            'medicamentos' => [['nombre' => $medicamento, 'dosis' => 1000, 'via' => 'Intravenosa', 'frecuencia' => 12]],
            'indicaciones' => 'Control de temperatura cada 4 horas',
            'plan_educacional' => 'Lavado de manos',
        ];
    }

    public function test_listado_solo_muestra_internados_del_mas_reciente_al_mas_antiguo(): void
    {
        $habitacion110 = $this->internado(1);
        $habitacion102 = $this->internado(2);
        $emergencia = IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa']);
        $sinHabitacion = IngresoFactory::new()->create();

        $this->sesion()->get('/atencion')->assertOk()
            ->assertViewHas('ingresos', fn ($lista) => $lista->modelKeys() === [$habitacion102->getKey(), $habitacion110->getKey()])
            ->assertSee('Pacientes internados')
            ->assertSee(route('atencion.enfermeria', $habitacion102))
            ->assertDontSee(route('atencion.enfermeria', $emergencia))
            ->assertDontSee(route('atencion.enfermeria', $sinHabitacion));
    }

    public function test_listado_muestra_ultimo_control_y_busca(): void
    {
        $ingreso = $this->internado();
        DB::table('tbl_signos_vitales')->insert(['id_ingreso' => $ingreso->getKey(), 'id_personal' => 2, 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 09:30:00', 'temperatura' => 37.2, 'id_usuario_creacion' => 1]);

        $this->sesion()->get('/atencion')->assertOk()->assertSee('11/10/2026 09:30')->assertSee('hace 2 horas 30 minutos');
        $this->get('/atencion?buscar='.$ingreso->paciente->numero_expediente)->assertOk()->assertViewHas('ingresos', fn ($lista) => $lista->count() === 1);
        $this->get('/atencion?buscar=NO-EXISTE')->assertOk()->assertSee('Ningún paciente internado coincide con la búsqueda.');
    }

    public function test_hoja_muestra_ordenes_vigentes_solo_lectura(): void
    {
        $ingreso = $this->internado();
        DB::table('tbl_paciente')->where('id_paciente', $ingreso->id_paciente)->update(['alergias' => 'Penicilina', 'tipo_sangre' => 'O+']);
        $this->evolucion($ingreso, '2026-10-10 13:00:00', 'Neumonía', null);
        $this->evolucion($ingreso, '2026-10-11 08:00:00', 'Neumonía en tratamiento', $this->ordenes('Blanda', 'Ampicilina'));
        $this->evolucion($ingreso, '2026-10-11 10:00:00', 'Neumonía en mejoría', $this->ordenes('HNO (hasta nueva orden)', 'Ceftriaxona'));

        $this->sesion()->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('Hoja de enfermería')
            ->assertSee('Alergias:')->assertSee('Penicilina')->assertSee('O+')
            ->assertSee('Neumonía en mejoría')
            ->assertSee('Indicadas por Rosa Médica el 11/10/2026 10:00')
            ->assertSee('HNO (hasta nueva orden)')
            ->assertSee('Ceftriaxona')->assertSee('1000 mg')->assertSee('Cada 12 horas')
            ->assertSee('40 gotas/min')
            ->assertSee('Control de temperatura cada 4 horas')->assertSee('Lavado de manos')
            ->assertSee('Órdenes anteriores (1)')
            ->assertSee('Solo lectura')
            ->assertDontSee(route('evolucion.guardar'), false)
            ->assertDontSee('name="ordenes[dieta]"', false);
    }

    public function test_hoja_sin_ordenes_lo_indica(): void
    {
        $ingreso = $this->internado();

        $this->sesion()->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('El médico todavía no ha registrado órdenes para este paciente.')
            ->assertSee('Todavía no hay controles de signos vitales.')
            ->assertSee('Todavía no hay notas de enfermería.');
    }

    public function test_hoja_no_disponible_para_emergencias_ni_atenciones_cerradas(): void
    {
        $emergencia = IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa']);
        $cerrado = $this->internado(1, ['estado' => 'Dado de alta']);

        $this->sesion()->get(route('atencion.enfermeria', $emergencia))->assertRedirect(route('atencion.index'))
            ->assertSessionHas('error', 'La hoja de enfermería solo está disponible para pacientes internados.');
        $this->get(route('atencion.enfermeria', $cerrado))->assertRedirect(route('atencion.index'));
    }

    public function test_enfermera_registra_signos_desde_la_hoja(): void
    {
        $ingreso = $this->internado();
        $datos = ['_formulario' => 'signos-enfermeria', '_vista' => 'enfermeria', 'id_ingreso' => $ingreso->getKey(), 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 11:45:00'];

        $this->sesion()->post('/signos-vitales', $datos + ['presion_sistolica' => 118, 'presion_diastolica' => 76, 'temperatura' => 37.4, 'observaciones' => 'Sin novedad'])
            ->assertSessionHasNoErrors()->assertRedirect(route('atencion.enfermeria', $ingreso))->assertSessionHas('exito', 'Signos vitales registrados correctamente.');

        $this->assertDatabaseHas('tbl_signos_vitales', ['id_ingreso' => $ingreso->getKey(), 'id_personal' => 2, 'presion_sistolica' => 118, 'temperatura' => 37.4]);
        $this->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('118/76')->assertSee('Sin novedad')->assertSee('Laura Enfermera')
            ->assertSee('Último control de signos: 11/10/2026 11:45');
    }

    public function test_control_de_signos_vacio_o_futuro_se_rechaza(): void
    {
        $ingreso = $this->internado();
        $datos = ['_formulario' => 'signos-enfermeria', '_vista' => 'enfermeria', 'id_ingreso' => $ingreso->getKey(), 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 11:45:00'];

        $this->sesion()->post('/signos-vitales', $datos)->assertSessionHasErrors(['signos' => 'Registre al menos un signo vital.']);
        $this->post('/signos-vitales', array_replace($datos, ['fecha_hora' => '2026-10-11 19:00:00', 'temperatura' => 37]))->assertSessionHasErrors('fecha_hora');

        $this->assertDatabaseCount('tbl_signos_vitales', 0);
    }

    public function test_expedientes_lista_pacientes_y_abre_su_expediente(): void
    {
        $internado = $this->internado();
        $emergencia = IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa']);
        DB::table('tbl_paciente')->where('id_paciente', $internado->id_paciente)->update(['nombres' => 'Carlos', 'apellidos' => 'Ramírez', 'dpi' => '2999888770101']);
        $sinAtencion = DB::table('tbl_paciente')->insertGetId(['numero_expediente' => 'EXP-SIN', 'nombres' => 'Ana', 'apellidos' => 'Solís', 'telefono' => '5555-5555', 'id_usuario_creacion' => 1]);
        $inactivo = DB::table('tbl_paciente')->insertGetId(['numero_expediente' => 'EXP-INA', 'nombres' => 'Pedro', 'apellidos' => 'Zeta', 'telefono' => '5555-5555', 'estado' => 'Inactivo', 'id_usuario_creacion' => 1]);

        $this->sesion()->get(route('expedientes.index'))->assertOk()
            ->assertSee('Expedientes')
            ->assertSee('Carlos Ramírez')->assertSee('Internado')->assertSee('En consulta')->assertSee('Sin atención activa')
            ->assertSee(route('pacientes.expediente', $internado->id_paciente))
            ->assertSee(route('pacientes.expediente', $sinAtencion))
            ->assertDontSee(route('pacientes.expediente', $inactivo));

        $this->get(route('expedientes.index', ['buscar' => '2999888770101']))->assertOk()
            ->assertViewHas('pacientes', fn ($lista) => $lista->pluck('id_paciente')->all() === [$internado->id_paciente]);
        $this->get(route('expedientes.index', ['estado' => 'Inactivo']))->assertOk()
            ->assertViewHas('pacientes', fn ($lista) => $lista->pluck('id_paciente')->all() === [$inactivo]);
        $this->get(route('expedientes.index', ['estado' => 'Otro']))->assertSessionHasErrors('estado');
        $this->assertNotNull($emergencia);

        $this->get(route('pacientes.expediente', $internado->id_paciente))->assertOk()
            ->assertSee('href="'.route('expedientes.index').'"', false)->assertSee('Volver a expedientes');
        $this->get(route('pacientes.expediente', ['paciente' => $internado->id_paciente, 'desde' => 'pacientes']))->assertOk()
            ->assertSee('Volver a pacientes')->assertDontSee('Volver a expedientes');
    }

    public function test_menu_incluye_expedientes(): void
    {
        $this->sesion()->get('/inicio')->assertOk()->assertSee('href="'.route('expedientes.index').'"', false);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function nota(array $cambios = []): array
    {
        return array_replace(['_formulario' => 'nota-enfermeria', 'fecha_hora' => '2026-10-11 11:45:00', 'estado_paciente' => 'Mejoró'], $cambios);
    }

    public function test_enfermera_registra_nota_con_signos_actividades_y_aviso(): void
    {
        $ingreso = $this->internado();
        $datos = $this->nota([
            'presion_sistolica' => 118, 'presion_diastolica' => 76, 'temperatura' => 37.8,
            'actividades' => ['Cambio de posición', 'Cambio de solución'], 'actividad_otra' => 'Control de glucosa',
            'observaciones' => 'Tolera dieta blanda. Se cambió suero salino 500 ml.', 'aviso_medico' => '1',
        ]);

        // Al avisar hay que indicar a qué médico, y debe tener cargo médico.
        $this->sesion()->post(route('notas-enfermeria.guardar', $ingreso), $datos)->assertSessionHasErrors(['id_personal_avisado' => 'Seleccione a qué médico se avisó.']);
        $this->post(route('notas-enfermeria.guardar', $ingreso), $datos + ['id_personal_avisado' => 2])->assertSessionHasErrors('id_personal_avisado');
        $this->assertDatabaseCount('tbl_atencion_enfermeria', 0);

        $this->post(route('notas-enfermeria.guardar', $ingreso), $datos + ['id_personal_avisado' => 1])->assertSessionHasNoErrors()->assertRedirect(route('atencion.enfermeria', $ingreso))
            ->assertSessionHas('exito', 'Nota de enfermería guardada correctamente.')
            ->assertSessionHas('borrador_clinico_guardado', 'nota-enfermeria');

        $nota = NotaEnfermeria::with('actividades')->sole();
        $this->assertSame('Mejoró', $nota->estado_paciente);
        $this->assertTrue($nota->aviso_medico);
        $this->assertSame(1, (int) $nota->id_personal_avisado);
        $this->assertSame(2, (int) $nota->id_personal);
        $this->assertSame(['Cambio de posición', 'Cambio de solución', 'Control de glucosa'], $nota->listaActividades());
        $this->assertDatabaseHas('tbl_signos_vitales', ['id_signos_vitales' => $nota->id_signos_vitales, 'id_ingreso' => $ingreso->getKey(), 'tipo_control' => 'Rutina', 'presion_sistolica' => 118, 'temperatura' => 37.8]);

        $this->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('Turno mañana')->assertSee('Laura Enfermera')->assertSee('Mejoró')
            ->assertSee('<span class="doc-signo-valor">118/76<small>mmHg</small></span>', false)->assertSee('37.8<small>°C</small>', false)
            ->assertSeeInOrder(['Se avisó al médico', 'Rosa Médica'])->assertSee('name="id_personal_avisado"', false)
            ->assertSeeInOrder(['Cambio de posición', 'Cambio de solución', 'Control de glucosa'])
            ->assertSee('Tolera dieta blanda.')->assertSee('Se avisó al médico')
            ->assertSee('Último control de signos: 11/10/2026 11:45');
    }

    public function test_nota_sin_signos_no_crea_control(): void
    {
        $ingreso = $this->internado();

        $this->sesion()->post(route('notas-enfermeria.guardar', $ingreso), $this->nota(['estado_paciente' => 'Sin cambios', 'observaciones' => 'Paciente duerme tranquilo.']))
            ->assertSessionHasNoErrors();

        $this->assertNull(NotaEnfermeria::sole()->id_signos_vitales);
        $this->assertDatabaseCount('tbl_signos_vitales', 0);
        $this->assertDatabaseCount('tbl_atencion_enfermeria_actividad', 0);
    }

    public function test_nota_vacia_incompleta_o_futura_se_rechaza(): void
    {
        $ingreso = $this->internado();
        $ruta = route('notas-enfermeria.guardar', $ingreso);

        $this->sesion()->post($ruta, $this->nota())->assertSessionHasErrors(['nota' => 'Registre signos vitales, marque una actividad o escriba la nota.']);
        $this->post($ruta, $this->nota(['estado_paciente' => null, 'observaciones' => 'Sin novedad']))->assertSessionHasErrors(['estado_paciente' => 'Indique cómo está el paciente.']);
        $this->post($ruta, $this->nota(['fecha_hora' => '2026-10-11 12:30:00', 'observaciones' => 'Sin novedad']))->assertSessionHasErrors(['fecha_hora' => 'La fecha y hora no pueden ser futuras.']);
        $this->post($ruta, $this->nota(['fecha_hora' => '2026-10-10 08:00:00', 'observaciones' => 'Sin novedad']))->assertSessionHasErrors('fecha_hora');
        $this->post($ruta, $this->nota(['actividades' => ['Inventada']]))->assertSessionHasErrors('actividades.0');
        $this->post($ruta, $this->nota(['presion_sistolica' => 120]))->assertSessionHasErrors('presion_diastolica');

        $this->assertDatabaseCount('tbl_atencion_enfermeria', 0);
        $this->assertDatabaseCount('tbl_signos_vitales', 0);
    }

    public function test_nota_solo_para_pacientes_internados(): void
    {
        $emergencia = IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa']);
        $cerrado = $this->internado(1, ['estado' => 'Dado de alta']);

        $this->sesion()->post(route('notas-enfermeria.guardar', $emergencia), $this->nota(['observaciones' => 'Nota']))
            ->assertSessionHasErrors(['nota' => 'Solo se registran notas de enfermería de pacientes internados.']);
        $this->post(route('notas-enfermeria.guardar', $cerrado), $this->nota(['observaciones' => 'Nota']))->assertSessionHasErrors();

        $this->assertDatabaseCount('tbl_atencion_enfermeria', 0);
    }

    public function test_turno_se_calcula_con_la_hora(): void
    {
        $turno = fn (string $fecha): string => (new NotaEnfermeria(['fecha_hora' => $fecha]))->turno();

        $this->assertSame('Noche', $turno('2026-10-11 06:59:00'));
        $this->assertSame('Mañana', $turno('2026-10-11 07:00:00'));
        $this->assertSame('Mañana', $turno('2026-10-11 12:59:00'));
        $this->assertSame('Tarde', $turno('2026-10-11 13:00:00'));
        $this->assertSame('Noche', $turno('2026-10-11 19:00:00'));
    }

    public function test_expediente_muestra_cada_nota_como_documento_ordenado_por_fecha(): void
    {
        $ingreso = $this->internado();
        $this->evolucion($ingreso, '2026-10-10 19:00:00', 'Neumonía', null);
        $this->evolucion($ingreso, '2026-10-11 08:00:00', 'Neumonía en tratamiento', $this->ordenes('Blanda', 'Ampicilina'));
        $this->sesion()->post(route('notas-enfermeria.guardar', $ingreso), $this->nota(['fecha_hora' => '2026-10-10 22:15:00', 'estado_paciente' => 'Empeoró', 'temperatura' => 39.1, 'observaciones' => 'Fiebre persistente.', 'aviso_medico' => '1', 'id_personal_avisado' => 1]))->assertSessionHasNoErrors();
        $this->post(route('notas-enfermeria.guardar', $ingreso), $this->nota(['fecha_hora' => '2026-10-11 09:30:00', 'actividades' => ['Curación']]))->assertSessionHasNoErrors();
        $evolucion = DB::table('tbl_evolucion_clinica')->where('fecha_hora', '2026-10-11 08:00:00')->value('id_evolucion');
        [$primera, $segunda] = NotaEnfermeria::orderBy('fecha_hora')->pluck('id_atencion_enfermeria')->all();

        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
            ->assertSeeInOrder(['Hoja de ingreso', 'Valoración inicial', 'Nota de enfermería #1', 'Empeoró', 'Aviso al médico', 'Evolución clínica #'.$evolucion, 'Nota de enfermería #2', 'Mejoró'])
            ->assertSee('Todos (5)')->assertSee('Valoración inicial (1)')->assertSee('Evoluciones (1)')->assertSee('Notas de enfermería (2)')
            ->assertSee('data-tipo-documento="nota"', false)->assertSee('data-tipo-documento="evolucion"', false)->assertSee('data-tipo-documento="valoracion"', false)
            ->assertSee('data-bs-target="#documento-nota-'.$primera.'"', false)
            ->assertSee('id="documento-nota-'.$segunda.'"', false)
            ->assertSeeInOrder(['Turno', 'Noche'])->assertSee('39.1<small>°C</small>', false)->assertSee('Fiebre persistente.')->assertSee('Se avisó al médico')
            ->assertSee('<i class="bi bi-check2" aria-hidden="true"></i> Curación', false);
    }

    public function test_migracion_de_notas_actualiza_tabla_anterior_y_es_repetible(): void
    {
        Schema::drop('tbl_atencion_enfermeria_actividad');
        Schema::drop('tbl_atencion_enfermeria');
        DB::unprepared('CREATE TABLE tbl_atencion_enfermeria (id_atencion_enfermeria INTEGER PRIMARY KEY AUTOINCREMENT, id_ingreso INT NOT NULL, id_personal INT NOT NULL, fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, actividad VARCHAR(180) NOT NULL, observaciones TEXT NULL, id_usuario_creacion INT NOT NULL, id_usuario_modificacion INT NULL, fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $migracion = require database_path('migrations/2026_10_26_010043_add_notas_to_tbl_atencion_enfermeria.php');

        $migracion->up();
        $migracion->up();

        $this->assertTrue(Schema::hasColumns('tbl_atencion_enfermeria', ['estado_paciente', 'id_signos_vitales', 'aviso_medico']));
        $this->assertTrue(Schema::hasTable('tbl_atencion_enfermeria_actividad'));
        $avisado = require database_path('migrations/2026_11_02_053000_add_medico_avisado_to_tbl_atencion_enfermeria.php');
        $avisado->up();
        $avisado->up();
        $this->assertTrue(Schema::hasColumn('tbl_atencion_enfermeria', 'id_personal_avisado'));
        $this->assertTrue(collect(Schema::getColumns('tbl_atencion_enfermeria'))->firstWhere('name', 'actividad')['nullable']);
    }

    public function test_formulario_de_nota_conserva_borrador_y_valida_en_linea(): void
    {
        $ingreso = $this->internado();

        $this->sesion()->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('data-borrador', false)
            ->assertSee('data-validar-en-linea', false)
            ->assertSee('data-al-menos-uno="presion_sistolica', false)
            ->assertSee('data-aviso-borrador-pagina="nota-enfermeria"', false)
            ->assertSee('Descartar y empezar de nuevo')
            ->assertSee('data-requerido-con="presion_diastolica"', false)
            ->assertSee('sanatorio:borrador:', false);
    }

    public function test_errores_del_servidor_aparecen_junto_a_cada_campo(): void
    {
        $ingreso = $this->internado();

        $this->sesion()->from(route('atencion.enfermeria', $ingreso))
            ->post(route('notas-enfermeria.guardar', $ingreso), $this->nota(['temperatura' => 60, 'observaciones' => 'Nota']))
            ->assertRedirect(route('atencion.enfermeria', $ingreso));

        $this->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('No se guardó la nota.')
            ->assertSee('<div class="invalid-feedback d-block">La temperatura debe estar entre 25 y 50.</div>', false)
            ->assertSee('value="60"', false)
            ->assertSee('bootstrap.Modal.getOrCreateInstance(modal).show()', false);
    }

    public function test_expedientes_muestra_primero_al_paciente_registrado_mas_reciente(): void
    {
        $antiguo = DB::table('tbl_paciente')->insertGetId(['numero_expediente' => 'EXP-A', 'nombres' => 'Ana', 'apellidos' => 'Alvarado', 'telefono' => '5555-5555', 'id_usuario_creacion' => 1, 'fecha_registro' => '2026-10-01 08:00:00']);
        $nuevo = DB::table('tbl_paciente')->insertGetId(['numero_expediente' => 'EXP-Z', 'nombres' => 'Emilio', 'apellidos' => 'Robles', 'telefono' => '5555-5555', 'id_usuario_creacion' => 1, 'fecha_registro' => '2026-10-11 09:00:00']);

        $this->sesion()->get(route('expedientes.index'))->assertOk()
            ->assertViewHas('pacientes', fn ($lista) => $lista->pluck('id_paciente')->take(2)->all() === [$nuevo, $antiguo])
            ->assertSeeInOrder(['Emilio Robles', 'Ana Alvarado']);
    }

    public function test_inicio_muestra_cifras_reales_y_turno_actual(): void
    {
        $this->internado(1);
        IngresoFactory::new()->create(['tipo_atencion' => 'Consulta externa', 'fecha_ingreso' => '2026-10-11 09:00:00']);

        $this->sesion()->get('/inicio')->assertOk()
            ->assertSeeInOrder(['1', 'Paciente internado'])
            ->assertSeeInOrder(['1', 'Consulta externa hoy'])
            ->assertSee('50 %')->assertSee('1 de 2 habitaciones')
            ->assertSee('Pendientes por cerrar')->assertSee('Todo al día')
            ->assertSee('Turno actual')->assertSee('Mañana · 07:00 – 13:00')->assertSee('1 paciente internado')
            ->assertSee('Resumen del turno de mañana');
    }
}
