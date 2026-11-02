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

    /**
     * Tipo del documento de ingreso firmado, que se sube al hospitalizar y no se elige a mano.
     * En la base queda con la categoría «Otro», que ya existe, para no cambiar la tabla.
     */
    public const TIPO_INGRESO_FIRMADO = 'Documento de ingreso firmado';

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
     * Documento de ingreso firmado que se subió al marcar la casilla del ingreso.
     */
    public function esDocumentoIngresoFirmado(): bool
    {
        return $this->tipo_documento === self::TIPO_INGRESO_FIRMADO && $this->tabla_origen === 'tbl_ingreso' && $this->id_ingreso === null;
    }

    /**
     * Nombre del tipo que se muestra en el expediente.
     */
    public function etiquetaCategoria(): string
    {
        return $this->esDocumentoIngresoFirmado() ? 'Documento de ingreso' : (string) $this->categoria;
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
