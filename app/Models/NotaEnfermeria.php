<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nota de la hoja de enfermería: cómo está el paciente, qué se le hizo y, si se tomaron,
 * sus signos vitales (guardados en tbl_signos_vitales y enlazados aquí).
 */
class NotaEnfermeria extends Model
{
    /** @var list<string> */
    public const ESTADOS = ['Mejoró', 'Sin cambios', 'Empeoró'];

    /** @var list<string> */
    public const ACTIVIDADES = [
        'Cambio de posición',
        'Cambio de solución',
        'Curación',
        'Baño / aseo',
        'Cambio de ropa de cama',
        'Alimentación asistida',
        'Administración de medicamentos',
        'Cambio de sonda',
        'Toma de muestra',
    ];

    protected $table = 'tbl_atencion_enfermeria';

    protected $primaryKey = 'id_atencion_enfermeria';

    public $timestamps = false;

    protected $fillable = [
        'id_ingreso',
        'id_personal',
        'fecha_hora',
        'estado_paciente',
        'id_signos_vitales',
        'actividad',
        'observaciones',
        'aviso_medico',
        'id_usuario_creacion',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'aviso_medico' => 'boolean',
        'fecha_registro' => 'datetime',
    ];

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class, 'id_ingreso', 'id_ingreso');
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'id_personal', 'id_personal');
    }

    public function signosVitales(): BelongsTo
    {
        return $this->belongsTo(SignoVital::class, 'id_signos_vitales', 'id_signos_vitales');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(NotaEnfermeriaActividad::class, 'id_atencion_enfermeria', 'id_atencion_enfermeria')->orderBy('id_actividad');
    }

    /**
     * Turno según la hora de la nota: mañana 7:00–12:59, tarde 13:00–18:59 y noche 19:00–6:59.
     */
    public function turno(): string
    {
        return self::turnoPorHora((int) $this->fecha_hora->format('G'));
    }

    /**
     * Turno que corresponde a una hora del día (0 a 23).
     */
    public static function turnoPorHora(int $hora): string
    {
        return match (true) {
            $hora >= 7 && $hora < 13 => 'Mañana',
            $hora >= 13 && $hora < 19 => 'Tarde',
            default => 'Noche',
        };
    }

    /**
     * Horario de cada turno, para mostrarlo en pantalla.
     */
    public static function horarioTurno(string $turno): string
    {
        return ['Mañana' => '07:00 – 13:00', 'Tarde' => '13:00 – 19:00', 'Noche' => '19:00 – 07:00'][$turno] ?? '';
    }

    /**
     * Color de la etiqueta del estado del paciente.
     */
    public function claseEstado(): string
    {
        return match ($this->estado_paciente) {
            'Mejoró' => 'bg-success',
            'Empeoró' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    /**
     * Actividades realizadas; las notas antiguas solo tienen el texto de la columna actividad.
     *
     * @return list<string>
     */
    public function listaActividades(): array
    {
        $actividades = $this->actividades->pluck('actividad')->all();

        return $actividades !== [] || blank($this->actividad) ? $actividades : [$this->actividad];
    }
}
