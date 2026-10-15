<?php

namespace Tests\Feature;

use App\Mail\RecuperarContrasenaMail;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class ResendHttpTransportTest extends TestCase
{
    public function test_envia_codigo_por_https_al_destinatario_correcto(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.resend.com/emails' => Http::response(['id' => 'correo-de-prueba'])]);
        config(['services.resend.key' => 'clave-ficticia', 'mail.from.address' => 'cuentas@example.com']);
        Mail::mailer('resend_http')->to('persona@example.com')->send(new RecuperarContrasenaMail('012345'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.resend.com/emails'
            && $request->hasHeader('Authorization', 'Bearer clave-ficticia')
            && $request['to'] === ['persona@example.com']
            && str_contains($request['html'], '012345')
            && str_contains($request['from'], 'cuentas@example.com'));
    }

    public function test_rechazo_del_proveedor_no_expone_el_codigo(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.resend.com/emails' => Http::response(['message' => 'Dato sensible: 012345'], 403)]);
        config(['services.resend.key' => 'clave-ficticia']);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('No se pudo completar el envío del correo.');
        Mail::mailer('resend_http')->to('persona@example.com')->send(new RecuperarContrasenaMail('012345'));
    }

    public function test_sin_clave_no_hay_peticion_externa(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        config(['services.resend.key' => null]);
        try {
            Mail::mailer('resend_http')->to('persona@example.com')->send(new RecuperarContrasenaMail('012345'));
            self::fail('Debía rechazar la falta de clave.');
        } catch (TransportException $error) {
            self::assertStringContainsString('Falta configurar', $error->getMessage());
        }
        Http::assertNothingSent();
    }
}
