<?php

namespace App\Services;

use App\Models\AltaMedica;
use App\Models\Cirugia;
use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Models\NotaEnfermeria;
use App\Models\Paciente;
use App\Models\SignoVital;
use App\Support\Permisos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DatosReportes
{
    public const TIPOS = [
        'pacientes' => ['Todos los pacientes', Paciente::class, 'fecha_registro', 'Pacientes'],
        'hospitalizaciones' => ['Hospitalizaciones en un período', Ingreso::class, 'fecha_ingreso', 'Expedientes'],
        'personal_atenciones' => ['Atención recibida por el paciente', Ingreso::class, 'fecha_ingreso', 'Expedientes'],
        'personal_actividad' => ['Actividad de un médico o enfermera', Ingreso::class, 'fecha_ingreso', 'Expedientes'],
        'personal_pacientes' => ['Pacientes atendidos por un empleado', Ingreso::class, 'fecha_ingreso', 'Expedientes'],
        'atenciones' => ['Ingresos y consultas', Ingreso::class, 'fecha_ingreso', 'Expedientes'],
        'internados' => ['Pacientes hospitalizados actualmente', Ingreso::class, 'fecha_ingreso', 'Expedientes'],
        'valoraciones' => ['Valoraciones iniciales', EvolucionClinica::class, 'fecha_hora', 'Expedientes'],
        'consultas' => ['Consultas externas', EvolucionClinica::class, 'fecha_hora', 'Expedientes'],
        'evoluciones' => ['Evoluciones clínicas', EvolucionClinica::class, 'fecha_hora', 'Expedientes'],
        'ordenes' => ['Órdenes médicas', EvolucionClinica::class, 'fecha_hora', 'Expedientes'],
        'enfermeria' => ['Notas de enfermería', NotaEnfermeria::class, 'fecha_hora', 'Expedientes'],
        'signos' => ['Signos vitales', SignoVital::class, 'fecha_hora', 'Expedientes'],
        'cirugias' => ['Cirugías', Cirugia::class, 'fecha_programada', 'Cirugías'],
        'altas' => ['Altas médicas', AltaMedica::class, 'fecha_hora', 'Expedientes'],
    ];

    public function disponibles(): array
    {
        return array_filter(self::TIPOS, fn ($tipo) => app(Permisos::class)->puede($tipo[3], 'Ver'));
    }

    public function consulta(string $tipo, array $filtros = []): Builder
    {
        [, $clase, $fecha] = self::TIPOS[$tipo];
        $q = $clase::query();
        if ($clase === Paciente::class) {
            $q->with('contactos');
        } elseif ($clase === Ingreso::class) {
            $q->with(['paciente', 'personalRegistra', 'hospitalizacion.habitacionActual.habitacion']);
        } else {
            $q->with('ingreso.paciente');
        }
        if ($clase === EvolucionClinica::class) {
            $q->with('personal')->where('estado', 'Activo');
            // Coincide con la clasificación del expediente: primera hoja activa sin estado de mejoría.
            $primera = 'id_evolucion = (SELECT e.id_evolucion FROM tbl_evolucion_clinica e WHERE e.id_ingreso = tbl_evolucion_clinica.id_ingreso AND e.estado = ? ORDER BY e.fecha_hora, e.id_evolucion LIMIT 1)';
            if (in_array($tipo, ['valoraciones', 'consultas'], true)) {
                $q->whereNull('estado_mejoria')->whereRaw($primera, ['Activo'])
                    ->whereHas('ingreso', fn ($i) => $tipo === 'consultas'
                        ? $i->whereIn('tipo_atencion', ['Consulta externa', 'Emergencia'])
                        : $i->where('tipo_atencion', 'Hospitalización'));
            } elseif ($tipo === 'evoluciones') {
                $q->where(fn ($e) => $e->whereNotNull('estado_mejoria')->orWhereRaw('NOT ('.$primera.')', ['Activo']));
            } else {
                $q->whereNotNull('ordenes_medicas');
            }
        } elseif ($clase === NotaEnfermeria::class) {
            $q->with(['personal', 'actividades', 'ordenes', 'signosVitales', 'medicoAvisado']);
        } elseif ($clase === SignoVital::class) {
            $q->with('personal');
        } elseif ($clase === AltaMedica::class) {
            $q->with(['medico', 'medicamentos']);
        } elseif ($clase === Cirugia::class) {
            $q->with(['equipo', 'controles.personal']);
        }
        $colFecha = $tipo === 'cirugias' ? DB::raw('COALESCE(fecha_inicio_real, fecha_programada)') : $fecha;
        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
            if (! empty($filtros[$campo]) && ! in_array($tipo, ['hospitalizaciones', 'internados'], true) && ! ($tipo === 'pacientes' && ! empty($filtros['situacion']))) {
                $limite = $filtros[$campo].($campo === 'desde' ? ' 00:00:00' : ' 23:59:59');
                if ($tipo === 'pacientes') {
                    $limite = Carbon::parse($limite, 'America/Guatemala')->utc()->format('Y-m-d H:i:s');
                }
                $q->where($colFecha, $operador, $limite);
            }
        }
        $paciente = function (Builder $p) use ($filtros): void {
            if (! empty($filtros['id_paciente'])) {
                $p->where('id_paciente', $filtros['id_paciente']);
            }
            if (! empty($filtros['situacion'])) {
                $p->whereHas('ingresos', function ($i) use ($filtros) {
                    if ($filtros['situacion'] === 'consultas') {
                        $i->whereIn('tipo_atencion', ['Consulta externa', 'Emergencia']);
                        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $op) {
                            if (! empty($filtros[$campo])) {
                                $i->where('fecha_ingreso', $op, $filtros[$campo].($campo === 'desde' ? ' 00:00:00' : ' 23:59:59'));
                            }
                        }
                    } else {
                        $i->whereHas('hospitalizacion', function ($h) use ($filtros) {
                            if ($filtros['situacion'] === 'actuales') {
                                $h->where('estado', 'Activa');
                            }
                            if (! empty($filtros['hasta'])) {
                                $h->where('fecha_inicio', '<=', $filtros['hasta'].' 23:59:59');
                            }
                            if (! empty($filtros['desde'])) {
                                $h->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $filtros['desde'].' 00:00:00'));
                            }
                        });
                        if ($filtros['situacion'] === 'actuales') {
                            $i->where('estado', 'Activo');
                        }
                    }
                });
            }
            if (! empty($filtros['paciente'])) {
                $busca = '%'.$filtros['paciente'].'%';
                $p->where(fn ($p) => $p->where('numero_expediente', 'like', $busca)->orWhere('codigo_temporal', 'like', $busca)->orWhere('dpi', 'like', $busca)->orWhere('nombres', 'like', $busca)->orWhere('apellidos', 'like', $busca));
            }
            foreach (['identificacion' => 'estado_identificacion', 'sexo' => 'sexo', 'estado_paciente' => 'estado'] as $f => $c) {
                if (! empty($filtros[$f])) {
                    $p->where($c, $filtros[$f]);
                }
            }
        };
        if ($clase === Paciente::class) {
            $paciente($q);
        } else {
            $q->whereHas($clase === Ingreso::class ? 'paciente' : 'ingreso.paciente', $paciente);
        }

        $ingreso = function (Builder $i) use ($filtros, $tipo): void {
            if (in_array($tipo, ['hospitalizaciones', 'internados'], true)) {
                $i->whereHas('hospitalizacion', function ($h) use ($filtros) {
                    if (! empty($filtros['hasta'])) {
                        $h->where('fecha_inicio', '<=', $filtros['hasta'].' 23:59:59');
                    }
                    if (! empty($filtros['desde'])) {
                        $h->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $filtros['desde'].' 00:00:00'));
                    }
                });
            }
            if (! empty($filtros['atencion'])) {
                $filtros['atencion'] === 'Consulta externa'
                    ? $i->whereIn('tipo_atencion', ['Consulta externa', 'Emergencia'])
                    : $i->where('tipo_atencion', 'Hospitalización');
            }
            if (! empty($filtros['estado_atencion'])) {
                $i->where('estado', $filtros['estado_atencion']);
            }
            if ($tipo === 'internados') {
                $i->where('estado', 'Activo')->whereHas('hospitalizacion', fn ($h) => $h->where('estado', 'Activa'));
            }
            if (! empty($filtros['habitacion'])) {
                $i->whereHas('hospitalizacion.historialHabitaciones', fn ($h) => $h->where('id_habitacion', $filtros['habitacion'])->when($tipo === 'internados', fn ($h) => $h->whereNull('fecha_hora_fin')));
            }
        };
        if ($clase === Ingreso::class) {
            $ingreso($q);
        } elseif ($clase !== Paciente::class) {
            $q->whereHas('ingreso', $ingreso);
        } elseif (! empty($filtros['atencion']) || ! empty($filtros['estado_atencion']) || ! empty($filtros['habitacion'])) {
            $q->whereHas('ingresos', $ingreso);
        }

        if (! empty($filtros['personal'])) {
            $id = $filtros['personal'];
            if ($clase === Cirugia::class && ($filtros['tipo'] ?? '') === 'personal_atenciones') {
                // En «Atención recibida por el paciente», quien hizo la cirugía es su equipo, no quien la anotó.
                $q->whereHas('equipo', fn ($p) => $p->where('tbl_personal.id_personal', $id)->where('tbl_cirugia_equipo.estado', '<>', 'Cancelado'));
            } elseif ($clase === Paciente::class || $clase === Cirugia::class) {
                $q->where(function ($r) use ($id, $clase): void {
                    $r->whereIn('id_usuario_creacion', DB::table('tbl_usuario')->select('id_usuario')->where('id_personal', $id));
                    if ($clase === Cirugia::class) {
                        $r->orWhereHas('equipo', fn ($p) => $p->where('tbl_personal.id_personal', $id)->where('tbl_cirugia_equipo.estado', '<>', 'Cancelado'));
                    }
                });
            } else {
                $q->where($clase === Ingreso::class ? 'id_personal_registra' : ($clase === AltaMedica::class ? 'id_personal_medico' : 'id_personal'), $id);
            }
        }
        if ($tipo === 'cirugias' && ! empty($filtros['estado_cirugia'])) {
            $q->where('estado', $filtros['estado_cirugia']);
        }
        if ($tipo === 'signos' && ! empty($filtros['tipo_control'])) {
            $q->where('tipo_control', $filtros['tipo_control']);
        }
        if ($tipo === 'enfermeria' && ! empty($filtros['turno'])) {
            $hora = DB::connection()->getDriverName() === 'sqlite' ? "CAST(strftime('%H', fecha_hora) AS INTEGER)" : 'HOUR(fecha_hora)';
            $q->whereRaw(match ($filtros['turno']) {
                'Mañana' => "$hora >= 7 AND $hora < 13", 'Tarde' => "$hora >= 13 AND $hora < 19", default => "($hora >= 19 OR $hora < 7)"
            });
        }

        return $q->orderByDesc($colFecha)->orderByDesc($q->getModel()->getKeyName());
    }

    public function nombre(?Model $persona): string
    {
        return trim(($persona?->nombres ?? '').' '.($persona?->apellidos ?? '')) ?: 'Sin registro';
    }

    public function fila(string $tipo, Model $r): array
    {
        $i = $r instanceof Ingreso ? $r : ($r instanceof Paciente ? null : $r->ingreso);
        $p = $r instanceof Paciente ? $r : $i->paciente;
        $autor = match (true) {
            $r instanceof Ingreso => $this->nombre($r->personalRegistra),
            $r instanceof AltaMedica => $this->nombre($r->medico),
            $r instanceof Paciente, $r instanceof Cirugia => $this->autorUsuario($r->id_usuario_creacion),
            default => $this->nombre($r->personal),
        };
        $fecha = $tipo === 'cirugias' ? ($r->fecha_inicio_real ?? $r->fecha_programada) : $r->{self::TIPOS[$tipo][2]};
        if ($r instanceof Paciente && $fecha) {
            $fecha = $fecha->copy()->setTimezone('America/Guatemala');
        }
        $detalle = match (true) {
            $r instanceof Paciente => $r->estado_identificacion.' · '.$r->estado,
            $r instanceof Ingreso => $r->nombreTipoAtencion().' · '.$r->estado.' · '.($r->hospitalizacion?->habitacionActual?->habitacion?->numero_habitacion ?? 'Sin habitación'),
            $r instanceof EvolucionClinica => $r->diagnostico ?: 'Sin diagnóstico registrado',
            $r instanceof NotaEnfermeria => $r->turno().' · '.($r->estado_paciente ?: 'Sin estado registrado'),
            $r instanceof SignoVital => $r->tipo_control.' · '.$this->resumenSignos($r),
            $r instanceof Cirugia => $r->tipo_operacion.' · '.$r->estado,
            default => $r->diagnostico_final ?: 'Sin diagnóstico registrado',
        };

        $estado = match (true) {
            $r instanceof EvolucionClinica => $r->estado_mejoria ?: 'Sin estado registrado',
            $r instanceof NotaEnfermeria => $r->estado_paciente ?: 'Sin estado registrado',
            $r instanceof SignoVital => 'Registrado',
            $r instanceof AltaMedica => 'Dado de alta',
            default => $r->estado ?: 'Sin registro',
        };

        return ['id' => $r->getKey(), 'fecha' => $fecha?->format('d/m/Y H:i') ?? 'Sin fecha', 'expediente' => $p->numero_expediente,
            'paciente' => $p->estado_identificacion === 'No identificado' ? 'No identificado · '.$p->codigo_temporal : $this->nombre($p),
            'responsable' => $autor, 'estado' => $estado, 'detalle' => $detalle];
    }

    private function autorUsuario(?int $id): string
    {
        $p = DB::table('tbl_usuario as u')->join('tbl_personal as p', 'p.id_personal', '=', 'u.id_personal')->where('u.id_usuario', $id)->first(['p.nombres', 'p.apellidos']);

        return $p ? trim($p->nombres.' '.$p->apellidos) : 'Sin registro';
    }

    public function resumenSignos(SignoVital $s): string
    {
        return 'PA: '.($s->presion_sistolica !== null && $s->presion_diastolica !== null ? "$s->presion_sistolica/$s->presion_diastolica" : '—').' mmHg; FC: '.($s->frecuencia_cardiaca ?? '—').' lpm; FR: '.($s->frecuencia_respiratoria ?? '—').' rpm; T: '.($s->temperatura ?? '—').' °C; SpO2: '.($s->saturacion_oxigeno ?? '—').' %';
    }

    /**
     * Hoja con los datos generales del paciente: identificación, contacto, datos clínicos y atenciones.
     */
    public function fichaPaciente(Paciente $p): array
    {
        $p->loadMissing(['contactos', 'ingresos']);
        $secciones = [
            'Identificación' => [
                'Nombre completo' => $p->estado_identificacion === 'No identificado' ? 'No identificado' : $this->nombre($p),
                'Número de expediente' => $p->numero_expediente,
                'Estado de identificación' => $p->estado_identificacion,
                'DPI' => $p->dpi,
                'Fecha de nacimiento' => $p->fecha_nacimiento?->format('d/m/Y'),
                'Edad' => $p->fecha_nacimiento ? $p->fecha_nacimiento->age.' años' : null,
                'Sexo' => $p->sexo,
                'Estado del paciente' => $p->estado,
            ],
            'Contacto' => ['Teléfono' => $p->telefono, 'Dirección' => $p->direccion],
            'Datos clínicos' => [
                'Tipo de sangre' => $p->tipo_sangre,
                'Talla' => $p->tallaTexto(),
                'Peso' => $p->pesoTexto(),
                'Alergias' => $p->alergias,
                'Antecedentes médicos' => $p->antecedentes_medicos,
                'Antecedentes familiares' => $p->antecedentes_familiares,
                'Observaciones' => $p->observaciones_generales,
            ],
        ];
        if ($p->estado_identificacion === 'No identificado' || filled($p->codigo_temporal)) {
            $secciones['Identificación']['Código temporal'] = $p->codigo_temporal;
        }
        foreach ($p->contactos as $n => $c) {
            $secciones['Personas de contacto']['Contacto '.($n + 1)] = "$c->nombre · $c->parentesco · $c->telefono";
        }
        foreach ($p->ingresos->sortBy('fecha_ingreso')->values() as $n => $i) {
            $secciones['Historial de atenciones']['Atención '.($n + 1)] = $i->fecha_ingreso?->format('d/m/Y H:i').' · '.$i->nombreTipoAtencion().' · '.$i->estado.($i->motivo_ingreso ? ' · '.$i->motivo_ingreso : '');
        }

        return [
            'id' => $p->getKey(),
            'paciente' => $p->estado_identificacion === 'No identificado' ? 'No identificado · '.$p->codigo_temporal : $this->nombre($p),
            'expediente' => $p->numero_expediente,
            'fecha' => $p->fecha_registro?->copy()->setTimezone('America/Guatemala')->format('d/m/Y H:i') ?? 'Sin fecha',
            'responsable' => $this->autorUsuario($p->id_usuario_creacion),
            'detalle' => $p->estado_identificacion.' · '.$p->estado,
            'etiqueta_fecha' => 'Fecha de registro',
            'secciones' => $secciones,
        ];
    }

    public function documento(string $tipo, Model $r): array
    {
        if ($r instanceof Paciente) {
            return $this->fichaPaciente($r);
        }
        $fila = $this->fila($tipo, $r);
        $i = $r instanceof Ingreso ? $r : ($r instanceof Paciente ? null : $r->ingreso);
        $p = $r instanceof Paciente ? $r : $i->paciente;
        $campos = ['Identificación' => $p->dpi ?: $p->codigo_temporal, 'Sexo' => $p->sexo,
            'Fecha de nacimiento' => $p->fecha_nacimiento?->format('d/m/Y'), 'Alergias registradas' => $p->alergias];
        if ($i) {
            $campos['Atención'] = '#'.$i->getKey().' · '.$i->nombreTipoAtencion();
        }
        $secciones = ['Datos del paciente' => $campos];
        $mapa = match (true) {
            $r instanceof Paciente => ['telefono' => 'Teléfono', 'direccion' => 'Dirección', 'tipo_sangre' => 'Tipo de sangre', 'antecedentes_medicos' => 'Antecedentes médicos', 'antecedentes_familiares' => 'Antecedentes familiares', 'observaciones_generales' => 'Observaciones'],
            $r instanceof Ingreso => ['motivo_ingreso' => 'Motivo de ingreso / consulta', 'estado' => 'Estado de la atención'],
            $r instanceof EvolucionClinica => ['motivo_atencion' => 'Motivo de atención', 'sintomas' => 'Síntomas', 'evaluacion_medica' => 'Evaluación médica', 'diagnostico' => 'Diagnóstico', 'estado_mejoria' => 'Evolución del paciente', 'indicaciones' => 'Tratamiento / indicaciones', 'observaciones' => 'Observaciones'],
            $r instanceof NotaEnfermeria => ['estado_paciente' => 'Estado del paciente', 'observaciones' => 'Nota de enfermería'],
            $r instanceof SignoVital => ['tipo_control' => 'Tipo de control', 'observaciones' => 'Observaciones'],
            $r instanceof Cirugia => ['tipo_operacion' => 'Cirugía', 'estado' => 'Estado', 'observaciones' => 'Observaciones', 'motivo_registro_posterior' => 'Motivo del registro posterior', 'motivo_cancelacion' => 'Motivo de cancelación'],
            default => ['diagnostico_final' => 'Diagnóstico final', 'observaciones' => 'Observaciones', 'condicion_egreso' => 'Condición de egreso', 'indicaciones_alta' => 'Indicaciones para casa'],
        };
        if ($tipo !== 'ordenes') {
            foreach ($mapa as $col => $titulo) {
                $secciones['Información de la hoja'][$titulo] = $r->$col;
            }
        }
        if ($r instanceof EvolucionClinica) {
            if ($tipo !== 'ordenes') {
                $examen = $r->examen_fisico ?? [];
                foreach (['presion_arterial' => 'Presión arterial (mmHg)', 'frecuencia_cardiaca' => 'Frecuencia cardíaca (lpm)', 'frecuencia_respiratoria' => 'Frecuencia respiratoria (rpm)', 'temperatura' => 'Temperatura (°C)', 'saturacion_oxigeno' => 'SpO2 (%)', 'talla' => 'Talla (cm)', 'cabeza' => 'Cabeza', 'tronco' => 'Tronco', 'extremidades' => 'Extremidades'] as $col => $titulo) {
                    $secciones['Examen físico'][$titulo] = $examen[$col] ?? null;
                }
                $secciones['Examen físico']['Peso (lb)'] = ExamenFisico::pesoEnLibras($examen);
            }
            $o = $r->ordenes_medicas ?? [];
            if ($cancelacion = OrdenesMedicas::cancelacion($o)) {
                $secciones['Órdenes médicas']['Estado'] = 'Canceladas el '.Carbon::parse($cancelacion['fecha'])->format('d/m/Y H:i').' por '.$cancelacion['medico'].'. Motivo: '.$cancelacion['motivo'];
            }
            foreach (['dieta' => 'Dieta', 'indicaciones' => 'Indicaciones para enfermería', 'plan_educacional' => 'Plan educacional'] as $col => $titulo) {
                $secciones['Órdenes médicas'][$titulo] = $o[$col] ?? null;
            }
            foreach ($o['soluciones'] ?? [] as $n => $s) {
                $secciones['Órdenes médicas']['Solución '.($n + 1)] = ($s['tipo'] ?? '').' · '.($s['volumen'] ?? '').' ml · '.($s['velocidad'] ?? '').' '.($s['unidad'] ?? '');
            }
            foreach ($o['medicamentos'] ?? [] as $n => $m) {
                $secciones['Órdenes médicas']['Medicamento '.($n + 1)] = ($m['nombre'] ?? '').' · '.($m['dosis'] ?? '').(is_numeric($m['dosis'] ?? null) ? ' mg' : '').' · '.($m['via'] ?? '').' · '.(is_numeric($m['frecuencia'] ?? null) ? 'Cada '.$m['frecuencia'].' h' : ($m['frecuencia'] ?? ''));
            }
        }
        if ($r instanceof NotaEnfermeria) {
            $secciones['Enfermería'] = ['Turno' => $r->turno(), 'Actividades' => implode('; ', $r->listaActividades()), 'Aviso al médico' => $r->aviso_medico ? 'Sí · '.$this->nombre($r->medicoAvisado) : 'No'];
            foreach ($r->ordenes as $orden) {
                $secciones['Órdenes médicas'][$orden->tipo.': '.$orden->descripcion] = $orden->resultado().($orden->aplicada || blank($orden->motivo) ? '' : ' · Motivo: '.$orden->motivo);
            }
            if ($r->signosVitales) {
                $secciones['Signos vitales']['Mediciones'] = $this->resumenSignos($r->signosVitales);
            }
        }
        if ($r instanceof SignoVital) {
            $secciones['Signos vitales']['Mediciones'] = $this->resumenSignos($r);
        }
        if ($r instanceof AltaMedica) {
            foreach ($r->medicamentos as $n => $m) {
                $secciones['Medicamentos para casa']['Medicamento '.($n + 1)] = "$m->medicamento · $m->dosis · $m->via_administracion · $m->frecuencia · $m->duracion";
            }
        }
        if ($r instanceof Cirugia) {
            foreach (['fecha_programada' => 'Inicio programado', 'fecha_fin_programada' => 'Fin programado', 'fecha_inicio_real' => 'Inicio real', 'fecha_fin_real' => 'Fin real'] as $col => $titulo) {
                $secciones['Fechas de cirugía'][$titulo] = $r->$col?->format('d/m/Y H:i');
            }
            foreach ($r->equipo as $n => $persona) {
                $secciones['Personal participante']['Persona '.($n + 1)] = $this->nombre($persona).' · '.$persona->pivot->estado;
            }
            foreach ($r->controles as $n => $s) {
                $secciones['Controles de recuperación']['Control '.($n + 1)] = $s->fecha_hora->format('d/m/Y H:i').' · '.$this->nombre($s->personal).' · '.$this->resumenSignos($s).' · '.$s->observaciones;
            }
        }
        if ($r instanceof Paciente) {
            foreach ($r->contactos as $n => $c) {
                $secciones['Personas de contacto']['Contacto '.($n + 1)] = "$c->nombre · $c->parentesco · $c->telefono";
            }
        }
        if ($r instanceof Ingreso) {
            $secciones['Ingreso']['Documento firmado'] = $r->documento_firmado ? 'Sí' : 'No';
            foreach ($r->hospitalizacion?->historialHabitaciones ?? [] as $n => $h) {
                $secciones['Habitaciones']['Asignación '.($n + 1)] = $h->habitacion?->numero_habitacion.' · '.$h->fecha_hora_inicio?->format('d/m/Y H:i').' a '.($h->fecha_hora_fin?->format('d/m/Y H:i') ?? 'Actual');
            }
        }

        return $fila + ['secciones' => $secciones];
    }
}
