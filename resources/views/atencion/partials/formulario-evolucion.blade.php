@if(isset($ingreso) && $ingreso->exists)
<div class="alert alert-info d-flex flex-wrap align-items-center gap-2"><i class="bi bi-heart-pulse" aria-hidden="true"></i><span>Consulte las cirugías y los controles de recuperación sin cerrar esta evolución.</span><a href="{{ route('atencion.show',$ingreso) }}#cirugias-paciente-{{ $ingreso->getKey() }}" target="_blank" rel="noopener" class="btn btn-outline-primary">Ver controles de recuperación <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></div>
@endif
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
@include('atencion.partials.examen-fisico', ['registro'=>$registro, 'formulario'=>$formulario, 'signosSeleccionables'=>$ingreso->esConsultaExterna()])
@foreach(['diagnostico'=>['Diagnóstico',1000,true], 'indicaciones'=>['Tratamiento',2000,false], 'observaciones'=>['Observaciones',1000,false]] as $campo=>$config)
<div class="mb-3"><label class="form-label" for="{{ $formulario }}-{{ $campo }}">{{ $config[0] }}</label><textarea id="{{ $formulario }}-{{ $campo }}" class="form-control" name="{{ $campo }}" rows="3" maxlength="{{ $config[1] }}" @required($config[2])>{{ $valorCampo($campo) }}</textarea></div>
@endforeach
