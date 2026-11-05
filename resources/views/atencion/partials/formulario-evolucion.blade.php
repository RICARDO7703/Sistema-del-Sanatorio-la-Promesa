@php
    $recuperar = old('_formulario') === $formulario;
    $valorCampo = fn ($campo, $defecto = '') => $recuperar ? old($campo) : ($registro?->{$campo} ?? $defecto);
    $ordenesValoracion = $recuperar ? old('ordenes', []) : ($registro?->ordenes_medicas ?? []);
    $conOrdenes = ! $ingreso->esConsultaExterna() && ($recuperar ? ((bool) old('con_ordenes') || \App\Services\OrdenesMedicas::cancelacion($registro?->ordenes_medicas) !== null) : filled($registro?->ordenes_medicas));
@endphp
@if(request('cirugia_recuperacion') || old('cirugia_recuperacion'))
<input type="hidden" name="cirugia_recuperacion" value="{{ old('cirugia_recuperacion', request('cirugia_recuperacion')) }}">
@endif
<input type="hidden" name="_formulario" value="{{ $formulario }}">
<input type="hidden" name="id_ingreso" value="{{ $ingreso->id_ingreso }}">
@unless($ingreso->esConsultaExterna())
<div class="mb-4">    <input type="hidden" name="ordenes_opcionales" value="1">
    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" name="con_ordenes" value="1" id="{{ $formulario }}-con-ordenes" data-activar-ordenes="{{ $formulario }}-ordenes" @disabled(\App\Services\OrdenesMedicas::cancelacion($registro?->ordenes_medicas) !== null) @checked($conOrdenes)>
        <label class="form-check-label" for="{{ $formulario }}-con-ordenes">Agregar órdenes médicas para enfermería</label>
    </div>
</div>
@endunless
<div class="row g-4" data-valoracion-columnas><section class="col-12 @if($conOrdenes) col-xl-6 @endif" data-valoracion-clinica><h5><i class="bi bi-clipboard2-pulse text-primary"></i> Evaluación clínica</h5><div class="row g-3 mb-3">
    <div class="col-12"><label class="form-label" for="{{ $formulario }}-fecha">Fecha y hora</label><input id="{{ $formulario }}-fecha" type="datetime-local" name="fecha_hora" class="form-control" value="{{ $recuperar ? old('fecha_hora') : ($registro?->fecha_hora?->format('Y-m-d\TH:i') ?? now('America/Guatemala')->format('Y-m-d\TH:i')) }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" required><small class="form-text text-muted d-block">¿Se atendió antes y se registra ahora (por ejemplo, sin internet)? Ponga la hora en que se atendió.</small></div>
    <div class="col-12"><label class="form-label" for="{{ $formulario }}-motivo">Motivo de consulta</label><input id="{{ $formulario }}-motivo" name="motivo_atencion" class="form-control" value="{{ $valorCampo('motivo_atencion', $ingreso->motivo_ingreso) }}" maxlength="255" required></div>
    <div class="col-12"><label class="form-label" for="{{ $formulario }}-sintomas">Síntomas</label><textarea id="{{ $formulario }}-sintomas" name="sintomas" class="form-control" rows="3" maxlength="1000">{{ $valorCampo('sintomas') }}</textarea></div>
</div>
@include('atencion.partials.examen-fisico', ['registro'=>$registro, 'formulario'=>$formulario])
@include('atencion.partials.aviso-alergias', ['paciente' => $ingreso->paciente])
@foreach(['diagnostico'=>['Diagnóstico',1000,true], 'indicaciones'=>['Tratamiento',2000,false], 'observaciones'=>['Observaciones',1000,false]] as $campo=>$config)
<div class="mb-3"><label class="form-label" for="{{ $formulario }}-{{ $campo }}">{{ $config[0] }}</label><textarea id="{{ $formulario }}-{{ $campo }}" class="form-control" name="{{ $campo }}" rows="3" maxlength="{{ $config[1] }}" @required($config[2])>{{ $valorCampo($campo) }}</textarea></div>
@endforeach
</section>
@unless($ingreso->esConsultaExterna())
<section class="col-12 col-xl-6 border-start" id="{{ $formulario }}-ordenes" @unless($conOrdenes) hidden @endunless>
<fieldset class="border-0 p-0 m-0">
    <legend class="float-none w-auto fs-5 fw-bold mb-2"><i class="bi bi-journal-medical text-primary"></i> Órdenes médicas (opcional)</legend>
    <div>
        <p class="text-muted">Indicaciones para enfermería vinculadas a esta valoración inicial.</p>
        @include('atencion.partials.campos-ordenes', ['prefijo' => $formulario.'-orden', 'ordenes' => $ordenesValoracion, 'deshabilitado' => ! $conOrdenes, 'registroOrdenesCanceladas' => $registro])
    </div>
</fieldset>
</section>
@endunless
</div>
