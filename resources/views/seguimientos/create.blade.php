@extends('layouts.app')
@section('title', 'Seguimiento - Sanatorio La Promesa')
@section('menu-pacientes', 'activo')
@section('content')
<div class="inicio-encabezado"><div class="inicio-icono"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></div><div><h1>Atención del paciente</h1><p>{{ $origen->paciente->estado_identificacion === 'Identificado' ? trim($origen->paciente->nombres.' '.$origen->paciente->apellidos) : 'Paciente no identificado' }} · {{ $origen->paciente->numero_expediente }}</p></div></div>
<a href="{{ route('pacientes.expediente', $origen->paciente) }}" class="btn btn-outline-secondary mb-3"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al expediente</a>
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@include('atencion.partials.consulta-emergencia', ['accionConsulta' => route('seguimientos.guardar', $origen), 'minimoConsulta' => $origen->altaMedica->fecha_hora])
@endsection
