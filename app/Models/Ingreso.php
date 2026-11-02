<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ingreso extends Model
{
    public const TIPO_HOSPITALIZACION = 'Hospitalización';

    /** Paciente que se atiende y se va el mismo día, sin quedarse internado. */
    public const TIPO_CONSULTA_EXTERNA = 'Consulta externa';

    /** Nombre anterior de la consulta externa; se acepta mientras la base no se haya actualizado. */
    public const TIPO_CONSULTA_EXTERNA_ANTERIOR = 'Emergencia';

    /** @var list<string> */
    public const TIPOS_ATENCION = [self::TIPO_CONSULTA_EXTERNA, self::TIPO_HOSPITALIZACION];

    protected $table = 'tbl_ingreso';

    protected $primaryKey = 'id_ingreso';

    public $timestamps = false;

    /**
     * La hora de registro se guarda en UTC desde la aplicación; con ella se cuentan las 12 horas para editar.
     */
    protected static function booted(): void
    {
        static::creating(function (self $registro): void {
            $registro->fecha_registro ??= now('UTC');
        });
    }

    protected $fillable = [
        'id_paciente',
        'id_ingreso_anterior',
        'tipo_atencion',
        'id_personal_registra',
        'fecha_ingreso',
        'motivo_ingreso',
        'documento_firmado',
        'estado',
        'id_usuario_creacion',
        'id_usuario_modificacion',
    ];

    protected $casts = [
        'fecha_ingreso' => 'datetime',
        'documento_firmado' => 'boolean',
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    public function historialPrevio(): Collection
    {
        return self::where('id_paciente', $this->id_paciente)
            ->where('estado', 'Dado de alta')
            ->where('fecha_ingreso', '<=', $this->fecha_ingreso)
            ->when($this->exists, fn ($query) => $query->where('id_ingreso', '<>', $this->getKey()))
            ->with(['altaMedica.medico', 'altaMedica.medicamentos', 'evoluciones' => fn ($query) => $query->where('estado', 'Activo')->with('personal')->orderBy('fecha_hora')->orderBy('id_evolucion')])
            ->orderByDesc('fecha_ingreso')->orderByDesc('id_ingreso')->get();
    }

    public function atencionAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_ingreso_anterior', 'id_ingreso');
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(self::class, 'id_ingreso_anterior', 'id_ingreso')->orderBy('fecha_ingreso');
    }

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

    public function notasEnfermeria(): HasMany
    {
        return $this->hasMany(NotaEnfermeria::class, 'id_ingreso', 'id_ingreso');
    }

    public function documentosClinicos()
    {
        return $this->hasMany(
            DocumentoClinico::class,
            'id_ingreso',
            'id_ingreso'
        );
    }

    /**
     * Archivo del documento de ingreso firmado, guardado en los archivos médicos del paciente.
     */
    public function documentoFirmado(): HasOne
    {
        return $this->hasOne(DocumentoClinico::class, 'id_registro_origen', 'id_ingreso')
            ->where('tabla_origen', 'tbl_ingreso')
            ->whereNull('id_ingreso')
            ->where('tipo_documento', DocumentoClinico::TIPO_INGRESO_FIRMADO)
            ->where('estado', 'Activo');
    }

    /**
     * Indica si la atención es una consulta externa (sin internamiento).
     */
    public function esConsultaExterna(): bool
    {
        return in_array($this->tipo_atencion, [self::TIPO_CONSULTA_EXTERNA, self::TIPO_CONSULTA_EXTERNA_ANTERIOR], true);
    }

    /**
     * Nombre del tipo de atención para mostrar en pantalla.
     */
    public function nombreTipoAtencion(): string
    {
        return $this->esConsultaExterna() ? self::TIPO_CONSULTA_EXTERNA : self::TIPO_HOSPITALIZACION;
    }
}
