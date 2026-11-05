@extends('layouts.app')
@section('title','Archivo del expediente')
@section('content')
<h1>{{ $documento->titulo }}</h1><p>{{ $documento->etiquetaCategoria() }}</p>
<a class="btn btn-outline-secondary mb-3" href="{{ route('pacientes.expediente',$documento->id_paciente) }}"><i class="bi bi-arrow-left"></i> Volver al expediente</a>
@if(session('exito'))<div class="alert alert-success">{{ session('exito') }}</div>@endif
<div class="card p-3 mb-3"><p>Subido el {{ $documento->fecha_registro?->format('d/m/Y H:i') }}</p><p>{{ $documento->nombre_archivo }} · {{ $documento->fecha_documento?->format('d/m/Y H:i') }}</p><p>{{ $documento->descripcion }}</p>
@if($documento->puedeEditar((int)session('id_usuario')) && app(\App\Support\Permisos::class)->puede('Documentos clínicos','Editar'))
<div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('archivos.editar',$documento) }}"><i class="bi bi-pencil"></i> Editar</a><form method="post" action="{{ route('archivos.quitar',$documento) }}" onsubmit="return confirm('¿Quitar este archivo del expediente?');">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Quitar archivo</button></form></div>@endif</div>
<a target="_blank" rel="noopener" class="btn btn-primary mb-3" href="{{ route('archivos.contenido',$documento) }}">Abrir archivo completo</a>
@if($documento->tipo_archivo==='pdf')<iframe title="Archivo del expediente" src="{{ route('archivos.contenido',$documento) }}" style="width:100%;height:75vh"></iframe>@else<img src="{{ route('archivos.contenido',$documento) }}" class="img-fluid" alt="Hoja del expediente">@endif
@endsection
