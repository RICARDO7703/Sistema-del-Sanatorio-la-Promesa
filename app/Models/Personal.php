<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $table = 'tbl_personal';

    protected $primaryKey = 'id_personal';

    public $timestamps = false;

    protected $fillable = [
        'nombres',
        'apellidos',
        'dpi',
        'telefono',
        'correo',
        'id_cargo',
        'id_especialidad',
        'numero_colegiado',
        'es_externo',
        'estado'
    ];

    protected $casts = [
        'es_externo' => 'boolean'
    ];

    public function cargo()
    {
        return $this->belongsTo(
            Cargo::class,
            'id_cargo',
            'id_cargo'
        );
    }

    public function especialidad()
    {
        return $this->belongsTo(
            Especialidad::class,
            'id_especialidad',
            'id_especialidad'
        );
    }
}