<div class="border rounded p-3 mb-3">
    <h5>Examen físico</h5>
    <dl class="row mb-0">
        @foreach(['frecuencia_cardiaca'=>'Frecuencia cardíaca (FC, lpm)', 'presion_arterial'=>'Presión arterial (PA, mmHg)', 'frecuencia_respiratoria'=>'Frecuencia respiratoria (FR, rpm)', 'talla'=>'Talla (cm)', 'peso'=>'Peso (lb)', 'temperatura'=>'Temperatura (°C)', 'saturacion_oxigeno'=>'Saturación de oxígeno (%)', 'cabeza'=>'Cabeza', 'tronco'=>'Tronco', 'extremidades'=>'Extremidades'] as $campo=>$etiqueta)
        <div class="col-md-4 mb-2"><dt>{{ $etiqueta }}</dt><dd class="mb-0" style="white-space: pre-wrap">{{ ($campo === 'peso' ? \App\Services\ExamenFisico::pesoEnLibras($registro->examen_fisico) : ($registro->examen_fisico[$campo] ?? null)) ?? 'Sin registro' }}</dd></div>
        @endforeach
        @if($registro->evaluacion_medica)
        <div class="col-12"><dt>Descripción adicional</dt><dd style="white-space: pre-wrap">{{ $registro->evaluacion_medica }}</dd></div>
        @endif
    </dl>
</div>
