<div class="evolucion-card">
    <h4>Signos vitales</h4>
    <form method="POST" action="{{ route('signos.guardar') }}">
        @csrf
        <input type="hidden" name="id_ingreso" value="{{ $ingreso->id_ingreso }}">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="signos-fecha">Fecha y hora</label><input id="signos-fecha" class="form-control" type="datetime-local" name="fecha_hora" value="{{ old('fecha_hora', now()->format('Y-m-d\TH:i')) }}" required></div>
            <div class="col-md-4"><label class="form-label" for="tipo-control">Tipo de control</label><select id="tipo-control" class="form-select" name="tipo_control">@foreach(['Ingreso', 'Consulta', 'Preoperatorio', 'Recuperación', 'Rutina'] as $tipo)<option @selected(old('tipo_control') === $tipo)>{{ $tipo }}</option>@endforeach</select></div>
            @foreach(['presion_sistolica' => ['Presión sistólica (mmHg)',30,300,1], 'presion_diastolica' => ['Presión diastólica (mmHg)',20,200,1], 'frecuencia_cardiaca' => ['Frecuencia cardíaca (lpm)',1,300,1], 'frecuencia_respiratoria' => ['Frecuencia respiratoria (rpm)',1,100,1], 'temperatura' => ['Temperatura (°C)',25,50,0.1], 'saturacion_oxigeno' => ['Saturación de oxígeno (%)',0,100,1]] as $campo => $config)
            <div class="col-md-4"><label class="form-label" for="{{ $campo }}">{{ $config[0] }}</label><input id="{{ $campo }}" type="number" class="form-control" name="{{ $campo }}" min="{{ $config[1] }}" max="{{ $config[2] }}" step="{{ $config[3] }}" value="{{ old($campo) }}"></div>
            @endforeach
            <div class="col-12"><label class="form-label" for="signos-observaciones">Observaciones</label><textarea id="signos-observaciones" name="observaciones" class="form-control" maxlength="1000">{{ old('observaciones') }}</textarea></div>
        </div>
        <button class="btn btn-primary mt-3">Registrar signos vitales</button>
    </form>
    <div class="table-responsive mt-4"><table class="table"><thead><tr><th>Fecha</th><th>Control</th><th>Presión</th><th>FC</th><th>FR</th><th>°C</th><th>SpO₂</th><th>Responsable</th></tr></thead><tbody>
        @forelse($ingreso->signosVitales as $signo)
        <tr><td>{{ $signo->fecha_hora->format('d/m/Y H:i') }}</td><td>{{ $signo->tipo_control }}</td><td>{{ $signo->presion_sistolica ?? '—' }}/{{ $signo->presion_diastolica ?? '—' }}</td><td>{{ $signo->frecuencia_cardiaca ?? '—' }}</td><td>{{ $signo->frecuencia_respiratoria ?? '—' }}</td><td>{{ $signo->temperatura ?? '—' }}</td><td>{{ $signo->saturacion_oxigeno ?? '—' }}</td><td>{{ $signo->personal?->nombres }} {{ $signo->personal?->apellidos }}</td></tr>
        @empty<tr><td colspan="8">Sin signos vitales registrados.</td></tr>@endforelse
    </tbody></table></div>
@foreach($ingreso->signosVitales as $signo)


<details class="border rounded p-3 mt-3"><summary>Editar signos #{{ $signo->getKey() }}</summary>
    <form method="POST" action="{{ route('signos.actualizar', $signo) }}">
        @csrf @method('PUT')
        <input type="hidden" name="id_ingreso" value="{{ $ingreso->id_ingreso }}">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="signos-fecha-{{ $signo->getKey() }}">Fecha y hora</label><input id="signos-fecha-{{ $signo->getKey() }}" class="form-control" type="datetime-local" name="fecha_hora" value="{{ $signo->fecha_hora->format('Y-m-d\TH:i') }}" required></div>
            <div class="col-md-4"><label class="form-label" for="tipo-control-{{ $signo->getKey() }}">Tipo de control</label><select id="tipo-control-{{ $signo->getKey() }}" class="form-select" name="tipo_control">@foreach(['Ingreso', 'Consulta', 'Preoperatorio', 'Recuperación', 'Rutina'] as $tipo)<option @selected($signo->tipo_control === $tipo)>{{ $tipo }}</option>@endforeach</select></div>
            @foreach(['presion_sistolica' => ['Presión sistólica (mmHg)',30,300,1], 'presion_diastolica' => ['Presión diastólica (mmHg)',20,200,1], 'frecuencia_cardiaca' => ['Frecuencia cardíaca (lpm)',1,300,1], 'frecuencia_respiratoria' => ['Frecuencia respiratoria (rpm)',1,100,1], 'temperatura' => ['Temperatura (°C)',25,50,0.1], 'saturacion_oxigeno' => ['Saturación de oxígeno (%)',0,100,1]] as $campo => $config)
            <div class="col-md-4"><label class="form-label" for="{{ $campo }}-{{ $signo->getKey() }}">{{ $config[0] }}</label><input id="{{ $campo }}-{{ $signo->getKey() }}" type="number" class="form-control" name="{{ $campo }}" min="{{ $config[1] }}" max="{{ $config[2] }}" step="{{ $config[3] }}" value="{{ $signo->{$campo} }}"></div>
            @endforeach
            <div class="col-12"><label class="form-label" for="signos-observaciones-{{ $signo->getKey() }}">Observaciones</label><textarea id="signos-observaciones-{{ $signo->getKey() }}" name="observaciones" class="form-control" maxlength="1000">{{ $signo->observaciones }}</textarea></div>
        </div>
        <button class="btn btn-primary mt-3">Guardar cambios</button>
    </form>
</details>


@endforeach
</div>
