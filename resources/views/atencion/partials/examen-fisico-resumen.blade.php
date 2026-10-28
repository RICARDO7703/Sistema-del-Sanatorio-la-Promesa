@php
    $etiquetasExamen = ['cabeza'=>'Cabeza', 'tronco'=>'Tronco', 'extremidades'=>'Extremidades', 'frecuencia_cardiaca'=>'Frecuencia cardíaca (FC, lpm)', 'presion_arterial'=>'Presión arterial (PA, mmHg)', 'frecuencia_respiratoria'=>'Frecuencia respiratoria (FR, rpm)', 'talla'=>'Talla (cm)', 'peso'=>'Peso (lb)', 'temperatura'=>'Temperatura (°C)', 'saturacion_oxigeno'=>'Saturación de oxígeno (%)'];
    // Solo se muestra lo que se registró; los signos que no se tomaron no llenan el documento de "Sin registro".
    $valoresExamen = collect($etiquetasExamen)->mapWithKeys(fn ($etiqueta, $campo) => [$campo => $campo === 'peso' ? \App\Services\ExamenFisico::pesoEnLibras($registro->examen_fisico) : ($registro->examen_fisico[$campo] ?? null)])
        ->filter(fn ($valor) => filled($valor));
@endphp
<div class="border rounded p-3 mb-3">
    <h5>Examen físico</h5>
    <dl class="row mb-0">
        @forelse($valoresExamen as $campo => $valor)
        <div class="col-md-4 mb-2"><dt>{{ $etiquetasExamen[$campo] }}</dt><dd class="mb-0" style="white-space: pre-wrap">{{ $valor }}</dd></div>
        @empty
        <div class="col-12"><p class="text-muted mb-2">No se registraron signos vitales ni examen por regiones.</p></div>
        @endforelse
        @if($registro->evaluacion_medica)
        <div class="col-12"><dt>Descripción adicional</dt><dd style="white-space: pre-wrap">{{ $registro->evaluacion_medica }}</dd></div>
        @endif
    </dl>
</div>
