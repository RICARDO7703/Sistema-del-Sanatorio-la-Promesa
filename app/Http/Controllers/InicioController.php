<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use App\Models\Hospitalizacion;
use App\Models\Ingreso;
use App\Models\NotaEnfermeria;
use Illuminate\View\View;

class InicioController extends Controller
{
    /**
     * Panel de inicio: cifras del día y consultas externas que quedaron abiertas de días anteriores.
     */
    public function index(): View
    {
        $ahora = now('America/Guatemala');
        $inicioDelDia = $ahora->copy()->startOfDay()->format('Y-m-d H:i:s');
        $tiposConsulta = [Ingreso::TIPO_CONSULTA_EXTERNA, Ingreso::TIPO_CONSULTA_EXTERNA_ANTERIOR];

        $emergenciasPendientes = Ingreso::with('paciente')
            ->where('estado', 'Activo')
            ->whereIn('tipo_atencion', $tiposConsulta)
            ->where('fecha_ingreso', '<', $inicioDelDia)
            ->orderBy('fecha_ingreso')
            ->get();

        $habitacionesActivas = Habitacion::where('estado_operativo', 'Activa')->count();
        $habitacionesOcupadas = Habitacion::where('estado_operativo', 'Activa')
            ->whereHas('asignacionesHospitalizacion', fn ($consulta) => $consulta->whereNull('fecha_hora_fin'))
            ->count();

        $cifras = [
            'internados' => Hospitalizacion::where('estado', 'Activa')->whereHas('ingreso', fn ($consulta) => $consulta->where('estado', 'Activo'))->count(),
            'consultasHoy' => Ingreso::whereIn('tipo_atencion', $tiposConsulta)->where('fecha_ingreso', '>=', $inicioDelDia)->where('estado', '<>', 'Cancelado')->count(),
            'ocupacion' => $habitacionesActivas > 0 ? (int) round($habitacionesOcupadas * 100 / $habitacionesActivas) : null,
            'habitacionesOcupadas' => $habitacionesOcupadas,
            'habitacionesActivas' => $habitacionesActivas,
        ];

        $turnoDelDia = NotaEnfermeria::turnoPorHora((int) $ahora->format('G'));

        return view('inicio', compact('emergenciasPendientes', 'cifras', 'ahora', 'turnoDelDia'));
    }
}
