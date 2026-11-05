<?php

namespace Tests\Feature;

use App\Http\Middleware\AvisarSinCambios;
use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Models\NotaEnfermeria;
use App\Services\DatosReportes;
use App\Services\OrdenesMedicas;
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
            ->assertSee('Carlos Ramírez')->assertSee('Hospitalizado')->assertSee('En consulta')->assertSee('Sin ingresos')
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

    public function test_nota_registra_ordenes_medicas_aplicadas_y_no_aplicadas(): void
    {
        $ingreso = $this->internado();
        $this->evolucion($ingreso, '2026-10-10 13:00:00', 'Neumonía', $this->ordenes('Blanda', 'Ceftriaxona'));
        $idOrdenes = (int) DB::table('tbl_evolucion_clinica')->max('id_evolucion');
        $ruta = route('notas-enfermeria.guardar', $ingreso);

        // La nota ofrece cada orden vigente para marcarla.
        $this->sesion()->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSeeInOrder(['Dieta blanda', 'Suero Salina · 500 ml · 40 gotas/min', 'Ceftriaxona · 1000 mg · Intravenosa · cada 12 horas', 'Control de temperatura cada 4 horas'])
            ->assertSee('name="ordenes[2][estado]"', false)->assertSee('Todavía no se ha registrado en una nota.');

        // Solo con órdenes marcadas la nota no está vacía; si no se aplicó, hay que decir por qué.
        $base = $this->nota(['ordenes_de' => $idOrdenes]);
        $this->post($ruta, $base + ['ordenes' => [2 => ['estado' => 'No aplicada']]])->assertSessionHasErrors(['ordenes.2.motivo' => 'Escriba por qué no se aplicó.']);
        $this->post($ruta, $base + ['ordenes' => [9 => ['estado' => 'Aplicada']]])->assertSessionHasErrors('ordenes');
        $this->post($ruta, ['ordenes_de' => $idOrdenes - 1] + $base + ['ordenes' => [0 => ['estado' => 'Aplicada']]])->assertSessionHasErrors('ordenes');
        $this->assertDatabaseCount('tbl_atencion_enfermeria', 0);

        // Todas las órdenes vigentes son obligatorias.
        $marcadas = [
            0 => ['estado' => 'Aplicada'],
            1 => ['estado' => ''],
            2 => ['estado' => 'Aplicada', 'motivo' => 'no se guarda'],
            3 => ['estado' => 'No aplicada', 'motivo' => 'Paciente dormido, se tomará a las 12:00'],
        ];
        $this->post($ruta, $base + ['ordenes' => $marcadas])->assertSessionHasErrors(['ordenes.1.estado' => 'Indique si «Suero Salina · 500 ml · 40 gotas/min» se aplicó o no.']);
        $this->assertDatabaseCount('tbl_atencion_enfermeria', 0);
        $marcadas[1] = ['estado' => 'No aplicada', 'motivo' => 'Se terminó el suero; se pidió a farmacia'];
        $this->post($ruta, $base + ['ordenes' => $marcadas])->assertSessionHasNoErrors();
        $nota = NotaEnfermeria::with('ordenes')->sole();
        $this->assertSame([
            ['Dieta', 'Dieta blanda', true, null],
            ['Solución', 'Suero Salina · 500 ml · 40 gotas/min', false, 'Se terminó el suero; se pidió a farmacia'],
            ['Medicamento', 'Ceftriaxona · 1000 mg · Intravenosa · cada 12 horas', true, null],
            ['Indicaciones', 'Control de temperatura cada 4 horas', false, 'Paciente dormido, se tomará a las 12:00'],
        ], $nota->ordenes->map(fn ($o) => [$o->tipo, $o->descripcion, $o->aplicada, $o->motivo])->all());
        $this->assertSame([$idOrdenes], $nota->ordenes->pluck('id_evolucion')->unique()->values()->all());

        // La hoja dice cuándo se aplicó y cuándo toca la siguiente dosis; la nota y su PDF muestran el resultado.
        $this->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('Última vez: 11/10 11:45')->assertSee('toca a las 23:45')->assertSee('Toca a las 23:45')
            ->assertSee('Se cumplieron')->assertSee('Motivo: Paciente dormido, se tomará a las 12:00');
        $this->get(route('pacientes.expediente', $ingreso->paciente))->assertOk()->assertSee('No se cumplieron:');
        $doc = app(DatosReportes::class)->documento('enfermeria', NotaEnfermeria::sole());
        $this->assertSame('Se aplicó', $doc['secciones']['Órdenes médicas']['Medicamento: Ceftriaxona · 1000 mg · Intravenosa · cada 12 horas']);
    }

    public function test_nota_con_hora_anterior_a_las_ordenes_no_las_exige_y_el_medico_puede_cancelarlas(): void
    {
        $ingreso = $this->internado();
        $this->evolucion($ingreso, '2026-10-11 08:00:00', 'Neumonía', $this->ordenes('Blanda', 'Ceftriaxona'));
        $evolucion = (int) DB::table('tbl_evolucion_clinica')->max('id_evolucion');
        $ruta = route('notas-enfermeria.guardar', $ingreso);

        // Una nota registrada después pero con hora anterior a las órdenes no las incluye.
        $this->sesion()->post($ruta, $this->nota(['fecha_hora' => '2026-10-11 07:30:00', 'observaciones' => 'Nota atrasada']))->assertSessionHasNoErrors();
        $this->get(route('atencion.enfermeria', $ingreso))->assertSee('data-ordenes-desde="2026-10-11T08:00"', false)->assertSee('después de la hora de esta nota');

        // Solo el médico que las indicó (personal 1) puede cancelarlas; la enfermera (usuario 1, personal 2) no.
        $cancelar = route('evolucion.cancelar-ordenes', $evolucion);
        $this->post($cancelar, ['motivo_cancelacion' => 'Prueba'])->assertSessionHas('error', 'Solo el médico que indicó estas órdenes puede cancelarlas.');
        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 1, 'id_rol' => 1, 'nombre_usuario' => 'medica', 'contrasena' => 'solo-pruebas']);
        $this->withSession(['id_usuario' => 2]);
        $this->get(route('pacientes.expediente', $ingreso->paciente))->assertOk()->assertSee('Cancelar estas órdenes');
        $this->post($cancelar, ['motivo_cancelacion' => ''])->assertSessionHasErrors(['motivo_cancelacion' => 'Escriba por qué se cancelan las órdenes.']);
        $this->post($cancelar, ['motivo_cancelacion' => 'Reacción alérgica al antibiótico'])->assertSessionHas('exito');
        $ordenes = json_decode(DB::table('tbl_evolucion_clinica')->where('id_evolucion', $evolucion)->value('ordenes_medicas'), true);
        $this->assertSame(['Reacción alérgica al antibiótico', 1, 'Rosa Médica'], [$ordenes['cancelacion']['motivo'], $ordenes['cancelacion']['id_personal'], $ordenes['cancelacion']['medico']]);
        $this->assertSame('Ceftriaxona', $ordenes['medicamentos'][0]['nombre'], 'Lo que se ordenó se conserva.');
        $this->post($cancelar, ['motivo_cancelacion' => 'Otra vez'])->assertSessionHas('error', 'Estas órdenes ya estaban canceladas.');

        // Enfermería ve el aviso y la nota ya no exige ni ofrece esas órdenes.
        $this->withSession(['id_usuario' => 1])->get(route('atencion.enfermeria', $ingreso))->assertOk()
            ->assertSee('Estas órdenes fueron canceladas')->assertSee('Reacción alérgica al antibiótico')->assertDontSee('name="ordenes_de"', false);
        $this->post($ruta, $this->nota(['fecha_hora' => '2026-10-11 11:50:00', 'observaciones' => 'Sin órdenes vigentes']))->assertSessionHasNoErrors();
        $this->get(route('pacientes.expediente', $ingreso->paciente))->assertSee('Órdenes canceladas')->assertDontSee('Cancelar estas órdenes')->assertDontSee('Reactivar estas órdenes');

        // El médico que las indicó puede reactivarlas desde la misma ventana (con «Confirmar cambios» en el pie).
        $reactivar = route('evolucion.reactivar-ordenes', $evolucion);
        $this->post($reactivar)->assertSessionHas('error', 'Solo el médico que indicó estas órdenes puede reactivarlas.');
        $this->withSession(['id_usuario' => 2]);
        $this->get(route('pacientes.expediente', $ingreso->paciente))->assertOk()->assertDontSee('Reactivar estas órdenes')->assertDontSee('data-lapiz-ordenes');
        $this->post($reactivar)->assertSessionHas('exito', 'Órdenes médicas reactivadas. Enfermería vuelve a verlas como vigentes.');
        $this->assertNull(OrdenesMedicas::cancelacion(EvolucionClinica::find($evolucion)->ordenes_medicas));
        $this->post($reactivar)->assertSessionHas('error', 'Estas órdenes ya están vigentes.');

        // Reactivar y corregir de una vez: el lápiz habilita los campos y se guarda todo junto.
        DB::table('tbl_evolucion_clinica')->where('id_evolucion', $evolucion)->update(['id_usuario_creacion' => 2]);
        $this->post($cancelar, ['motivo_cancelacion' => 'Cancelada por error'])->assertSessionHas('exito');
        $this->get(route('pacientes.expediente', $ingreso->paciente))->assertOk()->assertDontSee('data-lapiz-ordenes')->assertDontSee('Reactivar estas órdenes');
        $this->post($reactivar, ['editar_ordenes' => 1, 'ordenes' => ['dieta' => '']])->assertSessionHasErrors('ordenes.dieta');
        $this->assertNotNull(OrdenesMedicas::cancelacion(EvolucionClinica::find($evolucion)->ordenes_medicas), 'Si la corrección no es válida, no se reactiva.');
        $this->post($reactivar, ['editar_ordenes' => 1, 'ordenes' => ['dieta' => 'Líquida', 'indicaciones' => 'Reposo']])->assertSessionHas('exito', 'Órdenes médicas reactivadas y corregidas. Enfermería ya ve las nuevas.');
        $corregidas = EvolucionClinica::find($evolucion)->ordenes_medicas;
        $this->assertSame(['Líquida', 'Reposo', null], [$corregidas['dieta'], $corregidas['indicaciones'], OrdenesMedicas::cancelacion($corregidas)]);
        $this->withSession(['id_usuario' => 1])->get(route('atencion.enfermeria', $ingreso))->assertOk()->assertSee('name="ordenes_de"', false);
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

        $this->sesion()->post($ruta, $this->nota())->assertSessionHasErrors(['nota' => 'Registre signos vitales, marque una orden o actividad, o escriba la nota.']);
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
        $todas = ['ordenes_de' => $evolucionOrdenes = (int) DB::table('tbl_evolucion_clinica')->where('fecha_hora', '2026-10-11 08:00:00')->value('id_evolucion'), 'ordenes' => array_fill(0, 4, ['estado' => 'Aplicada'])];
        $this->post(route('notas-enfermeria.guardar', $ingreso), $this->nota(['fecha_hora' => '2026-10-11 09:30:00', 'actividades' => ['Curación']] + $todas))->assertSessionHasNoErrors();
        $evolucion = DB::table('tbl_evolucion_clinica')->where('fecha_hora', '2026-10-11 08:00:00')->value('id_evolucion');
        [$primera, $segunda] = NotaEnfermeria::orderBy('fecha_hora')->pluck('id_atencion_enfermeria')->all();

        $this->get(route('pacientes.expediente', $ingreso->id_paciente))->assertOk()
            ->assertSeeInOrder(['Documentos (5)', 'Nota de enfermería #2', 'Mejoró', 'Evolución clínica #'.$evolucion, 'Nota de enfermería #1', 'Empeoró', 'Aviso al médico', 'Valoración inicial', 'Hoja de ingreso'])
            ->assertSee('Todos (2)')->assertSee('Valoración inicial (1)')->assertSee('Evoluciones (1)')->assertSee('Notas de enfermería (1)')
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

    public function test_autor_edita_nota_sin_duplicarla_y_respeta_plazo(): void
    {
        $i = $this->internado();
        $this->sesion();
        $this->post(route('notas-enfermeria.guardar', $i), $this->nota(['temperatura' => 36.5]))->assertSessionHasNoErrors();
        $n = NotaEnfermeria::sole();
        $signo = $n->id_signos_vitales;
        $this->get(route('atencion.enfermeria', $i))->assertOk()->assertSee('data-editar-nota', false);
        // La corrección usa los mismos controles que «Nueva nota»: estado con botones, signos a elección y actividades como botones.
        $edicion = 'editar-nota-'.$n->getKey();
        $this->get(route('atencion.enfermeria', $i))->assertOk()
            ->assertSee('id="'.$edicion.'-estado-0" value="Mejoró" checked', false)
            ->assertSee('id="'.$edicion.'-elegir-temperatura" data-elegir-signo="temperatura" autocomplete="off" checked', false)
            ->assertSee('id="'.$edicion.'-actividad-otra" data-mostrar-otra-actividad', false)
            ->assertDontSee('PA sistólica (mmHg)');
        // Como las demás hojas, se indica hasta cuándo puede corregirla su autor.
        $this->get(route('pacientes.expediente', $i->id_paciente))->assertOk()->assertSee('Puede editarla hasta las')->assertSee('Puede editarla hasta el');
        $this->put(route('notas-enfermeria.actualizar', $n), $this->nota(['temperatura' => 37.1, 'observaciones' => 'Nota corregida']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tbl_atencion_enfermeria', 1);
        $this->assertDatabaseCount('tbl_signos_vitales', 1);
        $this->assertSame($signo, $n->fresh()->id_signos_vitales);
        $this->assertSame('Nota corregida', $n->fresh()->observaciones);
        // La corrección queda marcada como «Se editó»; volver a guardar sin cambiar nada no cuenta como edición.
        $this->get(route('pacientes.expediente', $i->id_paciente))->assertOk()->assertSee('Se editó el');
        $this->put(route('notas-enfermeria.actualizar', $n), $this->nota(['temperatura' => '37.10', 'observaciones' => 'Nota corregida']))->assertSessionHasNoErrors()->assertSessionHas('exito', AvisarSinCambios::MENSAJE);
        $this->assertSame(1, DB::table('tbl_bitacora')->where('accion', 'Edición de nota de enfermería')->where('id_registro', $n->getKey())->count());
        $this->put(route('notas-enfermeria.actualizar', $n), $this->nota(['temperatura' => 99]))->assertSessionHasErrors('temperatura');
        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 1, 'id_rol' => 1, 'nombre_usuario' => 'otro', 'contrasena' => 'prueba']);
        $this->withSession(['id_usuario' => 2])->put(route('notas-enfermeria.actualizar', $n), $this->nota())->assertForbidden();
        $this->get(route('pacientes.expediente', $i->id_paciente))->assertOk()->assertDontSee('Puede editarla hasta')->assertDontSee('data-editar-nota=', false)
            ->assertSee('Solo quien la registró puede corregirla, durante las primeras 12 horas.');
        $this->withSession(['id_usuario' => 1]);
        $i->update(['estado' => 'Dado de alta']);
        $this->put(route('notas-enfermeria.actualizar', $n), $this->nota())->assertForbidden();
        $i->update(['estado' => 'Activo']);
        $this->travel(13)->hours();
        $this->put(route('notas-enfermeria.actualizar', $n), $this->nota())->assertForbidden();
    }

    public function test_cambia_habitacion_desde_enfermeria_y_regresa_a_la_hoja(): void
    {
        $i = $this->internado();
        $this->sesion();
        $this->get(route('atencion.enfermeria', $i))->assertOk()->assertSee('Cambiar habitación');
        $h = $i->hospitalizacion;
        $this->patch(route('hospitalizacion.cambiar-habitacion', $h), ['_desde' => 'enfermeria', 'id_habitacion' => 2])->assertRedirect(route('atencion.enfermeria', $i));
        $this->assertSame(2, $h->fresh()->habitacionActual->id_habitacion);
        $this->assertSame(2, $h->historialHabitaciones()->count());
        $this->patch(route('hospitalizacion.cambiar-habitacion', $h), ['_desde' => 'enfermeria', 'id_habitacion' => 2])->assertSessionHasErrors('id_habitacion');
    }

    public function test_traslado_se_guarda_con_la_nota_o_se_revierte_si_falla(): void
    {
        $i = $this->internado();
        $this->sesion();
        $this->get(route('atencion.enfermeria', $i))->assertOk()->assertDontSee('data-bs-target="#modalHabitacionEnfermeria"', false)->assertSee('data-traslado-nota', false);
        $this->post(route('notas-enfermeria.guardar', $i), $this->nota(['cambiar_habitacion' => 1, 'id_habitacion' => 1]))->assertSessionHasErrors('id_habitacion');
        $this->assertDatabaseCount('tbl_atencion_enfermeria', 0);
        $this->post(route('notas-enfermeria.guardar', $i), $this->nota(['cambiar_habitacion' => 1, 'id_habitacion' => 2]))->assertSessionHasNoErrors();
        $this->assertSame(2, $i->fresh()->hospitalizacion->habitacionActual->id_habitacion);
        $this->assertStringContainsString('110 → 102', NotaEnfermeria::sole()->observaciones);
    }
}
