<div class="border-top pt-3">
    <h6 class="mb-3">Documentos del ingreso</h6>
    <div class="list-group">
        <button type="button" class="list-group-item list-group-item-action d-flex flex-wrap align-items-center justify-content-between gap-2" data-bs-toggle="modal" data-bs-target="#documento-ingreso-{{ $ingreso->getKey() }}">
            <span><i class="bi bi-file-earmark-text text-primary me-2"></i>Hoja de ingreso</span>
            <span class="small text-muted">{{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }} <i class="bi bi-eye ms-2"></i></span>
        </button>
        @foreach($ingreso->evoluciones as $registroDocumento)
        <button type="button" class="list-group-item list-group-item-action d-flex flex-wrap align-items-center justify-content-between gap-2" data-bs-toggle="modal" data-bs-target="#documento-evolucion-{{ $registroDocumento->getKey() }}">
            <span><i class="bi bi-file-earmark-medical text-primary me-2"></i>{{ $loop->first && $registroDocumento->ordenes_medicas === null ? 'Valoración inicial' : 'Evolución clínica #'.$registroDocumento->getKey() }}</span>
            <span class="small text-muted">{{ $registroDocumento->personal?->nombres }} {{ $registroDocumento->personal?->apellidos }} · {{ $registroDocumento->fecha_hora->format('d/m/Y H:i') }} <i class="bi bi-eye ms-2"></i></span>
        </button>
        @endforeach
        @if($ingreso->altaMedica)
        <button type="button" class="list-group-item list-group-item-action d-flex flex-wrap align-items-center justify-content-between gap-2" data-bs-toggle="modal" data-bs-target="#documento-alta-{{ $ingreso->getKey() }}">
            <span><i class="bi bi-file-earmark-check text-success me-2"></i>Alta médica</span>
            <span class="small text-muted">{{ $ingreso->altaMedica->medico?->nombres }} {{ $ingreso->altaMedica->medico?->apellidos }} · {{ $ingreso->altaMedica->fecha_hora->format('d/m/Y H:i') }} <i class="bi bi-eye ms-2"></i></span>
        </button>
        @endif
    </div>
</div>
