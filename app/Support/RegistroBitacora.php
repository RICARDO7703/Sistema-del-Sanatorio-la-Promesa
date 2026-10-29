<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistroBitacora
{
    public function registrar(Request $request, string $accion, string $resultado, string $descripcion, ?int $usuario = null, ?int $registro = null, ?string $nombre = null, string $tabla = 'tbl_usuario'): void
    {
        $agente = mb_substr($request->userAgent() ?? '', 0, 1024);
        $ip = $request->ip();
        if (config('services.bitacora_railway') && filter_var($request->header('X-Real-IP'), FILTER_VALIDATE_IP)) {
            $ip = $request->header('X-Real-IP');
        }
        DB::table('tbl_bitacora')->insert([
            'id_usuario' => $usuario,
            'usuario_intentado' => $nombre !== null ? mb_substr($nombre, 0, 50) : null,
            'accion' => $accion,
            'resultado' => $resultado,
            'descripcion' => $descripcion,
            'fecha_hora' => now('UTC')->format('Y-m-d H:i:s'),
            'ip' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null,
            'navegador' => $this->navegador($agente),
            'sistema_operativo' => $this->sistema($agente),
            'agente_usuario' => $agente ?: null,
            'tabla' => $tabla,
            'id_registro' => $registro,
        ]);
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
