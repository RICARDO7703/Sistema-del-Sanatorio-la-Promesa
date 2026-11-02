@php
    // Todos los documentos del ingreso en orden de fecha; a la misma hora se respeta el orden clínico.
    // Una consulta externa (sin internamiento) usa nombres de consulta, no de hospitalización.
    $emergencia = $ingreso->esConsultaExterna();
    $documentos = collect([['tipo' => 'general', 'fecha' => $ingreso->fecha_ingreso, 'orden' => 0, 'destino' => 'documento-ingreso-'.$ingreso->getKey(), 'titulo' => $emergencia ? 'Hoja de llegada' : 'Hoja de ingreso', 'icono' => 'bi-file-earmark-text text-primary', 'detalle' => '', 'editado' => ($ediciones ?? collect())->get('tbl_ingreso:'.$ingreso->getKey()), 'editable' => \App\Support\EdicionClinica::puedeEditar($ingreso) ? \App\Support\EdicionClinica::limiteLocal($ingreso) : null]]);
    foreach ($ingreso->evoluciones as $registroDocumento) {
        $esValoracion = $ingreso->evoluciones->first()->is($registroDocumento) && $registroDocumento->estado_mejoria === null;
        $documentos->push(['tipo' => $esValoracion ? 'valoracion' : 'evolucion', 'fecha' => $registroDocumento->fecha_hora, 'orden' => 1, 'destino' => 'documento-evolucion-'.$registroDocumento->getKey(),
            'titulo' => $esValoracion ? ($emergencia ? 'Consulta externa' : 'Valoración inicial') : 'Evolución clínica #'.$registroDocumento->getKey(),
            'icono' => 'bi-file-earmark-medical text-primary', 'detalle' => trim($registroDocumento->personal?->nombres.' '.$registroDocumento->personal?->apellidos),
            'editado' => ($ediciones ?? collect())->get('tbl_evolucion_clinica:'.$registroDocumento->getKey()),
            'editable' => \App\Support\EdicionClinica::puedeEditar($registroDocumento->setRelation('ingreso', $ingreso)) ? \App\Support\EdicionClinica::limiteLocal($registroDocumento) : null]);
    }
    foreach ($ingreso->notasEnfermeria->values() as $numero => $notaDocumento) {
        $documentos->push(['tipo' => 'nota', 'fecha' => $notaDocumento->fecha_hora, 'orden' => 2, 'destino' => 'documento-nota-'.$notaDocumento->getKey(),
            'titulo' => 'Nota de enfermería #'.($numero + 1), 'icono' => 'bi-journal-text text-info', 'detalle' => trim($notaDocumento->personal?->nombres.' '.$notaDocumento->personal?->apellidos), 'nota' => $notaDocumento]);
    }
    if ($ingreso->altaMedica) {
        $documentos->push(['tipo' => 'alta', 'fecha' => $ingreso->altaMedica->fecha_hora, 'orden' => 3, 'destino' => 'documento-alta-'.$ingreso->getKey(), 'titulo' => $emergencia ? 'Alta e indicaciones para casa' : 'Alta médica',
            'icono' => 'bi-file-earmark-check text-success', 'detalle' => trim($ingreso->altaMedica->medico?->nombres.' '.$ingreso->altaMedica->medico?->apellidos)]);
    }
    foreach ($ingreso->documentosClinicos->where('estado','Activo') as $archivo) {
        $claseArchivo = match($archivo->tipo_documento) { 'Valoración inicial'=>'valoracion', 'Consulta externa','Evolución clínica','Órdenes médicas'=>'evolucion', 'Nota de enfermería'=>'nota', default=>'general' };
        $documentos->push(['tipo'=>$claseArchivo,'fecha'=>$archivo->fecha_documento ?? $archivo->fecha_registro,'orden'=>4,'destino'=>'','titulo'=>$archivo->titulo,'icono'=>'bi-file-earmark-'.($archivo->tipo_archivo === 'pdf' ? 'pdf' : 'image').' text-primary','detalle'=>'Hoja en papel','archivo'=>$archivo,'editado'=>($ediciones ?? collect())->get('tbl_documento_clinico:'.$archivo->getKey())]);
    }
    $documentos = $documentos->sortBy([fn ($a, $b) => $a['fecha'] <=> $b['fecha'], fn ($a, $b) => $a['orden'] <=> $b['orden']])->values();
    $filtros = ['valoracion' => 'Valoración inicial', 'evolucion' => 'Evoluciones', 'nota' => 'Notas de enfermería', 'alta' => 'Alta médica'];
    $clinicos = $documentos->whereIn('tipo', array_keys($filtros))->count();
    $filtro = 'filtro-documentos-'.$ingreso->getKey();
@endphp
<div class="border-top pt-3" data-filtro-documentos>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h6 class="mb-0">{{ $emergencia ? 'Documentos de la atención' : 'Documentos del ingreso' }}</h6>
        @if($clinicos > 0 && ! $emergencia)
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Filtrar documentos del ingreso #{{ $ingreso->getKey() }}">
            <input type="radio" class="btn-check" name="{{ $filtro }}" id="{{ $filtro }}-todos" value="todos" checked>
            <label class="btn btn-sm btn-outline-primary" for="{{ $filtro }}-todos">Todos ({{ $documentos->count() }})</label>
            @foreach($filtros as $tipoFiltro => $nombreFiltro)
            <input type="radio" class="btn-check" name="{{ $filtro }}" id="{{ $filtro }}-{{ $tipoFiltro }}" value="{{ $tipoFiltro }}">
            <label class="btn btn-sm btn-outline-primary" for="{{ $filtro }}-{{ $tipoFiltro }}">{{ $nombreFiltro }} ({{ $documentos->where('tipo', $tipoFiltro)->count() }})</label>
            @endforeach
        </div>
        @endif
    </div>
    <div class="list-group">
        @foreach($documentos->groupBy(fn ($documento) => $documento['fecha']->format('Y-m-d')) as $diaDocumentos => $documentosDelDia)
        <div class="mb-3" data-dia-documentos>
            <h6 class="bg-light border rounded p-3 mb-0"><i class="bi bi-calendar3 text-primary me-2" aria-hidden="true"></i>Documentos del {{ \Illuminate\Support\Carbon::parse($diaDocumentos)->format('d/m/Y') }}</h6>
        @foreach($documentosDelDia as $documento)
        @if(isset($documento['archivo']))
        <a href="{{ route('archivos.show',$documento['archivo']) }}" class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between align-items-center gap-2" data-tipo-documento="{{ $documento['tipo'] }}"><span><i class="bi {{ $documento['icono'] }} me-2" aria-hidden="true"></i>{{ $documento['titulo'] }} <span class="badge bg-light text-dark border">Imagen / PDF</span></span><span class="small text-muted text-end">{{ $documento['fecha']->format('d/m/Y H:i') }} <i class="bi bi-eye" aria-hidden="true"></i>@if(! empty($documento['editado']))<span class="d-block documento-editado"><i class="bi bi-pencil-square" aria-hidden="true"></i> Se editó el {{ $documento['editado']->format('d/m/Y') }} a las {{ $documento['editado']->format('H:i') }}</span>@endif</span></a>
        @else
        <button type="button" class="list-group-item list-group-item-action d-flex flex-wrap align-items-center justify-content-between gap-2" data-bs-toggle="modal" data-bs-target="#{{ $documento['destino'] }}" data-tipo-documento="{{ $documento['tipo'] }}">
            <span>
                <i class="bi {{ $documento['icono'] }} me-2" aria-hidden="true"></i>{{ $documento['titulo'] }}
                @isset($documento['nota'])
                @if($documento['nota']->estado_paciente)<span class="badge {{ $documento['nota']->claseEstado() }} ms-1">{{ $documento['nota']->estado_paciente }}</span>@endif
                @if($documento['nota']->aviso_medico)<span class="badge bg-warning text-dark ms-1"><i class="bi bi-telephone" aria-hidden="true"></i> Aviso al médico</span>@endif
                @endisset
            </span>
            <span class="small text-muted text-end">{{ $documento['detalle'] !== '' ? $documento['detalle'].' · ' : '' }}{{ $documento['fecha']->format('d/m/Y H:i') }} <i class="bi bi-eye ms-2" aria-hidden="true"></i>
                @if(! empty($documento['editable']))<span class="d-block small text-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Puede editarla hasta las {{ $documento['editable']->format('H:i') }} del {{ $documento['editable']->format('d/m/Y') }}</span>@endif
                @if(! empty($documento['editado']))<span class="d-block documento-editado"><i class="bi bi-pencil-square" aria-hidden="true"></i> Se editó el {{ $documento['editado']->format('d/m/Y') }} a las {{ $documento['editado']->format('H:i') }}</span>@endif
            </span>
        </button>
        @endif
        @endforeach
        </div>
        @endforeach
        <p class="list-group-item text-muted mb-0" data-sin-documentos hidden>No hay documentos de este tipo.</p>
    </div>
</div>
