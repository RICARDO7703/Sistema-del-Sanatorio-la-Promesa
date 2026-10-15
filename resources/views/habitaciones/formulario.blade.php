@php($recuperar = (string) old('_habitacion') === $contexto)
<input type="hidden" name="_habitacion" value="{{ $contexto }}">
@foreach(['numero_habitacion'=>['Número de habitación',20],'tipo'=>['Tipo',50]] as $campo=>$config)
<label class="form-label d-block">{{ $config[0] }}<input class="form-control" name="{{ $campo }}" maxlength="{{ $config[1] }}" required value="{{ $recuperar ? old($campo) : $habitacion?->{$campo} }}"></label>
@endforeach
<label class="form-label d-block">Estado operativo<select class="form-select" name="estado_operativo" required>@foreach(['Activa','Mantenimiento','Inactiva'] as $estado)<option @selected(($recuperar ? old('estado_operativo') : ($habitacion?->estado_operativo ?? 'Activa')) === $estado)>{{ $estado }}</option>@endforeach</select></label>
<label class="form-label d-block">Observaciones<textarea class="form-control" name="observaciones" maxlength="500">{{ $recuperar ? old('observaciones') : $habitacion?->observaciones }}</textarea></label>
