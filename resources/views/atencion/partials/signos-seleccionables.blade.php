{{--
    Signos vitales a elección, igual que en la consulta externa: se tocan los signos que se van a tomar
    y solo esos campos aparecen. Se usa en la nota de enfermería y en los controles de recuperación.
    Variables: $prefijo (para los id), $valor (fn (string $campo) => valor inicial), $mostrarErrores (bool).
--}}
@php
    $mostrarErrores = $mostrarErrores ?? true;
    // Mismo orden que en la valoración y la consulta: PA, FC, FR, temperatura y saturación.
    // [botón, nombre, unidad]
    $signosNota = [
        'presion' => ['PA', 'Presión arterial', 'mmHg'],
        'frecuencia_cardiaca' => ['FC', 'Frecuencia cardíaca', 'lpm'],
        'frecuencia_respiratoria' => ['FR', 'Frecuencia respiratoria', 'rpm'],
        'temperatura' => ['Temp.', 'Temperatura', '°C'],
        'saturacion_oxigeno' => ['SpO₂', 'Saturación de oxígeno', '%'],
    ];
    // [mínimo, máximo, paso, mensaje, valor de ejemplo]
    $rangos = [
        'frecuencia_cardiaca' => [1, 300, 1, 'La frecuencia cardíaca debe estar entre 1 y 300 lpm.', '80'],
        'frecuencia_respiratoria' => [1, 100, 1, 'La frecuencia respiratoria debe estar entre 1 y 100 rpm.', '18'],
        'temperatura' => [25, 50, 0.1, 'La temperatura debe estar entre 25 y 50 °C.', '36.5'],
        'saturacion_oxigeno' => [0, 100, 1, 'La saturación debe estar entre 0 y 100 %.', '98'],
    ];
    $tomado = fn (string $signo) => $signo === 'presion'
        ? filled($valor('presion_sistolica')) || filled($valor('presion_diastolica'))
        : filled($valor($signo));
@endphp
<div data-signos-seleccionables>
    <p class="text-muted small mb-2">Toque los signos que va a tomar.</p>
    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Signos vitales a registrar">
        @foreach($signosNota as $signo => [$corto])
        <input type="checkbox" class="btn-check" id="{{ $prefijo }}-elegir-{{ $signo }}" data-elegir-signo="{{ $signo }}" autocomplete="off" @checked($tomado($signo))>
        <label class="btn btn-outline-primary" for="{{ $prefijo }}-elegir-{{ $signo }}">{{ $corto }}</label>
        @endforeach
    </div>
    <div class="signos-grid">
        <div data-signo="presion" @unless($tomado('presion')) hidden @endunless>
            <span class="form-label d-block">Presión arterial <span class="text-muted fw-normal">(mmHg)</span></span>
            <div class="input-group">
                <input type="number" id="{{ $prefijo }}-presion_sistolica" name="presion_sistolica" class="form-control @if($mostrarErrores) @error('presion_sistolica') is-invalid @enderror @endif" min="30" max="300" step="1" placeholder="120" aria-label="Presión sistólica" data-requerido-con="presion_diastolica" data-mensaje-requerido="Escriba también la presión sistólica." data-mensaje-rango="La sistólica debe estar entre 30 y 300." value="{{ $valor('presion_sistolica') }}">
                <span class="input-group-text">/</span>
                <input type="number" id="{{ $prefijo }}-presion_diastolica" name="presion_diastolica" class="form-control @if($mostrarErrores) @error('presion_diastolica') is-invalid @enderror @endif" min="20" max="200" step="1" placeholder="80" aria-label="Presión diastólica" data-requerido-con="presion_sistolica" data-mensaje-requerido="Escriba también la presión diastólica." data-mensaje-rango="La diastólica debe estar entre 20 y 200." value="{{ $valor('presion_diastolica') }}">
            </div>
            @if($mostrarErrores) @error('presion_sistolica')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @error('presion_diastolica')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
        </div>
        @foreach($rangos as $campo => [$minimo, $maximo, $paso, $mensaje, $ejemplo])
        <div data-signo="{{ $campo }}" @unless($tomado($campo)) hidden @endunless>
            <label class="form-label" for="{{ $prefijo }}-{{ $campo }}">{{ $signosNota[$campo][1] }} <span class="text-muted fw-normal">({{ $signosNota[$campo][2] }})</span></label>
            <input id="{{ $prefijo }}-{{ $campo }}" type="number" name="{{ $campo }}" class="form-control @if($mostrarErrores) @error($campo) is-invalid @enderror @endif" min="{{ $minimo }}" max="{{ $maximo }}" step="{{ $paso }}" placeholder="{{ $ejemplo }}" data-mensaje-rango="{{ $mensaje }}" value="{{ $valor($campo) }}">
            @if($mostrarErrores) @error($campo)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
        </div>
        @endforeach
    </div>
</div>
