<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /**
     * Cirugía de la que este registro es un control de recuperación (si lo es).
     */
    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'id_cirugia', 'id_cirugia');
    }

    /**
     * Nota de enfermería en la que se tomaron estos signos (los controles antiguos no tienen nota).
     */
    public function nota(): HasOne
    {
        return $this->hasOne(NotaEnfermeria::class, 'id_signos_vitales', 'id_signos_vitales');
    }
}
