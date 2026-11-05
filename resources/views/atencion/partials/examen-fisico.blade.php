@php
    $examen = $registro?->examen_fisico ?? [];
    $recuperar = old('_formulario') === $formulario;
    $valorExamen = fn ($campo) => $recuperar ? old('examen_fisico.'.$campo) : ($examen[$campo] ?? '');
    // En todas las hojas el examen por regiones se despliega con la flecha y los signos se eligen tocándolos.
    // Mismo orden en todas las hojas: PA, FC, FR, temperatura y saturación. La talla y el peso son datos del paciente.
    // [botón, nombre, unidad, valor de ejemplo]
    $signos = [
        'presion_arterial' => ['PA', 'Presión arterial', 'mmHg', '120/80'],
        'frecuencia_cardiaca' => ['FC', 'Frecuencia cardíaca', 'lpm', '80'],
        'frecuencia_respiratoria' => ['FR', 'Frecuencia respiratoria', 'rpm', '18'],
        'temperatura' => ['Temp.', 'Temperatura', '°C', '36.5'],
        'saturacion_oxigeno' => ['SpO₂', 'Saturación de oxígeno', '%', '98'],
    ];
    $numericos = ['frecuencia_cardiaca' => [1, 300, 1], 'frecuencia_respiratoria' => [1, 100, 1], 'temperatura' => [25, 50, 0.1], 'saturacion_oxigeno' => [0, 100, 1]];
    $tieneValor = fn ($campo) => filled($valorExamen($campo));
    $regionesConDatos = collect(['cabeza', 'tronco', 'extremidades'])->contains(fn ($campo) => filled($valorExamen($campo))) || filled($recuperar ? old('evaluacion_medica') : $registro?->evaluacion_medica);
@endphp
{{-- La descripción por regiones va antes que los signos vitales. --}}
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
        @foreach($signos as $campo => [$corto])
        <input type="checkbox" class="btn-check" id="{{ $formulario }}-elegir-{{ $campo }}" data-elegir-signo="{{ $campo }}" autocomplete="off" @checked($tieneValor($campo))>
        <label class="btn btn-outline-primary" for="{{ $formulario }}-elegir-{{ $campo }}">{{ $corto }}</label>
        @endforeach
    </div>
    <div class="signos-grid">
        @foreach($signos as $campo => [$corto, $nombre, $unidad, $ejemplo])
        <div data-signo="{{ $campo }}" @unless($tieneValor($campo)) hidden @endunless>
            <label class="form-label" for="{{ $formulario }}-{{ $campo }}">{{ $nombre }} <span class="text-muted fw-normal">({{ $unidad }})</span></label>
            @if($campo === 'presion_arterial')
            <input id="{{ $formulario }}-{{ $campo }}" class="form-control" type="text" name="examen_fisico[presion_arterial]" data-presion-arterial inputmode="numeric" autocomplete="off" placeholder="{{ $ejemplo }}" pattern="\s*[0-9]{2,3}\s*/\s*[0-9]{2,3}\s*" maxlength="15" title="Escriba ambas cifras separadas por /, por ejemplo 120/80" data-mensaje-formato="Escriba la presión con el formato 120/80." value="{{ $valorExamen('presion_arterial') }}">
            @else
            <input id="{{ $formulario }}-{{ $campo }}" class="form-control" type="number" name="examen_fisico[{{ $campo }}]" min="{{ $numericos[$campo][0] }}" max="{{ $numericos[$campo][1] }}" step="{{ $numericos[$campo][2] }}" placeholder="{{ $ejemplo }}" value="{{ $valorExamen($campo) }}">
            @endif
            @error('examen_fisico.'.$campo)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        @endforeach
    </div>
    {{-- Hojas antiguas con talla y peso: se conservan al corregirlas aunque ya no se pidan aquí. --}}
    @if($registro)
    @foreach(['talla', 'peso'] as $medidaAnterior)
    @if(filled($examen[$medidaAnterior] ?? null))
    <input type="hidden" name="examen_fisico[{{ $medidaAnterior }}]" value="{{ $medidaAnterior === 'peso' ? \App\Services\ExamenFisico::pesoEnLibras($examen) : $examen[$medidaAnterior] }}">
    @endif
    @endforeach
    @endif
</fieldset>
