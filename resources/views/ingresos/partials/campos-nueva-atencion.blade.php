{{--
    Campos para hospitalizar a un paciente (se queda internado).
    La consulta externa se inicia con un solo botón y no usa estos campos.
    Variables: $prefijo (para los id), $habitacionesDisponibles, $recuperar (usar old()), $mostrarAviso (opcional).
--}}
@php
    $fechaLlegada = $recuperar && old('fecha_ingreso') ? old('fecha_ingreso') : now('America/Guatemala')->format('Y-m-d\TH:i');
@endphp

<input type="hidden" name="tipo_atencion" value="{{ \App\Models\Ingreso::TIPO_HOSPITALIZACION }}">

@if($mostrarAviso ?? true)
<div class="alert alert-danger d-flex gap-2 align-items-center py-2" role="note">
    <i class="bi bi-hospital fs-4" aria-hidden="true"></i>
    <span><strong>Hospitalización</strong> · el paciente se queda internado.</span>
</div>
@endif

<div class="mb-3">
    <label class="form-label" for="{{ $prefijo }}-fecha">Fecha y hora de llegada</label>
    <input type="datetime-local" id="{{ $prefijo }}-fecha" name="fecha_ingreso" class="form-control @if($recuperar) @error('fecha_ingreso') is-invalid @enderror @endif" value="{{ $fechaLlegada }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" data-mensaje-requerido="Indique la fecha y hora de llegada." data-max-ahora data-mensaje-maximo="La fecha y hora de llegada no pueden ser futuras." required>
    @if($recuperar)
    @error('fecha_ingreso')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @endif
</div>

<div class="mb-3">
    <label class="form-label" for="{{ $prefijo }}-habitacion">Habitación (opcional)</label>
    <select id="{{ $prefijo }}-habitacion" name="id_habitacion" class="form-select @if($recuperar) @error('id_habitacion') is-invalid @enderror @endif" >
        <option value="">Asignar habitación después</option>
        @foreach($habitacionesDisponibles as $habitacion)
        <option value="{{ $habitacion->id_habitacion }}" @selected($recuperar && old('id_habitacion') == $habitacion->id_habitacion)>
            Habitación {{ $habitacion->numero_habitacion }}@if($habitacion->tipo) - {{ $habitacion->tipo }}@endif
        </option>
        @endforeach
    </select>
    @if($recuperar)
    @error('id_habitacion')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @endif
    @if($habitacionesDisponibles->isEmpty())
    <small class="text-danger d-block mt-2">No hay habitaciones disponibles.</small>
    @endif
</div>

<div class="mb-3">
    <label class="form-label" for="{{ $prefijo }}-motivo">Motivo de ingreso</label>
    <textarea id="{{ $prefijo }}-motivo" name="motivo_ingreso" class="form-control @if($recuperar) @error('motivo_ingreso') is-invalid @enderror @endif" rows="3" maxlength="255" placeholder="Ej. dificultad para respirar, fiebre alta">{{ $recuperar ? old('motivo_ingreso') : '' }}</textarea>
    @if($recuperar)
    @error('motivo_ingreso')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @endif
</div>

<div class="form-check">
    <input class="form-check-input" type="checkbox" name="documento_firmado" value="1" id="{{ $prefijo }}-documento" data-archivo-firmado="{{ $prefijo }}-documento-archivo" @checked($recuperar && old('documento_firmado'))>
    <label class="form-check-label" for="{{ $prefijo }}-documento">Documento de ingreso firmado</label>
</div>

{{-- Al marcar la casilla hay que subir el documento; se guarda en «Archivos médicos» del expediente. --}}
<div class="mt-2" id="{{ $prefijo }}-documento-archivo" @unless($recuperar && old('documento_firmado')) hidden @endunless>
    <label class="form-label" for="{{ $prefijo }}-documento-archivo-campo">Documento firmado (PDF o imagen) *</label>
    <input type="file" id="{{ $prefijo }}-documento-archivo-campo" name="documento_archivo" class="form-control @if($recuperar) @error('documento_archivo') is-invalid @enderror @endif" accept="application/pdf,image/jpeg,image/png,image/webp" data-mensaje-requerido="Suba el documento de ingreso firmado o desmarque la casilla." @if($recuperar && old('documento_firmado')) required @else disabled @endif>
    @if($recuperar)
    @error('documento_archivo')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @endif
    <small class="text-muted d-block">Máximo 10 MB. Quedará en «Archivos médicos» del expediente.</small>
</div>
