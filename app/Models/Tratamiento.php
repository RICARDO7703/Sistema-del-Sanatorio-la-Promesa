<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tratamiento extends Model
{
    protected $table = 'tbl_tratamiento';

    protected $primaryKey = 'id_tratamiento';

    public $timestamps = false;

    protected $fillable = [
        'id_ingreso',
        'id_evolucion',
        'id_personal_indica',
        'id_medicamento',
        'tipo_tratamiento',
        'descripcion_tratamiento',
        'dosis',
        'via_administracion',
        'frecuencia',
        'duracion',
        'indicaciones',
        'fecha_indicacion',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'id_usuario_creacion',
        'id_usuario_modificacion'
    ];

    protected $casts = [
        'fecha_indicacion' => 'datetime',
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

    public function evolucion()
    {
        return $this->belongsTo(
            EvolucionClinica::class,
            'id_evolucion',
            'id_evolucion'
        );
    }

    public function personalIndica()
    {
        return $this->belongsTo(
            Personal::class,
            'id_personal_indica',
            'id_personal'
        );
    }

    public function medicamento()
    {
        return $this->belongsTo(
            Medicamento::class,
            'id_medicamento',
            'id_medicamento'
        );
    }
}