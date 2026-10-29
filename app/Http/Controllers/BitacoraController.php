<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BitacoraController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'accion' => ['nullable', 'string', 'max:100'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'resultado' => ['nullable', 'in:Exitoso,Fallido'],
        ]);
        $consulta = DB::table('tbl_bitacora as b')
            ->leftJoin('tbl_usuario as u', 'u.id_usuario', '=', 'b.id_usuario')
            ->select('b.*', 'u.nombre_usuario');
        if (! empty($filtros['fecha'])) {
            $inicio = Carbon::parse($filtros['fecha'], 'America/Guatemala')->startOfDay()->utc();
            $consulta->where('b.fecha_hora', '>=', $inicio)->where('b.fecha_hora', '<', $inicio->copy()->addDay());
        }
        if (! empty($filtros['resultado'])) {
            $consulta->where('b.resultado', $filtros['resultado']);
        }
        if (! empty($filtros['accion'])) {
            $consulta->where('b.accion', 'like', '%'.$filtros['accion'].'%');
        }
        $registros = $consulta->orderByDesc('b.fecha_hora')->orderByDesc('b.id_bitacora')->paginate(20)->withQueryString();

        return view('bitacora.index', compact('registros'));
    }
}
