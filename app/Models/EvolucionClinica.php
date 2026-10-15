<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvolucionClinica extends Model
{
    protected $table = 'tbl_evolucion_clinica';

    protected $primaryKey = 'id_evolucion';

    public $timestamps = false;

    protected $fillable = [
        'id_ingreso',
        'id_personal',
        'fecha_hora',
        'motivo_atencion',
        'sintomas',
        'evaluacion_medica',
        'examen_fisico',
        'ordenes_medicas',
        'estado_mejoria',
        'diagnostico',
        'indicaciones',
        'observaciones',
        'estado',
        'id_usuario_creacion',
        'id_usuario_modificacion',
    ];

    protected $casts = [
        'examen_fisico' => 'array',
        'ordenes_medicas' => 'array',
        'fecha_hora' => 'datetime',
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

    public function tratamientos()
    {
        return $this->hasMany(
            Tratamiento::class,
            'id_evolucion',
            'id_evolucion'
        );
    }

    public function cirugias()
    {
        return $this->hasMany(
            Cirugia::class,
            'id_evolucion',
            'id_evolucion'
        );
    }
}
