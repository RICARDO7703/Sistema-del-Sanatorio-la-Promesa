<?php

namespace Tests\Feature;

use App\Http\Middleware\AvisarSinCambios;
use App\Models\DocumentoClinico;
use App\Models\Ingreso;
use Database\Factories\IngresoFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivoClinicoTest extends TestCase
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

    private function datos(array $cambios = []): array
    {
        return array_replace(['tipo' => 'Evolución clínica', 'fecha_documento' => '2026-10-11T11:00', 'archivo' => UploadedFile::fake()->createWithContent('hoja.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")], $cambios);
    }

    private function pdf(string $nombre = 'examen.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }

    private function examen(array $cambios = []): array
    {
        return array_replace(['_formulario' => 'archivo-paciente', 'categoria' => 'Laboratorio', 'titulo' => 'Hematología completa', 'archivo' => $this->pdf()], $cambios);
    }

    private function segundoUsuario(): void
    {
        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 1, 'id_rol' => 1, 'nombre_usuario' => 'medica', 'contrasena' => 'solo-pruebas']);
    }

    public function test_expediente_muestra_acciones_arriba_y_sube_examenes_del_paciente(): void
    {
        Storage::fake('documentos_clinicos');
        $paciente = IngresoFactory::new()->create(['estado' => 'Dado de alta'])->paciente;
        $expediente = route('pacientes.expediente', $paciente);

        $this->withSession(['id_usuario' => 1])->get($expediente)->assertOk()
            ->assertSeeInOrder(['id="acciones-expediente"', 'id="datos-paciente"', 'id="archivos-paciente"', 'Subir archivo médico', 'id="historial-evoluciones"'], false)
            ->assertSee('Todavía no hay archivos médicos');

        $this->from($expediente)->post(route('archivos.paciente.guardar', $paciente), $this->examen())->assertRedirect($expediente.'#archivos-paciente');
        $documento = DocumentoClinico::sole();
        $this->assertNull($documento->id_ingreso);
        $this->assertSame('Laboratorio', $documento->categoria);
        $this->assertSame('2026-10-11 12:00', $documento->fecha_documento->format('Y-m-d H:i'));
        $this->assertStringStartsWith('pacientes/'.$paciente->getKey().'/', $documento->ruta_archivo);
        Storage::disk('documentos_clinicos')->assertExists($documento->ruta_archivo);

        $this->get($expediente)->assertOk()->assertSee('Hematología completa')->assertSee('Subido el 11/10/2026 a las 12:00')->assertSee('data-abrir-documento', false);
        $this->get(route('archivos.show', $documento))->assertRedirect($expediente)
            ->assertSessionHas('abrir_archivo_subido.tipo', 'pdf')
            ->assertSessionHas('abrir_archivo_subido.url', route('archivos.contenido', $documento));
        $this->get($expediente)->assertOk()->assertSee('id="visorDocumentoSubido"', false)->assertSee('data-documento-inicial', false);
        $this->get(route('archivos.contenido', $documento))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_examen_del_paciente_valida_los_datos_y_reabre_la_ventana(): void
    {
        Storage::fake('documentos_clinicos');
        $paciente = IngresoFactory::new()->create()->paciente;
        $expediente = route('pacientes.expediente', $paciente);

        $this->withSession(['id_usuario' => 1])->from($expediente)
            ->post(route('archivos.paciente.guardar', $paciente), $this->examen(['categoria' => 'Receta', 'titulo' => '', 'archivo' => UploadedFile::fake()->create('virus.exe', 10)]))
            ->assertRedirect($expediente)->assertSessionHasErrors(['categoria', 'titulo', 'archivo']);
        $this->get($expediente)->assertSee("getElementById('modalArchivoPaciente')", false);
        $this->assertDatabaseCount('tbl_documento_clinico', 0);
    }

    public function test_solo_quien_subio_el_archivo_lo_edita_y_solo_durante_doce_horas(): void
    {
        Storage::fake('documentos_clinicos');
        $this->segundoUsuario();
        $paciente = IngresoFactory::new()->create()->paciente;
        $this->withSession(['id_usuario' => 1])->post(route('archivos.paciente.guardar', $paciente), $this->examen());
        $documento = DocumentoClinico::sole();
        $rutaAnterior = $documento->ruta_archivo;

        $this->travel(2)->hours();
        $this->get(route('archivos.editar', $documento))->assertOk()->assertSeeText('Puede corregir este archivo hasta el 12/10/2026 a las 00:00');
        $this->put(route('archivos.actualizar', $documento), ['categoria' => 'Radiografía', 'titulo' => 'Rayos X de tórax', 'fecha_documento' => '2026-10-11T09:30', 'archivo' => $this->pdf('nuevo.pdf')])
            ->assertRedirect(route('archivos.show', $documento));
        $documento->refresh();
        $this->assertSame(['Radiografía', 'Rayos X de tórax', 'nuevo.pdf'], [$documento->categoria, $documento->titulo, $documento->nombre_archivo]);
        $this->assertSame('2026-10-11 12:00', $documento->fecha_documento->format('Y-m-d H:i'), 'La fecha de subida no cambia al editar.');
        Storage::disk('documentos_clinicos')->assertMissing($rutaAnterior);
        Storage::disk('documentos_clinicos')->assertExists($documento->ruta_archivo);
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Edición de archivo clínico', 'tabla' => 'tbl_documento_clinico', 'id_registro' => $documento->getKey()]);
        $this->get(route('pacientes.expediente', $paciente))->assertSee('Se editó el 11/10/2026 a las 14:00');

        $this->withSession(['id_usuario' => 2]);
        $this->get(route('pacientes.expediente', $paciente))->assertOk()->assertSee('Rayos X de tórax')->assertDontSee(route('archivos.editar', $documento), false);
        $this->get(route('archivos.editar', $documento))->assertForbidden();
        $this->put(route('archivos.actualizar', $documento), ['categoria' => 'Otro', 'titulo' => 'Cambio ajeno', 'fecha_documento' => '2026-10-11T09:30'])->assertForbidden();

        $this->withSession(['id_usuario' => 1]);
        $this->travel(10)->hours();
        $this->travel(1)->minutes();
        $this->get(route('archivos.show', $documento))->assertOk()->assertDontSee(route('archivos.editar', $documento), false);
        $this->get(route('archivos.editar', $documento))->assertForbidden();
        $this->assertSame('Rayos X de tórax', $documento->fresh()->titulo);
    }

    public function test_hoja_de_una_atencion_se_corrige_dentro_del_plazo_sin_fecha_anterior_a_la_llegada(): void
    {
        Storage::fake('documentos_clinicos');
        $ingreso = IngresoFactory::new()->create(['fecha_ingreso' => '2026-10-11 08:00:00']);
        $this->withSession(['id_usuario' => 1])->post(route('archivos.guardar', $ingreso), $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $ingreso->getKey()]))->assertSessionHasNoErrors();
        $documento = DocumentoClinico::sole();

        $this->get(route('archivos.show', $documento))->assertSee(route('archivos.editar', $documento), false);
        $this->put(route('archivos.actualizar', $documento), ['fecha_documento' => '2026-10-11T07:00'])->assertSessionHasErrors('fecha_documento');
        $this->put(route('archivos.actualizar', $documento), ['fecha_documento' => '2026-10-11T09:15', 'descripcion' => 'Hora corregida'])->assertSessionHasNoErrors();
        $this->assertSame(['2026-10-11 09:15', 'Hora corregida', 'Hoja de ingreso'], [$documento->fresh()->fecha_documento->format('Y-m-d H:i'), $documento->fresh()->descripcion, $documento->fresh()->titulo]);
    }

    public function test_un_archivo_no_reemplaza_una_hoja_solo_se_agrega_a_una_registrada(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1]);
        // Sin hoja a la cual agregarlo, no se guarda: una evolución no se reemplaza por una foto.
        $this->post(route('archivos.guardar', $i), $this->datos())->assertSessionHasErrors(['referencia' => 'Los archivos se agregan a una hoja ya registrada: abra la hoja y use «Subir una foto o PDF».']);
        $this->post(route('archivos.guardar', $i), $this->datos(['tipo' => 'Otro documento', 'referencia' => 1]))->assertSessionHasErrors('tipo');
        $this->assertDatabaseCount('tbl_documento_clinico', 0);
        $this->assertFalse(Route::has('archivos.formulario'));
        $this->get(route('atencion.show', $i))->assertOk()->assertDontSee('Subir hoja en papel')->assertDontSee('en papel</a>', false);

        // Agregado a la hoja de ingreso: se guarda y se abre directamente.
        $this->post(route('archivos.guardar', $i), $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $i->getKey()]))->assertSessionHasNoErrors();
        $d = DocumentoClinico::sole();
        Storage::disk('documentos_clinicos')->assertExists($d->ruta_archivo);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
        $this->get(route('archivos.contenido', $d))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('pacientes.expediente', $i->paciente))->assertOk()->assertSee('1 archivo')->assertSee(route('archivos.contenido', $d), false);
    }

    public function test_imagen_y_adjunto_a_hoja_existente(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('archivos.guardar', $i), $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $i->getKey(), 'archivo' => UploadedFile::fake()->image('hoja.jpg')]))->assertSessionHasNoErrors();
        $d = DocumentoClinico::sole();
        $this->assertSame('tbl_ingreso', $d->tabla_origen);
        $this->get(route('archivos.show', $d))->assertRedirect(route('pacientes.expediente', $i->id_paciente))
            ->assertSessionHas('abrir_archivo_subido.tipo', 'jpg');
        $this->get(route('archivos.contenido', $d))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('pacientes.expediente', $i->paciente))->assertOk()
            ->assertSee('Hoja en papel / archivos')->assertSee('data-abrir-documento', false)->assertSee(route('archivos.contenido', $d), false)->assertDontSee('Subir una foto o PDF');
    }

    public function test_validacion_archivos_fechas_y_referencia_de_otro_paciente(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create(['fecha_ingreso' => '2026-10-11 10:00:00']);
        $otro = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1]);
        $ruta = route('archivos.guardar', $i);
        $hoja = ['tipo' => 'Hoja de ingreso', 'referencia' => $i->getKey()];
        $this->post($ruta, $this->datos($hoja + ['archivo' => null]))->assertSessionHasErrors('archivo');
        $this->post($ruta, $this->datos($hoja + ['archivo' => UploadedFile::fake()->create('programa.php', 1, 'application/x-php')]))->assertSessionHasErrors('archivo');
        $this->post($ruta, $this->datos($hoja + ['archivo' => UploadedFile::fake()->create('grande.pdf', 11000, 'application/pdf')]))->assertSessionHasErrors('archivo');
        $this->post($ruta, $this->datos($hoja + ['archivo' => UploadedFile::fake()->createWithContent('falso.pdf', 'texto normal')]))->assertSessionHasErrors('archivo');
        $this->post($ruta, $this->datos($hoja + ['fecha_documento' => '2026-10-12T11:00']))->assertSessionHasErrors('fecha_documento');
        $this->post($ruta, $this->datos($hoja + ['fecha_documento' => '2026-10-11T09:00']))->assertSessionHasErrors('fecha_documento');
        $this->post($ruta, $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $otro->getKey()]))->assertSessionHasErrors('referencia');
        $this->assertDatabaseCount('tbl_documento_clinico', 0);
        $this->assertCount(0, Storage::disk('documentos_clinicos')->allFiles());
    }

    public function test_impide_adjuntar_despues_del_alta(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create(['estado' => 'Dado de alta']);
        $this->withSession(['id_usuario' => 1])->post(route('archivos.guardar', $i), $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $i->getKey()]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tbl_alta_medica', 0);
        $this->assertSame('Dado de alta', $i->fresh()->estado);
    }

    public function test_archivos_requieren_sesion_y_archivo_existente(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $this->post(route('archivos.guardar', $i), $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $i->getKey()]))->assertRedirect('/');
        $this->withSession(['id_usuario' => 1])->post(route('archivos.guardar', $i), $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $i->getKey()]))->assertSessionHasNoErrors();
        $d = DocumentoClinico::sole();
        $this->flushSession();
        $this->get(route('archivos.contenido', $d))->assertRedirect('/');
        Storage::disk('documentos_clinicos')->delete($d->ruta_archivo);
        $this->withSession(['id_usuario' => 1])->get(route('archivos.contenido', $d))->assertNotFound();
    }

    public function test_migracion_de_archivos_vinculados_es_repetible(): void
    {
        $m = require database_path('migrations/2026_10_28_175254_vincular_archivos_a_hojas_clinicas.php');
        $m->up();
        $m->up();
        $this->assertTrue(Schema::hasColumn('tbl_documento_clinico', 'tabla_origen'));
    }

    public function test_hospitalizar_con_documento_firmado_exige_subirlo_y_lo_guarda_en_archivos_medicos(): void
    {
        Storage::fake('documentos_clinicos');
        $paciente = IngresoFactory::new()->create(['estado' => 'Dado de alta'])->paciente;
        $datos = ['id_paciente' => $paciente->getKey(), 'fecha_ingreso' => '2026-10-11T11:30', 'motivo_ingreso' => 'Desmayo', 'documento_firmado' => '1'];

        $this->withSession(['id_usuario' => 1])->post(route('ingresos.guardar'), $datos)
            ->assertSessionHasErrors(['documento_archivo' => 'Suba el documento de ingreso firmado o desmarque la casilla.']);
        $this->post(route('ingresos.guardar'), $datos + ['documento_archivo' => UploadedFile::fake()->createWithContent('falso.pdf', 'texto normal')])
            ->assertSessionHasErrors('documento_archivo');
        $this->assertSame(0, Ingreso::where('estado', 'Activo')->count());
        $this->assertDatabaseCount('tbl_documento_clinico', 0);

        $this->post(route('ingresos.guardar'), $datos + ['documento_archivo' => $this->pdf('consentimiento.pdf')])->assertSessionHasNoErrors();
        $ingreso = Ingreso::where('estado', 'Activo')->sole();
        $documento = DocumentoClinico::sole();
        $this->assertTrue($ingreso->documento_firmado);
        $this->assertNull($documento->id_ingreso);
        $this->assertTrue($documento->esDocumentoIngresoFirmado());
        $this->assertSame($ingreso->getKey(), (int) $documento->id_registro_origen);
        $this->assertSame('consentimiento.pdf', $documento->nombre_archivo);
        Storage::disk('documentos_clinicos')->assertExists($documento->ruta_archivo);

        $this->get(route('pacientes.expediente', $paciente))->assertOk()
            ->assertSeeInOrder(['id="archivos-paciente"', 'Documento de ingreso firmado · ingreso del 11/10/2026 11:30'], false)
            ->assertSee('data-abrir-documento', false)
            ->assertSee('id="visorDocumentoSubido"', false)
            ->assertSee(route('archivos.contenido', $documento), false)
            ->assertDontSee('href="'.route('archivos.show', $documento).'"', false);
        $this->get(route('atencion.show', $ingreso))->assertOk()->assertDontSee('id="editarDocumentoFirmado"', false);

        // Ya subido, no se puede desmarcar al editar el ingreso.
        $this->put(route('ingresos.actualizar', $ingreso), ['fecha_ingreso' => '2026-10-11T11:30', 'motivo_ingreso' => 'Síncope'])->assertSessionHasNoErrors();
        $this->assertTrue($ingreso->fresh()->documento_firmado);
        $this->assertDatabaseCount('tbl_documento_clinico', 1);

        // Su tipo no se cambia al corregirlo.
        $this->put(route('archivos.actualizar', $documento), ['categoria' => 'Otro', 'titulo' => 'Consentimiento firmado'])->assertSessionHasNoErrors();
        $this->assertSame('Otro', $documento->fresh()->categoria);
        $this->assertTrue($documento->fresh()->esDocumentoIngresoFirmado());
        $this->get(route('archivos.editar', $documento))->assertOk()->assertDontSee('name="categoria"', false);
    }

    public function test_sin_marcar_la_casilla_no_se_pide_el_documento_y_al_editar_se_puede_subir(): void
    {
        Storage::fake('documentos_clinicos');
        $paciente = IngresoFactory::new()->create(['estado' => 'Dado de alta'])->paciente;

        $this->withSession(['id_usuario' => 1])->post(route('ingresos.guardar'), ['id_paciente' => $paciente->getKey(), 'fecha_ingreso' => '2026-10-11T11:30', 'documento_archivo' => $this->pdf()])
            ->assertSessionHasNoErrors();
        $ingreso = Ingreso::where('estado', 'Activo')->sole();
        $this->assertFalse($ingreso->documento_firmado);
        $this->assertDatabaseCount('tbl_documento_clinico', 0);

        $this->put(route('ingresos.actualizar', $ingreso), ['fecha_ingreso' => '2026-10-11T11:30', 'documento_firmado' => '1'])->assertSessionHasErrors('documento_archivo');
        $this->assertFalse($ingreso->fresh()->documento_firmado);

        $this->put(route('ingresos.actualizar', $ingreso), ['fecha_ingreso' => '2026-10-11T11:30', 'documento_firmado' => '1', 'documento_archivo' => UploadedFile::fake()->image('firma.jpg')])
            ->assertSessionHasNoErrors();
        $this->assertTrue($ingreso->fresh()->documento_firmado);
        $this->assertSame($ingreso->getKey(), (int) DocumentoClinico::sole()->id_registro_origen);
    }

    public function test_documento_firmado_pendiente_se_sube_despues_desde_el_expediente(): void
    {
        Storage::fake('documentos_clinicos');
        $paciente = IngresoFactory::new()->create(['estado' => 'Dado de alta'])->paciente;
        $this->withSession(['id_usuario' => 1])->post(route('ingresos.guardar'), ['id_paciente' => $paciente->getKey(), 'fecha_ingreso' => '2026-10-11T11:30'])->assertSessionHasNoErrors();
        $ingreso = Ingreso::where('estado', 'Activo')->sole();
        $expediente = route('pacientes.expediente', $paciente);

        $this->get($expediente)->assertOk()->assertSee('Pendiente de firma')->assertSee(route('ingresos.documento-firmado', $ingreso), false);

        $this->post(route('ingresos.documento-firmado', $ingreso))
            ->assertRedirect($expediente.'#documento-ingreso-'.$ingreso->getKey())->assertSessionHasErrors('documento_archivo');
        $this->post(route('ingresos.documento-firmado', $ingreso), ['documento_archivo' => UploadedFile::fake()->createWithContent('falso.pdf', 'texto')])->assertSessionHasErrors('documento_archivo');
        $this->assertFalse($ingreso->fresh()->documento_firmado);

        $this->post(route('ingresos.documento-firmado', $ingreso), ['documento_archivo' => $this->pdf('firma.pdf')])
            ->assertSessionHasNoErrors()->assertSessionHas('exito', 'Documento de ingreso firmado agregado al expediente.');
        $this->assertTrue($ingreso->fresh()->documento_firmado);
        $documento = DocumentoClinico::sole();
        $this->assertTrue($documento->esDocumentoIngresoFirmado());
        $this->get($expediente)->assertOk()->assertSee('Firmado')->assertSee(route('archivos.show', $documento), false)
            ->assertDontSee(route('ingresos.documento-firmado', $ingreso), false);

        // Una segunda vez no se duplica.
        $this->post(route('ingresos.documento-firmado', $ingreso), ['documento_archivo' => $this->pdf('otra.pdf')])->assertSessionHas('error', 'Este ingreso ya tiene su documento firmado.');
        $this->assertDatabaseCount('tbl_documento_clinico', 1);
    }

    public function test_quitar_adjunto_exige_autor_plazo_y_atencion_activa(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create(['fecha_registro' => now('UTC')]);
        $this->withSession(['id_usuario' => 1])->post(route('archivos.guardar', $i), $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $i->getKey()]))->assertRedirect();
        $archivo = DocumentoClinico::sole();
        $this->segundoUsuario();
        $this->withSession(['id_usuario' => 2])->delete(route('archivos.quitar', $archivo))->assertForbidden();
        $this->withSession(['id_usuario' => 1]);
        $i->forceFill(['fecha_registro' => now('UTC')->subHours(13)])->save();
        $this->delete(route('archivos.quitar', $archivo))->assertForbidden();
        $this->post(route('archivos.guardar', $i), $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $i->getKey()]))->assertForbidden();
        $i->forceFill(['fecha_registro' => now('UTC'), 'estado' => 'Dado de alta'])->save();
        $this->delete(route('archivos.quitar', $archivo))->assertForbidden();
        $i->update(['estado' => 'Activo']);
        $this->delete(route('archivos.quitar', $archivo))->assertRedirect();
        $this->assertSame('Inactivo', $archivo->fresh()->estado);
        $this->get(route('archivos.contenido', $archivo))->assertNotFound();
        Storage::disk('documentos_clinicos')->assertExists($archivo->ruta_archivo);
    }

    public function test_firma_en_linea_valida_archivo_y_confirma_sin_redirigir(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->withHeaders(['Accept' => 'application/json']);
        $this->post(route('ingresos.documento-firmado', $i), ['documento_firmado' => 1])->assertUnprocessable()->assertJsonValidationErrors('documento_archivo');
        $this->post(route('ingresos.documento-firmado', $i), ['documento_firmado' => 1, 'documento_archivo' => $this->pdf()])->assertOk()->assertJsonStructure(['mensaje', 'url']);
        $this->assertTrue($i->fresh()->documento_firmado);
        $this->assertDatabaseCount('tbl_documento_clinico', 1);
    }

    public function test_ingreso_se_edita_en_linea_y_conserva_documento_firmado(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1]);
        $this->post(route('ingresos.documento-firmado', $i), ['documento_archivo' => $this->pdf()])->assertSessionHasNoErrors();
        $archivo = DocumentoClinico::sole();
        $this->get(route('pacientes.expediente', $i->id_paciente))->assertOk()->assertSee('data-editar-ingreso-enlinea', false)->assertSee('data-submit-ingreso', false);
        $this->putJson(route('ingresos.actualizar', $i), ['fecha_ingreso' => $i->fecha_ingreso->format('Y-m-d H:i:s'), 'motivo_ingreso' => 'Motivo corregido'])->assertOk()->assertJsonPath('motivo', 'Motivo corregido');
        $this->get(route('pacientes.expediente', $i->id_paciente))->assertOk()->assertSee('Se editó el');
        // La respuesta trae la marca para la lista; guardar sin cambios no la trae.
        $this->putJson(route('ingresos.actualizar', $i), ['fecha_ingreso' => $i->fecha_ingreso->format('Y-m-d H:i:s'), 'motivo_ingreso' => 'Motivo otra vez'])->assertOk()->assertJsonPath('editado', fn ($texto) => str_starts_with((string) $texto, 'Se editó el '));
        $this->putJson(route('ingresos.actualizar', $i), ['fecha_ingreso' => $i->fecha_ingreso->format('Y-m-d H:i'), 'motivo_ingreso' => 'Motivo otra vez'])->assertOk()->assertJsonPath('editado', null)->assertJsonPath('mensaje', AvisarSinCambios::MENSAJE);
        $this->assertTrue($i->fresh()->documento_firmado);
        $this->assertDatabaseCount('tbl_documento_clinico', 1);
        Storage::disk('documentos_clinicos')->assertExists($archivo->ruta_archivo);
    }

    public function test_examenes_comunes_se_guardan_como_laboratorio_con_tipo_especifico(): void
    {
        Storage::fake('documentos_clinicos');
        $paciente = IngresoFactory::new()->create()->paciente;
        $this->withSession(['id_usuario' => 1]);
        foreach (DocumentoClinico::EXAMENES_LABORATORIO as $tipo) {
            $this->post(route('archivos.paciente.guardar', $paciente), $this->examen(['categoria' => $tipo, 'titulo' => $tipo]))->assertSessionHasNoErrors();
            $documento = DocumentoClinico::orderByDesc('id_documento')->first();
            $this->assertSame('Laboratorio', $documento->categoria);
            $this->assertSame($tipo, $documento->etiquetaCategoria());
            $this->get(route('pacientes.expediente', $paciente))->assertOk()->assertSee($tipo);
        }
    }
}
