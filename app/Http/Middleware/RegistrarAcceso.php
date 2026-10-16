<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RegistrarAcceso
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->is('login')) {
            return $next($request);
        }

        $motivo = 'Credenciales incorrectas o cuenta no habilitada.';
        try {
            $response = $next($request);
            if ($response->getStatusCode() === 429) {
                $motivo = 'Límite de intentos alcanzado.';
            }

            return $response;
        } catch (Throwable $exception) {
            $motivo = match (true) {
                $exception instanceof ValidationException => 'Datos de acceso incompletos o inválidos.',
                $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 429 => 'Límite de intentos alcanzado.',
                default => 'Solicitud de acceso no completada.',
            };
            throw $exception;
        } finally {
            $id = $request->attributes->get('bitacora_usuario_autenticado');
            $nombre = $request->input('usuario');
            $agente = mb_substr($request->userAgent() ?? '', 0, 1024);
            $ip = $request->ip();
            // Railway establece esta cabecera en su entrada HTTP pública.
            if (config('services.bitacora_railway') && filter_var($request->header('X-Real-IP'), FILTER_VALIDATE_IP)) {
                $ip = $request->header('X-Real-IP');
            }
            DB::table('tbl_bitacora')->insert([
                'id_usuario' => $id,
                'usuario_intentado' => is_string($nombre) ? mb_substr($nombre, 0, 50) : null,
                'accion' => 'Inicio de sesión',
                'resultado' => $id ? 'Exitoso' : 'Fallido',
                'descripcion' => $id ? 'Acceso autenticado.' : $motivo,
                'fecha_hora' => now('UTC')->format('Y-m-d H:i:s'),
                'ip' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null,
                'navegador' => $this->navegador($agente),
                'sistema_operativo' => $this->sistema($agente),
                'agente_usuario' => $agente ?: null,
                'tabla' => 'tbl_usuario',
                'id_registro' => $id,
            ]);
        }
    }

    private function navegador(string $agente): string
    {
        foreach (['Edg(?:A|iOS)?' => 'Edge', 'OPR|Opera' => 'Opera', 'SamsungBrowser' => 'Samsung Internet', 'CriOS|Chrome' => 'Chrome', 'FxiOS|Firefox' => 'Firefox'] as $patron => $nombre) {
            if (preg_match('~(?:'.$patron.')/([0-9.]+)~i', $agente, $coincidencia)) {
                return $nombre.' '.$coincidencia[1];
            }
        }
        if (str_contains($agente, 'Safari/') && preg_match('~Version/([0-9.]+)~', $agente, $coincidencia)) {
            return 'Safari '.$coincidencia[1];
        }

        return 'No identificado';
    }

    private function sistema(string $agente): string
    {
        return match (true) {
            preg_match('~iPhone|iPad|iPod~i', $agente) === 1 => 'iOS / iPadOS',
            str_contains($agente, 'Android') => 'Android',
            str_contains($agente, 'Windows NT 10.0') => 'Windows 10 / 11',
            str_contains($agente, 'Windows') => 'Windows',
            str_contains($agente, 'CrOS') => 'ChromeOS',
            str_contains($agente, 'Macintosh') => 'macOS',
            str_contains($agente, 'Linux') => 'Linux',
            default => 'No identificado',
        };
    }
}
