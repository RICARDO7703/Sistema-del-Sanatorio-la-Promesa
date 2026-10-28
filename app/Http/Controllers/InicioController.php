<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use Illuminate\View\View;

class InicioController extends Controller
{
    public function index(): View
    {
        $inicioDelDia = now('America/Guatemala')->startOfDay()->format('Y-m-d H:i:s');

        $emergenciasPendientes = Ingreso::with('paciente')
            ->where('estado', 'Activo')
            ->whereIn('tipo_atencion', [Ingreso::TIPO_CONSULTA_EXTERNA, Ingreso::TIPO_CONSULTA_EXTERNA_ANTERIOR])
            ->where('fecha_ingreso', '<', $inicioDelDia)
            ->orderBy('fecha_ingreso')
            ->get();

        return view('inicio', compact('emergenciasPendientes'));
    }
}
