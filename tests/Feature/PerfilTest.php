<?php

namespace Tests\Feature;

use App\Models\Personal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::connection()->getPdo()->sqliteCreateFunction('regexp', fn ($pattern, $value) => $value !== null && preg_match('~'.$pattern.'~u', (string) $value) === 1, 2);
        DB::unprepared(file_get_contents(base_path('tests/Fixtures/v3.sqlite.sql')));
        (require database_path('migrations/2026_10_15_053133_create_recuperacion_contrasenas_table.php'))->up();
        (require database_path('migrations/2026_10_15_112918_add_verificacion_to_recuperacion_contrasenas_table.php'))->up();
        DB::table('tbl_cargo')->insert(['id_cargo' => 1, 'nombre_cargo' => 'Administrador']);
        DB::table('tbl_personal')->insert(['id_personal' => 1, 'id_cargo' => 1, 'nombres' => 'Persona', 'apellidos' => 'Prueba', 'dpi' => '1234567890123', 'correo' => 'persona@example.com']);
        DB::table('tbl_rol')->insert(['id_rol' => 1, 'nombre_rol' => 'Administrador']);
        DB::table('tbl_usuario')->insert(['id_usuario' => 1, 'id_personal' => 1, 'id_rol' => 1, 'nombre_usuario' => 'prueba', 'contrasena' => Hash::make('Anterior-12345')]);
        Mail::fake();
    }

    public function test_perfil_y_menu_muestran_nombre_real_y_opciones(): void
    {
        $this->get(route('perfil.show'))->assertRedirect('/');
        $this->withSession(['id_usuario' => 1])->get(route('perfil.show'))->assertOk()->assertSee('Persona Prueba')->assertSee('Mi perfil')->assertSee('Cambiar contraseña')->assertDontSee('Quitar foto');
    }

    public function test_cada_opcion_del_menu_abre_solo_su_pantalla(): void
    {
        $this->get(route('perfil.foto.editar'))->assertRedirect('/');
        $this->get(route('perfil.contrasena.editar'))->assertRedirect('/');
        $this->withSession(['id_usuario' => 1]);

        $this->get(route('perfil.show'))->assertOk()
            ->assertSee(route('perfil.foto.editar'), false)->assertSee(route('perfil.contrasena.editar'), false)
            ->assertSee('Datos de contacto')->assertSee('name="telefono"', false)
            ->assertDontSee('name="foto"', false)->assertDontSee('name="nueva_contrasena"', false);

        $this->get(route('perfil.foto.editar'))->assertOk()
            ->assertSee('<h1>Cambiar foto</h1>', false)->assertSee('name="foto"', false)->assertSee('Volver a mi perfil')
            ->assertDontSee('name="telefono"', false)->assertDontSee('name="nueva_contrasena"', false);

        $this->get(route('perfil.contrasena.editar'))->assertOk()
            ->assertSee('<h1>Cambiar contraseña</h1>', false)->assertSee('name="nueva_contrasena"', false)
            ->assertDontSee('name="telefono"', false)->assertDontSee('name="foto"', false);
    }

    public function test_cada_formulario_regresa_a_su_propia_pantalla(): void
    {
        Storage::fake('documentos_clinicos');
        $this->withSession(['id_usuario' => 1]);

        $this->from(route('perfil.show'))->patch(route('perfil.contacto'), ['telefono' => '55551234', 'correo' => 'persona@example.com'])->assertRedirect(route('perfil.show'));
        $this->from(route('perfil.foto.editar'))->post(route('perfil.foto'), ['foto' => UploadedFile::fake()->image('foto.jpg', 100, 100)])->assertRedirect(route('perfil.foto.editar'));
        $this->from(route('perfil.foto.editar'))->delete(route('perfil.quitar-foto'))->assertRedirect(route('perfil.foto.editar'));
        $this->from(route('perfil.contrasena.editar'))->post(route('perfil.contrasena'), ['contrasena_actual' => 'Equivocada-12345', 'nueva_contrasena' => 'Segura-Nueva123!', 'nueva_contrasena_confirmation' => 'Segura-Nueva123!'])->assertRedirect(route('perfil.contrasena.editar'));
        $this->from(route('perfil.contrasena.editar'))->post(route('perfil.contrasena'), ['contrasena_actual' => 'Anterior-12345', 'nueva_contrasena' => 'Segura-Nueva123!', 'nueva_contrasena_confirmation' => 'Segura-Nueva123!'])->assertRedirect(route('perfil.contrasena.editar'));
        $this->get(route('perfil.contrasena.editar'))->assertSee('Contraseña actualizada correctamente.');
    }

    public function test_cambia_contacto_solo_propio_y_correo_requiere_contrasena(): void
    {
        $this->withSession(['id_usuario' => 1])->patch(route('perfil.contacto'), ['telefono' => '55551234', 'correo' => 'persona@example.com', 'id_personal' => 999, 'id_cargo' => 999, 'estado' => 'Inactivo'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_personal', ['id_personal' => 1, 'telefono' => '5555-1234', 'estado' => 'Activo', 'id_cargo' => 1]);
        $this->patch(route('perfil.contacto'), ['correo' => 'nuevo@example.edu.gt'])->assertSessionHasErrors('clave_contacto');
        $this->patch(route('perfil.contacto'), ['correo' => 'nuevo@example.edu.gt', 'clave_contacto' => 'Anterior-12345'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_personal', ['id_personal' => 1, 'correo' => 'nuevo@example.edu.gt']);
        $this->patch(route('perfil.contacto'), ['correo' => ['invalido']])->assertSessionHasErrors('correo');
        $this->patch(route('perfil.contacto'), ['telefono' => 'abc'])->assertSessionHasErrors('telefono');
    }

    public function test_foto_se_guarda_reemplaza_y_quita_con_archivos_privados(): void
    {
        Storage::fake('documentos_clinicos');
        $this->withSession(['id_usuario' => 1])->post(route('perfil.foto'), ['foto' => UploadedFile::fake()->image('foto.jpg', 100, 100)])->assertSessionHasNoErrors();
        $persona = Personal::findOrFail(1);
        $primera = $persona->foto_perfil;
        Storage::disk('documentos_clinicos')->assertExists($primera);
        $this->get(route('perfil.foto.editar'))->assertOk()->assertSee('Foto de Persona')->assertSee('Quitar foto');
        $this->get(route('personal.foto', $persona))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->post(route('perfil.foto'), ['foto' => UploadedFile::fake()->image('otra.png', 100, 100)])->assertSessionHasNoErrors();
        Storage::disk('documentos_clinicos')->assertMissing($primera);
        $segunda = $persona->fresh()->foto_perfil;
        $this->delete(route('perfil.quitar-foto'))->assertSessionHasNoErrors();
        Storage::disk('documentos_clinicos')->assertMissing($segunda);
        $this->assertNull($persona->fresh()->foto_perfil);
        $this->get(route('personal.foto', $persona))->assertNotFound();
    }

    public function test_foto_rechaza_pdf_svg_archivos_grandes_y_dimensiones_invalidas(): void
    {
        Storage::fake('documentos_clinicos');
        $this->withSession(['id_usuario' => 1]);
        foreach ([UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('foto.svg', 10, 'image/svg+xml'), UploadedFile::fake()->image('pequena.jpg', 10, 10), UploadedFile::fake()->image('grande.jpg', 100, 100)->size(3000)] as $foto) {
            $this->post(route('perfil.foto'), ['foto' => $foto])->assertSessionHasErrors('foto');
        }
        $this->assertNull(Personal::findOrFail(1)->foto_perfil);
        $this->assertCount(0, Storage::disk('documentos_clinicos')->allFiles());
    }

    public function test_contrasena_requiere_actual_confirmacion_y_fortaleza_sin_guardar_secretos_en_sesion(): void
    {
        $this->withSession(['id_usuario' => 1]);
        $datos = ['contrasena_actual' => 'Equivocada-12345', 'nueva_contrasena' => 'Segura-Nueva123!', 'nueva_contrasena_confirmation' => 'Segura-Nueva123!'];
        $this->post(route('perfil.contrasena'), $datos)->assertSessionHasErrors('contrasena_actual')->assertSessionMissing('_old_input.contrasena_actual')->assertSessionMissing('_old_input.nueva_contrasena');
        $this->post(route('perfil.contrasena'), array_replace($datos, ['contrasena_actual' => 'Anterior-12345', 'nueva_contrasena_confirmation' => 'otra']))->assertSessionHasErrors('nueva_contrasena');
        $this->post(route('perfil.contrasena'), array_replace($datos, ['contrasena_actual' => 'Anterior-12345', 'nueva_contrasena' => 'corta', 'nueva_contrasena_confirmation' => 'corta']))->assertSessionHasErrors('nueva_contrasena');
        $this->assertTrue(Hash::check('Anterior-12345', DB::table('tbl_usuario')->value('contrasena')));
    }

    public function test_cambia_contrasena_conserva_sesion_y_anula_recuperacion_pendiente(): void
    {
        DB::table('recuperacion_contrasenas')->insert(['id_usuario' => 1, 'correo' => 'persona@example.com', 'token' => str_repeat('x', 64), 'created_at' => now()]);
        $this->withSession(['id_usuario' => 1])->post(route('perfil.contrasena'), ['contrasena_actual' => 'Anterior-12345', 'nueva_contrasena' => 'Segura-Nueva123!', 'nueva_contrasena_confirmation' => 'Segura-Nueva123!'])->assertSessionHasNoErrors()->assertSessionHas('id_usuario', 1);
        $this->assertTrue(Hash::check('Segura-Nueva123!', DB::table('tbl_usuario')->value('contrasena')));
        $this->assertDatabaseCount('recuperacion_contrasenas', 0);
        $this->post('/logout')->assertRedirect('/')->assertSessionMissing('id_usuario');
        $this->get(route('perfil.show'))->assertRedirect('/');
    }
}
