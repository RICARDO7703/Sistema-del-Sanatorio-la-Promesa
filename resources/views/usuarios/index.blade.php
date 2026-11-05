@extends('layouts.app')

@section('title', 'Usuarios - Sanatorio La Promesa')

@section('menu-usuarios', 'activo')

@section('content')
<div class="inicio-encabezado">
    <div class="inicio-icono"><i class="bi bi-person-lock" aria-hidden="true"></i></div>
    <div>
        <h1>Usuarios</h1>
        <p>Cuentas de acceso del personal y sus permisos</p>
    </div>
</div>

@if(request('seccion') === 'permisos')
<div class="alert alert-info d-flex gap-2" role="status"><i class="bi bi-shield-check" aria-hidden="true"></i><div><strong>Asignación de permisos.</strong> Busque al usuario y toque <strong>Editar y permisos</strong>: ahí se marcan los permisos de cada módulo (ver, ingresar, editar, eliminar).</div></div>
@endif
@if(session('exito'))<div class="alert alert-success" role="status">{{ session('exito') }}</div>@endif
@if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="pacientes-buscador mb-4">
    <form action="{{ route('usuarios.index') }}" method="GET">
        <div class="row g-3">
            <div class="col-md-7"><label class="visually-hidden" for="usuarios-buscar">Buscar</label><input id="usuarios-buscar" type="text" name="buscar" class="form-control" placeholder="Buscar por usuario o nombre" value="{{ $buscar }}" maxlength="100"></div>
            <div class="col-md-3"><label class="visually-hidden" for="usuarios-estado">Estado</label>
                <select id="usuarios-estado" name="estado" class="form-select">
                    @foreach(['Activo' => 'Activos', 'Inactivo' => 'Inactivos', 'Bloqueado' => 'Bloqueados', 'Todos' => 'Todos'] as $valor => $texto)
                    <option value="{{ $valor }}" @selected($estado === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search" aria-hidden="true"></i> Buscar</button></div>
        </div>
    </form>
</div>

<div class="pacientes-tabla">
    <div class="tabla-titulo">
        <div>
            <h3>Cuentas de acceso</h3>
            <p>El administrador siempre tiene acceso a todo. A los demás se les marcan los permisos por módulo.</p>
        </div>
        <a href="{{ route('usuarios.crear') }}" class="btn btn-primary"><i class="bi bi-person-plus" aria-hidden="true"></i> Nuevo usuario</a>
    </div>
    @if($personasSinCuenta === 0)
    <p class="text-muted small mb-3">Todo el personal activo ya tiene cuenta. Para crear otra, primero registre a la persona en «Personal».</p>
    @endif
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr><th>Usuario</th><th>Persona</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                @forelse($usuarios as $cuenta)
                <tr>
                    <td><strong>{{ $cuenta->nombre_usuario }}</strong></td>
                    <td>{{ trim($cuenta->nombres.' '.$cuenta->apellidos) }}<small class="d-block text-muted">{{ $cuenta->nombre_cargo ?: 'Sin cargo' }}</small></td>
                    <td>@if(\App\Support\Permisos::rolEsAdministrador($cuenta->nombre_rol))<span class="badge bg-dark">{{ $cuenta->nombre_rol }} · acceso total</span>@else<span class="badge bg-light text-dark border">{{ $cuenta->nombre_rol }}</span>@endif</td>
                    <td><span class="badge {{ $cuenta->estado === 'Activo' ? 'bg-success' : ($cuenta->estado === 'Bloqueado' ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $cuenta->estado }}</span></td>
                    <td>{{ $cuenta->ultimo_acceso ? \Illuminate\Support\Carbon::parse($cuenta->ultimo_acceso, 'UTC')->setTimezone('America/Guatemala')->format('d/m/Y H:i') : 'Nunca' }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('usuarios.editar', $cuenta->id_usuario) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil" aria-hidden="true"></i> Editar y permisos</a>
                            @if((int) $cuenta->id_usuario !== (int) session('id_usuario'))
                            <form action="{{ route('usuarios.estado', $cuenta->id_usuario) }}" method="POST" class="m-0">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm {{ $cuenta->estado === 'Activo' ? 'btn-outline-danger' : 'btn-outline-success' }}"><i class="bi {{ $cuenta->estado === 'Activo' ? 'bi-person-x' : 'bi-person-check' }}" aria-hidden="true"></i> {{ $cuenta->estado === 'Activo' ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-muted">No hay usuarios con ese filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $usuarios->links() }}</div>
</div>
@endsection
