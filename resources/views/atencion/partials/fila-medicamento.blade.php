@php
    $dosis = $fila['dosis'] ?? '';
    $frecuencia = $fila['frecuencia'] ?? '';
    $vias = \App\Services\OrdenesMedicas::VIAS;
@endphp
<div class="border rounded p-2 mb-2 fila-orden">
    <label class="form-label w-100">Medicamento<input type="text" class="form-control" name="ordenes[medicamentos][{{ $indice }}][nombre]" value="{{ $fila['nombre'] ?? '' }}" placeholder="Ej. Diclofenaco" maxlength="255" data-mensaje-requerido="Escriba el nombre del medicamento." required></label>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label w-100">Dosis
            <span class="input-group"><input type="number" class="form-control" name="ordenes[medicamentos][{{ $indice }}][dosis]" value="{{ is_numeric($dosis) ? $dosis : '' }}" min="0.01" max="100000" step="0.01" placeholder="Ej. 500" data-mensaje-requerido="Indique la dosis en miligramos." required><span class="input-group-text">mg</span></span>
            @if($dosis !== '' && ! is_numeric($dosis))<small class="text-muted d-block">Registro anterior: {{ $dosis }}</small>@endif
        </label></div>
        <div class="col-md-4"><label class="form-label w-100">Vía
            <select class="form-select" name="ordenes[medicamentos][{{ $indice }}][via]" data-mensaje-requerido="Seleccione la vía." required>
                <option value="">Seleccione</option>
                @foreach($vias as $via)<option @selected(($fila['via'] ?? '') === $via)>{{ $via }}</option>@endforeach
            </select>
            @if(($fila['via'] ?? '') !== '' && ! in_array($fila['via'], $vias, true))<small class="text-muted d-block">Registro anterior: {{ $fila['via'] }}</small>@endif
        </label></div>
        <div class="col-md-4"><label class="form-label w-100">Frecuencia (cada)
            <span class="input-group"><input type="number" class="form-control" name="ordenes[medicamentos][{{ $indice }}][frecuencia]" value="{{ is_numeric($frecuencia) ? $frecuencia : '' }}" min="1" max="168" step="1" placeholder="Ej. 8" data-mensaje-requerido="Indique cada cuántas horas." required><span class="input-group-text">horas</span></span>
            @if($frecuencia !== '' && ! is_numeric($frecuencia))<small class="text-muted d-block">Registro anterior: {{ $frecuencia }}</small>@endif
        </label></div>
    </div>
    <button type="button" class="btn btn-outline-danger btn-sm" data-quitar>Quitar medicamento</button>
</div>
