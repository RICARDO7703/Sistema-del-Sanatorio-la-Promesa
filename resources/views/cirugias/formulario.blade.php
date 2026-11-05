@extends('layouts.app')
@section('content')
@php
    $volverValoracion = (bool) old('volver_valoracion', request()->boolean('volver_valoracion'));
    $destinoVolver = route('atencion.show', ['ingreso' => $ingreso, 'consulta' => $volverValoracion && ! $ingreso->esConsultaExterna() ? 'valoracion' : null]);
@endphp
<div class="inicio-encabezado"><div class="inicio-icono"><i class="bi bi-calendar-plus" aria-hidden="true"></i></div><div><h1>{{ $modo === 'realizada' ? 'Registrar cirugía ya realizada' : ($cirugia ? 'Agendar / reprogramar cirugía' : 'Agendar cirugía') }}</h1><p>{{ $ingreso->paciente->estado_identificacion === 'Identificado' ? trim($ingreso->paciente->nombres.' '.$ingreso->paciente->apellidos) : 'Paciente no identificado · '.$ingreso->paciente->codigo_temporal }} · {{ $ingreso->paciente->numero_expediente }}</p></div></div>
<a class="btn btn-outline-secondary mb-3" href="{{ $destinoVolver }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a la atención</a>
@if($errors->any())<div class="alert alert-danger" role="alert">Revise los campos indicados.<ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('cirugias.guardar',$ingreso) }}" class="evolucion-card" data-validar-en-linea data-validar-al-enviar>
@csrf<input type="hidden" name="volver_valoracion" value="{{ $volverValoracion ? 1 : 0 }}"><input type="hidden" name="modo" value="{{ $modo }}">@if($cirugia)<input type="hidden" name="cirugia" value="{{ $cirugia->getKey() }}">@endif
<div class="row g-4">
<div class="col-12"><label class="form-label" for="cirugia-nombre"><i class="bi bi-hospital" aria-hidden="true"></i> Nombre de la cirugía *</label><input id="cirugia-nombre" class="form-control" name="tipo_operacion" maxlength="150" required value="{{ old('tipo_operacion',$cirugia?->tipo_operacion) }}"></div>
@if($modo === 'agendar')
<div class="col-md-6"><label class="form-label" for="cirugia-inicio"><i class="bi bi-calendar-event" aria-hidden="true"></i> Fecha y hora programada *</label><input id="cirugia-inicio" type="datetime-local" class="form-control" name="fecha_programada" required min="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" value="{{ old('fecha_programada',$cirugia?->fecha_programada?->format('Y-m-d\TH:i')) }}"></div>
@endif
@if($modo === 'realizada')
@foreach(['fecha_inicio_real'=>'Inicio real de la cirugía','fecha_fin_real'=>'Final real de la cirugía'] as $campo=>$etiqueta)
<div class="col-12 col-md-6"><label class="form-label" for="{{ $campo }}"><i class="bi bi-clock-history" aria-hidden="true"></i> {{ $etiqueta }} *</label><input id="{{ $campo }}" type="datetime-local" class="form-control" name="{{ $campo }}" required max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" value="{{ old($campo) }}" aria-describedby="{{ $campo }}-ayuda"><small id="{{ $campo }}-ayuda" class="form-text text-muted">Puede poner una fecha y hora pasada: la de cuando se realizó la cirugía.</small></div>
@endforeach
<div class="col-12"><label class="form-label" for="motivo-posterior"><i class="bi bi-journal-text" aria-hidden="true"></i> ¿Por qué se registró después? *</label><textarea id="motivo-posterior" name="motivo_registro_posterior" class="form-control" required maxlength="500" rows="3" placeholder="Ej. Se priorizó la atención inmediata del paciente.">{{ old('motivo_registro_posterior') }}</textarea></div>
@endif
@if(in_array($modo,['agendar','realizada']))
<fieldset class="col-12" data-filtro-equipo><legend class="h5"><i class="bi bi-people" aria-hidden="true"></i> Personal participante <small class="text-muted">(opcional)</small></legend><p>Busque por cargo o nombre y pulse «+ Agregar». Puede incluir varias personas de distintos cargos.</p><div class="row g-3 mb-3"><div class="col-md-5"><label class="form-label" for="equipo-cargo"><i class="bi bi-funnel"></i> Cargo</label><select class="form-select" id="equipo-cargo" data-cargo-equipo><option value="">Todos los cargos</option>@foreach($personal->map(fn($p)=>$p->cargo?->nombre_cargo)->filter()->unique()->sort() as $cargo)<option value="{{ $cargo }}">{{ $cargo }}</option>@endforeach</select></div><div class="col-md-7"><label class="form-label" for="equipo-buscar">Buscar personal</label><input id="equipo-buscar" type="search" class="form-control" placeholder="Escriba el nombre" data-buscar-equipo></div></div><p class="text-muted small" data-total-equipo></p>
<div class="border rounded-3 p-3 mb-3"><strong><i class="bi bi-people"></i> Equipo seleccionado</strong><div data-equipo-seleccionado class="d-flex flex-column gap-2 mt-2"></div></div><div class="cirugia-personal">@foreach($personal as $persona)<div class="cirugia-persona" data-personal-cirugia data-cargo="{{ $persona->cargo?->nombre_cargo }}"><input hidden type="checkbox" name="personal[]" value="{{ $persona->getKey() }}" @checked(in_array($persona->getKey(),old('personal',$cirugia?->equipo->modelKeys() ?? [])))><span><strong>{{ $persona->nombres }} {{ $persona->apellidos }}</strong><small class="d-block text-muted">{{ $persona->cargo?->nombre_cargo }}</small></span><button type="button" class="btn btn-outline-primary btn-sm ms-auto" data-agregar-equipo><i class="bi bi-plus-lg"></i> Agregar</button></div>@endforeach</div>
<p hidden class="text-muted" data-equipo-vacio>No hay personal que coincida con el cargo y la búsqueda.</p></fieldset>
@endif
<div class="col-12"><label for="cirugia-observaciones" class="form-label"><i class="bi bi-chat-left-text" aria-hidden="true"></i> Observaciones (opcional)</label><textarea id="cirugia-observaciones" class="form-control" name="observaciones" rows="3" maxlength="2000">{{ old('observaciones',$cirugia?->observaciones) }}</textarea></div>
</div><div class="d-flex flex-wrap gap-2 justify-content-end mt-4"><a class="btn btn-outline-secondary" href="{{ $destinoVolver }}">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ $modo === 'realizada' ? 'Guardar cirugía realizada' : 'Guardar programación' }}</button></div>
</form>
@endsection
