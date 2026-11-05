@extends('layouts.app')
@section('title', 'Editar archivo - Sanatorio La Promesa')
@section('content')
@php
    $delPaciente = $documento->esDelPaciente();
    $limite = $documento->editableHasta()->setTimezone('America/Guatemala');
    $minimo = $delPaciente ? null : $documento->ingreso->fecha_ingreso->format('Y-m-d\TH:i');
@endphp
<div class="inicio-encabezado"><div class="inicio-icono"><i class="bi bi-pencil-square" aria-hidden="true"></i></div><div><h1>Editar archivo</h1><p>{{ $documento->paciente->numero_expediente }} · {{ $documento->paciente->estado_identificacion === 'Identificado' ? trim($documento->paciente->nombres.' '.$documento->paciente->apellidos) : 'Paciente no identificado' }}</p></div></div>
<a class="btn btn-outline-secondary mb-3" href="{{ route('archivos.show', $documento) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver sin cambiar</a>
<div class="alert alert-warning d-flex gap-2" role="note"><i class="bi bi-clock-history" aria-hidden="true"></i><span>Puede corregir este archivo hasta el <strong>{{ $limite->format('d/m/Y') }} a las {{ $limite->format('H:i') }}</strong>. Después solo se podrá ver.</span></div>
@if($errors->any())<div class="alert alert-danger" role="alert"><strong>No se guardaron los cambios.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ route('archivos.actualizar', $documento) }}" method="POST" enctype="multipart/form-data" class="evolucion-card" novalidate data-validar-en-linea data-validar-al-enviar>
    @csrf
    @method('PUT')
    <div class="row g-4">
        @if($delPaciente)
        <div class="col-12 col-md-5">
            @if($documento->esDocumentoIngresoFirmado())
            <label class="form-label" for="editar-categoria">Tipo</label>
            <input id="editar-categoria" class="form-control" value="{{ $documento->etiquetaCategoria() }}" readonly>
            @else
            <label class="form-label" for="editar-categoria">Tipo *</label>
            <select id="editar-categoria" name="categoria" class="form-select" required data-mensaje-requerido="Seleccione el tipo de archivo.">
                @foreach(\App\Models\DocumentoClinico::CATEGORIAS as $categoria)
                <option value="{{ $categoria }}" @selected(old('categoria', $documento->etiquetaCategoria()) === $categoria)>{{ $categoria }}</option>
                @endforeach
            </select>
            @endif
        </div>
        <div class="col-12 col-md-7">
            <label class="form-label" for="editar-titulo">Nombre del archivo *</label>
            <input id="editar-titulo" name="titulo" class="form-control" maxlength="150" required value="{{ old('titulo', $documento->titulo) }}" data-mensaje-requerido="Escriba el nombre del archivo.">
        </div>
        @else
        <div class="col-12 col-md-6">
            <label class="form-label" for="editar-tipo">Tipo de hoja</label>
            <input id="editar-tipo" class="form-control" value="{{ $documento->titulo }}" readonly>
        </div>
        @endif
        @if($delPaciente)
        <div class="col-12 col-md-6">
            <p class="form-label mb-1">Subido el</p>
            <p class="mb-0">{{ $documento->fecha_documento?->format('d/m/Y') }} a las {{ $documento->fecha_documento?->format('H:i') }}</p>
        </div>
        @else
        <div class="col-12 col-md-6">
            <label class="form-label" for="editar-fecha">Fecha y hora de la hoja *</label>
            <input id="editar-fecha" type="datetime-local" name="fecha_documento" class="form-control" required @if($minimo) min="{{ $minimo }}" @endif max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" data-max-ahora value="{{ old('fecha_documento', $documento->fecha_documento?->format('Y-m-d\TH:i')) }}" data-mensaje-requerido="Indique la fecha." data-mensaje-maximo="La fecha no puede ser futura.">
        </div>
        @endif
        <div class="col-12">
            <label class="form-label" for="editar-archivo">Reemplazar el archivo (opcional)</label>
            <input id="editar-archivo" type="file" name="archivo" class="form-control" accept="application/pdf,image/jpeg,image/png,image/webp">
            <small class="text-muted">Archivo actual: {{ $documento->nombre_archivo }}. Si no elige otro, se conserva. PDF, JPG o PNG, máximo 10 MB.</small>
        </div>
        <div class="col-12">
            <label class="form-label" for="editar-descripcion">Observaciones (opcional)</label>
            <textarea id="editar-descripcion" name="descripcion" class="form-control" rows="3" maxlength="2000">{{ old('descripcion', $documento->descripcion) }}</textarea>
        </div>
    </div>
    <button class="btn btn-primary mt-4"><i class="bi bi-check-lg" aria-hidden="true"></i> Confirmar cambios</button>
</form>
@endsection
