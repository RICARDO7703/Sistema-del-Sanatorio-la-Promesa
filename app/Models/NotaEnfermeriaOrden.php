<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Orden médica que enfermería marcó como aplicada o no aplicada en una nota.
 */
class NotaEnfermeriaOrden extends Model
{
    protected $table = 'tbl_atencion_enfermeria_orden';

    protected $primaryKey = 'id_orden_aplicada';

    public $timestamps = false;

    protected $fillable = [
        'id_atencion_enfermeria',
        'id_evolucion',
        'tipo',
        'descripcion',
        'aplicada',
        'motivo',
    ];

    protected $casts = [
        'aplicada' => 'boolean',
    ];

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaEnfermeria::class, 'id_atencion_enfermeria', 'id_atencion_enfermeria');
    }

    /**
     * Cómo se lee el resultado según el tipo de orden: «Se dio», «Se aplicó», «Se cumplió»…
     */
    public function resultado(): string
    {
        return self::textoResultado($this->tipo, $this->aplicada);
    }

    public static function textoResultado(string $tipo, bool $aplicada): string
    {
        return match ($tipo) {
            'Dieta' => $aplicada ? 'Se dio' : 'No se dio',
            'Indicaciones' => $aplicada ? 'Se cumplieron' : 'No se cumplieron',
            default => $aplicada ? 'Se aplicó' : 'No se aplicó',
        };
    }
}
