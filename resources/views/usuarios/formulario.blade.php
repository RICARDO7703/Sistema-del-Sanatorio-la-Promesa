@extends('layouts.app')

@section('title', ($usuario ? 'Editar usuario' : 'Nuevo usuario').' - Sanatorio La Promesa')

@section('menu-usuarios', 'activo')

@php
    $esNuevo = $usuario === null;
    $marcados = old('permisos', $marcados);
    $rolElegido = (string) old('id_rol', $usuario?->id_rol ?? '');
    $datosRoles = $roles->mapWithKeys(fn ($rol) => [$rol->id_rol => [
        'administrador' => \App\Support\Permisos::rolEsAdministrador($rol->nombre_rol),
        'sugeridos' => collect(\App\Support\Permisos::SUGERIDOS[$rol->nombre_rol] ?? [])->flatMap(fn ($acciones, $modulo) => array_map(fn ($accion) => $modulo.'|'.$accion, $acciones))->values(),
    ]]);
@endphp

@section('content')
<div class="inicio-encabezado">
    <div class="inicio-icono"><i class="bi {{ $esNuevo ? 'bi-person-plus' : 'bi-person-gear' }}" aria-hidden="true"></i></div>
    <div>
        <h1>{{ $esNuevo ? 'Nuevo usuario' : 'Editar usuario' }}</h1>
        <p>{{ $esNuevo ? 'Cree la cuenta de acceso de una persona del personal y elija sus permisos.' : trim($usuario->nombres.' '.$usuario->apellidos).' · '.$usuario->nombre_usuario }}</p>
    </div>
</div>

<a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary mb-3"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a usuarios</a>

@if(session('exito'))<div class="alert alert-success" role="status">{{ session('exito') }}</div>@endif
@if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><strong>No se guardaron los cambios.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<form action="{{ $esNuevo ? route('usuarios.guardar') : route('usuarios.actualizar', $usuario->id_usuario) }}" method="POST" enctype="multipart/form-data" novalidate data-validar-en-linea data-validar-al-enviar id="form-usuario">
    @csrf
    @unless($esNuevo) @method('PUT') @endunless

    <section class="evolucion-card mb-4">
        <h2 class="h4 mb-3"><i class="bi bi-person-badge text-primary" aria-hidden="true"></i> Cuenta</h2>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                @if($esNuevo)
                <label class="form-label" for="usuario-persona">Persona del personal *</label>
                <select id="usuario-persona" name="id_personal" class="form-select" required data-mensaje-requerido="Elija a la persona que usará la cuenta.">
                    <option value="">Seleccione</option>
                    @foreach($personas as $persona)
                    <option value="{{ $persona->id_personal }}" @selected((string) old('id_personal') === (string) $persona->id_personal)>{{ trim($persona->nombres.' '.$persona->apellidos) }}{{ $persona->cargo ? ' · '.$persona->cargo->nombre_cargo : '' }}</option>
                    @endforeach
                </select>
                @if($personas->isEmpty())<small class="text-danger d-block mt-1">Todo el personal activo ya tiene cuenta. Registre primero a la persona en «Personal».</small>@else<small class="text-muted">Solo aparece el personal activo que todavía no tiene cuenta.</small>@endif
                @else
                <p class="form-label mb-1">Persona</p>
                <p class="mb-0">{{ trim($usuario->nombres.' '.$usuario->apellidos) }} <span class="text-muted">· {{ $usuario->nombre_cargo ?: 'Sin cargo' }}</span></p>
                <p class="small mb-0 mt-1">Estado de la cuenta: <span class="badge {{ $usuario->estado === 'Activo' ? 'bg-success' : ($usuario->estado === 'Bloqueado' ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $usuario->estado }}</span></p>
                @endif
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="usuario-nombre">Nombre de usuario *</label>
                <input id="usuario-nombre" name="nombre_usuario" class="form-control" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._\-]+" autocapitalize="none" autocomplete="off" placeholder="Ej. dra.lopez" value="{{ old('nombre_usuario', $usuario?->nombre_usuario) }}" data-mensaje-requerido="Escriba el nombre de usuario." data-mensaje-formato="Use solo letras sin tilde, números, punto, guion o guion bajo.">
                <small class="text-muted">Con este nombre inicia sesión. Sin espacios ni tildes.</small>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="usuario-rol">Rol *</label>
                <select id="usuario-rol" name="id_rol" class="form-select" required data-mensaje-requerido="Elija el rol.">
                    <option value="">Seleccione</option>
                    @foreach($roles as $rol)
                    <option value="{{ $rol->id_rol }}" @selected($rolElegido === (string) $rol->id_rol)>{{ $rol->nombre_rol }}</option>
                    @endforeach
                </select>
            </div>
            @if($esNuevo)
            <div class="col-12 col-md-6">
                <label class="form-label" for="usuario-contrasena">Contraseña inicial *</label>
                <input id="usuario-contrasena" type="password" name="contrasena" class="form-control" required minlength="12" maxlength="72" autocomplete="new-password" data-mensaje-requerido="Escriba la contraseña.">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="usuario-contrasena-2">Repita la contraseña *</label>
                <input id="usuario-contrasena-2" type="password" name="contrasena_confirmation" class="form-control" required minlength="12" maxlength="72" autocomplete="new-password" data-mensaje-requerido="Repita la contraseña.">
            </div>
            <div class="col-12"><small class="text-muted">Al menos 12 caracteres, con mayúsculas, minúsculas, números y un símbolo. La persona podrá cambiarla en «Mi perfil».</small></div>
            @endif
            <div class="col-12">
                <span class="form-label d-block">Foto de perfil <span class="text-muted fw-normal">(opcional)</span></span>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span data-vista-foto>
                        @include('perfil.avatar', ['personaAvatar' => $usuario, 'claseAvatar' => 'perfil-avatar-grande'])
                    </span>
                    <div class="flex-grow-1" style="min-width: 0">
                        <label class="visually-hidden" for="usuario-foto">Foto de perfil</label>
                        <input id="usuario-foto" type="file" name="foto" class="form-control @error('foto') is-invalid @enderror" accept="image/jpeg,image/png,image/webp" data-elegir-foto>
                        @error('foto')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <small class="text-muted d-block">JPG, PNG o WEBP de hasta 2 MB. Se muestra en el menú y en «Personal».</small>
                        @if(! $esNuevo && $usuario->foto_perfil)
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="quitar_foto" value="1" id="usuario-quitar-foto" @checked(old('quitar_foto'))>
                            <label class="form-check-label" for="usuario-quitar-foto">Quitar la foto actual</label>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="evolucion-card mb-4" aria-labelledby="titulo-permisos">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h4 mb-0" id="titulo-permisos"><i class="bi bi-shield-check text-primary" aria-hidden="true"></i> Permisos por módulo</h2>
            <div class="d-flex flex-wrap gap-2" data-acciones-permisos>
                <button type="button" class="btn btn-outline-primary btn-sm" data-aplicar-sugeridos><i class="bi bi-magic" aria-hidden="true"></i> Marcar los sugeridos del rol</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-quitar-permisos><i class="bi bi-x-square" aria-hidden="true"></i> Quitar todos</button>
            </div>
        </div>
        <div class="alert alert-dark d-flex gap-2" role="note" data-aviso-administrador hidden><i class="bi bi-stars" aria-hidden="true"></i><span>El rol de administrador tiene acceso a todo el sistema, incluida la pantalla de Usuarios. No hace falta marcar permisos.</span></div>
        <div data-tabla-permisos>
            <p class="text-muted">«Ver» permite entrar al módulo. «Guardar» registra información nueva, «Editar» corrige lo registrado y «Eliminar» desactiva, cancela o anula. Lo que no aplica a un módulo aparece con una raya.</p>
            <div class="table-responsive">
                <table class="table align-middle tabla-permisos">
                    <thead>
                        <tr><th>Módulo</th>@foreach(\App\Support\Permisos::ACCIONES as $accion => $nombreAccion)<th class="text-center">{{ $nombreAccion }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach(\App\Support\Permisos::MODULOS as $modulo => [$nombreModulo, $descripcionModulo, $acciones])
                        <tr data-modulo="{{ $modulo }}">
                            <td><strong>{{ $nombreModulo }}</strong><small class="d-block text-muted">{{ $descripcionModulo }}</small></td>
                            @foreach(\App\Support\Permisos::ACCIONES as $accion => $nombreAccion)
                            <td class="text-center">
                                @if(in_array($accion, $acciones, true))
                                @php($clave = $modulo.'|'.$accion)
                                <input class="form-check-input permiso-casilla" type="checkbox" name="permisos[]" value="{{ $clave }}" id="permiso-{{ md5($clave) }}" data-accion="{{ $accion }}" @checked(in_array($clave, $marcados, true)) aria-label="{{ $nombreAccion }} en {{ $nombreModulo }}">
                                @else
                                <span class="text-muted" aria-label="No aplica">—</span>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <button type="submit" class="btn btn-primary btn-lg mb-4"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ $esNuevo ? 'Crear usuario' : 'Guardar cambios' }}</button>
</form>

@unless($esNuevo)
<section class="evolucion-card mb-4" id="restablecer">
    <h2 class="h4"><i class="bi bi-key text-primary" aria-hidden="true"></i> Restablecer contraseña</h2>
    <p class="text-muted">Úselo si la persona olvidó su contraseña. Escriba una nueva y entréguela en persona.</p>
    <form action="{{ route('usuarios.contrasena', $usuario->id_usuario) }}" method="POST" novalidate data-validar-en-linea data-validar-al-enviar>
        @csrf @method('PATCH')
        <div class="row g-3">
            <div class="col-12 col-md-6"><label class="form-label" for="restablecer-contrasena">Contraseña nueva *</label><input id="restablecer-contrasena" type="password" name="contrasena" class="form-control" required minlength="12" maxlength="72" autocomplete="new-password" data-mensaje-requerido="Escriba la contraseña nueva."></div>
            <div class="col-12 col-md-6"><label class="form-label" for="restablecer-contrasena-2">Repita la contraseña *</label><input id="restablecer-contrasena-2" type="password" name="contrasena_confirmation" class="form-control" required minlength="12" maxlength="72" autocomplete="new-password" data-mensaje-requerido="Repita la contraseña nueva."></div>
        </div>
        <p class="text-muted small mt-2">Al menos 12 caracteres, con mayúsculas, minúsculas, números y un símbolo.</p>
        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-key" aria-hidden="true"></i> Restablecer contraseña</button>
    </form>
</section>
@if($usuario->id_usuario !== (int) session('id_usuario'))
<section class="evolucion-card mb-4">
    <h2 class="h4"><i class="bi bi-power text-primary" aria-hidden="true"></i> Acceso</h2>
    <p class="text-muted">{{ $usuario->estado === 'Activo' ? 'Si la persona ya no trabaja en el sanatorio, desactive su cuenta. Sus registros se conservan.' : 'La cuenta no puede iniciar sesión. Actívela para devolverle el acceso.' }}</p>
    <form action="{{ route('usuarios.estado', $usuario->id_usuario) }}" method="POST" class="m-0">
        @csrf @method('PATCH')
        <button type="submit" class="btn {{ $usuario->estado === 'Activo' ? 'btn-outline-danger' : 'btn-outline-success' }}"><i class="bi {{ $usuario->estado === 'Activo' ? 'bi-person-x' : 'bi-person-check' }}" aria-hidden="true"></i> {{ $usuario->estado === 'Activo' ? 'Desactivar cuenta' : 'Activar cuenta' }}</button>
    </form>
</section>
@endif
@endunless
@endsection

@section('scripts')
<script>
// Vista previa de la foto elegida antes de guardar.
(() => {
    const campo = document.querySelector('[data-elegir-foto]');
    const vista = document.querySelector('[data-vista-foto] .perfil-avatar');
    if (!campo || !vista) return;
    const original = vista.innerHTML;
    campo.addEventListener('change', () => {
        const archivo = campo.files?.[0];
        if (!archivo || !archivo.type.startsWith('image/')) { vista.innerHTML = original; return; }
        const imagen = document.createElement('img');
        imagen.alt = 'Vista previa de la foto';
        imagen.src = URL.createObjectURL(archivo);
        vista.replaceChildren(imagen);
        const quitar = document.getElementById('usuario-quitar-foto');
        if (quitar) quitar.checked = false;
    });
})();
</script>
<script>
(() => {
    const roles = @json($datosRoles);
    const esNuevo = @json($esNuevo);
    const selectRol = document.getElementById('usuario-rol');
    const casillas = [...document.querySelectorAll('.permiso-casilla')];
    const tabla = document.querySelector('[data-tabla-permisos]');
    const aviso = document.querySelector('[data-aviso-administrador]');
    const acciones = document.querySelector('[data-acciones-permisos]');

    const actualizarVista = () => {
        const rol = roles[selectRol.value];
        const administrador = Boolean(rol && rol.administrador);
        tabla.hidden = administrador;
        acciones.hidden = administrador;
        aviso.hidden = !administrador;
    };
    const aplicarSugeridos = () => {
        const rol = roles[selectRol.value];
        const sugeridos = rol ? rol.sugeridos : [];
        casillas.forEach(casilla => { casilla.checked = sugeridos.includes(casilla.value); });
    };

    // Guardar, Editar o Eliminar necesitan «Ver»; quitar «Ver» quita lo demás del módulo.
    casillas.forEach(casilla => casilla.addEventListener('change', () => {
        const fila = casilla.closest('tr');
        const ver = fila.querySelector('[data-accion="Ver"]');
        if (!ver) return;
        if (casilla === ver && !ver.checked) {
            fila.querySelectorAll('.permiso-casilla').forEach(otra => { otra.checked = false; });
        } else if (casilla !== ver && casilla.checked) {
            ver.checked = true;
        }
    }));

    selectRol.addEventListener('change', () => {
        if (esNuevo) aplicarSugeridos();
        actualizarVista();
    });
    document.querySelector('[data-aplicar-sugeridos]').addEventListener('click', aplicarSugeridos);
    document.querySelector('[data-quitar-permisos]').addEventListener('click', () => casillas.forEach(casilla => { casilla.checked = false; }));
    actualizarVista();
})();
</script>
@endsection
