@if($cirugiasPanel->isNotEmpty() || (!($soloLecturaCirugia ?? false) && $ingreso->estado === 'Activo'))
<section class="evolucion-card mb-3" id="cirugias-paciente-{{ $ingreso->getKey() }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div><h2 class="h4 mb-1"><i class="bi bi-hospital text-primary" aria-hidden="true"></i> Cirugías y recuperación</h2><p class="text-muted mb-0">Programación, personal y controles después de la cirugía.</p></div>
        @if(!($soloLecturaCirugia ?? false) && $ingreso->estado === 'Activo')
        <div class="d-flex flex-wrap gap-2">
            @if($permisosCirugia['agendar'])<a class="btn btn-primary" href="{{ route('cirugias.formulario',$ingreso) }}"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Agendar cirugía</a><a class="btn btn-outline-success" href="{{ route('cirugias.formulario',[$ingreso,'modo'=>'realizada']) }}"><i class="bi bi-clipboard2-check" aria-hidden="true"></i> Registrar cirugía ya realizada</a>@endif
        </div>
        @endif
    </div>
    @forelse($cirugiasPanel as $cirugiaPanel)
        <div class="cirugia-fila"><div><strong>{{ $cirugiaPanel->tipo_operacion }}</strong><span class="d-block text-muted"><i class="bi bi-calendar-event" aria-hidden="true"></i> {{ ($cirugiaPanel->fecha_programada ?? $cirugiaPanel->fecha_inicio_real)?->format('d/m/Y H:i') ?? 'Pendiente de agendar' }} @if($cirugiaPanel->fecha_fin_programada) – {{ $cirugiaPanel->fecha_fin_programada->format('H:i') }} @endif</span></div><span class="badge {{ $cirugiaPanel->estado === 'En cirugía' ? 'bg-danger' : ($cirugiaPanel->estado === 'Realizada' ? 'bg-success' : 'bg-secondary') }}">{{ $cirugiaPanel->estado }}</span><a class="btn btn-outline-primary" href="{{ route('cirugias.show',$cirugiaPanel) }}"><i class="bi bi-eye" aria-hidden="true"></i> Ver detalles y controles</a></div>
    @empty<p class="text-muted mb-0">No hay cirugías registradas para esta atención.</p>@endforelse
</section>
@endif
