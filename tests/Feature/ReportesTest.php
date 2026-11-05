<?php

namespace Tests\Feature;

use App\Models\AltaMedica;
use App\Models\Cirugia;
use App\Models\DocumentoClinico;
use App\Models\EvolucionClinica;
use App\Models\NotaEnfermeria;
use App\Models\SignoVital;
use App\Services\DatosReportes;
use App\Services\PdfReportes;
use App\Services\ReportesAmpliados;
use Database\Factories\IngresoFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class ReportesTest extends TestCase
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

    public function test_filtros_y_clasificacion_de_documentos(): void
    {
        $i = IngresoFactory::new()->create(['fecha_ingreso' => '2026-10-10 10:00:00']);
        $a = EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-10 10:05:00', 'diagnostico' => 'Inicial de prueba', 'id_usuario_creacion' => 1]);
        $b = EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 2, 'fecha_hora' => '2026-10-11 23:59:59', 'estado_mejoria' => 'Mejoró', 'diagnostico' => 'Evolución de prueba', 'id_usuario_creacion' => 1]);
        $datos = app(DatosReportes::class);
        $this->assertSame([$a->getKey()], $datos->consulta('valoraciones')->pluck('id_evolucion')->all());
        $this->assertSame([$b->getKey()], $datos->consulta('evoluciones', ['desde' => '2026-10-11', 'hasta' => '2026-10-11', 'personal' => 2, 'paciente' => $i->paciente->numero_expediente])->pluck('id_evolucion')->all());
        $this->assertSame(0, $datos->consulta('evoluciones', ['personal' => 1])->count());
        $this->withSession(['id_usuario' => 1])->get(route('reportes.index', ['tipo' => 'evoluciones']))->assertOk()->assertSee('Evolución de prueba')->assertSee('Laura Enfermera');
        $this->getJson(route('reportes.pdf', ['desde' => '2026-10-12', 'hasta' => '2026-10-10']))->assertUnprocessable();
        $this->getJson(route('reportes.pdf', ['tipo' => 'inexistente']))->assertUnprocessable();
    }

    public function test_pdf_real_autor_y_bitacora(): void
    {
        $i = IngresoFactory::new()->create();
        $r = EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-10 11:00:00', 'diagnostico' => 'Diagnóstico de prueba', 'observaciones' => str_repeat('Descripción extensa para verificar saltos de página. ', 150), 'ordenes_medicas' => ['dieta' => 'Normal', 'medicamentos' => [['nombre' => 'Producto ficticio', 'dosis' => 1, 'via' => 'Oral', 'frecuencia' => 8]]], 'id_usuario_creacion' => 1]);
        $doc = app(DatosReportes::class)->documento('valoraciones', $r);
        $this->assertSame('Rosa Médica', $doc['responsable']);
        $this->assertStringContainsString('Producto ficticio', $doc['secciones']['Órdenes médicas']['Medicamento 1']);
        $res = $this->withSession(['id_usuario' => 1])->get(route('reportes.documento', ['valoraciones', $r->getKey(), 'descargar' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $res->getContent());
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        // Las hojas sueltas y los listados no se registran en la bitácora: solo los expedientes.
        $this->assertDatabaseCount('tbl_bitacora', 0);
        if (getenv('REPORT_PDF_QA')) {
            file_put_contents(getenv('REPORT_PDF_QA').'/documento.pdf', $res->getContent());
        }
        $lista = $this->get(route('reportes.pdf', ['tipo' => 'valoraciones']))->assertOk();
        if (getenv('REPORT_PDF_QA')) {
            file_put_contents(getenv('REPORT_PDF_QA').'/listado.pdf', $lista->getContent());
        }
        $this->get(route('reportes.documento', ['valoraciones', 999999]))->assertNotFound();
    }

    public function test_reportes_no_omiten_notas_signos_cirugias_ni_altas(): void
    {
        $i = IngresoFactory::new()->create();
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Cirugía de prueba', 'fecha_programada' => '2026-10-10 12:00:00', 'estado' => 'Programada', 'id_usuario_creacion' => 1]);
        $s = SignoVital::create(['id_ingreso' => $i->getKey(), 'id_cirugia' => $c->getKey(), 'id_personal' => 2, 'tipo_control' => 'Recuperación', 'fecha_hora' => '2026-10-10 13:00:00', 'frecuencia_cardiaca' => 80, 'id_usuario_creacion' => 1]);
        $n = NotaEnfermeria::create(['id_ingreso' => $i->getKey(), 'id_personal' => 2, 'fecha_hora' => '2026-10-10 13:00:00', 'id_signos_vitales' => $s->getKey(), 'observaciones' => 'Nota de prueba', 'estado_paciente' => 'Sin cambios', 'id_usuario_creacion' => 1]);
        $alta = AltaMedica::create(['id_ingreso' => $i->getKey(), 'id_personal_medico' => 1, 'fecha_hora' => '2026-10-11 10:00:00', 'diagnostico_final' => 'Prueba', 'condicion_egreso' => 'Mejorado', 'id_usuario_creacion' => 1]);
        $d = app(DatosReportes::class);
        foreach (['pacientes' => $i->paciente, 'atenciones' => $i, 'enfermeria' => $n, 'signos' => $s, 'cirugias' => $c, 'altas' => $alta] as $tipo => $registro) {
            $obtenido = $d->consulta($tipo)->findOrFail($registro->getKey());
            $doc = $d->documento($tipo, $obtenido);
            $this->assertSame($i->paciente->numero_expediente, $doc['expediente']);
            $this->assertNotEmpty($doc['responsable']);
        }
        $this->assertSame(1, $d->consulta('enfermeria', ['turno' => 'Tarde'])->count());
        $this->assertSame(0, $d->consulta('enfermeria', ['turno' => 'Noche'])->count());
        $this->assertStringContainsString('80 lpm', $d->documento('enfermeria', $n)['secciones']['Signos vitales']['Mediciones']);
        $this->withSession(['id_usuario' => 1])->get(route('pacientes.expediente', $i->paciente))->assertOk()->assertSee('Descargar PDF');
    }

    public function test_no_expone_reportes_sin_permisos(): void
    {
        $this->get(route('reportes.index'))->assertRedirect();
        DB::table('tbl_rol')->where('id_rol', 1)->update(['nombre_rol' => 'Secretaria']);
        $this->asignarPermisos(1, ['Reportes' => ['Ver'], 'Pacientes' => ['Ver']]);
        $this->withSession(['id_usuario' => 1])->getJson(route('reportes.pdf', ['tipo' => 'evoluciones']))->assertForbidden();
        $this->getJson(route('reportes.documento', ['evoluciones', 1]))->assertForbidden();
        $this->get(route('reportes.index', ['tipo' => 'pacientes']))->assertOk();
    }

    public function test_limite_no_trunca_y_fecha_hasta_sin_desde_es_valida(): void
    {
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->get(route('reportes.index', ['tipo' => 'atenciones', 'hasta' => '2026-10-12']))->assertOk();
        $filas = [];
        for ($n = 0; $n < 1001; $n++) {
            $filas[] = ['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-10 11:00:00', 'tipo_control' => 'Rutina', 'id_usuario_creacion' => 1];
        }
        foreach (array_chunk($filas, 100) as $grupo) {
            DB::table('tbl_signos_vitales')->insert($grupo);
        }
        $this->getJson(route('reportes.pdf', ['tipo' => 'signos']))->assertUnprocessable()->assertJsonValidationErrors('tipo');
        $this->assertDatabaseCount('tbl_bitacora', 0);
    }

    public function test_pdf_archivado_conserva_original_y_rechaza_ruta_invalida(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $original = app(PdfReportes::class)->generar('Documento de prueba', []);
        $ruta = 'hojas/'.$i->getKey().'/prueba.pdf';
        Storage::disk('documentos_clinicos')->put($ruta, $original);
        $d = DocumentoClinico::create(['categoria' => 'Otro', 'id_paciente' => $i->id_paciente, 'id_ingreso' => $i->getKey(), 'id_personal_responsable' => 1, 'tipo_documento' => 'Evolución clínica', 'titulo' => 'Prueba', 'ruta_archivo' => $ruta, 'nombre_archivo' => 'prueba.pdf', 'tipo_archivo' => 'pdf', 'fecha_documento' => '2026-10-10 11:00:00', 'id_usuario_creacion' => 1]);
        $res = $this->withSession(['id_usuario' => 1])->get(route('reportes.archivo', $d))->assertOk();
        $this->assertSame($original, $res->getContent());
        $d->update(['ruta_archivo' => 'hojas/otra/prueba.pdf']);
        $this->get(route('reportes.archivo', $d))->assertNotFound();
    }

    public function test_imagen_archivada_se_convierte_a_pdf(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $ruta = 'hojas/'.$i->getKey().'/prueba.jpg';
        Storage::disk('documentos_clinicos')->put($ruta, file_get_contents(public_path('imagenes/logo-sanatorio.jpg')));
        $d = DocumentoClinico::create(['categoria' => 'Otro', 'id_paciente' => $i->id_paciente, 'id_ingreso' => $i->getKey(), 'id_personal_responsable' => 1, 'tipo_documento' => 'Nota de enfermería', 'titulo' => 'Imagen de prueba', 'ruta_archivo' => $ruta, 'nombre_archivo' => 'prueba.jpg', 'tipo_archivo' => 'jpg', 'fecha_documento' => '2026-10-10 11:00:00', 'id_usuario_creacion' => 1]);
        $res = $this->withSession(['id_usuario' => 1])->get(route('reportes.archivo', $d))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $res->getContent());
        if (getenv('REPORT_PDF_QA')) {
            file_put_contents(getenv('REPORT_PDF_QA').'/imagen.pdf', $res->getContent());
        }
    }

    public function test_internados_e_identificacion_filtran_paciente_y_habitacion_actual(): void
    {
        $i = IngresoFactory::new()->create();
        $i->paciente->update(['estado_identificacion' => 'No identificado', 'nombres' => null, 'apellidos' => null, 'codigo_temporal' => 'TMP-TEST-1']);
        $h = DB::table('tbl_hospitalizacion')->insertGetId(['id_ingreso' => $i->getKey(), 'fecha_inicio' => '2026-10-10 10:00:00', 'estado' => 'Activa', 'id_usuario_creacion' => 1]);
        DB::table('tbl_hospitalizacion_habitacion')->insert(['id_hospitalizacion' => $h, 'id_habitacion' => 1, 'fecha_hora_inicio' => '2026-10-10 10:00:00', 'id_usuario_creacion' => 1]);
        $d = app(DatosReportes::class);
        $this->assertSame(1, $d->consulta('internados', ['identificacion' => 'No identificado', 'habitacion' => 1])->count());
        $this->assertSame(0, $d->consulta('internados', ['habitacion' => 2])->count());
        $this->assertSame(0, $d->consulta('internados', ['identificacion' => 'Identificado'])->count());
        DB::table('tbl_hospitalizacion')->where('id_hospitalizacion', $h)->update(['estado' => 'Finalizada']);
        $this->assertSame(0, $d->consulta('internados')->count());
    }

    public function test_hospitalizaciones_solapadas_y_todos_los_pacientes(): void
    {
        $i = IngresoFactory::new()->create(['fecha_ingreso' => '2026-09-01 10:00:00']);
        $h = DB::table('tbl_hospitalizacion')->insertGetId(['id_ingreso' => $i->getKey(), 'fecha_inicio' => '2026-09-01 10:00:00', 'fecha_fin' => '2026-10-12 10:00:00', 'estado' => 'Finalizada', 'id_usuario_creacion' => 1]);
        $d = app(DatosReportes::class);
        $this->assertSame(1, $d->consulta('pacientes')->count());
        $this->assertSame(1, $d->consulta('hospitalizaciones', ['desde' => '2026-10-10', 'hasta' => '2026-10-11'])->count());
        $this->assertSame(0, $d->consulta('internados')->count());
        $this->assertSame(1, $d->consulta('pacientes', ['situacion' => 'internados', 'desde' => '2026-10-10', 'hasta' => '2026-10-11'])->count());
        $this->assertSame(0, $d->consulta('hospitalizaciones', ['desde' => '2026-10-13'])->count());
    }

    public function test_actividad_del_empleado_no_depende_de_quien_creo_paciente(): void
    {
        $i = IngresoFactory::new()->create();
        EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-10 11:00:00', 'diagnostico' => 'Consulta de Rosa', 'id_usuario_creacion' => 1]);
        EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-11 11:00:00', 'estado_mejoria' => 'Mejoró', 'diagnostico' => 'Control de Rosa', 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1]);
        $s = app(ReportesAmpliados::class);
        $this->assertCount(2, $s->actividad(['tipo' => 'personal_actividad', 'personal' => 1]));
        $this->assertCount(1, $s->actividad(['tipo' => 'personal_pacientes', 'personal' => 1]));
        $this->assertCount(0, $s->actividad(['tipo' => 'personal_actividad', 'personal' => 2]));
        $this->get(route('reportes.index', ['tipo' => 'personal_pacientes', 'personal' => 1]))->assertOk()->assertSee('2 registros de atención');
        $this->getJson(route('reportes.pdf', ['tipo' => 'personal_actividad']))->assertOk();
    }

    public function test_expediente_pdf_cronologico_incluye_pdf_subido(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create(['fecha_ingreso' => '2026-10-10 09:00:00']);
        $e = EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-10 10:00:00', 'diagnostico' => 'Primera hoja de prueba', 'id_usuario_creacion' => 1]);
        NotaEnfermeria::create(['id_ingreso' => $i->getKey(), 'id_personal' => 2, 'fecha_hora' => '2026-10-10 11:00:00', 'observaciones' => 'Nota posterior de prueba', 'id_usuario_creacion' => 1]);
        $ruta = 'hojas/'.$i->getKey().'/adjunto.pdf';
        Storage::disk('documentos_clinicos')->put($ruta, app(PdfReportes::class)->generar('Adjunto final', []));
        DocumentoClinico::create(['categoria' => 'Otro', 'id_paciente' => $i->id_paciente, 'id_ingreso' => $i->getKey(), 'id_personal_responsable' => 1, 'titulo' => 'Adjunto final', 'tipo_documento' => 'Evolución clínica', 'ruta_archivo' => $ruta, 'nombre_archivo' => 'adjunto.pdf', 'tipo_archivo' => 'pdf', 'fecha_documento' => '2026-10-10 12:00:00', 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1]);
        $docs = app(ReportesAmpliados::class)->documentos($i->paciente, []);
        $this->assertSame(['atenciones', 'valoraciones', 'enfermeria', 'archivo'], $docs->pluck('tipo')->all());
        $res = $this->get(route('reportes.expediente', [$i->paciente, 'descargar' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Descarga de expediente', 'id_registro' => $i->id_paciente, 'descripcion' => 'Se descargó el expediente completo en un PDF ('.$i->paciente->numero_expediente.', 4 documentos).']);
        $this->get(route('reportes.expediente', [$i->paciente, 'desde' => '2026-10-10']))->assertOk();
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Impresión de expediente', 'id_registro' => $i->id_paciente, 'descripcion' => 'Se abrió el expediente completo en PDF para imprimirlo o verlo ('.$i->paciente->numero_expediente.', 4 documentos). Fechas: 10/10/2026.']);
        $this->assertDatabaseCount('tbl_bitacora', 2);
        $this->assertStringStartsWith('%PDF-', $res->getContent());
        $lector = new Fpdi;
        $this->assertGreaterThanOrEqual(4, $lector->setSourceFile(StreamReader::createByString($res->getContent())));
        if (getenv('REPORT_PDF_QA')) {
            file_put_contents(getenv('REPORT_PDF_QA').'/expediente-completo.pdf', $res->getContent());
        }
        $this->assertCount(0, app(ReportesAmpliados::class)->documentos($i->paciente, ['desde' => '2026-10-11']));
        $this->getJson(route('reportes.expediente', [$i->paciente, 'desde' => '2026-10-11']))->assertUnprocessable();
    }

    public function test_expediente_en_zip_con_carpeta_datos_del_paciente_y_documentos_separados(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create(['fecha_ingreso' => '2026-10-10 09:00:00']);
        $i->paciente->update(['nombres' => 'José', 'apellidos' => 'Peña', 'dpi' => '1234567890125', 'tipo_sangre' => 'O+']);
        EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-10 10:00:00', 'diagnostico' => 'Primera hoja', 'id_usuario_creacion' => 1]);
        NotaEnfermeria::create(['id_ingreso' => $i->getKey(), 'id_personal' => 2, 'fecha_hora' => '2026-10-10 11:30:00', 'observaciones' => 'Nota de prueba', 'id_usuario_creacion' => 1]);
        $original = app(PdfReportes::class)->generar('Laboratorio', []);
        $ruta = 'hojas/'.$i->getKey().'/lab.pdf';
        Storage::disk('documentos_clinicos')->put($ruta, $original);
        DocumentoClinico::create(['categoria' => 'Otro', 'id_paciente' => $i->id_paciente, 'id_ingreso' => $i->getKey(), 'id_personal_responsable' => 1, 'titulo' => 'Resultados: hematología', 'tipo_documento' => 'Evolución clínica', 'ruta_archivo' => $ruta, 'nombre_archivo' => 'lab.pdf', 'tipo_archivo' => 'pdf', 'fecha_documento' => '2026-10-10 12:00:00', 'id_usuario_creacion' => 1]);

        $this->withSession(['id_usuario' => 1]);
        $exp = $i->paciente->numero_expediente;
        $res = $this->get(route('reportes.expediente.zip', $i->paciente))->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('attachment; filename="'.$exp.' - Jose Pena.zip"', $res->headers->get('Content-Disposition'));

        $archivo = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($archivo, $res->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($archivo, \ZipArchive::CHECKCONS));
        $nombres = [];
        for ($n = 0; $n < $zip->numFiles; $n++) {
            $nombres[] = $zip->getNameIndex($n);
            $this->assertStringStartsWith('%PDF-', $zip->getFromIndex($n));
        }
        $carpeta = $exp.' - Jose Pena/';
        $this->assertSame([
            $carpeta.'000 Datos del paciente.pdf',
            $carpeta.'001 2026-10-10 09.00 Hoja de ingreso - llegada.pdf',
            $carpeta.'002 2026-10-10 10.00 Valoracion inicial.pdf',
            $carpeta.'003 2026-10-10 11.30 Nota de enfermeria.pdf',
            $carpeta.'004 2026-10-10 12.00 Resultados hematologia.pdf',
        ], $nombres);
        // El PDF subido se entrega tal como se guardó.
        $this->assertSame($original, $zip->getFromIndex(4));
        $zip->close();
        unlink($archivo);
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Descarga de expediente', 'id_usuario' => 1, 'tabla' => 'tbl_paciente', 'id_registro' => $i->id_paciente, 'descripcion' => 'Se descargó el expediente en .zip, con los datos del paciente y cada documento por separado ('.$exp.', 4 documentos).']);
        // Abrir el PDF subido por su cuenta no queda en la bitácora; armar el .zip tampoco agrega registros por cada archivo.
        $this->assertDatabaseCount('tbl_bitacora', 1);

        $ficha = app(DatosReportes::class)->fichaPaciente($i->paciente->fresh());
        $this->assertSame('1234567890125', $ficha['secciones']['Identificación']['DPI']);
        $this->assertSame('O+', $ficha['secciones']['Datos clínicos']['Tipo de sangre']);
        $this->assertStringContainsString('Hospitalización', $ficha['secciones']['Historial de atenciones']['Atención 1']);

        $this->get(route('pacientes.expediente', $i->paciente))->assertSee('Descargar archivos por separado (.zip)');
        $this->get('/reportes?reporte=expediente&zip=1&id_paciente='.$i->id_paciente)->assertOk()->assertHeader('Content-Type', 'application/zip');
    }

    public function test_categorias_fechas_opcionales_estadisticas_y_vista_previa(): void
    {
        $i = IngresoFactory::new()->create(['fecha_ingreso' => '2026-10-10 10:00:00']);
        IngresoFactory::new()->create(['id_paciente' => $i->id_paciente, 'fecha_ingreso' => '2026-10-11 10:00:00']);
        $this->withSession(['id_usuario' => 1]);
        $this->get('/reportes')->assertOk()->assertSee('¿Qué reporte necesita?')->assertSee('Expediente completo de un paciente')->assertViewHas('mostrar', false)->assertViewHas('clave', null);
        if (getenv('REPORT_UI_QA')) {
            file_put_contents(getenv('REPORT_UI_QA').'/reportes-inicial.html', $this->get('/reportes')->getContent());
        }
        foreach (['desde', 'hasta'] as $campo) {
            $res = $this->get('/reportes?reporte=estadisticas.atenciones&consultar=1&'.$campo.'=2026-10-10')->assertOk()->assertSee('Vista previa del reporte');
            $this->assertSame(1, $res->viewData('estadisticas')['Atenciones registradas']);
        }
        $res = $this->get('/reportes?categoria=estadisticas&tipo=atenciones&consultar=1&desde=2026-10-10&hasta=2026-10-11')->assertOk();
        $this->assertSame(2, $res->viewData('estadisticas')['Atenciones registradas']);
        $this->assertSame(1, $res->viewData('estadisticas')['Pacientes distintos']);
        $this->get('/reportes/pdf?reporte=estadisticas.atenciones')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/reportes?reporte=personal.personal_actividad')->assertOk()->assertViewHas('clave', 'personal.personal_actividad');
        $this->get('/reportes?reporte=expediente')->assertOk()->assertSee('Vista previa')->assertSee('Descargar expediente');
        $res = $this->get('/reportes?reporte=expediente&generar=1&id_paciente='.$i->id_paciente)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', $res->headers->get('Content-Disposition'));
        $res = $this->get('/reportes?reporte=expediente&descargar=1&id_paciente='.$i->id_paciente)->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $this->get('/reportes?reporte=expediente&descargar=1')->assertSessionHasErrors(['id_paciente' => 'Elija el paciente cuyo expediente desea generar.']);
    }

    public function test_un_solo_formulario_ignora_datos_que_no_corresponden_al_reporte(): void
    {
        $i = IngresoFactory::new()->create();
        $n = NotaEnfermeria::create(['id_ingreso' => $i->getKey(), 'id_personal' => 2, 'fecha_hora' => '2026-10-10 14:00:00', 'observaciones' => 'Nota de la tarde', 'estado_paciente' => 'Estable', 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1]);

        // El turno de enfermería y el estado de cirugía no aplican a la lista de pacientes: se ignoran.
        $res = $this->get('/reportes?reporte=pacientes.pacientes&consultar=1&turno=Noche&estado_cirugia=Cancelada')->assertOk();
        $this->assertSame(['categoria' => 'pacientes', 'tipo' => 'pacientes'], $res->viewData('filtros'));
        $this->assertSame(1, $res->viewData('registros')->total());

        // En notas de enfermería, el turno sí se aplica.
        $res = $this->get('/reportes?reporte=pacientes.enfermeria&consultar=1&turno=Tarde')->assertOk()->assertSee('Notas de enfermería')->assertSee('Estable');
        $this->assertSame([$n->getKey()], $res->viewData('filas')->pluck('id')->all());
        $this->assertSame(0, $this->get('/reportes?reporte=pacientes.enfermeria&consultar=1&turno=Noche')->viewData('registros')->total());

        $this->getJson('/reportes?reporte=inexistente.algo')->assertUnprocessable();
    }

    public function test_estandar_pdf_listados_estadisticas_y_bitacora_con_paginacion(): void
    {
        $this->withSession(['id_usuario' => 1]);
        $servicio = app(PdfReportes::class);
        $filas = array_map(fn ($n) => ['fecha' => '11/10/2026 10:00', 'paciente' => 'Paciente de prueba '.$n, 'expediente' => 'TEST-'.$n, 'responsable' => 'Rosa Médica', 'estado' => $n % 2 ? 'Realizada' : 'Programada', 'detalle' => 'Cirugía simulada. '.str_repeat('Información de prueba para comprobar el contenido completo. ', 5)], range(1, 35));
        $reportes = [
            'actividad' => ['Actividad del personal', $filas],
            'estadisticas' => ['Estadísticas de atenciones', [['indicador' => 'Hospitalizaciones', 'cantidad' => 12], ['indicador' => 'Consultas externas', 'cantidad' => 25]]],
            'bitacora' => ['Bitácora del sistema', array_map(fn ($n) => ['bitacora' => true, 'fecha' => '11/10/2026 10:00', 'usuario' => 'Laura Enfermera', 'intentado' => 'enfermera', 'accion' => 'Registro de nota', 'resultado' => 'Exitoso', 'registro' => 'Paciente de prueba '.$n, 'detalle' => 'Se registró una nota de enfermería de prueba.', 'ip' => '127.0.0.1', 'navegador' => 'Navegador de prueba', 'sistema' => 'Windows'], range(1, 12))],
        ];
        foreach ($reportes as $nombre => [$titulo, $registros]) {
            $bytes = $servicio->generar($titulo, $registros, ['Desde' => '01/10/2026', 'Hasta' => '11/10/2026', 'Personal' => 'Rosa Médica']);
            $this->assertStringStartsWith('%PDF-', $bytes);
            $lector = new Fpdi;
            $paginas = $lector->setSourceFile(StreamReader::createByString($bytes));
            $this->assertGreaterThanOrEqual($nombre === 'actividad' ? 2 : 1, $paginas);
            if (getenv('REPORT_PDF_QA')) {
                file_put_contents(getenv('REPORT_PDF_QA').'/'.$nombre.'-estandar.pdf', $bytes);
            }
        }
        $ingreso = IngresoFactory::new()->create();
        $cirugia = Cirugia::create(['id_ingreso' => $ingreso->getKey(), 'tipo_operacion' => 'Cirugía de prueba', 'fecha_programada' => '2026-10-11 10:00:00', 'estado' => 'Programada', 'id_usuario_creacion' => 1]);
        $this->assertSame('Programada', app(DatosReportes::class)->fila('cirugias', $cirugia)['estado']);
    }

    public function test_paciente_concreto_ignora_filtros_demograficos_contradictorios(): void
    {
        $i = IngresoFactory::new()->create();
        $i->paciente->update(['estado_identificacion' => 'Identificado', 'sexo' => 'Masculino']);
        $this->withSession(['id_usuario' => 1]);
        $respuesta = $this->get(route('reportes.index', ['reporte' => 'pacientes.pacientes', 'consultar' => 1, 'id_paciente' => $i->id_paciente, 'identificacion' => 'No identificado', 'sexo' => 'Femenino', 'estado_paciente' => 'Inactivo']))->assertOk();
        $this->assertSame(1, $respuesta->viewData('registros')->total());
        if (getenv('REPORT_UI_QA')) {
            file_put_contents(getenv('REPORT_UI_QA').'/reportes-paciente.html', $respuesta->getContent());
        }
        foreach (['identificacion', 'sexo', 'estado_paciente'] as $campo) {
            $this->assertArrayNotHasKey($campo, $respuesta->viewData('filtros'));
            $this->assertMatchesRegularExpression('~data-campo="'.$campo.'"[^>]*hidden~', $respuesta->getContent());
        }
        $respuesta = $this->get(route('reportes.index', ['reporte' => 'pacientes.pacientes', 'consultar' => 1, 'identificacion' => 'No identificado']))->assertOk();
        $this->assertSame(0, $respuesta->viewData('registros')->total());
        $this->assertSame('No identificado', $respuesta->viewData('filtros')['identificacion']);
    }

    public function test_signos_del_paciente_incluyen_enfermeria_y_examen_fisico_con_origen_y_fechas(): void
    {
        $i = IngresoFactory::new()->create();
        $otra = IngresoFactory::new()->create();
        $inicial = EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-10 10:00:00', 'diagnostico' => 'Prueba', 'examen_fisico' => ['frecuencia_cardiaca' => 91], 'id_usuario_creacion' => 1]);
        $evolucion = EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-11 11:00:00', 'estado_mejoria' => 'Mejoró', 'diagnostico' => 'Prueba', 'examen_fisico' => ['temperatura' => 37.2, 'saturacion_oxigeno' => 0], 'id_usuario_creacion' => 1]);
        EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-11 11:15:00', 'estado_mejoria' => 'Mejoró', 'examen_fisico' => ['cabeza' => 'Sin mediciones'], 'id_usuario_creacion' => 1]);
        $signo = SignoVital::create(['id_ingreso' => $i->getKey(), 'id_personal' => 2, 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 11:30:00', 'frecuencia_cardiaca' => 80, 'id_usuario_creacion' => 1]);
        SignoVital::create(['id_ingreso' => $otra->getKey(), 'id_personal' => 2, 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 11:30:00', 'frecuencia_cardiaca' => 70, 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1]);
        $f = ['reporte' => 'pacientes.signos', 'id_paciente' => $i->id_paciente, 'consultar' => 1];
        $respuesta = $this->get(route('reportes.index', $f))->assertOk()->assertSee('91')->assertSee('37.2')->assertSee('Rosa Médica')->assertSee('Laura Enfermera');
        $filas = $respuesta->viewData('filas');
        $this->assertCount(3, $filas);
        $this->assertSame(['signos', 'evoluciones', 'valoraciones'], $filas->pluck('tipo_pdf')->all());
        $this->assertSame([$signo->getKey(), $evolucion->getKey(), $inicial->getKey()], $filas->pluck('id')->all());
        $this->assertStringContainsString('SpO2 (%): 0', $filas[1]['detalle']);
        $this->assertSame(2, $this->get(route('reportes.index', $f + ['desde' => '2026-10-11']))->viewData('registros')->total());
        $this->assertSame(2, $this->get(route('reportes.index', $f + ['personal' => 1]))->viewData('registros')->total());
        $enfermeria = $this->get(route('reportes.index', $f + ['personal' => 2]))->assertOk()
            ->assertSee('Quién tomó los signos')->assertDontSee('data-accesos-paciente', false)
            ->assertSee('Signos vitales por paciente y personal')->assertSee('Atención recibida por el paciente')->assertSee('Expediente completo de un paciente');
        $this->assertSame(1, $enfermeria->viewData('registros')->total());
        $this->assertSame($signo->getKey(), $enfermeria->viewData('filas')->first()['id']);
        $this->assertSame(1, $this->get(route('reportes.index', $f + ['tipo_control' => 'Ingreso']))->viewData('registros')->total());
        $this->get(route('reportes.pdf', $f))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_personal_del_paciente_muestra_acciones_y_responsables_sin_mezclar_pacientes(): void
    {
        $i = IngresoFactory::new()->create();
        $otra = IngresoFactory::new()->create();
        $e = EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-11 10:00:00', 'diagnostico' => 'Evaluación de prueba', 'indicaciones' => 'Tratamiento registrado de prueba', 'id_usuario_creacion' => 1]);
        $n = NotaEnfermeria::create(['id_ingreso' => $i->getKey(), 'id_personal' => 2, 'fecha_hora' => '2026-10-11 11:00:00', 'observaciones' => 'Nota descriptiva de la atención', 'estado_paciente' => 'Mejoró', 'id_usuario_creacion' => 1]);
        $n->actividades()->create(['actividad' => 'Cambio de posición']);
        $n->ordenes()->create(['id_evolucion' => $e->getKey(), 'tipo' => 'Dieta', 'descripcion' => 'Dieta líquida', 'aplicada' => true]);
        NotaEnfermeria::create(['id_ingreso' => $otra->getKey(), 'id_personal' => 2, 'fecha_hora' => '2026-10-11 11:30:00', 'observaciones' => 'NO MOSTRAR OTRO PACIENTE', 'id_usuario_creacion' => 1]);
        $this->withSession(['id_usuario' => 1]);
        $f = ['reporte' => 'pacientes.personal_atenciones', 'consultar' => 1, 'id_paciente' => $i->id_paciente];
        $r = $this->get(route('reportes.index', $f))->assertOk()->assertSee('Rosa Médica')->assertSee('Laura Enfermera')->assertSee('Cambio de posición')->assertSee('Dieta líquida')->assertSee('Se dio')->assertSee('Nota descriptiva de la atención')->assertSee('Tratamiento registrado de prueba')->assertDontSee('NO MOSTRAR OTRO PACIENTE');
        $this->assertSame(3, $r->viewData('registros')->total());
        $this->assertSame(1, $this->get(route('reportes.index', $f + ['personal' => 2]))->viewData('registros')->total());
        $this->assertSame(0, $this->get(route('reportes.index', $f + ['desde' => '2026-10-09']))->viewData('registros')->total());
        $this->get(route('reportes.pdf', $f))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_atencion_del_paciente_filtra_signos_cirugias_y_personal_en_un_solo_reporte(): void
    {
        $i = IngresoFactory::new()->create();
        $otra = IngresoFactory::new()->create();
        $e = EvolucionClinica::create(['id_ingreso' => $i->getKey(), 'id_personal' => 1, 'fecha_hora' => '2026-10-11 10:00:00', 'diagnostico' => 'Evaluación simulada', 'examen_fisico' => ['temperatura' => 37.1], 'id_usuario_creacion' => 1]);
        $signo = SignoVital::create(['id_ingreso' => $i->getKey(), 'id_personal' => 2, 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 11:00:00', 'frecuencia_cardiaca' => 85, 'id_usuario_creacion' => 1]);
        SignoVital::create(['id_ingreso' => $otra->getKey(), 'id_personal' => 2, 'tipo_control' => 'Rutina', 'fecha_hora' => '2026-10-11 11:00:00', 'frecuencia_cardiaca' => 70, 'id_usuario_creacion' => 1]);
        $c = Cirugia::create(['id_ingreso' => $i->getKey(), 'tipo_operacion' => 'Cirugía simulada del paciente', 'estado' => 'Realizada', 'fecha_inicio_real' => '2026-10-11 09:00:00', 'fecha_fin_real' => '2026-10-11 09:30:00', 'id_usuario_creacion' => 1]);
        $c->equipo()->attach(1, ['estado' => 'Participó', 'rol_quirurgico' => 'Otro']);
        $this->withSession(['id_usuario' => 1]);
        $f = ['reporte' => 'pacientes.personal_atenciones', 'id_paciente' => $i->id_paciente, 'consultar' => 1];
        $todo = $this->get(route('reportes.index', $f))->assertOk()->assertSee('Atención recibida por el paciente')->assertSee('¿Qué atención desea consultar?')->assertSee('Cirugía simulada del paciente')->assertSee('37.1')->assertSee('85 lpm');
        $this->assertCount(5, $todo->viewData('filas'));
        $enfermeria = $this->get(route('reportes.index', $f + ['actividad' => 'signos', 'personal' => 2]))->assertOk();
        $this->assertSame([$signo->getKey()], $enfermeria->viewData('filas')->pluck('id')->all());
        $medico = $this->get(route('reportes.index', $f + ['actividad' => 'signos', 'personal' => 1]))->assertOk();
        $this->assertSame([$e->getKey()], $medico->viewData('filas')->pluck('id')->all());
        $cirugias = $this->get(route('reportes.index', $f + ['actividad' => 'cirugias', 'personal' => 1]))->assertOk()->assertSee('Rosa Médica');
        $this->assertSame([$c->getKey()], $cirugias->viewData('filas')->pluck('id')->all());
        $this->assertSame(0, $this->get(route('reportes.index', $f + ['actividad' => 'cirugias', 'personal' => 2]))->viewData('registros')->total());
        foreach (['signos', 'cirugias'] as $actividad) {
            $this->get(route('reportes.pdf', $f + ['actividad' => $actividad, 'personal' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
        $this->getJson(route('reportes.index', $f + ['actividad' => 'inexistente']))->assertUnprocessable();
    }
}
