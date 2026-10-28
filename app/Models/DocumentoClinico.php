<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoClinico extends Model
{
    protected $table = 'tbl_documento_clinico';

    protected $primaryKey = 'id_documento';

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
    ];

    protected $casts = [
        'fecha_documento' => 'datetime',
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

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
