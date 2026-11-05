@php($recuperar = (string) old('_habitacion') === $contexto)
<input type="hidden" name="_habitacion" value="{{ $contexto }}">
<label class="form-label d-block">Número de habitación<input class="form-control" name="numero_habitacion" maxlength="20" required value="{{ $recuperar ? old('numero_habitacion') : $habitacion?->numero_habitacion }}"></label>
@php($tipoSeleccionado = $recuperar ? old('tipo') : $habitacion?->tipo)
<label class="form-label d-block">Tipo<select class="form-select" name="tipo" required><option value="">Seleccione un tipo</option>@foreach($tiposHabitacion as $tipo)<option value="{{ $tipo }}" @selected($tipoSeleccionado === $tipo)>{{ $tipo }}</option>@endforeach</select></label>
<label class="form-label d-block">Estado operativo<select class="form-select" name="estado_operativo" required>@foreach(['Activa','Mantenimiento','Inactiva'] as $estado)<option @selected(($recuperar ? old('estado_operativo') : ($habitacion?->estado_operativo ?? 'Activa')) === $estado)>{{ $estado }}</option>@endforeach</select></label>
<label class="form-label d-block">Observaciones<textarea class="form-control" name="observaciones" maxlength="500">{{ $recuperar ? old('observaciones') : $habitacion?->observaciones }}</textarea></label>
