<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $table = 'tbl_personal';

    protected $primaryKey = 'id_personal';

    public $timestamps = false;

    /**
     * Cargos que se consideran médicos (para avisos de enfermería y altas).
     *
     * @var list<string>
     */
    public const CARGOS_MEDICOS = ['Médico general', 'Médico especialista', 'Cirujano', 'Cirujano externo', 'Anestesiólogo'];

    public const CARGOS_CON_COLEGIADO = [...self::CARGOS_MEDICOS, 'Médico', 'Enfermera', 'Enfermero', 'Licenciada en Enfermería', 'Licenciado en Enfermería', 'Químico Biólogo', 'Química Bióloga', 'Nutricionista'];

    protected $fillable = [
        'nombres',
        'apellidos',
        'dpi',
        'telefono',
        'correo', 'foto_perfil',
        'id_cargo',
        'id_especialidad',
        'numero_colegiado',
        'es_externo',
        'estado',
    ];

    protected $casts = [
        'es_externo' => 'boolean',
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
