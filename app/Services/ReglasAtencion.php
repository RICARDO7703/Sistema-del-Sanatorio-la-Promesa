<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReglasAtencion
{
    /**
     * Zona horaria del sanatorio. Las fechas clínicas se guardan como hora local de Guatemala.
     */
    public const ZONA_HORARIA = 'America/Guatemala';

    /**
     * Hora actual de Guatemala, con la misma forma en que se guardan las fechas clínicas,
     * para compararla con ellas sin el desfase de la hora del servidor (UTC).
     */
    public static function ahora(): Carbon
    {
        return Carbon::parse(now(self::ZONA_HORARIA)->format('Y-m-d H:i:s'));
    }

    /**
     * Regla de validación que rechaza fechas posteriores a la hora actual de Guatemala.
     */
    public static function reglaNoFutura(): string
    {
        return 'before_or_equal:'.self::ahora()->format('Y-m-d H:i:s');
    }

    public static function ingresoActivo(string $estado): bool
    {
        return $estado === 'Activo';
    }

    public static function fechaValida(DateTimeInterface $fecha, DateTimeInterface $ingreso, DateTimeInterface $ahora): bool
    {
        return $fecha >= $ingreso && $fecha <= $ahora;
    }

    public static function cierreValido(DateTimeInterface $fecha, DateTimeInterface $inicio): bool
    {
        return $fecha > $inicio;
    }

    public static function tratamientoValido(string $tipo, ?int $medicamento, ?string $descripcion): bool
    {
        return match ($tipo) {
            'Medicamento' => $medicamento !== null && $medicamento > 0,
            'No farmacológico' => trim($descripcion ?? '') !== '',
            default => false,
        };
    }

    public static function exigirActivo(string $estado): void
    {
        if (! self::ingresoActivo($estado)) {
            throw ValidationException::withMessages(['ingreso' => 'El ingreso ya no se encuentra activo.']);
        }
    }

    public static function validarFecha(DateTimeInterface $fecha, DateTimeInterface $ingreso, DateTimeInterface $ahora, string $campo = 'fecha_hora'): void
    {
        if (! self::fechaValida($fecha, $ingreso, $ahora)) {
            throw ValidationException::withMessages([$campo => 'La fecha debe estar entre el ingreso y el momento actual.']);
        }
    }

    public static function validarCierre(DateTimeInterface $fecha, DateTimeInterface $inicio): void
    {
        if (! self::cierreValido($fecha, $inicio)) {
            throw ValidationException::withMessages(['fecha_hora' => 'El cierre debe ser posterior al inicio de la hospitalización y de la habitación actual.']);
        }
    }

    public static function validarTratamiento(string $tipo, ?int $medicamento, ?string $descripcion): void
    {
        if (! self::tratamientoValido($tipo, $medicamento, $descripcion)) {
            throw ValidationException::withMessages([$tipo === 'Medicamento' ? 'id_medicamento' : 'descripcion_tratamiento' => 'Seleccione el medicamento o describa el tratamiento no farmacológico.']);
        }
    }
}
