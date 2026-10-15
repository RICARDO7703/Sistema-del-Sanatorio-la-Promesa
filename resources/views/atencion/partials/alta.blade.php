@if($ingreso->altaMedica)
<div class="evolucion-card"><h4>Alta médica registrada</h4><p>{{ $ingreso->altaMedica->fecha_hora->format('d/m/Y H:i') }}</p><p><strong>Diagnóstico final:</strong> {{ $ingreso->altaMedica->diagnostico_final }}</p><p><strong>Condición de egreso:</strong> {{ $ingreso->altaMedica->condicion_egreso }}</p><p><strong>Indicaciones de alta:</strong> {{ $ingreso->altaMedica->indicaciones_alta }}</p><p>{{ $ingreso->altaMedica->observaciones }}</p></div>
@else
<div class="evolucion-card">
    <h4>Alta médica</h4>
    <p>El alta cierra el ingreso y la hospitalización, libera la habitación y finaliza los tratamientos activos de este ingreso.</p>
    <form method="POST" action="{{ route('alta.guardar', $ingreso) }}">
        @csrf
        <input type="hidden" name="_formulario" value="alta">
        @php($recuperarAlta = old('_formulario') === 'alta')
        <label class="form-label" for="alta-medico">Médico responsable del alta</label><select id="alta-medico" class="form-select mb-3" name="id_personal_medico" required><option value="">Seleccione un médico</option>@foreach($medicos as $medico)<option value="{{ $medico->id_personal }}" @selected(($recuperarAlta ? old('id_personal_medico') : null) == $medico->id_personal)>{{ $medico->nombres }} {{ $medico->apellidos }}</option>@endforeach</select>
        <label class="form-label" for="alta-fecha">Fecha y hora</label><input id="alta-fecha" class="form-control mb-3" name="fecha_hora" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ ($recuperarAlta ? old('fecha_hora') : now()->format('Y-m-d\TH:i')) }}" required>
        @foreach(['diagnostico_final'=>['Diagnóstico final',2000,true], 'condicion_egreso'=>['Condición de egreso',150,true], 'indicaciones_alta'=>['Indicaciones de alta',2000,false], 'observaciones'=>['Observaciones',2000,false]] as $campo=>$config)
        <label class="form-label" for="alta-{{ $campo }}">{{ $config[0] }}</label><textarea id="alta-{{ $campo }}" class="form-control mb-3" name="{{ $campo }}" maxlength="{{ $config[1] }}" @required($config[2])>{{ $recuperarAlta ? old($campo) : '' }}</textarea>
        @endforeach
        <button class="btn btn-success">Registrar alta médica</button>
    </form>
</div>

@endif
