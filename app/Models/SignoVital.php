<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignoVital extends Model
{
    protected $table = 'tbl_signos_vitales';

    protected $primaryKey = 'id_signos_vitales';

    public $timestamps = false;

    protected $fillable = [
        'id_ingreso', 'id_cirugia',
        'id_personal',
        'tipo_control',
        'fecha_hora',
        'presion_sistolica',
        'presion_diastolica',
        'frecuencia_cardiaca',
        'frecuencia_respiratoria',
        'temperatura',
        'saturacion_oxigeno',
        'observaciones',
        'id_usuario_creacion',
        'id_usuario_modificacion',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'temperatura' => 'decimal:1',
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    public function ingreso()
    {
        return $this->belongsTo(
            Ingreso::class,
            'id_ingreso',
            'id_ingreso'
        );
    }

    public function personal()
    {
        return $this->belongsTo(
            Personal::class,
            'id_personal',
            'id_personal'
        );
    }
}
