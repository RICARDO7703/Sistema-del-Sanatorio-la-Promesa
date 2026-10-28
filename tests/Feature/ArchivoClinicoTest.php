<?php

namespace Tests\Feature;

use App\Http\Controllers\ArchivoClinicoController;
use App\Models\DocumentoClinico;
use Database\Factories\IngresoFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    public function test_subir_pdf_sin_transcribir_y_abrirlo_desde_expediente(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('archivos.guardar', $i), $this->datos())->assertSessionHasNoErrors();
        $d = DocumentoClinico::sole();
        Storage::disk('documentos_clinicos')->assertExists($d->ruta_archivo);
        $this->assertDatabaseCount('tbl_evolucion_clinica', 0);
        $this->assertSame($i->id_paciente, $d->id_paciente);
        $this->get(route('archivos.show', $d))->assertOk()->assertSee('Evolución clínica')->assertSee('iframe', false);
        $this->get(route('archivos.contenido', $d))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('pacientes.expediente', $i->paciente))->assertOk()->assertSee(route('archivos.show', $d), false)->assertSee('Imagen / PDF');
    }

    public function test_imagen_y_adjunto_a_hoja_existente(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1])->post(route('archivos.guardar', $i), $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $i->getKey(), 'archivo' => UploadedFile::fake()->image('hoja.jpg')]))->assertSessionHasNoErrors();
        $d = DocumentoClinico::sole();
        $this->assertSame('tbl_ingreso', $d->tabla_origen);
        $this->get(route('archivos.show', $d))->assertOk()->assertSee('<img src=', false);
        $this->get(route('archivos.contenido', $d))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('pacientes.expediente', $i->paciente))->assertOk()->assertSee('Subir imagen o PDF');
    }

    public function test_validacion_archivos_fechas_y_referencia_de_otro_paciente(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create(['fecha_ingreso' => '2026-10-11 10:00:00']);
        $otro = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1]);
        $ruta = route('archivos.guardar', $i);
        $this->post($ruta, $this->datos(['archivo' => null]))->assertSessionHasErrors('archivo');
        $this->post($ruta, $this->datos(['archivo' => UploadedFile::fake()->create('programa.php', 1, 'application/x-php')]))->assertSessionHasErrors('archivo');
        $this->post($ruta, $this->datos(['archivo' => UploadedFile::fake()->create('grande.pdf', 11000, 'application/pdf')]))->assertSessionHasErrors('archivo');
        $this->post($ruta, $this->datos(['archivo' => UploadedFile::fake()->createWithContent('falso.pdf', 'texto normal')]))->assertSessionHasErrors('archivo');
        $this->post($ruta, $this->datos(['fecha_documento' => '2026-10-12T11:00']))->assertSessionHasErrors('fecha_documento');
        $this->post($ruta, $this->datos(['fecha_documento' => '2026-10-11T09:00']))->assertSessionHasErrors('fecha_documento');
        $this->post($ruta, $this->datos(['tipo' => 'Hoja de ingreso', 'referencia' => $otro->getKey()]))->assertSessionHasErrors('referencia');
        $this->assertDatabaseCount('tbl_documento_clinico', 0);
        $this->assertCount(0, Storage::disk('documentos_clinicos')->allFiles());
    }

    public function test_admite_archivar_despues_del_alta_sin_modificar_la_atencion(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create(['estado' => 'Dado de alta']);
        $this->withSession(['id_usuario' => 1])->post(route('archivos.guardar', $i), $this->datos(['tipo' => 'Alta médica']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tbl_alta_medica', 0);
        $this->assertSame('Dado de alta', $i->fresh()->estado);
    }

    public function test_archivos_requieren_sesion_y_archivo_existente(): void
    {
        Storage::fake('documentos_clinicos');
        $i = IngresoFactory::new()->create();
        $this->get(route('archivos.formulario', $i))->assertRedirect('/');
        $this->withSession(['id_usuario' => 1])->post(route('archivos.guardar', $i), $this->datos())->assertSessionHasNoErrors();
        $d = DocumentoClinico::sole();
        $this->flushSession();
        $this->get(route('archivos.contenido', $d))->assertRedirect('/');
        Storage::disk('documentos_clinicos')->delete($d->ruta_archivo);
        $this->withSession(['id_usuario' => 1])->get(route('archivos.contenido', $d))->assertNotFound();
    }

    public function test_todas_las_hojas_tienen_formulario_y_migracion_repetible(): void
    {
        $i = IngresoFactory::new()->create();
        $this->withSession(['id_usuario' => 1]);
        foreach (array_keys(ArchivoClinicoController::HOJAS) as $tipo) {
            $this->get(route('archivos.formulario', [$i, 'tipo' => $tipo]))->assertOk()->assertSee('multipart/form-data', false);
        }
        $m = require database_path('migrations/2026_10_28_175254_vincular_archivos_a_hojas_clinicas.php');
        $m->up();
        $m->up();
        $this->assertTrue(Schema::hasColumn('tbl_documento_clinico', 'tabla_origen'));
    }
}
