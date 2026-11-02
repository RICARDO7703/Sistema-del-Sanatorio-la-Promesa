@php
    $segundos = (int) (isset($exception) && method_exists($exception, 'getHeaders') ? ($exception->getHeaders()['Retry-After'] ?? 60) : 60);
    $segundos = max(1, min($segundos, 3600));
    $anterior = url()->previous();
    $volver = $anterior && $anterior !== url()->current() ? $anterior : url('/');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.tema')
    <meta name="theme-color" content="#f5f9fe">
    <title>Demasiados intentos - Sanatorio La Promesa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/estilos.css') }}">
</head>
<body class="login-body login-acceso">
    <div class="acceso-fondo" aria-hidden="true">
        <span class="acceso-mancha acceso-mancha-1"></span>
        <span class="acceso-mancha acceso-mancha-2"></span>
        <span class="acceso-mancha acceso-mancha-3"></span>
    </div>
    <main class="acceso-contenedor">
        <div class="acceso-card text-center">
            <div class="acceso-marca">
                <span class="acceso-emblema">
                    <img src="{{ asset('imagenes/emblema-sanatorio.png') }}" alt="" width="64" height="64">
                </span>
                <p class="acceso-sanatorio">Sanatorio</p>
                <p class="acceso-nombre">La Promesa</p>
                <span class="acceso-separador" aria-hidden="true"><span></span></span>
            </div>
            <h1 class="acceso-titulo"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Demasiados intentos</h1>
            <p class="acceso-subtitulo">Por seguridad, el sistema detuvo los intentos por un momento.</p>
            <div class="alert alert-warning text-start" role="status" aria-live="polite">
                Espere <strong data-espera="{{ $segundos }}">{{ $segundos }} {{ $segundos === 1 ? 'segundo' : 'segundos' }}</strong> y vuelva a intentarlo. No hace falta cerrar el navegador.
            </div>
            <a href="{{ $volver }}" class="btn btn-primary w-100 btn-login"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Volver a intentar</a>
            <div class="mt-3">
                <a href="{{ route('password.request') }}" class="acceso-enlace">¿Olvidaste tu contraseña?</a>
            </div>
            <div class="acceso-pie">
                <span><i class="bi bi-shield-check" aria-hidden="true"></i> Acceso seguro</span>
                <span>Sistema de Información Clínica · © 2026</span>
            </div>
        </div>
    </main>
    <script>
        (() => {
            const contador = document.querySelector('[data-espera]');
            let restantes = Number(contador.dataset.espera);
            const temporizador = setInterval(() => {
                restantes -= 1;
                if (restantes <= 0) {
                    clearInterval(temporizador);
                    const aviso = contador.closest('.alert');
                    aviso.classList.replace('alert-warning', 'alert-success');
                    aviso.textContent = 'Ya puede volver a intentarlo.';
                    return;
                }
                contador.textContent = restantes + (restantes === 1 ? ' segundo' : ' segundos');
            }, 1000);
        })();
    </script>
</body>
</html>
