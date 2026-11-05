<?php

namespace App\Http\Middleware;

use App\Support\CambiosEdicion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En los formularios de edición: si se guardó sin cambiar nada, el mensaje de éxito dice que todo sigue igual.
 */
class AvisarSinCambios
{
    public const MENSAJE = 'No hizo ningún cambio: todo sigue igual.';

    public function handle(Request $request, Closure $next): Response
    {
        $cambios = app(CambiosEdicion::class);
        $cambios->iniciar();
        try {
            $respuesta = $next($request);
        } finally {
            $huboCambios = $cambios->terminar();
        }

        $sesion = $request->session();
        $exitoNuevo = in_array('exito', (array) $sesion->get('_flash.new', []), true);
        if (! $huboCambios && $respuesta->isRedirection() && $exitoNuevo && ! $sesion->has('errors')) {
            $sesion->flash('exito', self::MENSAJE);
        }

        return $respuesta;
    }
}
