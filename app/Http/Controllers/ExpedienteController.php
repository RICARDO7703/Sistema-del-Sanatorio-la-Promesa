<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use App\Models\Paciente;

class ExpedienteController extends Controller
{
    public function show(Paciente $paciente)
    {
        $paciente->load('contactos');

        $ingresos = $paciente->ingresos()->with(['altaMedica.medico', 'personalRegistra', 'hospitalizacion.habitacionActual.habitacion', 'hospitalizacion.historialHabitaciones' => fn ($consulta) => $consulta->with('habitacion')->orderBy('fecha_hora_inicio')->orderBy('id_hospitalizacion_habitacion'), 'evoluciones' => fn ($consulta) => $consulta->where('estado', 'Activo')->with('personal')->orderBy('fecha_hora')->orderBy('id_evolucion')])->withCount(['evoluciones' => fn ($consulta) => $consulta->where('estado', 'Activo')])
            ->orderBy('fecha_ingreso', 'desc')
            ->get();

        $ingresoActivo = $paciente->ingresos()->with('hospitalizacion.habitacionActual.habitacion')
            ->where('estado', 'Activo')
            ->orderBy('fecha_ingreso', 'desc')
            ->first();

        $habitacionesDisponibles = Habitacion::where(
            'estado_operativo',
            'Activa'
        )
            ->whereDoesntHave(
                'asignacionesHospitalizacion',
                function ($consulta) {
                    $consulta->whereNull('fecha_hora_fin');
                }
            )
            ->orderBy('numero_habitacion')
            ->get();

        return view(
            'expedientes.show',
            compact(
                'paciente',
                'ingresos',
                'ingresoActivo',
                'habitacionesDisponibles'
            )
        );
    }
}
