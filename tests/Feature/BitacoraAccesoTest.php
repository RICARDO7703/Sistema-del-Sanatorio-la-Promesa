<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BitacoraAccesoTest extends TestCase
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

    public function test_registra_acceso_exitoso_sin_guardar_contrasena(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0.0.0 Safari/537.36'])
            ->post('/login', ['usuario' => 'prueba', 'contrasena' => 'Anterior-12345'])->assertRedirect('/inicio');
        $this->assertDatabaseHas('tbl_bitacora', ['id_usuario' => 1, 'id_registro' => 1, 'tabla' => 'tbl_usuario', 'resultado' => 'Exitoso', 'ip' => '203.0.113.10', 'navegador' => 'Chrome 130.0.0.0', 'sistema_operativo' => 'Windows 10 / 11']);
        self::assertStringNotContainsString('Anterior-12345', json_encode(DB::table('tbl_bitacora')->first()));
    }

    public function test_fallos_no_se_atribuyen_a_usuario_autenticado(): void
    {
        foreach (['prueba', 'inexistente'] as $nombre) {
            $this->post('/login', ['usuario' => $nombre, 'contrasena' => 'incorrecta'])->assertSessionHas('error');
            $this->assertDatabaseHas('tbl_bitacora', ['usuario_intentado' => $nombre, 'id_usuario' => null, 'id_registro' => null, 'resultado' => 'Fallido']);
        }
        DB::table('tbl_usuario')->update(['estado' => 'Inactivo']);
        $this->post('/login', ['usuario' => 'prueba', 'contrasena' => 'Anterior-12345'])->assertSessionHas('error');
        $this->assertDatabaseCount('tbl_bitacora', 3);
        self::assertSame(0, DB::table('tbl_bitacora')->whereNotNull('id_usuario')->count());
    }

    public function test_registra_formularios_invalidos_y_limite_de_intentos(): void
    {
        $this->get('/')->assertOk();
        $this->assertDatabaseCount('tbl_bitacora', 0);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['usuario' => ['invalido']])->assertSessionHasErrors();
        }
        $this->post('/login', ['usuario' => ['invalido']])->assertStatus(429);
        $this->assertDatabaseCount('tbl_bitacora', 6);
        $this->assertDatabaseHas('tbl_bitacora', ['descripcion' => 'Límite de intentos alcanzado.', 'resultado' => 'Fallido']);
    }

    public function test_avisos_de_salida_y_errores_de_otra_persona_no_bloquean_el_inicio_de_sesion(): void
    {
        DB::table('tbl_personal')->insert(['id_personal' => 2, 'id_cargo' => 1, 'nombres' => 'Otra', 'apellidos' => 'Persona', 'dpi' => '1234567890124']);
        DB::table('tbl_usuario')->insert(['id_usuario' => 2, 'id_personal' => 2, 'id_rol' => 1, 'nombre_usuario' => 'segunda', 'contrasena' => Hash::make('Segunda-12345')]);

        $this->post('/login', ['usuario' => 'prueba', 'contrasena' => 'Anterior-12345'])->assertRedirect('/inicio');
        for ($i = 0; $i < 4; $i++) {
            $this->post('/sesion/salida-pagina', ['pagina' => (string) Str::uuid()])->assertNoContent();
        }
        $this->post('/logout')->assertRedirect('/');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['usuario' => 'segunda', 'contrasena' => 'incorrecta'])->assertSessionHas('error');
        }
        $this->post('/login', ['usuario' => 'Segunda ', 'contrasena' => 'incorrecta'])->assertStatus(429)
            ->assertHeader('Retry-After')->assertSee('Demasiados intentos')->assertSee('Espere')->assertDontSee('Too Many Requests');

        $this->post('/login', ['usuario' => 'prueba', 'contrasena' => 'Anterior-12345'])->assertRedirect('/inicio');
    }

    public function test_limite_por_red_usa_la_ip_real_en_railway(): void
    {
        config(['services.bitacora_railway' => true]);
        for ($i = 0; $i < 30; $i++) {
            $this->withHeaders(['X-Real-IP' => '203.0.113.10'])->post('/login', ['usuario' => 'usuario'.$i, 'contrasena' => 'incorrecta'])->assertRedirect();
        }
        $this->withHeaders(['X-Real-IP' => '203.0.113.10'])->post('/login', ['usuario' => 'otro', 'contrasena' => 'incorrecta'])->assertStatus(429);
        $this->withHeaders(['X-Real-IP' => '203.0.113.11'])->post('/login', ['usuario' => 'prueba', 'contrasena' => 'Anterior-12345'])->assertRedirect('/inicio');
    }

    public function test_railway_ipv6_y_navegador_movil(): void
    {
        config(['services.bitacora_railway' => true]);
        $this->withHeaders(['X-Real-IP' => '2001:db8::1', 'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Version/18.0 Mobile/15E148 Safari/604.1'])
            ->post('/login', ['usuario' => 'prueba', 'contrasena' => 'incorrecta']);
        $this->assertDatabaseHas('tbl_bitacora', ['ip' => '2001:db8::1', 'navegador' => 'Safari 18.0', 'sistema_operativo' => 'iOS / iPadOS']);
    }

    public function test_local_no_confia_en_ip_enviada_por_cliente(): void
    {
        config(['services.bitacora_railway' => false]);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['X-Real-IP' => '203.0.113.20', 'X-Forwarded-For' => '203.0.113.21'])
            ->post('/login', ['usuario' => 'prueba', 'contrasena' => 'incorrecta']);
        $this->assertDatabaseHas('tbl_bitacora', ['ip' => '127.0.0.1']);
    }

    public function test_consulta_filtros_hora_local_y_escape(): void
    {
        $this->get('/bitacora')->assertRedirect('/');
        $this->travelTo(Carbon::parse('2026-10-16 02:30:00', 'UTC'));
        $this->post('/login', ['usuario' => '<script>alert(1)</script>', 'contrasena' => 'incorrecta']);
        $this->withSession(['id_usuario' => 1])->get('/bitacora?fecha=2026-10-15&resultado=Fallido')
            ->assertOk()->assertSee('15/10/2026 20:30:00')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/bitacora?fecha=2026-10-16')->assertOk()->assertSee('No hay registros');
        $this->get('/bitacora?resultado=Exitoso')->assertOk()->assertSee('No hay registros');
        $this->assertDatabaseCount('tbl_bitacora', 1);
    }

    public function test_migracion_conserva_registros_y_admite_repeticion(): void
    {
        Schema::table('tbl_bitacora', fn ($table) => $table->dropColumn(['ip', 'navegador', 'sistema_operativo', 'agente_usuario', 'tabla', 'id_registro']));
        DB::table('tbl_bitacora')->insert(['accion' => 'Anterior', 'resultado' => 'Exitoso']);
        $migration = require database_path('migrations/2026_10_16_042044_add_contexto_to_tbl_bitacora.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('tbl_bitacora', ['accion' => 'Anterior', 'ip' => null]);
    }

    public function test_bitacora_pagina_cinco_filtra_fechas_y_exporta_pdf(): void
    {
        for ($n = 0; $n < 7; $n++) {
            DB::table('tbl_bitacora')->insert(['id_usuario' => 1, 'accion' => 'Acción de prueba', 'resultado' => 'Exitoso', 'descripcion' => 'Detalle '.$n, 'tabla' => 'tbl_usuario', 'id_registro' => 1, 'fecha_hora' => '2026-10-15 18:00:00']);
        }
        DB::table('tbl_bitacora')->insert(['accion' => 'Otra acción', 'resultado' => 'Fallido', 'fecha_hora' => '2026-10-16 18:00:00']);
        $this->get('/bitacora/pdf')->assertRedirect('/');
        $this->withSession(['id_usuario' => 1]);
        $res = $this->get('/bitacora?desde=2026-10-15&accion=Acci%C3%B3n%20de%20prueba')->assertOk()->assertSee('Usuario · prueba')->assertSee('Ver e imprimir');
        $this->assertCount(5, $res->viewData('registros'));
        $this->assertSame(7, $res->viewData('registros')->total());
        $this->get('/bitacora?desde=2026-10-15&page=2')->assertOk()->assertViewHas('registros', fn ($p) => $p->count() === 2);
        $this->get('/bitacora?hasta=2026-10-16')->assertOk()->assertViewHas('registros', fn ($p) => $p->total() === 1);
        $this->get('/bitacora?desde=2026-10-15&hasta=2026-10-16')->assertOk()->assertViewHas('registros', fn ($p) => $p->total() === 8);
        $this->getJson('/bitacora?desde=2026-10-16&hasta=2026-10-15')->assertUnprocessable();
        $pdf = $this->get('/bitacora/pdf?desde=2026-10-15&descargar=1')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        if (getenv('REPORT_PDF_QA')) {
            file_put_contents(getenv('REPORT_PDF_QA').'/bitacora.pdf', $pdf->getContent());
        }
    }
}
