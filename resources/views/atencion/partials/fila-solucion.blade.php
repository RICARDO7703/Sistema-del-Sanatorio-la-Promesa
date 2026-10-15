<div class="border rounded p-2 mb-2 fila-orden">
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label">Tipo de suero<select class="form-select" name="ordenes[soluciones][{{ $indice }}][tipo]" required><option value="">Seleccione</option>@foreach(['Mixta','Salina','Glucosada','Hartmann','Otra'] as $tipo)<option @selected(($fila['tipo'] ?? '') === $tipo)>{{ $tipo }}</option>@endforeach</select></label></div>
        <div class="col-md-4"><label class="form-label">Volumen (ml)<input type="number" class="form-control" name="ordenes[soluciones][{{ $indice }}][volumen]" value="{{ $fila['volumen'] ?? '' }}" min="0.01" max="99999" step="0.01" required></label></div>
        <div class="col-md-4"><label class="form-label">Velocidad / frecuencia<input class="form-control" name="ordenes[soluciones][{{ $indice }}][velocidad]" value="{{ $fila['velocidad'] ?? '' }}" maxlength="100" required></label></div>
    </div><button type="button" class="btn btn-outline-danger btn-sm" data-quitar>Quitar solución</button>
</div>
