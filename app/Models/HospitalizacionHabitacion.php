<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HospitalizacionHabitacion extends Model
{
    protected $table = 'tbl_hospitalizacion_habitacion';

    protected $primaryKey = 'id_hospitalizacion_habitacion';

    public $timestamps = false;

    protected $fillable = [
        'id_hospitalizacion',
        'id_habitacion',
        'fecha_hora_inicio',
        'fecha_hora_fin',
        'observaciones',
        'id_usuario_creacion'
    ];

    protected $casts = [
        'fecha_hora_inicio' => 'datetime',
        'fecha_hora_fin' => 'datetime',
        'fecha_registro' => 'datetime'
    ];

    public function hospitalizacion()
    {
        return $this->belongsTo(
            Hospitalizacion::class,
            'id_hospitalizacion',
            'id_hospitalizacion'
        );
    }

    public function habitacion()
    {
        return $this->belongsTo(
            Habitacion::class,
            'id_habitacion',
            'id_habitacion'
        );
    }
}