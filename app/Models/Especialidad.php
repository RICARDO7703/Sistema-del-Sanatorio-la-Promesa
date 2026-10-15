<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    protected $table = 'tbl_especialidad';

    protected $primaryKey = 'id_especialidad';

    public $timestamps = false;

    protected $fillable = [
        'nombre_especialidad',
        'estado'
    ];

    public function personal()
    {
        return $this->hasMany(
            Personal::class,
            'id_especialidad',
            'id_especialidad'
        );
    }

    public function cargos()
    {
        return $this->belongsToMany(
            Cargo::class,
            'tbl_cargo_especialidad',
            'id_especialidad',
            'id_cargo'
        );
    }
}