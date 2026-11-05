<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Paciente extends Model
{
    protected $table = 'tbl_paciente';

    protected $primaryKey = 'id_paciente';

    public $timestamps = false;

    public function cirugiaEnCurso(): ?Cirugia
    {
        return Cirugia::whereHas('ingreso', fn ($q) => $q->where('id_paciente', $this->getKey()))
            ->where('estado', 'En cirugía')->orderByDesc('id_cirugia')->first();
    }

    public function exigirFueraDeCirugia(): void
    {
        if ($this->cirugiaEnCurso()) {
            throw ValidationException::withMessages(['cirugia' => 'El paciente está en cirugía. Espere a que finalice para registrar una nueva atención, hospitalización o nota de enfermería.']);
        }
    }

    protected $fillable = [
        'fecha_registro',
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
        'talla',
        'peso',
        'alergias',
        'antecedentes_medicos',
        'antecedentes_familiares',
        'observaciones_generales',
        'estado',
        'id_usuario_creacion',
        'id_usuario_modificacion',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'talla' => 'float',
        'peso' => 'float',
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime',
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

    /**
     * Talla para mostrar, por ejemplo «165 cm», o null si no se ha registrado.
     */
    public function tallaTexto(): ?string
    {
        return $this->talla ? self::numero($this->talla).' cm' : null;
    }

    /**
     * Peso para mostrar, por ejemplo «150 lb», o null si no se ha registrado.
     */
    public function pesoTexto(): ?string
    {
        return $this->peso ? self::numero($this->peso).' lb' : null;
    }

    private static function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
