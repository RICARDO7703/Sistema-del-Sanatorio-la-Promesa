@extends('layouts.app')
@section('content')
<div class="inicio-encabezado"><div class="inicio-icono"><i class="bi bi-calendar-plus" aria-hidden="true"></i></div><div><h1>{{ $modo === 'realizada' ? 'Registrar cirugía ya realizada' : ($cirugia ? 'Agendar / reprogramar cirugía' : 'Agendar cirugía') }}</h1><p>{{ $ingreso->paciente->estado_identificacion === 'Identificado' ? trim($ingreso->paciente->nombres.' '.$ingreso->paciente->apellidos) : 'Paciente no identificado · '.$ingreso->paciente->codigo_temporal }} · {{ $ingreso->paciente->numero_expediente }}</p></div></div>
<a class="btn btn-outline-secondary mb-3" href="{{ route('atencion.show',$ingreso) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a la atención</a>
@if($errors->any())<div class="alert alert-danger" role="alert">Revise los campos indicados.<ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('cirugias.guardar',$ingreso) }}" class="evolucion-card" data-validar-en-linea data-validar-al-enviar>
@csrf<input type="hidden" name="modo" value="{{ $modo }}">@if($cirugia)<input type="hidden" name="cirugia" value="{{ $cirugia->getKey() }}">@endif
<div class="row g-4">
<div class="col-12"><label class="form-label" for="cirugia-nombre"><i class="bi bi-hospital" aria-hidden="true"></i> Nombre de la cirugía *</label><input id="cirugia-nombre" class="form-control" name="tipo_operacion" maxlength="150" required value="{{ old('tipo_operacion',$cirugia?->tipo_operacion) }}"></div>
@if($modo === 'agendar')
<div class="col-md-6"><label class="form-label" for="cirugia-inicio"><i class="bi bi-calendar-event" aria-hidden="true"></i> Fecha y hora programada *</label><input id="cirugia-inicio" type="datetime-local" class="form-control" name="fecha_programada" required min="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" value="{{ old('fecha_programada',$cirugia?->fecha_programada?->format('Y-m-d\TH:i')) }}"></div>
@endif
@if($modo === 'realizada')
<div class="col-12"><div class="alert alert-info mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Para atenciones de urgencia que se registran después de operar. Escriba las horas en que ocurrió la cirugía; el sistema conservará por separado la fecha de registro.</div></div>
@foreach(['fecha_inicio_real'=>'Inicio real de la cirugía','fecha_fin_real'=>'Final real de la cirugía'] as $campo=>$etiqueta)
<div class="col-12 col-md-6"><label class="form-label" for="{{ $campo }}"><i class="bi bi-clock-history" aria-hidden="true"></i> {{ $etiqueta }} *</label><input id="{{ $campo }}" type="datetime-local" class="form-control" name="{{ $campo }}" required min="{{ $ingreso->fecha_ingreso->format('Y-m-d\TH:i') }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" value="{{ old($campo) }}"></div>
@endforeach
<div class="col-12"><label class="form-label" for="motivo-posterior"><i class="bi bi-journal-text" aria-hidden="true"></i> ¿Por qué se registró después? *</label><textarea id="motivo-posterior" name="motivo_registro_posterior" class="form-control" required maxlength="500" rows="3" placeholder="Ej. Se priorizó la atención inmediata del paciente.">{{ old('motivo_registro_posterior') }}</textarea></div>
@endif
@if(in_array($modo,['agendar','realizada']))
<fieldset class="col-12"><legend class="h5"><i class="bi bi-people" aria-hidden="true"></i> Personal participante <small class="text-muted">(opcional)</small></legend><p>Marque las personas que participarán. Su cargo aparece junto al nombre.</p><input type="search" class="form-control mb-3" placeholder="Buscar personal por nombre o cargo" aria-label="Buscar personal" oninput="this.parentElement.querySelectorAll('[data-personal-cirugia]').forEach(fila => fila.hidden = !fila.textContent.toLocaleLowerCase().includes(this.value.toLocaleLowerCase()))">
<div class="cirugia-personal">@foreach($personal as $persona)<label class="cirugia-persona" data-personal-cirugia><input class="form-check-input me-2" type="checkbox" name="personal[]" value="{{ $persona->getKey() }}" @checked(in_array($persona->getKey(),old('personal',$cirugia?->equipo->modelKeys() ?? [])))><span><strong>{{ $persona->nombres }} {{ $persona->apellidos }}</strong><small class="d-block text-muted">{{ $persona->cargo?->nombre_cargo }}</small></span></label>@endforeach</div>
</fieldset>
@endif
<div class="col-12"><label for="cirugia-observaciones" class="form-label"><i class="bi bi-chat-left-text" aria-hidden="true"></i> Observaciones (opcional)</label><textarea id="cirugia-observaciones" class="form-control" name="observaciones" rows="3" maxlength="2000">{{ old('observaciones',$cirugia?->observaciones) }}</textarea></div>
</div><div class="d-flex flex-wrap gap-2 justify-content-end mt-4"><a class="btn btn-outline-secondary" href="{{ route('atencion.show',$ingreso) }}">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ $modo === 'realizada' ? 'Guardar cirugía realizada' : 'Guardar programación' }}</button></div>
</form>
@endsection
