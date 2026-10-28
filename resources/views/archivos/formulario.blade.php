@extends('layouts.app')
@section('content')
<div class="inicio-encabezado"><div class="inicio-icono"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i></div><div><h1>Subir hoja en papel</h1><p>{{ $ingreso->paciente->numero_expediente }} · {{ $ingreso->paciente->estado_identificacion === 'Identificado' ? trim($ingreso->paciente->nombres.' '.$ingreso->paciente->apellidos) : 'Paciente no identificado' }}</p></div></div>
<a class="btn btn-outline-secondary mb-3" href="{{ route('pacientes.expediente',$ingreso->id_paciente) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al expediente</a>
@if($errors->any())<div class="alert alert-danger" role="alert"><strong>No se guardó la hoja.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ route('archivos.guardar',$ingreso) }}" method="POST" enctype="multipart/form-data" class="evolucion-card" data-validar-en-linea data-validar-al-enviar>@csrf
<div class="alert alert-info"><i class="bi bi-info-circle" aria-hidden="true"></i> Fotografíe la hoja completa o seleccione el PDF. No necesita transcribir su contenido. El archivo se guardará para consulta en el expediente.</div>
<div class="row g-4">
<div class="col-12 col-md-6"><label class="form-label" for="archivo-tipo"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i> Tipo de hoja *</label>
@if($referencia)<input type="hidden" name="tipo" value="{{ $tipo }}"><input type="hidden" name="referencia" value="{{ $referencia }}"><input id="archivo-tipo" class="form-control" value="{{ $tipo }} · registro #{{ $referencia }}" readonly>
@else<select id="archivo-tipo" name="tipo" class="form-select" required>@foreach(array_keys(\App\Http\Controllers\ArchivoClinicoController::HOJAS) as $hoja)<option value="{{ $hoja }}" @selected(old('tipo',$tipo)===$hoja)>{{ $hoja }}</option>@endforeach</select>@endif
</div>
<div class="col-12 col-md-6"><label class="form-label" for="archivo-fecha"><i class="bi bi-calendar-event" aria-hidden="true"></i> Fecha y hora de la hoja *</label><input id="archivo-fecha" type="datetime-local" name="fecha_documento" class="form-control" required min="{{ $ingreso->fecha_ingreso->format('Y-m-d\TH:i') }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" value="{{ old('fecha_documento',now('America/Guatemala')->format('Y-m-d\TH:i')) }}"><small class="text-muted">La hora en que se realizó la atención escrita en papel.</small></div>
<div class="col-12"><label class="form-label" for="archivo-hoja"><i class="bi bi-camera" aria-hidden="true"></i> Imagen o PDF *</label><input id="archivo-hoja" type="file" name="archivo" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" required><p class="text-muted mt-2">JPG, PNG, WEBP o PDF. Máximo 10 MB por archivo. Para varias páginas puede subir un PDF o agregar más imágenes después.</p></div>
<div class="col-12"><label class="form-label" for="archivo-descripcion">Observaciones (opcional)</label><textarea id="archivo-descripcion" name="descripcion" class="form-control" rows="3" maxlength="2000" placeholder="Ej. Hoja elaborada durante el corte de energía.">{{ old('descripcion') }}</textarea></div>
</div><p class="text-muted mt-4">Subir una hoja no cambia las órdenes médicas ni el estado del paciente. El alta y las demás acciones se registran con sus botones habituales.</p>
<button class="btn btn-primary"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> Guardar hoja en el expediente</button>
</form>
@endsection
