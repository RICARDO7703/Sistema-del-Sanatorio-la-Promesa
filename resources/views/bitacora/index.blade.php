@extends('layouts.app')
@section('title', 'Bitácora - Sanatorio La Promesa')
@section('menu-bitacora', 'activo')
@section('content')
<div class="inicio-encabezado">
    <div class="inicio-icono"><i class="bi bi-clipboard-data"></i></div>
    <div><h1>Bitácora</h1><p>Inicios de sesión e intentos fallidos. Hora de Guatemala.</p></div>
</div>
<form method="GET" action="{{ url('/bitacora') }}" class="mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-4"><label for="fecha" class="form-label">Fecha</label><input id="fecha" name="fecha" type="date" value="{{ request('fecha') }}" class="form-control"></div>
        <div class="col-md-4"><label for="resultado" class="form-label">Resultado</label><select id="resultado" name="resultado" class="form-select"><option value="">Todos</option>@foreach(['Exitoso', 'Fallido'] as $resultado)<option @selected(request('resultado') === $resultado)>{{ $resultado }}</option>@endforeach</select></div>
        <div class="col-md-4 d-flex gap-2"><button class="btn btn-primary">Buscar</button><a href="{{ url('/bitacora') }}" class="btn btn-outline-secondary">Limpiar</a></div>
    </div>
    @foreach($errors->all() as $error)<p class="text-danger mt-2">{{ $error }}</p>@endforeach
</form>
<div class="bitacora-card">
    <h2 class="h4">Registro de accesos</h2>
    <p>{{ $registros->total() }} registros. El nombre intentado no acredita la identidad de quien intentó entrar.</p>
    <div class="table-responsive"><table class="table align-middle bitacora-tabla">
        <thead><tr><th>ID bitácora</th><th>Fecha y hora</th><th>Usuario autenticado</th><th>Usuario intentado</th><th>Acción</th><th>Resultado</th><th>IP</th><th>Navegador</th><th>Sistema operativo</th><th>Tabla</th><th>ID registro</th><th>Detalle</th></tr></thead>
        <tbody>@forelse($registros as $registro)
        <tr>
            <td>{{ $registro->id_bitacora }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($registro->fecha_hora, 'UTC')->setTimezone('America/Guatemala')->format('d/m/Y H:i:s') }}</td>
            <td>{{ $registro->nombre_usuario ?? 'Sin autenticar' }}@if($registro->id_usuario)<span class="d-block text-muted">ID: {{ $registro->id_usuario }}</span>@endif</td>
            <td>{{ $registro->usuario_intentado ?: 'Sin nombre' }}</td>
            <td>{{ $registro->accion }}</td>
            <td><span class="badge {{ $registro->resultado === 'Exitoso' ? 'bg-success' : 'bg-danger' }}">{{ $registro->resultado }}</span></td>
            <td>{{ $registro->ip ?: 'Sin registro' }}</td><td>{{ $registro->navegador ?: 'Sin registro' }}</td><td>{{ $registro->sistema_operativo ?: 'Sin registro' }}</td>
            <td>{{ $registro->tabla ?: '—' }}</td><td>{{ $registro->id_registro ?? '—' }}</td><td>{{ $registro->descripcion }}</td>
        </tr>
        @empty<tr><td colspan="12" class="text-center">No hay accesos registrados para estos filtros.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $registros->links('pagination::bootstrap-5') }}
</div>
@endsection
