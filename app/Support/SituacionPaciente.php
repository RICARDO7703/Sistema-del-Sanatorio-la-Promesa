<?php

namespace App\Support;

use App\Models\Cirugia;
use App\Models\Paciente;
use Illuminate\Support\Collection;

/**
 * Calcula la situación actual de cada paciente (hospitalizado, en consulta, dado de alta, en cirugía…)
 * para que el listado de pacientes y el de expedientes muestren siempre lo mismo.
 */
class SituacionPaciente
{
    /**
     * Agrega a cada paciente situacion_actual, habitacion_actual, fecha_ultima_alta,
     * ultima_alta_emergencia y la relación cirugiaActual.
     *
     * @param  Collection<int, Paciente>  $pacientes
     */
    public static function asignar(Collection $pacientes): void
    {
        $pacientes->loadMissing(['ingresos.hospitalizacion.habitacionActual.habitacion', 'ingresos.altaMedica']);

        foreach ($pacientes as $paciente) {
            $paciente->situacion_actual = 'Sin ingresos';
            $paciente->habitacion_actual = null;
            $paciente->fecha_ultima_alta = null;
            $paciente->ultima_alta_emergencia = false;

            $ingresoActivo = $paciente->ingresos->where('estado', 'Activo')->sortByDesc('fecha_ingreso')->first();

            if ($ingresoActivo) {
                $hospitalizacion = $ingresoActivo->hospitalizacion;

                if ($hospitalizacion && $hospitalizacion->estado === 'Activa') {
                    $paciente->situacion_actual = 'Hospitalizado';
                    $paciente->habitacion_actual = $hospitalizacion->habitacionActual?->habitacion?->numero_habitacion;
                } elseif ($ingresoActivo->esConsultaExterna()) {
                    $paciente->situacion_actual = 'En consulta';
                } else {
                    $paciente->situacion_actual = 'En atención';
                }

                continue;
            }

            $ultimoIngresoConAlta = $paciente->ingresos
                ->filter(fn ($ingreso) => $ingreso->altaMedica !== null)
                ->sortByDesc(fn ($ingreso) => $ingreso->altaMedica->fecha_hora)
                ->first();

            if ($ultimoIngresoConAlta) {
                $paciente->situacion_actual = 'Dado de alta';
                $paciente->ultima_alta_emergencia = $ultimoIngresoConAlta->esConsultaExterna();
                $paciente->fecha_ultima_alta = $ultimoIngresoConAlta->altaMedica->fecha_hora;
            }
        }

        $cirugiasEnCurso = Cirugia::with('ingreso.paciente')->whereIn('estado', ['En cirugía', 'Programada', 'Aprobada', 'Evaluación preoperatoria'])
            ->orderByRaw("CASE WHEN estado = 'En cirugía' THEN 0 ELSE 1 END")->orderBy('fecha_programada')->get()
            ->filter(fn (Cirugia $cirugia) => $cirugia->atencionQuirurgicaDisponible());

        foreach ($pacientes as $paciente) {
            $cirugiaActual = $cirugiasEnCurso->first(fn (Cirugia $cirugia) => (int) $cirugia->ingreso->id_paciente === (int) $paciente->getKey());
            $paciente->setRelation('cirugiaActual', $cirugiaActual);

            if ($cirugiaActual?->estado === 'En cirugía') {
                $paciente->situacion_actual = 'En cirugía';
            }
        }
    }
}
