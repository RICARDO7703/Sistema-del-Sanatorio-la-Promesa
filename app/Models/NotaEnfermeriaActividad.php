<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaEnfermeriaActividad extends Model
{
    protected $table = 'tbl_atencion_enfermeria_actividad';

    protected $primaryKey = 'id_actividad';

    public $timestamps = false;

    protected $fillable = [
        'id_atencion_enfermeria',
        'actividad',
    ];

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaEnfermeria::class, 'id_atencion_enfermeria', 'id_atencion_enfermeria');
    }
}
