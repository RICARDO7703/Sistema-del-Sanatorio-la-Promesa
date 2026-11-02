@php
    $recuperar = old('_formulario') === $formulario;
    $valorCampo = fn ($campo, $defecto = '') => $recuperar ? old($campo) : ($registro?->{$campo} ?? $defecto);
@endphp
<input type="hidden" name="_formulario" value="{{ $formulario }}">
<input type="hidden" name="id_ingreso" value="{{ $ingreso->id_ingreso }}">
<div class="row g-3 mb-3">
    <div class="col-md-4"><label class="form-label" for="{{ $formulario }}-fecha">Fecha y hora</label><input id="{{ $formulario }}-fecha" type="datetime-local" name="fecha_hora" class="form-control" value="{{ $recuperar ? old('fecha_hora') : ($registro?->fecha_hora?->format('Y-m-d\TH:i') ?? now('America/Guatemala')->format('Y-m-d\TH:i')) }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" required></div>
    <div class="col-md-8"><label class="form-label" for="{{ $formulario }}-motivo">Motivo de consulta</label><input id="{{ $formulario }}-motivo" name="motivo_atencion" class="form-control" value="{{ $valorCampo('motivo_atencion', $ingreso->motivo_ingreso) }}" maxlength="255" required></div>
    <div class="col-12"><label class="form-label" for="{{ $formulario }}-sintomas">Síntomas</label><textarea id="{{ $formulario }}-sintomas" name="sintomas" class="form-control" rows="3" maxlength="1000">{{ $valorCampo('sintomas') }}</textarea></div>
</div>
@include('atencion.partials.examen-fisico', ['registro'=>$registro, 'formulario'=>$formulario])
@include('atencion.partials.aviso-alergias', ['paciente' => $ingreso->paciente])
@foreach(['diagnostico'=>['Diagnóstico',1000,true], 'indicaciones'=>['Tratamiento',2000,false], 'observaciones'=>['Observaciones',1000,false]] as $campo=>$config)
<div class="mb-3"><label class="form-label" for="{{ $formulario }}-{{ $campo }}">{{ $config[0] }}</label><textarea id="{{ $formulario }}-{{ $campo }}" class="form-control" name="{{ $campo }}" rows="3" maxlength="{{ $config[1] }}" @required($config[2])>{{ $valorCampo($campo) }}</textarea></div>
@endforeach
@unless($ingreso->esConsultaExterna())
@php
    $ordenesValoracion = $recuperar ? old('ordenes', []) : ($registro?->ordenes_medicas ?? []);
    $conOrdenes = $recuperar ? (bool) old('con_ordenes') : filled($registro?->ordenes_medicas);
@endphp
<fieldset class="border rounded p-3 mb-3">
    <legend class="float-none w-auto px-2 fs-5">Órdenes médicas (opcional)</legend>
    <input type="hidden" name="ordenes_opcionales" value="1">
    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" name="con_ordenes" value="1" id="{{ $formulario }}-con-ordenes" data-activar-ordenes="{{ $formulario }}-ordenes" @checked($conOrdenes)>
        <label class="form-check-label" for="{{ $formulario }}-con-ordenes">Agregar órdenes médicas para enfermería</label>
    </div>
    <div id="{{ $formulario }}-ordenes" @unless($conOrdenes) hidden @endunless>
        <p class="text-muted small">Si las agrega, enfermería las verá como órdenes vigentes hasta la siguiente evolución.</p>
        @include('atencion.partials.campos-ordenes', ['prefijo' => $formulario.'-orden', 'ordenes' => $ordenesValoracion, 'deshabilitado' => ! $conOrdenes])
    </div>
</fieldset>
@endunless
