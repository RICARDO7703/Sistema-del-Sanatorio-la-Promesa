<?php

namespace App\Http\Middleware;

use App\Models\Ingreso;
use App\Services\ReglasAtencion;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SesionClinica
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->session()->get('id_usuario');
        if (! $usuario) {
            return redirect('/');
        }
        if (! DB::table('tbl_usuario')->where('id_usuario', $usuario)->where('estado', 'Activo')->whereIn('id_personal', DB::table('tbl_personal')->select('id_personal')->where('estado', 'Activo'))->whereIn('id_rol', DB::table('tbl_rol')->select('id_rol')->where('estado', 'Activo'))->exists()) {
            $request->session()->forget('id_usuario');

            return redirect('/')->with('aviso_sesion', 'Tu sesión ya no está disponible. Inicia sesión nuevamente.');
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $idIngreso = null;
        foreach (['ingreso', 'evolucion', 'signo', 'tratamiento', 'hospitalizacion'] as $parametro) {
            $modelo = $request->route($parametro);
            if ($modelo instanceof Model) {
                $idIngreso = $modelo->getAttribute('id_ingreso');
                break;
            }
        }
        if (in_array($request->route()?->getName(), ['evolucion.guardar', 'signos.guardar', 'tratamientos.guardar'], true)) {
            $idIngreso = filter_var($request->input('id_ingreso'), FILTER_VALIDATE_INT) ?: null;
        }
        if (! $idIngreso) {
            return $next($request);
        }

        return DB::transaction(function () use ($idIngreso, $request, $next): Response {
            $ingreso = Ingreso::whereKey($idIngreso)->lockForUpdate()->first();
            if ($ingreso) {
                ReglasAtencion::exigirActivo($ingreso->estado);
            }

            return $next($request);
        });
    }
}
