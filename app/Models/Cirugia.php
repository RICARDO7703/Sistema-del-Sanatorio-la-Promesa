<?php

namespace App\Models;

use App\Support\EdicionClinica;
use App\Support\Permisos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Cirugia extends Model
{
    protected $table = 'tbl_cirugia';

    protected $primaryKey = 'id_cirugia';

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
        'id_ingreso',
        'id_evolucion',
        'id_personal_indica',
        'tipo_operacion',
        'fecha_programada', 'fecha_fin_programada', 'fecha_inicio_real', 'fecha_fin_real', 'motivo_cancelacion', 'motivo_registro_posterior',
        'estado',
        'observaciones',
        'id_usuario_creacion',
        'id_usuario_modificacion',
    ];

    protected $casts = [
        'fecha_programada' => 'datetime', 'fecha_fin_programada' => 'datetime', 'fecha_inicio_real' => 'datetime', 'fecha_fin_real' => 'datetime',
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    public function ingreso()
    {
        return $this->belongsTo(
            Ingreso::class,
            'id_ingreso',
            'id_ingreso'
        );
    }

    public function evolucion()
    {
        return $this->belongsTo(
            EvolucionClinica::class,
            'id_evolucion',
            'id_evolucion'
        );
    }

    public function personalIndica()
    {
        return $this->belongsTo(
            Personal::class,
            'id_personal_indica',
            'id_personal'
        );
    }

    public function equipo(): BelongsToMany
    {
        return $this->belongsToMany(Personal::class, 'tbl_cirugia_equipo', 'id_cirugia', 'id_personal')->withPivot('rol_quirurgico', 'estado');
    }

    /**
     * Cirugía terminada más reciente del ingreso: la única a la que se le registran controles de recuperación.
     */
    public static function recienteParaControl(int $idIngreso): ?self
    {
        return self::where('id_ingreso', $idIngreso)->where('estado', 'Realizada')->whereNotNull('fecha_fin_real')
            ->orderByDesc('fecha_fin_real')->orderByDesc('id_cirugia')->first();
    }

    /**
     * Texto corto para elegirla en listas: tipo de operación y hora en que terminó.
     */
    public function descripcionCorta(): string
    {
        return $this->tipo_operacion.($this->fecha_fin_real ? ' · terminó el '.$this->fecha_fin_real->format('d/m/Y H:i') : '');
    }

    public function atencionQuirurgicaDisponible(): bool
    {
        return $this->ingreso?->paciente?->estado === 'Activo'
            && ($this->ingreso->estado === 'Activo'
                || ($this->ingreso->estado === 'Dado de alta' && $this->ingreso->esConsultaExterna()));
    }

    public function programacionGestionable(): bool
    {
        return in_array($this->estado, ['Programada', 'Aprobada', 'Evaluación preoperatoria'], true)
            && $this->atencionQuirurgicaDisponible();
    }

    /** Acciones del equipo que participa, independientes de quién agendó la cirugía. */
    public function permiteAccion(string $accion): bool
    {
        if (! app(Permisos::class)->puede('Cirugías', 'Ver')) {
            return false;
        }
        $personal = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');
        $participante = $this->equipo->first(fn ($persona) => (int) $persona->getKey() === (int) $personal && $persona->pivot->estado !== 'Cancelado' && $persona->estado === 'Activo');
        if ($accion === 'reprogramar') {
            return $this->programacionGestionable()
                && app(Permisos::class)->puede('Cirugías', Permisos::EDITAR);
        }
        if ($accion === 'terminar') {
            return $participante !== null && in_array($participante->cargo?->nombre_cargo, [...Personal::CARGOS_MEDICOS, 'Médico'], true);
        }
        if ($accion === 'cancelar') {
            return $participante !== null;
        }

        return $accion === 'iniciar' && $this->exists && $participante !== null;
    }

    public function puedeCorregirDatos(): bool
    {
        if (in_array($this->estado, ['Programada', 'Aprobada', 'Evaluación preoperatoria'], true)) {
            return $this->permiteAccion('reprogramar');
        }
        if ($this->estado === 'Realizada') {
            return $this->atencionQuirurgicaDisponible()
                && app(Permisos::class)->puede('Cirugías', Permisos::EDITAR)
                && $this->permiteAccion('cancelar')
                && (EdicionClinica::editableHasta($this)?->isFuture() ?? false);
        }

        return $this->estado !== 'En cirugía' && EdicionClinica::puedeEditar($this);
    }

    public function ordenRecuperacion(): ?EvolucionClinica
    {
        if ($this->estado !== 'Realizada' || ! $this->fecha_fin_real) {
            return null;
        }
        if (! $this->id_evolucion) {
            return null;
        }

        $orden = $this->evolucion;

        return $orden && (int) $orden->id_ingreso === (int) $this->id_ingreso ? $orden : null;
    }

    public function controles(): HasMany
    {
        return $this->hasMany(SignoVital::class, 'id_cirugia', 'id_cirugia')->orderByDesc('fecha_hora')->orderByDesc('id_signos_vitales');
    }
}
