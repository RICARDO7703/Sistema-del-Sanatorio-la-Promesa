@php
    $examen = $registro?->examen_fisico ?? [];
    $recuperar = old('_formulario') === $formulario;
    $valoresIniciales = $valoresIniciales ?? [];
    $valorExamen = fn ($campo) => $recuperar ? old('examen_fisico.'.$campo) : ($campo === 'peso' ? (\App\Services\ExamenFisico::pesoEnLibras($examen) ?? ($valoresIniciales['peso'] ?? '')) : ($examen[$campo] ?? ($valoresIniciales[$campo] ?? '')));
    $signosSeleccionables = $signosSeleccionables ?? false;
    $signos = [
        'frecuencia_cardiaca' => ['FC', 'Frecuencia cardíaca (FC, lpm)'],
        'presion_arterial' => ['PA', 'Presión arterial (PA, mmHg)'],
        'frecuencia_respiratoria' => ['FR', 'Frecuencia respiratoria (FR, rpm)'],
        'talla' => ['Talla', 'Talla (cm)'],
        'peso' => ['Peso', 'Peso (lb)'],
        'temperatura' => ['Temp.', 'Temperatura (°C)'],
        'saturacion_oxigeno' => ['SpO₂', 'Saturación de oxígeno (%)'],
    ];
    $numericos = ['frecuencia_cardiaca' => [1, 300, 1], 'frecuencia_respiratoria' => [1, 100, 1], 'talla' => [0.01, 999.99, 0.01], 'peso' => [0.01, 999.99, 0.01], 'temperatura' => [25, 50, 0.1], 'saturacion_oxigeno' => [0, 100, 1]];
    $tieneValor = fn ($campo) => filled($valorExamen($campo));
    $regionesConDatos = collect(['cabeza', 'tronco', 'extremidades'])->contains(fn ($campo) => filled($valorExamen($campo))) || filled($recuperar ? old('evaluacion_medica') : $registro?->evaluacion_medica);
@endphp
{{-- La descripción por regiones va antes que los signos vitales. --}}
@if($signosSeleccionables)
<fieldset class="border rounded p-3 mb-3">
    <legend class="float-none w-auto px-2 fs-5">Examen por regiones (opcional)</legend>
    <details @if($regionesConDatos) open @endif><summary>Escribir la descripción por regiones</summary>
    <div class="row g-3">
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
    </details>
</fieldset>
<fieldset class="border rounded p-3 mb-3" data-signos-seleccionables>
    <legend class="float-none w-auto px-2 fs-5">Signos vitales (opcional)</legend>
    <p class="text-muted small mb-2">Toque los signos que va a tomar.</p>
    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Signos vitales a registrar">
        @foreach($signos as $campo => [$corto, $largo])
        <input type="checkbox" class="btn-check" id="{{ $formulario }}-elegir-{{ $campo }}" data-elegir-signo="{{ $campo }}" autocomplete="off" @checked($tieneValor($campo))>
        <label class="btn btn-outline-primary" for="{{ $formulario }}-elegir-{{ $campo }}">{{ $corto }}</label>
        @endforeach
    </div>
    <div class="row g-3">
        @foreach($signos as $campo => [$corto, $largo])
        <div class="{{ in_array($campo, ['presion_arterial', 'frecuencia_cardiaca', 'frecuencia_respiratoria'], true) ? 'col-md-4' : 'col-md-3' }}" @if($signosSeleccionables) data-signo="{{ $campo }}" @unless($tieneValor($campo)) hidden @endunless @endif>
            <label class="form-label" for="{{ $formulario }}-{{ $campo }}">{{ $largo }}</label>
            @if($campo === 'presion_arterial')
            <input id="{{ $formulario }}-{{ $campo }}" class="form-control" type="text" name="examen_fisico[presion_arterial]" data-presion-arterial inputmode="numeric" autocomplete="off" placeholder="120/80" pattern="\s*[0-9]{2,3}\s*/\s*[0-9]{2,3}\s*" maxlength="15" title="Escriba ambas cifras separadas por /, por ejemplo 120/80" data-mensaje-formato="Escriba la presión con el formato 120/80." value="{{ $valorExamen('presion_arterial') }}">
            @else
            <input id="{{ $formulario }}-{{ $campo }}" class="form-control" type="number" name="examen_fisico[{{ $campo }}]" min="{{ $numericos[$campo][0] }}" max="{{ $numericos[$campo][1] }}" step="{{ $numericos[$campo][2] }}" value="{{ $valorExamen($campo) }}">
            @endif
            @if(! $recuperar && ! $registro && filled($valoresIniciales[$campo] ?? null))
            <small class="text-muted">Tomado del registro anterior; corríjalo si cambió.</small>
            @endif
            @if($signosSeleccionables)
            @error('examen_fisico.'.$campo)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @endif
        </div>
        @endforeach
    </div>
</fieldset>
@else
<fieldset class="border rounded p-3 mb-3">
    <legend class="float-none w-auto px-2 fs-5">Examen físico</legend>
    <div class="row g-3">
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
    <h6 class="mt-4 mb-3">Signos vitales</h6>
    <div class="row g-3">
        @foreach($signos as $campo => [$corto, $largo])
        <div class="{{ in_array($campo, ['presion_arterial', 'frecuencia_cardiaca', 'frecuencia_respiratoria'], true) ? 'col-md-4' : 'col-md-3' }}" @if($signosSeleccionables) data-signo="{{ $campo }}" @unless($tieneValor($campo)) hidden @endunless @endif>
            <label class="form-label" for="{{ $formulario }}-{{ $campo }}">{{ $largo }}</label>
            @if($campo === 'presion_arterial')
            <input id="{{ $formulario }}-{{ $campo }}" class="form-control" type="text" name="examen_fisico[presion_arterial]" data-presion-arterial inputmode="numeric" autocomplete="off" placeholder="120/80" pattern="\s*[0-9]{2,3}\s*/\s*[0-9]{2,3}\s*" maxlength="15" title="Escriba ambas cifras separadas por /, por ejemplo 120/80" data-mensaje-formato="Escriba la presión con el formato 120/80." value="{{ $valorExamen('presion_arterial') }}">
            @else
            <input id="{{ $formulario }}-{{ $campo }}" class="form-control" type="number" name="examen_fisico[{{ $campo }}]" min="{{ $numericos[$campo][0] }}" max="{{ $numericos[$campo][1] }}" step="{{ $numericos[$campo][2] }}" value="{{ $valorExamen($campo) }}">
            @endif
            @if(! $recuperar && ! $registro && filled($valoresIniciales[$campo] ?? null))
            <small class="text-muted">Tomado del registro anterior; corríjalo si cambió.</small>
            @endif
            @if($signosSeleccionables)
            @error('examen_fisico.'.$campo)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @endif
        </div>
        @endforeach
    </div>
</fieldset>
@endif
