<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cirugia extends Model
{
    protected $table = 'tbl_cirugia';

    protected $primaryKey = 'id_cirugia';

    public $timestamps = false;

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

    public function controles(): HasMany
    {
        return $this->hasMany(SignoVital::class, 'id_cirugia', 'id_cirugia')->orderByDesc('fecha_hora')->orderByDesc('id_signos_vitales');
    }
}
