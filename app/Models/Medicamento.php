<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicamento extends Model
{
    protected $table = 'tbl_medicamento';

    protected $primaryKey = 'id_medicamento';

    public $timestamps = false;

    protected $fillable = [
        'nombre_medicamento',
        'presentacion',
        'concentracion',
        'estado'
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime'
    ];

    public function tratamientos()
    {
        return $this->hasMany(
            Tratamiento::class,
            'id_medicamento',
            'id_medicamento'
        );
    }
}