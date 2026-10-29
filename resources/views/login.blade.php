<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f9fe">

    <title>Login - Sanatorio La Promesa</title>

    <!-- Letra Nunito -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <!-- Estilos propios -->
    <link rel="stylesheet" href="{{ asset('css/estilos.css') }}">
</head>

<body class="login-body login-acceso">

    <!-- Manchas de color difuminadas del fondo -->
    <div class="acceso-fondo" aria-hidden="true">
        <span class="acceso-mancha acceso-mancha-1"></span>
        <span class="acceso-mancha acceso-mancha-2"></span>
        <span class="acceso-mancha acceso-mancha-3"></span>
    </div>

    <main class="acceso-contenedor">

        <div class="acceso-card">

            <!-- Marca -->
            <div class="acceso-marca">
                <span class="acceso-emblema">
                    <img src="{{ asset('imagenes/emblema-sanatorio.png') }}" alt="" width="64" height="64">
                </span>
                <p class="acceso-sanatorio">Sanatorio</p>
                <p class="acceso-nombre">La Promesa</p>
                <span class="acceso-separador" aria-hidden="true"><span></span></span>
            </div>

            <h1 class="acceso-titulo">Bienvenido de vuelta</h1>
            <p class="acceso-subtitulo">Ingrese a su cuenta</p>

            @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

            <form action="{{ route('login.validar') }}" method="POST">

                @csrf

                <!-- Usuario -->
                <div class="mb-3">
                    <label class="form-label" for="usuario">Usuario</label>
                    <div class="login-input">
                        <i class="bi bi-person icono-izquierdo" aria-hidden="true"></i>
                        <input
                            type="text"
                            name="usuario"
                            id="usuario"
                            class="form-control"
                            placeholder="Ingrese su usuario"
                            value="{{ old('usuario') }}"
                            autocomplete="username"
                            autocapitalize="none"
                            required
                        >
                    </div>
                    @error('usuario')
                        <small class="acceso-error">{{ $message }}</small>
                    @enderror
                </div>

                <!-- Contraseña -->
                <div class="mb-3">
                    <label class="form-label" for="contrasena">Contraseña</label>
                    <div class="login-input">
                        <i class="bi bi-lock icono-izquierdo" aria-hidden="true"></i>
                        <input
                            type="password"
                            name="contrasena"
                            id="contrasena"
                            class="form-control"
                            placeholder="Ingrese su contraseña"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="icono-derecho"
                            id="mostrarContrasena"
                            aria-label="Mostrar contraseña"
                        ><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                    @error('contrasena')
                        <small class="acceso-error">{{ $message }}</small>
                    @enderror
                </div>

                @if(session('aviso_sesion'))
                    <div class="alert alert-info" role="status">{{ session('aviso_sesion') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <button type="submit" class="btn btn-primary w-100 btn-login">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    Ingresar
                </button>

            </form>

            <div class="text-center mt-3">
                <a href="{{ route('password.request') }}" class="acceso-enlace">¿Olvidaste tu contraseña?</a>
            </div>

            <div class="acceso-pie">
                <span><i class="bi bi-shield-check" aria-hidden="true"></i> Acceso seguro</span>
                <span>Sistema de Información Clínica · © 2026</span>
            </div>

        </div>

    </main>

    <script>
        const inputContrasena = document.getElementById('contrasena');
        const botonMostrar = document.getElementById('mostrarContrasena');

        botonMostrar.addEventListener('click', function () {
            const mostrar = inputContrasena.type === 'password';
            inputContrasena.type = mostrar ? 'text' : 'password';
            botonMostrar.querySelector('i').className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
            botonMostrar.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    </script>

</body>
</html>
