<?php

namespace App\Support;

use App\Models\Cirugia;
use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Models\NotaEnfermeria;
use Illuminate\Support\Carbon;

/**
 * Regla para corregir hojas clínicas (hoja de ingreso, valoración, consulta, evoluciones, notas de enfermería y cirugías):
 * solo quien las registró, durante las primeras 12 horas y mientras la atención siga activa.
 */
class EdicionClinica
{
    public const HORAS = 12;

    public const MENSAJE = 'Solo quien lo registró puede editarlo, y únicamente durante las primeras 12 horas mientras la atención siga activa.';

    /**
     * Momento (UTC) hasta el que se puede editar, contado desde que se guardó el registro.
     */
    public static function editableHasta(Ingreso|EvolucionClinica|Cirugia|NotaEnfermeria $registro): ?Carbon
    {
        $creado = $registro->getRawOriginal('fecha_registro');

        return $creado ? Carbon::parse($creado, 'UTC')->addHours(self::HORAS) : null;
    }

    public static function puedeEditar(Ingreso|EvolucionClinica|Cirugia $registro, ?int $idUsuario = null): bool
    {
        $idUsuario ??= (int) session('id_usuario');
        $ingreso = $registro instanceof Ingreso ? $registro : $registro->ingreso;
        $limite = self::editableHasta($registro);
        // Una cirugía no tiene estado «Activo»: se puede corregir mientras no esté cancelada.
        $vigente = $registro instanceof Cirugia ? $registro->estado !== 'Cancelada' : $registro->estado === 'Activo';

        return $idUsuario > 0
            && $ingreso?->estado === 'Activo'
            && $vigente
            && (int) $registro->id_usuario_creacion === $idUsuario
            && $limite !== null && $limite->isFuture();
    }

    /**
     * Hora local (Guatemala) hasta la que se puede editar, para mostrarla.
     */
    public static function limiteLocal(Ingreso|EvolucionClinica|Cirugia|NotaEnfermeria $registro): ?Carbon
    {
        return self::editableHasta($registro)?->setTimezone('America/Guatemala');
    }
}
