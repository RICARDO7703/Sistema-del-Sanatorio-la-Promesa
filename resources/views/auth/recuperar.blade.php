<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Recuperar contraseña - Sanatorio La Promesa</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/estilos.css') }}">
</head>
<body class="login-body">
<main class="login-contenedor">
    <div class="login-card">
        <img src="{{ asset('imagenes/logo-sanatorio.jpg') }}" alt="Sanatorio La Promesa" class="logo-login">
        <h1 class="login-titulo">{{ ['solicitar' => 'Recuperar contraseña', 'codigo' => 'Verificar código', 'nueva' => 'Nueva contraseña'][$paso] }}</h1>
        <p class="text-center text-secondary">Paso {{ ['solicitar' => 1, 'codigo' => 2, 'nueva' => 3][$paso] }} de 3</p>
        @if(session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul></div>
        @endif
        <form method="POST" action="{{ route(['solicitar' => 'password.email', 'codigo' => 'password.verify', 'nueva' => 'password.update'][$paso]) }}">
            @csrf
            @if($paso === 'solicitar')
                <p>Escribe tu usuario y el correo registrado en tu ficha de personal. Te enviaremos un código para verificar tu identidad.</p>
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <input class="form-control" id="usuario" name="usuario" autocomplete="username" maxlength="50" value="{{ old('usuario') }}" required>
                </div>
                <div class="mb-4">
                    <label for="correo" class="form-label">Correo electrónico</label>
                    <input class="form-control" id="correo" name="correo" type="email" autocomplete="email" maxlength="150" value="{{ old('correo') }}" required>
                </div>
            @elseif($paso === 'codigo')
                <p id="ayuda-codigo">Escribe el token de 6 dígitos recibido por correo. Caduca en 10 minutos y admite hasta 5 intentos.</p>
                <div class="mb-4">
                    <label for="codigo" class="form-label">Código de verificación (token)</label>
                    <input class="form-control text-center fs-3" id="codigo" name="codigo" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" aria-describedby="ayuda-codigo" required autofocus>
                </div>
            @else
                <p id="ayuda-password">Código verificado. Usa al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos.</p>
                <div class="mb-3">
                    <label for="password" class="form-label">Nueva contraseña</label>
                    <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="ayuda-password" required>
                </div>
                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
                </div>
            @endif
            <button type="submit" class="btn btn-primary w-100 btn-login">{{ ['solicitar' => 'Enviar código', 'codigo' => 'Verificar código', 'nueva' => 'Guardar contraseña'][$paso] }}</button>
        </form>
        @if($paso !== 'solicitar')
            <p class="text-center mt-3"><a href="{{ route('password.request') }}">Solicitar otro código</a></p>
        @endif
        <p class="text-center mt-4 mb-0"><a href="{{ url('/') }}">Volver a iniciar sesión</a></p>
    </div>
</main>
</body>
</html>
