<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use App\Models\Paciente;

class ExpedienteController extends Controller
{
    public function show(Paciente $paciente)
    {
        $paciente->load('contactos');

        $ingresos = $paciente->ingresos()->with('altaMedica.medico')
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
