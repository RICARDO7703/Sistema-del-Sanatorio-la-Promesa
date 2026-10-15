<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    protected $table = 'tbl_paciente';

    protected $primaryKey = 'id_paciente';

    public $timestamps = false;

    protected $fillable = [
        'numero_expediente',
        'estado_identificacion',
        'codigo_temporal',
        'nombres',
        'apellidos',
        'dpi',
        'fecha_nacimiento',
        'sexo',
        'telefono',
        'direccion',
        'tipo_sangre',
        'alergias',
        'antecedentes_medicos',
        'antecedentes_familiares',
        'observaciones_generales',
        'estado',
        'id_usuario_creacion',
        'id_usuario_modificacion'
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime'
    ];

    public function ingresos()
    {
        return $this->hasMany(
            Ingreso::class,
            'id_paciente',
            'id_paciente'
        );
    }

    public function contactos()
    {
        return $this->hasMany(
            ContactoPaciente::class,
            'id_paciente',
            'id_paciente'
        );
    }

    public function documentosClinicos()
    {
        return $this->hasMany(
            DocumentoClinico::class,
            'id_paciente',
            'id_paciente'
        );
    }
}
