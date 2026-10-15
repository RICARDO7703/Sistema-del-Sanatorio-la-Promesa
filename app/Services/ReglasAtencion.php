<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;

class ReglasAtencion
{
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
