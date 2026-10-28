<section class="alert alert-primary mb-4" aria-label="Atención anterior">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><strong>Seguimiento de la atención #{{ $anterior->getKey() }}</strong><p class="mb-0">{{ $anterior->fecha_ingreso->format('d/m/Y H:i') }} · {{ $anterior->nombreTipoAtencion() }}</p></div>
        <a href="{{ route('atencion.show', $anterior) }}" class="btn btn-outline-primary">Ver consulta anterior</a>
    </div>
    @if($anterior->altaMedica)
    <hr>
    <dl class="row g-3 mb-0">
        <div class="col-md-6"><dt>Diagnóstico anterior</dt><dd class="mb-0 text-break">{{ $anterior->altaMedica->diagnostico_final }}</dd></div>
        <div class="col-md-6"><dt>Indicaciones anteriores</dt><dd class="mb-0 text-break">{{ $anterior->altaMedica->indicaciones_alta ?: 'Sin indicaciones registradas' }}</dd></div>
        @if($anterior->altaMedica->medicamentos->isNotEmpty())
        <div class="col-12"><dt>Medicamentos indicados en esa visita</dt><dd class="mb-0"><ul class="mb-0 ps-3">@foreach($anterior->altaMedica->medicamentos as $medicamento)<li class="text-break">{{ $medicamento->medicamento }} · {{ $medicamento->dosis }} · {{ $medicamento->frecuencia }}@if($medicamento->duracion) · {{ $medicamento->duracion }}@endif</li>@endforeach</ul></dd></div>
        @endif
    </dl>
    @endif
</section>
