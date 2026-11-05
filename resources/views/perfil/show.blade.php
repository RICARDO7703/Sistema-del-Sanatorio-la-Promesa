@php
    $pantallas = [
        'perfil' => ['Mi perfil', 'bi-person-gear', 'Su cuenta y sus datos de contacto.'],
        'foto' => ['Cambiar foto', 'bi-camera', 'La foto aparece junto a su nombre en la parte superior del sistema.'],
        'contrasena' => ['Cambiar contraseña', 'bi-key', 'Escriba su contraseña actual y la nueva contraseña.'],
        'apariencia' => ['Apariencia', 'bi-circle-half', 'Elija cómo se ve el sistema en este dispositivo.'],
    ];
    [$tituloPerfil, $iconoPerfil, $descripcionPerfil] = $pantallas[$seccion];
@endphp
@extends('layouts.app')
@section('title', $tituloPerfil.' - Sanatorio La Promesa')
@section('menu-perfil', 'activo')
@section('content')
<div class="inicio-encabezado"><div class="inicio-icono"><i class="bi {{ $iconoPerfil }}" aria-hidden="true"></i></div><div><h1>{{ $tituloPerfil }}</h1><p>{{ $descripcionPerfil }}</p></div></div>

@if($seccion !== 'perfil')
<a href="{{ route('perfil.show') }}" class="btn btn-outline-secondary mb-3"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a mi perfil</a>
@endif

@if(session('exito'))<div class="alert alert-success" role="status">{{ session('exito') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><strong>No se guardaron los cambios.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if($seccion === 'perfil')
<section class="evolucion-card mb-4"><div class="d-flex flex-wrap align-items-center gap-3 mb-4">@include('perfil.avatar',['personaAvatar'=>$persona,'claseAvatar'=>'perfil-avatar-grande'])<div><h2 class="h4 mb-1">{{ $persona->nombres }} {{ $persona->apellidos }}</h2><p class="text-muted mb-0">{{ $persona->cargo?->nombre_cargo }}</p></div></div>
<dl class="enfermeria-datos"><div><dt><i class="bi bi-person-badge" aria-hidden="true"></i> Usuario</dt><dd>{{ $nombreUsuario }}</dd></div><div><dt><i class="bi bi-shield-check" aria-hidden="true"></i> Rol</dt><dd>{{ $rol }}</dd></div><div><dt><i class="bi bi-check-circle" aria-hidden="true"></i> Estado de la cuenta</dt><dd>{{ $estadoCuenta }}</dd></div></dl><p class="text-muted mt-3 mb-0">Para cambiar el nombre, cargo o permisos, comuníquese con el administrador.</p></section>

<section class="evolucion-card" id="contacto"><h2 class="h4"><i class="bi bi-envelope text-primary" aria-hidden="true"></i> Datos de contacto</h2><form action="{{ route('perfil.contacto') }}" method="POST" data-validar-en-linea data-validar-al-enviar>@csrf @method('PATCH')
<label for="perfil-telefono" class="form-label">Teléfono</label><input id="perfil-telefono" name="telefono" class="form-control mb-3" inputmode="tel" pattern="[0-9]{4}-?[0-9]{4}" maxlength="9" value="{{ old('telefono',$persona->telefono) }}">
<label for="perfil-correo" class="form-label">Correo electrónico</label><input id="perfil-correo" type="email" name="correo" class="form-control mb-3" maxlength="150" value="{{ old('correo',$persona->correo) }}">
<label for="perfil-clave-contacto" class="form-label">Contraseña actual (si cambia el correo)</label><input id="perfil-clave-contacto" type="password" name="clave_contacto" class="form-control mb-2" autocomplete="current-password" maxlength="100"><p class="text-muted">Confirme su contraseña al cambiar o quitar el correo usado para recuperar su cuenta.</p>
<button class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Guardar datos de contacto</button></form></section>
@endif

@if($seccion === 'foto')
<section class="evolucion-card perfil-pantalla" id="foto">
<div class="d-flex flex-wrap align-items-center gap-3 mb-3">@include('perfil.avatar',['personaAvatar'=>$persona,'claseAvatar'=>'perfil-avatar-grande'])<div><h2 class="h5 mb-1">{{ $persona->foto_perfil ? 'Foto actual' : 'Todavía no tiene foto' }}</h2><p class="text-muted mb-0">{{ $persona->foto_perfil ? 'Puede reemplazarla o quitarla.' : 'Mientras no agregue una, se mostrará el icono de usuario.' }}</p></div></div>
<form action="{{ route('perfil.foto') }}" method="POST" enctype="multipart/form-data">@csrf<label for="perfil-foto" class="form-label">Seleccione su foto</label><input id="perfil-foto" type="file" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp" required><p class="text-muted mt-2">JPG, PNG o WEBP. Máximo 2 MB. Entre 64 y 4096 píxeles por lado.</p><button class="btn btn-primary"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> {{ $persona->foto_perfil ? 'Reemplazar foto' : 'Guardar foto' }}</button></form>
@if($persona->foto_perfil)<form class="mt-3" action="{{ route('perfil.quitar-foto') }}" method="POST">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash" aria-hidden="true"></i> Quitar foto</button></form>@endif
</section>
@endif

@if($seccion === 'apariencia')
<section class="evolucion-card perfil-pantalla" id="apariencia" aria-labelledby="titulo-apariencia">
    <h2 class="h5 mb-1" id="titulo-apariencia">Modo de color</h2>
    <p class="text-muted">El cambio se aplica de inmediato y se recuerda en este dispositivo (computadora o teléfono).</p>
    <div class="tema-opciones" role="radiogroup" aria-label="Modo de color">
        <input type="radio" class="btn-check" name="tema" id="tema-claro" value="claro" data-tema-opcion autocomplete="off">
        <label class="tema-opcion" for="tema-claro">
            <span class="tema-opcion-titulo"><span><i class="bi bi-sun me-2" aria-hidden="true"></i>Claro</span><i class="bi bi-check-circle-fill" aria-hidden="true"></i></span>
            <span class="tema-muestra tema-muestra-claro" aria-hidden="true">
                <span class="tema-muestra-menu"><span></span><span></span><span></span><span></span></span>
                <span class="tema-muestra-cuerpo"><span></span><span class="tema-muestra-tarjeta"><span></span><span></span><span></span></span></span>
            </span>
            <small class="text-muted">Fondo claro, el diseño de siempre.</small>
        </label>
        <input type="radio" class="btn-check" name="tema" id="tema-oscuro" value="oscuro" data-tema-opcion autocomplete="off">
        <label class="tema-opcion" for="tema-oscuro">
            <span class="tema-opcion-titulo"><span><i class="bi bi-moon-stars me-2" aria-hidden="true"></i>Oscuro</span><i class="bi bi-check-circle-fill" aria-hidden="true"></i></span>
            <span class="tema-muestra tema-muestra-oscuro" aria-hidden="true">
                <span class="tema-muestra-menu"><span></span><span></span><span></span><span></span></span>
                <span class="tema-muestra-cuerpo"><span></span><span class="tema-muestra-tarjeta"><span></span><span></span><span></span></span></span>
            </span>
            <small class="text-muted">Fondo oscuro, cansa menos la vista en turnos de noche.</small>
        </label>
    </div>
    <p class="small text-muted mt-3 mb-0" role="status" data-tema-aviso></p>
</section>
@endif

@if($seccion === 'contrasena')
<section class="evolucion-card perfil-pantalla" id="contrasena"><form action="{{ route('perfil.contrasena') }}" method="POST" data-validar-en-linea data-validar-al-enviar>@csrf
<div class="row g-3"><div class="col-12"><label for="perfil-actual" class="form-label">Contraseña actual *</label><input id="perfil-actual" type="password" name="contrasena_actual" class="form-control" required autocomplete="current-password" maxlength="100"></div><div class="col-12"><label for="perfil-nueva" class="form-label">Nueva contraseña *</label><input id="perfil-nueva" type="password" name="nueva_contrasena" class="form-control" required minlength="5" maxlength="72" autocomplete="new-password"></div><div class="col-12"><label for="perfil-confirmar" class="form-label">Repita la nueva contraseña *</label><input id="perfil-confirmar" type="password" name="nueva_contrasena_confirmation" class="form-control" required minlength="5" maxlength="72" autocomplete="new-password"></div></div><p class="text-muted mt-3">Use al menos 5 caracteres y al menos un número.</p><button class="btn btn-primary"><i class="bi bi-shield-lock" aria-hidden="true"></i> Cambiar contraseña</button></form></section>
@endif
@endsection
