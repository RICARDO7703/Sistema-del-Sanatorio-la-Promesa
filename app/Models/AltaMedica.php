<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AltaMedica extends Model
{
    protected $table = 'tbl_alta_medica';

    protected $primaryKey = 'id_alta';

    public $timestamps = false;

    protected $fillable = [
        'id_ingreso',
        'id_personal_medico',
        'fecha_hora',
        'diagnostico_final',
        'condicion_egreso',
        'indicaciones_alta',
        'observaciones',
        'id_usuario_creacion',
        'id_usuario_modificacion'
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime'
    ];

    public function ingreso()
    {
        return $this->belongsTo(
            Ingreso::class,
            'id_ingreso',
            'id_ingreso'
        );
    }

    public function medico()
    {
        return $this->belongsTo(
            Personal::class,
            'id_personal_medico',
            'id_personal'
        );
    }
}