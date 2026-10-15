@php
    $asignaciones = $ingreso->hospitalizacion?->historialHabitaciones ?? collect();
    $asignacionVigente = $asignaciones->first(fn ($asignacion) => $asignacion->fecha_hora_fin === null);
    $asignacionMostrar = $asignacionVigente ?? $asignaciones->last();
@endphp
<div class="mb-3">
    <dl class="mb-0">
        <dt class="text-muted fw-normal">{{ $asignacionVigente ? 'Habitación actual' : ($asignacionMostrar ? 'Última habitación ocupada' : 'Habitación') }}</dt>
        <dd class="mb-0">{{ $asignacionMostrar?->habitacion?->numero_habitacion ?? 'Sin habitación asignada' }}@if($asignacionMostrar?->habitacion?->tipo) <span class="text-muted">· {{ $asignacionMostrar->habitacion->tipo }}</span>@endif</dd>
    </dl>
    @if($asignaciones->count() > 1)
    <details class="mt-2">
        <summary>Cambios de habitación</summary>
        <div class="table-responsive mt-2">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Habitación</th><th>Desde</th><th>Hasta</th></tr></thead>
                <tbody>
                    @foreach($asignaciones as $asignacion)
                    <tr><td>{{ $asignacion->habitacion?->numero_habitacion ?? 'Sin registro' }}</td><td>{{ $asignacion->fecha_hora_inicio->format('d/m/Y H:i') }}</td><td>{{ $asignacion->fecha_hora_fin?->format('d/m/Y H:i') ?? 'Actual' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
    @endif
</div>
