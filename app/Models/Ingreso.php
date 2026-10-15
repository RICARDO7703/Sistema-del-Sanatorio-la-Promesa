<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingreso extends Model
{
    protected $table = 'tbl_ingreso';

    protected $primaryKey = 'id_ingreso';

    public $timestamps = false;

    protected $fillable = [
        'id_paciente',
        'id_personal_registra',
        'fecha_ingreso',
        'motivo_ingreso',
        'documento_firmado',
        'estado',
        'id_usuario_creacion',
        'id_usuario_modificacion'
    ];

    protected $casts = [
        'fecha_ingreso' => 'datetime',
        'documento_firmado' => 'boolean',
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime'
    ];

    public function paciente()
    {
        return $this->belongsTo(
            Paciente::class,
            'id_paciente',
            'id_paciente'
        );
    }

    public function personalRegistra()
    {
        return $this->belongsTo(
            Personal::class,
            'id_personal_registra',
            'id_personal'
        );
    }

    public function hospitalizacion()
    {
        return $this->hasOne(
            Hospitalizacion::class,
            'id_ingreso',
            'id_ingreso'
        );
    }

    public function altaMedica()
    {
        return $this->hasOne(
            AltaMedica::class,
            'id_ingreso',
            'id_ingreso'
        );
    }


    public function evoluciones()
    {
        return $this->hasMany(
            EvolucionClinica::class,
            'id_ingreso',
            'id_ingreso'
        );
    }


    public function tratamientos()
    {
        return $this->hasMany(
            Tratamiento::class,
            'id_ingreso',
            'id_ingreso'
        );
    }

    public function cirugias()
    {
        return $this->hasMany(
            Cirugia::class,
            'id_ingreso',
            'id_ingreso'
        );
    }

    public function signosVitales()
    {
        return $this->hasMany(
            SignoVital::class,
            'id_ingreso',
            'id_ingreso'
        );
    }

    public function documentosClinicos()
    {
        return $this->hasMany(
            DocumentoClinico::class,
            'id_ingreso',
            'id_ingreso'
        );
    }



    




}
