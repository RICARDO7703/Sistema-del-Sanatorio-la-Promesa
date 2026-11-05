<div class="evolucion-card">
    <h4>Tratamientos</h4>
    <form method="POST" action="{{ route('tratamientos.guardar') }}">
        @csrf
        <input type="hidden" name="id_ingreso" value="{{ $ingreso->id_ingreso }}">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="tipo-tratamiento">Tipo de tratamiento</label><select id="tipo-tratamiento" class="form-select" name="tipo_tratamiento">@foreach(['Medicamento','No farmacológico'] as $tipo)<option @selected(old('tipo_tratamiento') === $tipo)>{{ $tipo }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="tratamiento-medicamento">Medicamento</label><select id="tratamiento-medicamento" name="id_medicamento" class="form-select"><option value="">Seleccione para tratamiento farmacológico</option>@foreach($medicamentos as $medicamento)<option value="{{ $medicamento->id_medicamento }}" @selected(old('id_medicamento') == $medicamento->id_medicamento)>{{ $medicamento->nombre_medicamento }} {{ $medicamento->presentacion }} {{ $medicamento->concentracion }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="tratamiento-evolucion">Evolución relacionada (opcional)</label><select id="tratamiento-evolucion" class="form-select" name="id_evolucion"><option value="">Sin vincular</option>@foreach($ingreso->evoluciones as $evolucion)<option value="{{ $evolucion->id_evolucion }}" @selected(old('id_evolucion') == $evolucion->id_evolucion)>{{ $evolucion->fecha_hora->format('d/m/Y H:i') }} — {{ $evolucion->motivo_atencion }}</option>@endforeach</select></div>
            @foreach(['descripcion_tratamiento'=>['Descripción',255], 'dosis'=>['Dosis',100], 'via_administracion'=>['Vía de administración',80], 'frecuencia'=>['Frecuencia',100], 'duracion'=>['Duración',100], 'indicaciones'=>['Indicaciones',2000]] as $campo=>$config)
            <div class="col-md-6"><label class="form-label" for="tratamiento-{{ $campo }}">{{ $config[0] }}</label><input id="tratamiento-{{ $campo }}" class="form-control" name="{{ $campo }}" maxlength="{{ $config[1] }}" value="{{ old($campo) }}"></div>
            @endforeach
            @foreach(['fecha_indicacion'=>'Fecha de indicación', 'fecha_inicio'=>'Inicio (opcional)', 'fecha_fin'=>'Fin (opcional)'] as $campo=>$label)
            <div class="col-md-4"><label class="form-label" for="tratamiento-{{ $campo }}">{{ $label }}</label><input id="tratamiento-{{ $campo }}" type="datetime-local" class="form-control" name="{{ $campo }}" value="{{ old($campo, $campo === 'fecha_indicacion' ? now('America/Guatemala')->format('Y-m-d\TH:i') : '') }}" @required($campo === 'fecha_indicacion')></div>
            @endforeach
        </div>
        <button class="btn btn-primary mt-3">Registrar tratamiento</button>
    </form>
    <div class="table-responsive mt-4"><table class="table"><thead><tr><th>Indicación</th><th>Tratamiento</th><th>Dosis / vía / frecuencia</th><th>Indicaciones</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
    @forelse($ingreso->tratamientos as $tratamiento)
    <tr><td>{{ $tratamiento->fecha_indicacion->format('d/m/Y H:i') }}</td><td>{{ $tratamiento->medicamento?->nombre_medicamento ?? $tratamiento->descripcion_tratamiento }}</td><td>{{ $tratamiento->dosis }} / {{ $tratamiento->via_administracion }} / {{ $tratamiento->frecuencia }}</td><td>{{ $tratamiento->indicaciones }}</td><td>{{ $tratamiento->estado }}</td><td>@if($tratamiento->estado === 'Activo')<form method="POST" action="{{ route('tratamientos.eliminar', $tratamiento) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Cancelar tratamiento</button></form>@endif</td></tr>
    @empty<tr><td colspan="6">Sin tratamientos registrados.</td></tr>@endforelse
    </tbody></table></div>
@foreach($ingreso->tratamientos as $tratamiento)
@if($tratamiento->estado === 'Activo')

<details class="border rounded p-3 mt-3"><summary>Editar tratamiento #{{ $tratamiento->getKey() }}</summary>
    <form method="POST" action="{{ route('tratamientos.actualizar', $tratamiento) }}">
        @csrf @method('PUT')
        <input type="hidden" name="id_ingreso" value="{{ $ingreso->id_ingreso }}">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="tipo-tratamiento-{{ $tratamiento->getKey() }}">Tipo de tratamiento</label><select id="tipo-tratamiento-{{ $tratamiento->getKey() }}" class="form-select" name="tipo_tratamiento">@foreach(['Medicamento','No farmacológico'] as $tipo)<option @selected($tratamiento->tipo_tratamiento === $tipo)>{{ $tipo }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="tratamiento-medicamento-{{ $tratamiento->getKey() }}">Medicamento</label><select id="tratamiento-medicamento-{{ $tratamiento->getKey() }}" name="id_medicamento" class="form-select"><option value="">Seleccione para tratamiento farmacológico</option>@foreach($medicamentos as $medicamento)<option value="{{ $medicamento->id_medicamento }}" @selected($tratamiento->id_medicamento == $medicamento->id_medicamento)>{{ $medicamento->nombre_medicamento }} {{ $medicamento->presentacion }} {{ $medicamento->concentracion }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="tratamiento-evolucion-{{ $tratamiento->getKey() }}">Evolución relacionada (opcional)</label><select id="tratamiento-evolucion-{{ $tratamiento->getKey() }}" class="form-select" name="id_evolucion"><option value="">Sin vincular</option>@foreach($ingreso->evoluciones as $evolucion)<option value="{{ $evolucion->id_evolucion }}" @selected($tratamiento->id_evolucion == $evolucion->id_evolucion)>{{ $evolucion->fecha_hora->format('d/m/Y H:i') }} — {{ $evolucion->motivo_atencion }}</option>@endforeach</select></div>
            @foreach(['descripcion_tratamiento'=>['Descripción',255], 'dosis'=>['Dosis',100], 'via_administracion'=>['Vía de administración',80], 'frecuencia'=>['Frecuencia',100], 'duracion'=>['Duración',100], 'indicaciones'=>['Indicaciones',2000]] as $campo=>$config)
            <div class="col-md-6"><label class="form-label" for="tratamiento-{{ $campo }}-{{ $tratamiento->getKey() }}">{{ $config[0] }}</label><input id="tratamiento-{{ $campo }}-{{ $tratamiento->getKey() }}" class="form-control" name="{{ $campo }}" maxlength="{{ $config[1] }}" value="{{ $tratamiento->{$campo} }}"></div>
            @endforeach
            @foreach(['fecha_indicacion'=>'Fecha de indicación', 'fecha_inicio'=>'Inicio (opcional)', 'fecha_fin'=>'Fin (opcional)'] as $campo=>$label)
            <div class="col-md-4"><label class="form-label" for="tratamiento-{{ $campo }}-{{ $tratamiento->getKey() }}">{{ $label }}</label><input id="tratamiento-{{ $campo }}-{{ $tratamiento->getKey() }}" type="datetime-local" class="form-control" name="{{ $campo }}" value="{{ $tratamiento->{$campo}?->format('Y-m-d\TH:i') }}" @required($campo === 'fecha_indicacion')></div>
            @endforeach
        </div>
        <button class="btn btn-primary mt-3">Confirmar cambios</button>
    </form>
</details>

@endif
@endforeach
</div>
