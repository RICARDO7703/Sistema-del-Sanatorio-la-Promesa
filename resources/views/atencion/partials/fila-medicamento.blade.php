<div class="border rounded p-2 mb-2 fila-orden">
    <label class="form-label w-100">Medicamento<input type="text" class="form-control" name="ordenes[medicamentos][{{ $indice }}][nombre]" value="{{ $fila['nombre'] ?? '' }}" placeholder="Escriba el nombre y presentación" maxlength="255" required></label>
    <div class="row g-2">@foreach(['dosis'=>'Dosis','via'=>'Vía','frecuencia'=>'Frecuencia'] as $campo=>$etiqueta)<div class="col-md-4"><label class="form-label">{{ $etiqueta }}<input class="form-control" name="ordenes[medicamentos][{{ $indice }}][{{ $campo }}]" value="{{ $fila[$campo] ?? '' }}" maxlength="{{ $campo === 'via' ? 80 : 100 }}" required></label></div>@endforeach</div>
    <button type="button" class="btn btn-outline-danger btn-sm" data-quitar>Quitar medicamento</button>
</div>
