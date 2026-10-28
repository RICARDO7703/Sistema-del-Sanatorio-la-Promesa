<?php

namespace App\Services;

use App\Models\Habitacion;
use App\Models\Hospitalizacion;
use App\Models\HospitalizacionHabitacion;
use App\Models\Ingreso;
use App\Models\Paciente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Registra una atención nueva (hospitalización o consulta externa) para un paciente.
 *
 * Las reglas de negocio lanzan RuntimeException con un mensaje apto para mostrar al usuario.
 */
class RegistroIngreso
{
    /**
     * @param  array{id_paciente: int, tipo_atencion: string, fecha_ingreso: string, motivo_ingreso?: ?string, id_ingreso_anterior?: ?int, documento_firmado?: bool, id_habitacion?: ?int}  $datos
     */
    public static function registrar(array $datos, int $idUsuario, int $idPersonal): Ingreso
    {
        return DB::transaction(function () use ($datos, $idUsuario, $idPersonal): Ingreso {
            $paciente = Paciente::whereKey($datos['id_paciente'])->lockForUpdate()->firstOrFail();

            if ($paciente->estado !== 'Activo') {
                throw new RuntimeException('El paciente no se encuentra activo.');
            }

            if (Ingreso::where('id_paciente', $paciente->id_paciente)->where('estado', 'Activo')->exists()) {
                throw new RuntimeException('El paciente ya tiene un ingreso activo.');
            }

            $anterior = null;
            if (! empty($datos['id_ingreso_anterior'])) {
                $anterior = Ingreso::whereKey($datos['id_ingreso_anterior'])->lockForUpdate()->first();
                if (! $anterior || $anterior->id_paciente !== $paciente->id_paciente || $anterior->estado !== 'Dado de alta' || ! $anterior->altaMedica) {
                    throw new RuntimeException('Seleccione una atención finalizada del mismo paciente.');
                }
                if ($datos['tipo_atencion'] !== Ingreso::TIPO_CONSULTA_EXTERNA) {
                    throw new RuntimeException('El seguimiento comienza como una consulta sin internamiento.');
                }
                if (Carbon::parse($datos['fecha_ingreso'])->lt($anterior->altaMedica->fecha_hora)) {
                    throw new RuntimeException('El seguimiento no puede comenzar antes de la salida de la atención anterior.');
                }
            }

            $hospitalizar = $datos['tipo_atencion'] === Ingreso::TIPO_HOSPITALIZACION;
            $habitacion = $hospitalizar && ! empty($datos['id_habitacion']) ? self::habitacionDisponible((int) $datos['id_habitacion']) : null;

            $ingreso = Ingreso::create([
                'id_paciente' => $paciente->id_paciente,
                'id_ingreso_anterior' => $anterior?->getKey(),
                'tipo_atencion' => $datos['tipo_atencion'],
                'id_personal_registra' => $idPersonal,
                'fecha_ingreso' => $datos['fecha_ingreso'],
                'motivo_ingreso' => $datos['motivo_ingreso'] ?? null,
                'documento_firmado' => (bool) ($datos['documento_firmado'] ?? false),
                'estado' => 'Activo',
                'id_usuario_creacion' => $idUsuario,
            ]);

            if ($hospitalizar) {
                $hospitalizacion = Hospitalizacion::create([
                    'id_ingreso' => $ingreso->id_ingreso,
                    'fecha_inicio' => $datos['fecha_ingreso'],
                    'motivo' => $datos['motivo_ingreso'] ?? null,
                    'estado' => 'Activa',
                    'id_usuario_creacion' => $idUsuario,
                ]);

                if ($habitacion) {
                    HospitalizacionHabitacion::create([
                        'id_hospitalizacion' => $hospitalizacion->id_hospitalizacion,
                        'id_habitacion' => $habitacion->id_habitacion,
                        'fecha_hora_inicio' => $datos['fecha_ingreso'],
                        'id_usuario_creacion' => $idUsuario,
                    ]);
                }
            }

            return $ingreso;
        });
    }

    private static function habitacionDisponible(int $idHabitacion): Habitacion
    {
        $habitacion = Habitacion::whereKey($idHabitacion)->lockForUpdate()->first();

        if (! $habitacion) {
            throw new RuntimeException('Debe seleccionar una habitación.');
        }

        if ($habitacion->estado_operativo !== 'Activa') {
            throw new RuntimeException('La habitación seleccionada no está disponible.');
        }

        $ocupada = HospitalizacionHabitacion::where('id_habitacion', $habitacion->id_habitacion)
            ->whereNull('fecha_hora_fin')
            ->lockForUpdate()
            ->exists();

        if ($ocupada) {
            throw new RuntimeException('La habitación seleccionada ya está ocupada.');
        }

        return $habitacion;
    }
}
