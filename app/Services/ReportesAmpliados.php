<?php

namespace App\Services;

use App\Models\DocumentoClinico;
use App\Models\Paciente;
use App\Support\Permisos;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReaderException;

class ReportesAmpliados
{
    public function __construct(private DatosReportes $datos) {}

    public function actividad(array $filtros): Collection
    {

        $actividad = $filtros['actividad'] ?? null;
        if ($actividad !== null) {
            abort_unless(isset($this->datos->disponibles()[$actividad]), 403);
        }
        if ($actividad === 'signos') {
            return $this->signos($filtros);
        }
        $tipos = $actividad === 'atenciones' || $actividad === 'ordenes' ? [$actividad] : ['valoraciones', 'consultas', 'evoluciones', 'enfermeria', 'signos', 'altas', 'cirugias'];
        $filas = collect();
        if (($filtros['tipo'] ?? '') === 'personal_atenciones' && $actividad === null) {
            $filas = $this->signos($filtros);
            $tipos = ['atenciones', 'valoraciones', 'consultas', 'evoluciones', 'enfermeria', 'altas', 'cirugias'];
        }
        foreach ($tipos as $tipo) {
            if ($actividad !== null && $tipo !== $actividad) {
                continue;
            }
            if (! isset($this->datos->disponibles()[$tipo])) {
                continue;
            }
            foreach ($this->datos->consulta($tipo, $filtros)->limit(1001)->get() as $registro) {
                $fila = $this->datos->fila($tipo, $registro);
                $fila['tipo_pdf'] = $tipo;
                $secciones = $this->datos->documento($tipo, $registro)['secciones'];
                $acciones = [];
                foreach ($secciones as $seccion => $campos) {
                    if (in_array($seccion, ['Datos del paciente', 'Examen físico', 'Fechas de cirugía', 'Personal participante', 'Controles de recuperación'], true)) {
                        continue;
                    }
                    foreach ($campos as $etiqueta => $valor) {
                        if (filled($valor)) {
                            $acciones[] = $etiqueta.': '.$valor;
                        }
                    }
                }
                if ($tipo === 'cirugias') {
                    $equipo = $registro->equipo->filter(fn ($persona) => $persona->pivot->estado !== 'Cancelado');
                    if ($equipo->isNotEmpty()) {
                        $fila['responsable'] = $equipo->map(fn ($persona) => $this->datos->nombre($persona))->implode(', ');
                        $acciones[] = 'Registrada por: '.$this->datos->fila($tipo, $registro)['responsable'];
                    }
                }
                $fila['detalle'] = DatosReportes::TIPOS[$tipo][0].' · '.implode(' · ', $acciones);
                $fila['orden'] = ($tipo === 'cirugias' ? ($registro->fecha_inicio_real ?? $registro->fecha_programada) : $registro->{DatosReportes::TIPOS[$tipo][2]})?->format('Y-m-d H:i:s') ?? '';
                $filas->push($fila);
                if ($filas->count() > 1000) {
                    throw ValidationException::withMessages(['desde' => 'Más de 1,000 actividades. Reduzca las fechas.']);
                }
            }
        }
        $filas = $filas->sortByDesc('orden')->values();
        if (($filtros['tipo'] ?? '') === 'personal_pacientes') {
            return $filas->groupBy('expediente')->map(function ($grupo) {
                $fila = $grupo->first();
                $fila['detalle'] = $grupo->count().' registros de atención · '.implode(', ', $grupo->pluck('tipo_pdf')->unique()->map(fn ($tipo) => DatosReportes::TIPOS[$tipo][0])->all());

                return $fila;
            })->values();
        }

        return $filas;
    }

    /**
     * Incluye mediciones de enfermería y las tomadas dentro de las hojas médicas.
     */
    public function signos(array $filtros): Collection
    {
        $filas = $this->datos->consulta('signos', $filtros)->limit(1001)->get()->map(function ($signo): array {
            $fila = $this->datos->fila('signos', $signo);
            $fila['tipo_pdf'] = 'signos';
            $fila['orden'] = $signo->fecha_hora->format('Y-m-d H:i:s');

            return $fila;
        });
        foreach (['valoraciones' => 'Ingreso', 'consultas' => 'Consulta', 'evoluciones' => 'Rutina'] as $tipo => $control) {
            if (! empty($filtros['tipo_control']) && $filtros['tipo_control'] !== $control) {
                continue;
            }
            foreach ($this->datos->consulta($tipo, $filtros)->whereNotNull('examen_fisico')->limit(1001)->get() as $hoja) {
                $valores = [];
                foreach (['presion_arterial' => 'PA (mmHg)', 'frecuencia_cardiaca' => 'FC (lpm)', 'frecuencia_respiratoria' => 'FR (rpm)', 'temperatura' => 'Temperatura (°C)', 'saturacion_oxigeno' => 'SpO2 (%)'] as $campo => $etiqueta) {
                    if (filled($hoja->examen_fisico[$campo] ?? null)) {
                        $valores[] = $etiqueta.': '.$hoja->examen_fisico[$campo];
                    }
                }
                if ($valores === []) {
                    continue;
                }
                $fila = $this->datos->fila($tipo, $hoja);
                $fila['tipo_pdf'] = $tipo;
                $fila['orden'] = $hoja->fecha_hora->format('Y-m-d H:i:s');
                $fila['estado'] = 'Registrado';
                $fila['detalle'] = DatosReportes::TIPOS[$tipo][0].' · '.implode(' · ', $valores);
                $filas->push($fila);
            }
        }
        if ($filas->count() > 1000) {
            throw ValidationException::withMessages(['tipo' => 'Más de 1,000 controles de signos vitales. Reduzca las fechas o elija un paciente.']);
        }

        return $filas->sortByDesc('orden')->values();
    }

    public function documentos(Paciente $paciente, array $filtros): Collection
    {
        foreach (['Expedientes', 'Cirugías', 'Documentos clínicos'] as $modulo) {
            abort_unless(app(Permisos::class)->puede($modulo, 'Ver'), 403);
        }
        $documentos = collect();
        $filtros = ['id_paciente' => $paciente->getKey()] + array_intersect_key($filtros, array_flip(['desde', 'hasta']));
        foreach (['atenciones', 'valoraciones', 'consultas', 'evoluciones', 'enfermeria', 'signos', 'cirugias', 'altas'] as $tipo) {
            $q = $this->datos->consulta($tipo, $filtros);
            if ($tipo === 'signos') {
                $q->whereDoesntHave('nota');
            }
            foreach ($q->limit(201)->get() as $registro) {
                $fecha = $tipo === 'cirugias' ? ($registro->fecha_inicio_real ?? $registro->fecha_programada) : $registro->{DatosReportes::TIPOS[$tipo][2]};
                $documentos->push(['tipo' => $tipo, 'registro' => $registro, 'orden' => $fecha?->format('Y-m-d H:i:s') ?? '', 'clave' => $tipo.'-'.$registro->getKey()]);
            }
        }
        $archivos = DocumentoClinico::where('id_paciente', $paciente->getKey())->where('estado', 'Activo');
        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
            if (! empty($filtros[$campo])) {
                $archivos->where('fecha_documento', $operador, $filtros[$campo].($campo === 'desde' ? ' 00:00:00' : ' 23:59:59'));
            }
        }
        // Un archivo adjunto a una hoja (por ejemplo, la foto de una cirugía) va dentro del PDF de esa hoja, no como documento aparte.
        $hojas = $documentos->keyBy(fn (array $documento) => $documento['registro']->getTable().':'.$documento['registro']->getKey());
        $adjuntos = [];
        foreach ($archivos->orderBy('fecha_documento')->orderBy('id_documento')->limit(201)->get() as $archivo) {
            $origen = $archivo->tabla_origen && $archivo->id_registro_origen ? $archivo->tabla_origen.':'.$archivo->id_registro_origen : null;
            if ($origen !== null && $hojas->has($origen)) {
                $adjuntos[$origen][] = $archivo;

                continue;
            }
            $documentos->push(['tipo' => 'archivo', 'registro' => $archivo, 'orden' => $archivo->fecha_documento?->format('Y-m-d H:i:s') ?? '', 'clave' => 'archivo-'.$archivo->getKey()]);
        }
        $documentos = $documentos->map(fn (array $documento) => $documento + ['adjuntos' => $adjuntos[$documento['registro']->getTable().':'.$documento['registro']->getKey()] ?? []]);
        if ($documentos->count() + count($adjuntos, COUNT_RECURSIVE) - count($adjuntos) > 200) {
            throw ValidationException::withMessages(['desde' => 'El expediente supera 200 documentos. Elija un intervalo de fechas menor.']);
        }

        return $documentos->sortBy([['orden', 'asc'], ['clave', 'asc']])->values();
    }

    public function unir(iterable $partes): string
    {
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);
        $totalBytes = 0;
        foreach ($partes as $parte) {
            $totalBytes += strlen($parte['bytes']);
            if ($totalBytes > 80000000) {
                throw ValidationException::withMessages(['desde' => 'Los documentos superan 80 MB. Reduzca el intervalo de fechas.']);
            }
            try {
                $paginas = $pdf->setSourceFile(StreamReader::createByString($parte['bytes']));
                if ($pdf->PageNo() + $paginas > 500) {
                    throw ValidationException::withMessages(['desde' => 'El expediente supera 500 páginas. Reduzca las fechas.']);
                }
                for ($n = 1; $n <= $paginas; $n++) {
                    $plantilla = $pdf->importPage($n);
                    $tamano = $pdf->getTemplateSize($plantilla);
                    $pdf->AddPage($tamano['orientation'], [$tamano['width'], $tamano['height']]);
                    $pdf->useTemplate($plantilla);
                }
            } catch (PdfParserException|PdfReaderException $e) {
                throw ValidationException::withMessages(['archivo' => 'No se pudo unir '.$parte['nombre'].'. Puede estar protegido o utilizar una compresión no compatible. Vuelva a guardarlo como PDF sin contraseña y súbalo de nuevo. No se descargó un expediente incompleto.']);
            }
        }
        if ($pdf->PageNo() === 0) {
            throw ValidationException::withMessages(['desde' => 'No hay documentos en esas fechas.']);
        }

        return $pdf->Output('S');
    }
}
