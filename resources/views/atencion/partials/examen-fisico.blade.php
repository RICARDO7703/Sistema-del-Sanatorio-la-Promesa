@php
    $examen = $registro?->examen_fisico ?? [];
    $recuperar = old('_formulario') === $formulario;
    $valorExamen = fn ($campo) => $recuperar ? old('examen_fisico.'.$campo) : ($campo === 'peso' ? (\App\Services\ExamenFisico::pesoEnLibras($examen) ?? '') : ($examen[$campo] ?? ''));
@endphp
<fieldset class="border rounded p-3 mb-3">
    <legend class="float-none w-auto px-2 fs-5">Examen físico</legend>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="{{ $formulario }}-fc">Frecuencia cardíaca (FC, lpm)</label>
            <input id="{{ $formulario }}-fc" class="form-control" type="number" name="examen_fisico[frecuencia_cardiaca]" min="1" max="300" step="1" value="{{ $valorExamen('frecuencia_cardiaca') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="{{ $formulario }}-pa">Presión arterial (PA, mmHg)</label>
            <input id="{{ $formulario }}-pa" class="form-control" type="text" name="examen_fisico[presion_arterial]" data-presion-arterial inputmode="numeric" autocomplete="off" placeholder="120/80" pattern="\s*[0-9]{2,3}\s*/\s*[0-9]{2,3}\s*" maxlength="15" title="Escriba ambas cifras separadas por /, por ejemplo 120/80" value="{{ $valorExamen('presion_arterial') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="{{ $formulario }}-fr">Frecuencia respiratoria (FR, rpm)</label>
            <input id="{{ $formulario }}-fr" class="form-control" type="number" name="examen_fisico[frecuencia_respiratoria]" min="1" max="100" step="1" value="{{ $valorExamen('frecuencia_respiratoria') }}">
        </div>
        @foreach(['talla'=>['Talla (cm)',0.01,999.99,0.01], 'peso'=>['Peso (lb)',0.01,999.99,0.01], 'temperatura'=>['Temperatura (°C)',25,50,0.1], 'saturacion_oxigeno'=>['Saturación de oxígeno (%)',0,100,1]] as $campo=>$config)
        <div class="col-md-3">
            <label class="form-label" for="{{ $formulario }}-{{ $campo }}">{{ $config[0] }}</label>
            <input id="{{ $formulario }}-{{ $campo }}" class="form-control" type="number" name="examen_fisico[{{ $campo }}]" min="{{ $config[1] }}" max="{{ $config[2] }}" step="{{ $config[3] }}" value="{{ $valorExamen($campo) }}">
        </div>
        @endforeach
        <div class="col-12"><h6 class="mt-2 mb-0">Descripción por regiones</h6></div>
        @foreach(['cabeza'=>'Cabeza', 'tronco'=>'Tronco', 'extremidades'=>'Extremidades'] as $campo=>$etiqueta)
        <div class="col-md-4">
            <label class="form-label" for="{{ $formulario }}-{{ $campo }}">{{ $etiqueta }}</label>
            <textarea id="{{ $formulario }}-{{ $campo }}" class="form-control" name="examen_fisico[{{ $campo }}]" rows="4" maxlength="2000" placeholder="Describa los hallazgos observados">{{ $valorExamen($campo) }}</textarea>
        </div>
        @endforeach
        <div class="col-12">
            <label class="form-label" for="{{ $formulario }}-evaluacion">Descripción adicional del examen físico</label>
            <textarea id="{{ $formulario }}-evaluacion" class="form-control" name="evaluacion_medica" rows="2" maxlength="2000">{{ $recuperar ? old('evaluacion_medica') : $registro?->evaluacion_medica }}</textarea>
        </div>
    </div>
</fieldset>
