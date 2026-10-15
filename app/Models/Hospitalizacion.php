<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hospitalizacion extends Model
{
    protected $table = 'tbl_hospitalizacion';

    protected $primaryKey = 'id_hospitalizacion';

    public $timestamps = false;

    protected $fillable = [
        'id_ingreso',
        'fecha_inicio',
        'fecha_fin',
        'motivo',
        'estado',
        'observaciones',
        'id_usuario_creacion',
        'id_usuario_modificacion'
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
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

    public function historialHabitaciones()
    {
        return $this->hasMany(
            HospitalizacionHabitacion::class,
            'id_hospitalizacion',
            'id_hospitalizacion'
        );
    }

    public function habitacionActual()
    {
        return $this->hasOne(
            HospitalizacionHabitacion::class,
            'id_hospitalizacion',
            'id_hospitalizacion'
        )
        ->whereNull('fecha_hora_fin')
        ->latestOfMany('fecha_hora_inicio');
    }
}