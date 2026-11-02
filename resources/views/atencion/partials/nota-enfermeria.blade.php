@php
    $signosNota = $nota->signosVitales;
    $medidasNota = $signosNota ? array_filter([
        'PA' => $signosNota->presion_sistolica ? $signosNota->presion_sistolica.'/'.$signosNota->presion_diastolica.' mmHg' : null,
        'FC' => $signosNota->frecuencia_cardiaca ? $signosNota->frecuencia_cardiaca.' lpm' : null,
        'FR' => $signosNota->frecuencia_respiratoria ? $signosNota->frecuencia_respiratoria.' rpm' : null,
        'Temp.' => $signosNota->temperatura !== null ? $signosNota->temperatura.' °C' : null,
        'SpO₂' => $signosNota->saturacion_oxigeno !== null ? $signosNota->saturacion_oxigeno.' %' : null,
    ]) : [];
    $actividadesNota = $nota->listaActividades();
@endphp
@if($medidasNota)
<div class="d-flex flex-wrap gap-2 mb-2" aria-label="Signos vitales">
    @foreach($medidasNota as $sigla => $valor)
    <span class="badge text-bg-light border fs-6 fw-normal">{{ $sigla }} {{ $valor }}</span>
    @endforeach
</div>
@endif
@if($actividadesNota)
<p class="mb-1 text-break"><strong>Actividades:</strong> {{ implode(' · ', $actividadesNota) }}</p>
@endif
@if(filled($nota->observaciones))
<p class="mb-1 text-break" style="white-space: pre-wrap">{{ $nota->observaciones }}</p>
@endif
@if($nota->aviso_medico)
<p class="mb-0 text-danger-emphasis"><i class="bi bi-telephone" aria-hidden="true"></i> <span>Se avisó al médico</span>@if($nota->medicoAvisado)<span>: <strong>{{ $nota->medicoAvisado->nombres }} {{ $nota->medicoAvisado->apellidos }}</strong></span>@endif</p>
@endif
