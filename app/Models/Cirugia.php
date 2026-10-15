<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cirugia extends Model
{
    protected $table = 'tbl_cirugia';

    protected $primaryKey = 'id_cirugia';

    public $timestamps = false;

    protected $fillable = [
        'id_ingreso',
        'id_evolucion',
        'id_personal_indica',
        'tipo_operacion',
        'fecha_programada',
        'estado',
        'observaciones',
        'id_usuario_creacion',
        'id_usuario_modificacion'
    ];

    protected $casts = [
        'fecha_programada' => 'datetime',
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

    public function evolucion()
    {
        return $this->belongsTo(
            EvolucionClinica::class,
            'id_evolucion',
            'id_evolucion'
        );
    }

    public function personalIndica()
    {
        return $this->belongsTo(
            Personal::class,
            'id_personal_indica',
            'id_personal'
        );
    }
}