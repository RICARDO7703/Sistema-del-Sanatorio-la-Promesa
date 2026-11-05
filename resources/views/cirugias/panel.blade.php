@if(! ($ocultarAvisoCirugia ?? false))
@foreach($cirugiasPanel->where('estado', 'En cirugía') as $enCurso)
<div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3" role="status">
    <div><strong><i class="bi bi-hospital" aria-hidden="true"></i> En cirugía · {{ $enCurso->tipo_operacion }}</strong><span class="d-block">Inicio real: {{ $enCurso->fecha_inicio_real?->format('d/m/Y H:i') ?? 'Pendiente de registrar' }} · Sin finalizar</span></div>
    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cirugia-detalle-{{ $enCurso->getKey() }}"><i class="bi bi-eye" aria-hidden="true"></i> {{ $enCurso->permiteAccion('terminar') ? 'Ver y finalizar cirugía' : 'Ver cirugía' }}</button>
</div>
@endforeach
@endif
@if(!($soloVentanasCirugia ?? false) && $cirugiasPanel->isEmpty() && !($soloLecturaCirugia ?? false) && $ingreso->estado === 'Activo' && !$ingreso->esConsultaExterna() && $permisosCirugia['agendar'])
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-primary" href="{{ route('cirugias.formulario', $ingreso) }}"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Agendar cirugía</a>
    @if($ingreso->hospitalizacion?->estado === 'Activa')
    <a class="btn btn-outline-success" href="{{ route('cirugias.formulario', [$ingreso, 'modo' => 'realizada']) }}"><i class="bi bi-clipboard2-check" aria-hidden="true"></i> Registrar cirugía ya realizada</a>
    @endif
</div>
@endif
@if(! ($soloVentanasCirugia ?? false) && $cirugiasPanel->isNotEmpty())
<section class="evolucion-card mb-3" id="cirugias-paciente-{{ $ingreso->getKey() }}">
    <div class="d-flex flex-column align-items-start gap-3 mb-3">
        <div><h2 class="h4 mb-1"><i class="bi bi-hospital text-primary" aria-hidden="true"></i> Cirugías y recuperación</h2><p class="text-muted mb-0">Programación, personal y controles después de la cirugía. Los controles se registran en las notas de enfermería.</p></div>
        @if(!($soloLecturaCirugia ?? false) && $ingreso->estado === 'Activo')
        <div class="d-flex flex-wrap gap-2">
            @if($permisosCirugia['agendar'] && !(request()->routeIs('atencion.show') && $ingreso->esConsultaExterna()))<a class="btn btn-primary" href="{{ route('cirugias.formulario', ['ingreso' => $ingreso, 'volver_valoracion' => request()->routeIs('atencion.show') ? 1 : null]) }}"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Agendar cirugía</a>
            @if($ingreso->hospitalizacion?->estado === 'Activa')<a class="btn btn-outline-success" href="{{ route('cirugias.formulario',[$ingreso,'modo'=>'realizada']) }}"><i class="bi bi-clipboard2-check" aria-hidden="true"></i> Registrar cirugía ya realizada</a>@endif
            @endif
        </div>
        @endif
    </div>
    @forelse($cirugiasPanel as $cirugiaPanel)
        <div class="cirugia-fila"><div><strong>{{ $cirugiaPanel->tipo_operacion }}</strong><span class="d-block text-muted"><i class="bi bi-calendar-event" aria-hidden="true"></i> {{ ($cirugiaPanel->fecha_programada ?? $cirugiaPanel->fecha_inicio_real)?->format('d/m/Y H:i') ?? 'Pendiente de agendar' }} @if($cirugiaPanel->fecha_fin_programada) – {{ $cirugiaPanel->fecha_fin_programada->format('H:i') }} @endif</span></div><span class="badge {{ $cirugiaPanel->estado === 'En cirugía' ? 'bg-danger' : ($cirugiaPanel->estado === 'Realizada' ? 'bg-success' : 'bg-secondary') }}">{{ $cirugiaPanel->estado }}</span><button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cirugia-detalle-{{ $cirugiaPanel->getKey() }}"><i class="bi bi-eye" aria-hidden="true"></i> Ver detalles y controles @if($cirugiaPanel->controles->isNotEmpty())<span class="badge text-bg-primary ms-1">{{ $cirugiaPanel->controles->count() }}</span>@endif</button></div>
    @empty<p class="text-muted mb-0">No hay cirugías registradas para esta atención.</p>@endforelse
</section>
@endif

{{-- Las ventanas van al final de la página para que se vean aunque el panel esté dentro de una sección plegada. --}}
@push('modales')
@foreach($cirugiasPanel as $cirugiaPanel)
@include('cirugias.modal', ['cirugia' => $cirugiaPanel->setRelation('ingreso', $ingreso)])
@endforeach
@endpush
@php
    $cirugiaPorAbrir = $cirugiasPanel->first(fn ($cirugiaPanel) => (int) session('abrir_cirugia') === $cirugiaPanel->getKey() || old('_formulario') === 'cirugia-'.$cirugiaPanel->getKey());
@endphp
@if($cirugiaPorAbrir)
@push('modales')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ventana = document.getElementById(@json('cirugia-detalle-'.$cirugiaPorAbrir->getKey()));
    if (ventana) bootstrap.Modal.getOrCreateInstance(ventana).show();
});
</script>
@endpush
@endif
