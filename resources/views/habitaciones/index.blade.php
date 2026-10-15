@extends('layouts.app')
@section('title', 'Habitaciones - Sanatorio La Promesa')
@section('menu-habitaciones', 'activo')
@section('content')
<div class="inicio-encabezado"><h1>Habitaciones</h1></div>
@if(session('exito'))<div class="alert alert-success">{{ session('exito') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="GET" action="{{ route('habitaciones.index') }}" class="d-flex gap-2 mb-3"><input class="form-control" name="buscar" value="{{ $buscar }}" maxlength="100" placeholder="Buscar habitación"><button class="btn btn-primary">Buscar</button></form>
<div class="evolucion-card">
    <p>La ocupación se actualiza al asignar, trasladar o dar de alta a un paciente.</p>
    <details @if(old('_habitacion') === 'nueva') open @endif><summary class="btn btn-primary">Nueva habitación</summary>
        <form method="POST" action="{{ route('habitaciones.guardar') }}" class="mt-3">@csrf
            @include('habitaciones.formulario', ['habitacion'=>null,'contexto'=>'nueva'])
            <button class="btn btn-primary mt-3">Guardar habitación</button>
        </form>
    </details>
    <div class="table-responsive"><table class="table align-middle mt-3"><thead><tr><th>No. habitación</th><th>Tipo</th><th>Disponibilidad</th><th>Observaciones</th><th>Acciones</th></tr></thead><tbody>
    @forelse($habitaciones as $habitacion)
    <tr><td>{{ $habitacion->numero_habitacion }}</td><td>{{ $habitacion->tipo }}</td><td>{{ $habitacion->ocupada ? 'Ocupada' : ($habitacion->estado_operativo === 'Activa' ? 'Disponible' : $habitacion->estado_operativo) }}</td><td>{{ $habitacion->observaciones ?: 'Sin observaciones' }}</td><td>
        <details @if(old('_habitacion') == $habitacion->getKey()) open @endif><summary>Editar</summary><form method="POST" action="{{ route('habitaciones.actualizar',$habitacion) }}" class="mt-2">@csrf @method('PUT')
            @include('habitaciones.formulario', ['contexto'=>(string)$habitacion->getKey()])
            <button class="btn btn-primary mt-2">Guardar cambios</button>
        </form></details>
    </td></tr>
    @empty<tr><td colspan="5">No se encontraron habitaciones.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
