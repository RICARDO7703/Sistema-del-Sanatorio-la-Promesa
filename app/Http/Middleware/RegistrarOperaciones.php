<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RegistrarOperaciones
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $request->session()->get('id_usuario') || $request->routeIs('sesion.salida-pagina')) {
            return $next($request);
        }
        $request->attributes->set('auditar_operaciones', true);
        DB::beginTransaction();
        try {
            $response = $next($request);
            if ($response->getStatusCode() >= 400 || $request->attributes->get('operacion_rechazada')) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return $response;
        } catch (\Throwable $error) {
            DB::rollBack();
            throw $error;
        } finally {
            $request->attributes->remove('auditar_operaciones');
        }
    }
}
