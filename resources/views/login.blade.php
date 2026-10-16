<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Sanatorio La Promesa</title>

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

<body class="login-body">

    <div class="login-contenedor">

        <div class="login-card">

            <!-- Logo -->
            <img
                src="{{ asset('imagenes/logo-sanatorio.jpg') }}"
                alt="Logo Sanatorio La Promesa"
                class="logo-login"
            >

            <!-- Separador -->
            <div class="separador-login">
                <span></span>
            </div>

            <h2 class="login-titulo">
                Iniciar sesión
            </h2>

            @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
            <form action="{{ route('login.validar') }}" method="POST">

                @csrf

                <!-- Usuario -->
                <div class="mb-4">

                    <label class="form-label">
                        Usuario
                    </label>

                    <div class="login-input">

                        <i class="bi bi-person-fill icono-izquierdo"></i>

                        <input
                            type="text"
                            name="usuario"
                            class="form-control"
                            placeholder="Ingrese su usuario"
                            value="{{ old('usuario') }}"
                            required
                        >

                    </div>

                    @error('usuario')
                        <small class="text-danger">
                            {{ $message }}
                        </small>
                    @enderror

                </div>

                <!-- Contraseña -->
                <div class="mb-3">

                    <label class="form-label">
                        Contraseña
                    </label>

                    <div class="login-input">

                        <i class="bi bi-lock-fill icono-izquierdo"></i>

                        <input
                            type="password"
                            name="contrasena"
                            id="contrasena"
                            class="form-control"
                            placeholder="Ingrese su contraseña"
                            required
                        >

                        <i
                            class="bi bi-eye-fill icono-derecho"
                            id="mostrarContrasena"
                            role="button"
                        ></i>

                    </div>

                    @error('contrasena')
                        <small class="text-danger">
                            {{ $message }}
                        </small>
                    @enderror

                </div>

                @if(session('aviso_sesion'))
                    <div class="alert alert-info" role="status">{{ session('aviso_sesion') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif

                <button
                    type="submit"
                    class="btn btn-primary w-100 btn-login"
                >
                    Iniciar sesión
                </button>

            </form>

            <div class="text-center mt-4">
                <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
            </div>

        </div>

    </div>

    <script>
        const inputContrasena = document.getElementById('contrasena');
        const botonMostrar = document.getElementById('mostrarContrasena');

        botonMostrar.addEventListener('click', function () {

            if (inputContrasena.type === 'password') {
                inputContrasena.type = 'text';
                botonMostrar.classList.remove('bi-eye-fill');
                botonMostrar.classList.add('bi-eye-slash-fill');
            } else {
                inputContrasena.type = 'password';
                botonMostrar.classList.remove('bi-eye-slash-fill');
                botonMostrar.classList.add('bi-eye-fill');
            }

        });
    </script>

</body>
</html>
