<?php

namespace App\Support;

use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use Illuminate\Support\Carbon;

/**
 * Regla para corregir hojas clínicas (hoja de ingreso, valoración, consulta y evoluciones):
 * solo quien las registró, durante las primeras 12 horas y mientras la atención siga activa.
 */
class EdicionClinica
{
    public const HORAS = 12;

    public const MENSAJE = 'Solo quien lo registró puede editarlo, y únicamente durante las primeras 12 horas mientras la atención siga activa.';

    /**
     * Momento (UTC) hasta el que se puede editar, contado desde que se guardó el registro.
     */
    public static function editableHasta(Ingreso|EvolucionClinica $registro): ?Carbon
    {
        $creado = $registro->getRawOriginal('fecha_registro');

        return $creado ? Carbon::parse($creado, 'UTC')->addHours(self::HORAS) : null;
    }

    public static function puedeEditar(Ingreso|EvolucionClinica $registro, ?int $idUsuario = null): bool
    {
        $idUsuario ??= (int) session('id_usuario');
        $ingreso = $registro instanceof Ingreso ? $registro : $registro->ingreso;
        $limite = self::editableHasta($registro);

        return $idUsuario > 0
            && $ingreso?->estado === 'Activo'
            && $registro->estado === 'Activo'
            && (int) $registro->id_usuario_creacion === $idUsuario
            && $limite !== null && $limite->isFuture();
    }

    /**
     * Hora local (Guatemala) hasta la que se puede editar, para mostrarla.
     */
    public static function limiteLocal(Ingreso|EvolucionClinica $registro): ?Carbon
    {
        return self::editableHasta($registro)?->setTimezone('America/Guatemala');
    }
}
