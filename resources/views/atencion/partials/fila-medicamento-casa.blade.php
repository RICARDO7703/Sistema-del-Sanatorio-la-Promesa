<div class="border rounded p-2 mb-2 fila-medicamento-casa">
    <label class="form-label w-100">Medicamento<input type="text" class="form-control @error('medicamentos_casa.'.$indice.'.medicamento') is-invalid @enderror" name="medicamentos_casa[{{ $indice }}][medicamento]" value="{{ $fila['medicamento'] ?? '' }}" placeholder="Ej. Ibuprofeno" maxlength="255" data-mensaje-requerido="Escriba el nombre del medicamento." required>@error('medicamentos_casa.'.$indice.'.medicamento')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror</label>
    <div class="row g-2">
        @foreach(['dosis' => ['Dosis', 100, true, 'Ej. 50'], 'frecuencia' => ['Frecuencia (cada cuántas horas)', 100, true, 'Ej. 8'], 'duracion' => ['Días', 100, false, 'Ej. 5']] as $campo => [$etiqueta, $maximo, $obligatorio, $ejemplo])
        <div class="col-sm-4"><label class="form-label w-100">{{ $etiqueta }}<input type="text" inputmode="{{ $campo==='duracion' ? 'numeric' : 'decimal' }}" pattern="{{ $campo==='duracion' ? '[0-9]+' : '[0-9]+([.][0-9]{1,2})?' }}" oninput="this.value=this.value.replace({{ $campo==='duracion' ? '/[^0-9]/g' : '/[^0-9.]/g' }},'')" class="form-control @error('medicamentos_casa.'.$indice.'.'.$campo) is-invalid @enderror" name="medicamentos_casa[{{ $indice }}][{{ $campo }}]" value="{{ $fila[$campo] ?? '' }}" maxlength="{{ $maximo }}" placeholder="{{ $ejemplo }}" data-mensaje-requerido="Indique {{ $campo === 'dosis' ? 'la dosis' : 'la frecuencia' }}." @required($obligatorio)>@error('medicamentos_casa.'.$indice.'.'.$campo)<span class="invalid-feedback d-block">{{ $message }}</span>@enderror</label></div>
        @endforeach
    </div>
    <button type="button" class="btn btn-outline-danger btn-sm" data-quitar-medicamento-casa>Quitar medicamento</button>
</div>
