<?php

namespace App\Http\Middleware;

use App\Support\Permisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detiene la solicitud si el usuario no tiene el permiso que exige la ruta (ver Permisos::RUTAS).
 * Una ruta nueva sin permiso asignado queda bloqueada para quien no sea administrador.
 */
class PermisoModulo
{
    public function handle(Request $request, Closure $next): Response
    {
        $nombre = $request->route()?->getName();
        if (in_array($nombre, Permisos::RUTAS_LIBRES, true)) {
            return $next($request);
        }
        [$modulo, $accion] = Permisos::RUTAS[$nombre] ?? ['Usuarios', Permisos::VER];
        if (app(Permisos::class)->puede($modulo, $accion)) {
            return $next($request);
        }

        $mensaje = 'No tiene permiso para '.Permisos::descripcion($modulo, $accion).'. Pida al administrador que se lo asigne.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje], 403);
        }
        $destino = $request->isMethodSafe() ? url('/inicio') : url()->previous();
        if ($destino === $request->fullUrl()) {
            $destino = url('/inicio');
        }

        return redirect()->to($destino)->with('permiso_denegado', $mensaje);
    }
}
