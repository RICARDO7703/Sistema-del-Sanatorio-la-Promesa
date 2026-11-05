{{-- Documentos agrupados por día; cada día tiene su propio filtro. Dentro de una atención no hace falta decir a cuál pertenecen. --}}
@php
    $diasDocumentos = collect($documentosPorDia)->keys();
@endphp
{{-- En el teléfono los días van en una fila deslizable y se ve un día a la vez (el más reciente al abrir); en pantallas grandes se ven todos. --}}
<div class="dias-documentos" data-dias-documentos>
@if($diasDocumentos->count() > 1)
<div class="selector-dias" role="group" aria-label="Elegir el día">
    <button type="button" class="selector-dias-flecha" data-dia-anterior aria-label="Día anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
    <div class="selector-dias-lista">
        @foreach($diasDocumentos->sort()->values() as $diaSelector)
        @php
            $fechaSelector = \Illuminate\Support\Carbon::parse($diaSelector)->locale('es');
        @endphp
        <button type="button" class="selector-dia" data-elegir-dia="{{ $diaSelector }}" aria-pressed="false" aria-label="{{ \Illuminate\Support\Str::ucfirst($fechaSelector->isoFormat('dddd D [de] MMMM')) }}">
            <span>{{ \Illuminate\Support\Str::ucfirst(rtrim($fechaSelector->isoFormat('ddd'), '.')) }}</span>
            <strong>{{ $fechaSelector->day }}</strong>
            <small>{{ rtrim($fechaSelector->isoFormat('MMM'), '.') }}</small>
        </button>
        @endforeach
    </div>
    <button type="button" class="selector-dias-flecha" data-dia-siguiente aria-label="Día siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
</div>
@endif
@forelse($documentosPorDia as $dia => $documentosDelDia)
@php
    $filtroDia = 'filtro-documentos-'.($prefijoFiltro ?? 'expediente').'-'.$dia;
    $tiposDelDia = collect(\App\Support\DocumentosExpediente::FILTROS)->filter(fn ($nombre, $tipo) => $documentosDelDia->contains('tipo', $tipo));
@endphp
<section class="dia-documentos border rounded-3" data-filtro-documentos data-dia="{{ $dia }}" aria-label="Documentos del {{ \Illuminate\Support\Carbon::parse($dia)->format('d/m/Y') }}">
    <header class="dia-documentos-cabecera">
        <h5 class="mb-0"><i class="bi bi-calendar3 text-primary me-2" aria-hidden="true"></i>{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Carbon::parse($dia)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')) }}</h5>
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Filtrar documentos del {{ \Illuminate\Support\Carbon::parse($dia)->format('d/m/Y') }}">
            <input type="radio" class="btn-check" name="{{ $filtroDia }}" id="{{ $filtroDia }}-todos" value="todos" checked>
            <label class="btn btn-sm btn-outline-primary" for="{{ $filtroDia }}-todos">Todos ({{ $documentosDelDia->count() }})</label>
            @foreach($tiposDelDia as $tipoFiltro => $nombreFiltro)
            <input type="radio" class="btn-check" name="{{ $filtroDia }}" id="{{ $filtroDia }}-{{ $tipoFiltro }}" value="{{ $tipoFiltro }}">
            <label class="btn btn-sm btn-outline-primary" for="{{ $filtroDia }}-{{ $tipoFiltro }}">{{ $nombreFiltro }} ({{ $documentosDelDia->where('tipo', $tipoFiltro)->count() }})</label>
            @endforeach
        </div>
    </header>
    <div class="list-group list-group-flush" data-dia-documentos>
        @foreach($documentosDelDia as $documento)
        @php
            $etiquetaAtencion = ! ($mostrarAtencion ?? true) ? '' : '<span class="badge documento-atencion '.($documento['atencionConsulta'] ? 'documento-atencion-consulta' : 'documento-atencion-ingreso').'"><i class="bi '.($documento['atencionConsulta'] ? 'bi-person-walking' : 'bi-hospital').'" aria-hidden="true"></i> '.e($documento['atencion']).'</span>';
        @endphp
        @if(isset($documento['archivo']))
        <a href="{{ route('archivos.contenido',$documento['archivo']) }}" data-abrir-documento data-tipo-archivo="{{ $documento['archivo']->tipo_archivo }}" data-titulo-archivo="{{ $documento['archivo']->titulo }}" data-nombre-archivo="{{ $documento['archivo']->nombre_archivo }}" class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between align-items-center gap-2" data-tipo-documento="{{ $documento['tipo'] }}">
            <span><i class="bi {{ $documento['icono'] }} me-2" aria-hidden="true"></i>{{ $documento['titulo'] }} <span class="badge bg-light text-dark border">Imagen / PDF</span> {!! $etiquetaAtencion !!}</span>
            <span class="small text-muted text-end">{{ $documento['fecha']->format('H:i') }} <i class="bi bi-eye ms-2" aria-hidden="true"></i>@if(! empty($documento['editado']))<span class="d-block documento-editado"><i class="bi bi-pencil-square" aria-hidden="true"></i> Se editó el {{ $documento['editado']->format('d/m/Y') }} a las {{ $documento['editado']->format('H:i') }}</span>@endif</span>
        </a>
        @else
        <button type="button" class="list-group-item list-group-item-action d-flex flex-wrap align-items-center justify-content-between gap-2" data-bs-toggle="modal" data-bs-target="#{{ $documento['destino'] }}" data-tipo-documento="{{ $documento['tipo'] }}">
            <span>
                <i class="bi {{ $documento['icono'] }} me-2" aria-hidden="true"></i>{{ $documento['titulo'] }}
                @isset($documento['nota'])
                @if($documento['nota']->estado_paciente)<span class="badge {{ $documento['nota']->claseEstado() }} ms-1">{{ $documento['nota']->estado_paciente }}</span>@endif
                @if($documento['nota']->aviso_medico)<span class="badge bg-warning text-dark ms-1"><i class="bi bi-telephone" aria-hidden="true"></i> Aviso al médico</span>@endif
                @if($documento['nota']->signosVitales?->cirugia)<span class="badge text-bg-primary ms-1"><i class="bi bi-hospital" aria-hidden="true"></i> Control de recuperación · {{ $documento['nota']->signosVitales->cirugia->tipo_operacion }}</span>@endif
                @endisset
                @if(! empty($documento['estadoCirugia']))<span class="badge {{ $documento['estadoCirugia'] === 'En cirugía' ? 'bg-danger' : ($documento['estadoCirugia'] === 'Realizada' ? 'bg-success' : 'bg-secondary') }} ms-1">{{ $documento['estadoCirugia'] }}</span>@endif
                @if(! empty($documento['adjuntos']))<span class="badge bg-light text-dark border ms-1" title="Archivos adjuntos"><i class="bi bi-paperclip" aria-hidden="true"></i> {{ $documento['adjuntos'] }} {{ $documento['adjuntos'] === 1 ? 'archivo' : 'archivos' }}</span>@endif
                @if(! empty($documento['cirugia']))<span class="badge text-bg-primary ms-1"><i class="bi bi-hospital" aria-hidden="true"></i> {{ $documento['cirugia']->tipo_operacion }}</span>@endif
                {!! $etiquetaAtencion !!}
            </span>
            <span class="small text-muted text-end">{{ $documento['detalle'] !== '' ? $documento['detalle'].' · ' : '' }}{{ $documento['fecha']->format('H:i') }} <i class="bi bi-eye ms-2" aria-hidden="true"></i>
                @if(! empty($documento['editable']))<span class="d-block small text-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Puede editarla hasta las {{ $documento['editable']->format('H:i') }} del {{ $documento['editable']->format('d/m/Y') }}</span>@endif
                @if(! empty($documento['editado']))<span class="d-block documento-editado"><i class="bi bi-pencil-square" aria-hidden="true"></i> Se editó el {{ $documento['editado']->format('d/m/Y') }} a las {{ $documento['editado']->format('H:i') }}</span>@endif
            </span>
        </button>
        @endif
        @endforeach
    </div>
    <p class="text-muted px-3 py-2 mb-0" data-sin-documentos hidden>No hay documentos de este tipo en este día.</p>
</section>
@empty
<p class="text-muted border rounded p-3 mb-0">Todavía no hay documentos.</p>
@endforelse
</div>
