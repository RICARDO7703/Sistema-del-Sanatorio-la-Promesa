<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Habitacion extends Model
{
    protected $table = 'tbl_habitacion';

    protected $primaryKey = 'id_habitacion';

    public $timestamps = false;

    protected $fillable = [
        'numero_habitacion',
        'tipo',
        'estado_operativo',
        'observaciones'
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime'
    ];

    public function asignacionesHospitalizacion()
    {
        return $this->hasMany(
            HospitalizacionHabitacion::class,
            'id_habitacion',
            'id_habitacion'
        );
    }
}