<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactoPaciente extends Model
{
    protected $table = 'tbl_contacto_paciente';

    protected $primaryKey = 'id_contacto';

    public $timestamps = false;

    protected $fillable = [
        'id_paciente',
        'nombre',
        'parentesco',
        'telefono'
    ];

    public function paciente()
    {
        return $this->belongsTo(
            Paciente::class,
            'id_paciente',
            'id_paciente'
        );
    }
}