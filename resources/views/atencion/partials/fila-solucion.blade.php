@php
    $velocidad = $fila['velocidad'] ?? '';
    $unidad = $fila['unidad'] ?? 'gotas/min';
@endphp
<div class="border rounded p-2 mb-2 fila-orden">
    <div class="row g-2">
        <div class="col-sm-6"><label class="form-label w-100">Tipo de suero<select class="form-select" name="ordenes[soluciones][{{ $indice }}][tipo]" data-mensaje-requerido="Seleccione el tipo de suero." required><option value="">Seleccione</option>@foreach(['Mixta','Salina','Glucosada','Hartmann','Otra'] as $tipo)<option @selected(($fila['tipo'] ?? '') === $tipo)>{{ $tipo }}</option>@endforeach</select></label></div>
        <div class="col-sm-6"><label class="form-label w-100">Volumen (ml)<input type="number" class="form-control" name="ordenes[soluciones][{{ $indice }}][volumen]" value="{{ $fila['volumen'] ?? '' }}" min="0.01" max="99999" step="0.01" data-mensaje-requerido="Indique el volumen." required></label></div>
        <div class="col-12"><label class="form-label w-100">Goteo
            <span class="input-group flex-nowrap">
                <input type="number" class="form-control" style="min-width: 4rem" name="ordenes[soluciones][{{ $indice }}][velocidad]" value="{{ is_numeric($velocidad) ? $velocidad : '' }}" min="1" max="999" step="1" placeholder="Ej. 40" data-mensaje-requerido="Indique el goteo." required>
                <select class="form-select" style="max-width: 11rem" name="ordenes[soluciones][{{ $indice }}][unidad]" aria-label="Unidad de goteo">
                    <option value="gotas/min" @selected($unidad === 'gotas/min')>gotas/min</option>
                    <option value="microgotas/min" @selected($unidad === 'microgotas/min')>microgotas/min</option>
                </select>
            </span>
            <small class="text-muted d-block">Microgotas para niños.</small>
            @if($velocidad !== '' && ! is_numeric($velocidad))<small class="text-muted d-block">Registro anterior: {{ $velocidad }}</small>@endif
        </label></div>
    </div><button type="button" class="btn btn-outline-danger btn-sm" data-quitar>Quitar solución</button>
</div>
