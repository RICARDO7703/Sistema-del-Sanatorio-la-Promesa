<?php

namespace App\Http\Middleware;

use App\Support\RegistroBitacora;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RegistrarAcceso
{
    public function handle(Request $request, Closure $next): Response
    {
        $acciones = [
            'login' => 'Inicio de sesión',
            'recuperar-contrasena' => 'Solicitud de recuperación',
            'verificar-codigo' => 'Verificación de recuperación',
            'restablecer-contrasena' => 'Recuperación de contraseña',
        ];
        $accion = $acciones[$request->path()] ?? null;
        if (! $request->isMethod('POST') || ! $accion) {
            return $next($request);
        }
        $login = $request->is('login');
        $motivo = $login ? 'Credenciales incorrectas o cuenta no habilitada.' : 'Solicitud de recuperación no completada.';
        try {
            $response = $next($request);
            if ($response->getStatusCode() === 429) {
                $motivo = 'Límite de intentos alcanzado.';
            } elseif ($response->getStatusCode() === 419) {
                $motivo = 'Solicitud rechazada por sesión caducada o verificación CSRF.';
            }

            return $response;
        } catch (Throwable $exception) {
            $motivo = match (true) {
                $exception instanceof ValidationException => 'Datos incompletos, inválidos o verificación caducada.',
                $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 429 => 'Límite de intentos alcanzado.',
                default => 'Solicitud no completada.',
            };
            throw $exception;
        } finally {
            if (! $request->attributes->get('bitacora_registrada', false)) {
                $id = $login ? $request->attributes->get('bitacora_usuario_autenticado') : null;
                $exito = $login ? (bool) $id : $request->attributes->get('bitacora_exito', false);
                $nombre = $request->attributes->get('bitacora_nombre', $request->input('usuario'));
                app(RegistroBitacora::class)->registrar(
                    $request, $accion, $exito ? 'Exitoso' : 'Fallido',
                    $id ? 'Acceso autenticado.' : $request->attributes->get('bitacora_descripcion', $motivo),
                    $id, $id ?? $request->attributes->get('bitacora_registro'),
                    is_string($nombre) ? $nombre : null,
                );
            }
        }
    }
}
