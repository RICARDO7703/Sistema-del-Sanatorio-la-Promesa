<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AltaMedicamento extends Model
{
    protected $table = 'tbl_alta_medicamento';

    protected $primaryKey = 'id_alta_medicamento';

    public $timestamps = false;

    protected $fillable = [
        'id_alta',
        'medicamento',
        'dosis',
        'via_administracion',
        'frecuencia',
        'duracion',
    ];

    public function alta(): BelongsTo
    {
        return $this->belongsTo(AltaMedica::class, 'id_alta', 'id_alta');
    }
}
