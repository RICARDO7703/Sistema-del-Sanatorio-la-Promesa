{{-- Etiquetas de la situación actual del paciente; las comparten el listado de pacientes y el de expedientes. --}}
@if($paciente->cirugiaActual && $paciente->cirugiaActual->estado !== 'En cirugía')
<div class="mb-2">
<button class="badge text-bg-primary border-0" type="button" data-bs-toggle="modal" data-bs-target="#cirugia-detalle-{{ $paciente->cirugiaActual->getKey() }}" @disabled(! app(\App\Support\Permisos::class)->puede('Cirugías', 'Ver'))><i class="bi bi-calendar-event" aria-hidden="true"></i> Cirugía programada</button>
</div>
@endif
@if($paciente->situacion_actual === 'En cirugía')
    <button class="badge bg-danger border-0" type="button" data-bs-toggle="modal" data-bs-target="#cirugia-detalle-{{ $paciente->cirugiaActual?->getKey() }}" @disabled(! $paciente->cirugiaActual || ! app(\App\Support\Permisos::class)->puede('Cirugías', 'Ver'))><i class="bi bi-hospital" aria-hidden="true"></i> En cirugía</button>
@elseif(
    $paciente->situacion_actual
    === 'Hospitalizado'
)

    <span class="badge bg-danger">
        Hospitalizado
    </span>

@elseif(
    $paciente->situacion_actual
    === 'En consulta'
)

    <span class="badge bg-consulta">
        En consulta
    </span>

@elseif(
    $paciente->situacion_actual
    === 'En atención'
)

    <span class="badge bg-primary">
        En atención
    </span>

@elseif(
    $paciente->situacion_actual
    === 'Dado de alta'
)

    <span class="badge bg-success">
        {{ $paciente->ultima_alta_emergencia ? 'Atención finalizada' : 'Dado de alta' }}
    </span>

@else

    <span class="badge bg-secondary">
        Sin ingresos
    </span>

@endif
