<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Lleva la cuenta de lo que realmente cambia al guardar un formulario de edición.
 * Si al final no cambió nada, AvisarSinCambios muestra «todo sigue igual».
 */
class CambiosEdicion
{
    /**
     * Columnas que se escriben en cada guardado y no son un cambio del usuario.
     *
     * @var list<string>
     */
    private const IGNORAR = ['id_usuario_modificacion', 'fecha_actualizacion', 'updated_at'];

    private bool $vigilando = false;

    private int $cambios = 0;

    public function iniciar(): void
    {
        $this->vigilando = true;
        $this->cambios = 0;
    }

    /**
     * Deja de vigilar y dice si hubo algún cambio.
     */
    public function terminar(): bool
    {
        $this->vigilando = false;

        return $this->cambios > 0;
    }

    /**
     * Para cambios que no pasan por modelos (consultas directas o tablas intermedias).
     */
    public function registrar(): void
    {
        if ($this->vigilando) {
            $this->cambios++;
        }
    }

    /**
     * Evita guardar un registro cuando lo único distinto es quién lo modificó: no hubo cambio real.
     * Antes descarta las diferencias que solo son de forma (un dato vacío guardado como null, «500» en vez de 500,
     * los segundos de una hora que el formulario no muestra), para que volver a guardar sin tocar nada no cuente como edición.
     * Devuelve false para cancelar ese guardado; null para seguir normalmente.
     */
    public function antesDeActualizar(Model $modelo): ?bool
    {
        if (! $this->vigilando) {
            return null;
        }
        $iguales = [];
        foreach ($modelo->getDirty() as $campo => $valor) {
            if (! in_array($campo, self::IGNORAR, true) && self::normalizar($modelo->getRawOriginal($campo)) === self::normalizar($valor)) {
                $iguales[$campo] = $modelo->getRawOriginal($campo);
            }
        }
        if ($iguales !== []) {
            $modelo->setRawAttributes(array_replace($modelo->getAttributes(), $iguales));
        }
        $distintos = array_keys($modelo->getDirty());

        return $distintos !== [] && array_diff($distintos, self::IGNORAR) === [] ? false : null;
    }

    /**
     * Forma comparable de un valor guardado: JSON decodificado y sin datos vacíos, números como número
     * y fechas con hora hasta los minutos.
     */
    private static function normalizar(mixed $valor): mixed
    {
        if (is_string($valor)) {
            $valor = trim($valor);
            if ($valor !== '' && ($valor[0] === '{' || $valor[0] === '[')) {
                $decodificado = json_decode($valor, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return self::normalizar($decodificado);
                }
            }
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(:\d{2}(\.\d+)?)?$/', $valor, $partes)) {
                return $partes[1].' '.$partes[2];
            }
            if (preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $valor)) {
                return (float) $valor;
            }

            return $valor === '' ? null : $valor;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        if (is_array($valor)) {
            $lista = array_is_list($valor);
            $valor = array_map(self::normalizar(...), $valor);
            if (! $lista) {
                $valor = array_filter($valor, fn ($dato) => $dato !== null);
                ksort($valor);
            }

            return $valor === [] ? null : $valor;
        }

        return $valor;
    }
}
