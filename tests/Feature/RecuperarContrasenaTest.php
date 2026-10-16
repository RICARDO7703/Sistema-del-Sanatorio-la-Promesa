<?php

namespace Tests\Feature;

use App\Mail\RecuperarContrasenaMail;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecuperarContrasenaTest extends TestCase
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

    private function solicitar(): string
    {
        $this->post('/recuperar-contrasena', ['usuario' => 'prueba', 'correo' => 'persona@example.com'])->assertRedirect(route('password.code'))->assertSessionHas('status');
        $mail = Mail::sent(RecuperarContrasenaMail::class)->last();
        self::assertNotNull($mail);
        self::assertTrue($mail->hasTo('persona@example.com'));
        self::assertMatchesRegularExpression('/^[0-9]{6}$/', $mail->codigo);
        self::assertStringContainsString($mail->codigo, $mail->render());

        return $mail->codigo;
    }

    private function datos(): array
    {
        return ['password' => 'Nueva-Segura123!', 'password_confirmation' => 'Nueva-Segura123!'];
    }

    public function test_solicita_verifica_y_cambia_la_contrasena(): void
    {
        $this->get('/')->assertSee(route('password.request'), false);
        $this->get('/recuperar-contrasena')->assertOk()->assertSee('Paso 1 de 3');
        $codigo = $this->solicitar();
        self::assertSame(hash_hmac('sha256', $codigo, config('app.key')), DB::table('recuperacion_contrasenas')->value('token'));
        $this->get('/verificar-codigo')->assertOk()->assertSee('Código de verificación (token)');
        $this->post('/verificar-codigo', ['codigo' => $codigo])->assertRedirect(route('password.reset'));
        $this->get('/restablecer-contrasena')->assertOk()->assertSee('Código verificado');
        $this->post('/restablecer-contrasena', $this->datos())->assertRedirect('/')->assertSessionHas('status');
        self::assertTrue(Hash::check('Nueva-Segura123!', DB::table('tbl_usuario')->value('contrasena')));
        $this->assertDatabaseCount('recuperacion_contrasenas', 0);
        $this->post('/restablecer-contrasena', $this->datos())->assertRedirect(route('password.request'));
        $this->travel(61)->seconds();
        $this->post('/login', ['usuario' => 'prueba', 'contrasena' => 'Anterior-12345'])->assertSessionHas('error');
        $this->post('/login', ['usuario' => 'prueba', 'contrasena' => 'Nueva-Segura123!'])->assertRedirect('/inicio');
    }

    public function test_no_permite_saltar_la_verificacion_ni_reutilizar_el_codigo(): void
    {
        $this->get('/restablecer-contrasena')->assertRedirect(route('password.request'));
        $codigo = $this->solicitar();
        $this->post('/restablecer-contrasena', $this->datos() + ['codigo' => $codigo])->assertRedirect(route('password.request'));
        $this->post('/verificar-codigo', ['codigo' => $codigo])->assertRedirect(route('password.reset'));
        $this->withSession(['recuperacion_usuario' => 1])->post('/verificar-codigo', ['codigo' => $codigo])->assertSessionHasErrors('codigo');
        self::assertTrue(Hash::check('Anterior-12345', DB::table('tbl_usuario')->value('contrasena')));
    }

    public function test_no_revela_cuentas_ni_envia_a_otro_correo(): void
    {
        $this->post('/recuperar-contrasena', ['usuario' => 'inexistente', 'correo' => 'persona@example.com'])->assertRedirect(route('password.code'));
        $mensaje = session('status');
        $this->post('/recuperar-contrasena', ['usuario' => 'prueba', 'correo' => 'otro@example.com'])->assertSessionHas('status', $mensaje);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('recuperacion_contrasenas', 0);
    }

    public function test_caducidad_del_codigo(): void
    {
        $codigo = $this->solicitar();
        $this->travel(11)->minutes();
        $this->post('/verificar-codigo', ['codigo' => $codigo])->assertSessionHasErrors('codigo');
        self::assertArrayNotHasKey('codigo', session()->getOldInput());
        self::assertTrue(Hash::check('Anterior-12345', DB::table('tbl_usuario')->value('contrasena')));
    }

    public function test_bloquea_codigo_tras_cinco_intentos_aunque_cambie_la_sesion(): void
    {
        $codigo = $this->solicitar();
        $incorrecto = $codigo === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) {
            $this->post('/verificar-codigo', ['codigo' => $incorrecto])->assertSessionHasErrors('codigo');
            $this->travel(61)->seconds();
        }
        $this->assertDatabaseHas('recuperacion_contrasenas', ['intentos' => 5]);
        session()->invalidate();
        $this->withSession(['recuperacion_usuario' => 1])->post('/verificar-codigo', ['codigo' => $codigo])->assertSessionHasErrors('codigo');
    }

    public function test_reenvio_invalida_codigo_anterior_y_no_envia_repetidamente(): void
    {
        $primero = $this->solicitar();
        $this->post('/recuperar-contrasena', ['usuario' => 'prueba', 'correo' => 'persona@example.com']);
        Mail::assertSentCount(1);
        $this->travel(61)->seconds();
        $segundo = $this->solicitar();
        Mail::assertSentCount(2);
        if ($primero !== $segundo) {
            $this->post('/verificar-codigo', ['codigo' => $primero])->assertSessionHasErrors('codigo');
        }
        $this->post('/verificar-codigo', ['codigo' => $segundo])->assertRedirect(route('password.reset'));
    }

    public function test_contrasena_debil_confirmacion_y_permiso_caducado(): void
    {
        $codigo = $this->solicitar();
        $this->post('/verificar-codigo', ['codigo' => $codigo])->assertRedirect(route('password.reset'));
        $this->post('/restablecer-contrasena', ['password' => 'abc', 'password_confirmation' => 'abc'])->assertSessionHasErrors('password');
        self::assertArrayNotHasKey('password', session()->getOldInput());
        $this->post('/restablecer-contrasena', array_replace($this->datos(), ['password_confirmation' => 'OtraClave-12345']))->assertSessionHasErrors('password');
        $this->travel(11)->minutes();
        $this->post('/restablecer-contrasena', $this->datos())->assertSessionHasErrors('codigo');
    }

    public function test_revalida_correo_y_estado_al_guardar(): void
    {
        $codigo = $this->solicitar();
        $this->post('/verificar-codigo', ['codigo' => $codigo]);
        DB::table('tbl_personal')->update(['correo' => 'nuevo@example.com']);
        $this->post('/restablecer-contrasena', $this->datos())->assertSessionHasErrors('codigo');
        DB::table('tbl_personal')->update(['correo' => 'persona@example.com', 'estado' => 'Inactivo']);
        $this->post('/restablecer-contrasena', $this->datos())->assertSessionHasErrors('codigo');
    }

    public function test_visita_sin_sesion_no_muestra_error_y_login_incorrecto_si(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Debe iniciar sesión con un usuario activo.');
        $this->get('/inicio')->assertRedirect('/')->assertSessionMissing('error');
        $this->get('/')->assertOk()->assertDontSee('Debe iniciar sesión con un usuario activo.');
        $this->post('/login', ['usuario' => 'prueba', 'contrasena' => 'incorrecta'])->assertSessionHas('error', 'Usuario o contraseña incorrectos.');
        $this->get('/')->assertSee('Usuario o contraseña incorrectos.');
        $this->get('/')->assertDontSee('Usuario o contraseña incorrectos.');
    }

    public function test_cuenta_desactivada_sigue_sin_acceso(): void
    {
        DB::table('tbl_usuario')->update(['estado' => 'Inactivo']);
        $this->withSession(['id_usuario' => 1])->get('/inicio')->assertRedirect('/')->assertSessionMissing('id_usuario')->assertSessionHas('aviso_sesion');
    }

    public function test_produccion_no_simula_envio_con_mailer_log(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        app()->instance('env', 'production');
        config(['mail.default' => 'log']);
        $this->post('/recuperar-contrasena', ['usuario' => 'prueba', 'correo' => 'persona@example.com'])->assertSessionHas('error');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('recuperacion_contrasenas', 0);
    }

    public function test_csrf_sigue_activo_en_produccion(): void
    {
        app()->instance('env', 'production');
        $this->post('/recuperar-contrasena', ['usuario' => 'prueba', 'correo' => 'persona@example.com'])->assertStatus(419);
        $this->post('/verificar-codigo', ['codigo' => '123456'])->assertStatus(419);
        $this->post('/restablecer-contrasena', $this->datos())->assertStatus(419);
        Mail::assertNothingSent();
    }
}
