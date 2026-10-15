<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    protected $table = 'tbl_cargo';

    protected $primaryKey = 'id_cargo';

    public $timestamps = false;

    protected $fillable = [
        'nombre_cargo',
        'estado'
    ];

    public function personal()
    {
        return $this->hasMany(
            Personal::class,
            'id_cargo',
            'id_cargo'
        );
    }

    public function especialidades()
    {
        return $this->belongsToMany(
            Especialidad::class,
            'tbl_cargo_especialidad',
            'id_cargo',
            'id_especialidad'
        );
    }
}