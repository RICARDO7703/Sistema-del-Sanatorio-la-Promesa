<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DocumentoClinico extends Model
{
    protected $table = 'tbl_documento_clinico';

    protected $primaryKey = 'id_documento';

    /**
     * Horas en que quien subió un archivo todavía puede corregirlo desde el expediente.
     */
    public const HORAS_PARA_EDITAR = 12;

    /**
     * Tipos de examen o archivo que se suben directamente al expediente del paciente.
     *
     * @var list<string>
     */
    public const CATEGORIAS = ['Laboratorio', 'Radiografía', 'Ultrasonido', 'Electrocardiograma', 'Informe', 'Otro'];

    public $timestamps = false;

    protected $fillable = [
        'id_paciente',
        'id_ingreso',
        'id_personal_responsable',
        'categoria',
        'titulo',
        'tipo_documento', 'tabla_origen', 'id_registro_origen',
        'descripcion',
        'ruta_archivo',
        'nombre_archivo',
        'tipo_archivo',
        'fecha_documento',
        'estado',
        'id_usuario_creacion',
        'id_usuario_modificacion',
        'fecha_registro',
    ];

    protected $casts = [
        'fecha_documento' => 'datetime',
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    /**
     * Archivo subido al expediente del paciente, sin ligarlo a una atención.
     */
    public function esDelPaciente(): bool
    {
        return $this->id_ingreso === null;
    }

    /**
     * Momento (UTC) hasta el que quien lo subió puede editarlo.
     */
    public function editableHasta(): ?Carbon
    {
        return $this->fecha_registro ? Carbon::parse($this->fecha_registro, 'UTC')->addHours(self::HORAS_PARA_EDITAR) : null;
    }

    /**
     * Solo quien subió el archivo puede editarlo, y solo durante las primeras 12 horas.
     */
    public function puedeEditar(?int $idUsuario): bool
    {
        $limite = $this->editableHasta();

        return $this->estado === 'Activo' && $idUsuario !== null && (int) $this->id_usuario_creacion === $idUsuario
            && $limite !== null && $limite->isFuture();
    }

    public function paciente()
    {
        return $this->belongsTo(
            Paciente::class,
            'id_paciente',
            'id_paciente'
        );
    }

    public function ingreso()
    {
        return $this->belongsTo(
            Ingreso::class,
            'id_ingreso',
            'id_ingreso'
        );
    }

    public function personalResponsable()
    {
        return $this->belongsTo(
            Personal::class,
            'id_personal_responsable',
            'id_personal'
        );
    }
}
