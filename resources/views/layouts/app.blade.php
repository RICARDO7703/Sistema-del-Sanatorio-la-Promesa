<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Sanatorio La Promesa')</title>

    <!-- Letra Nunito -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Estilos propios -->
    <link rel="stylesheet" href="{{ asset('css/estilos.css') }}">
</head>

@php
    $tituloPagina = \Illuminate\Support\Str::before($__env->yieldContent('title', 'Inicio'), ' - ');
@endphp

<body class="sistema-body">

    <div class="sistema-contenedor">

        <!-- Menú lateral -->
        <aside class="sidebar" id="barra-lateral" aria-label="Menú principal">

            <a class="sidebar-logo" href="{{ url('/inicio') }}" aria-label="Ir al inicio">
                <img
                    src="{{ asset('imagenes/logo-sanatorio.jpg') }}"
                    alt="Logo Sanatorio La Promesa"
                >
            </a>

            <nav class="nav flex-column sidebar-menu">

                <a class="nav-link @yield('menu-inicio')" href="{{ url('/inicio') }}">
                    <i class="bi bi-house-door"></i>
                    <span>Inicio</span>
                </a>

                @puede('Pacientes', 'Ver')
                <a class="nav-link @yield('menu-pacientes')" href="{{ url('/pacientes') }}">
                    <i class="bi bi-people"></i>
                    <span>Pacientes</span>
                </a>
                @endpuede

                @puede('Expedientes', 'Ver')
                <a class="nav-link @yield('menu-expedientes')" href="{{ route('expedientes.index') }}">
                    <i class="bi bi-folder2-open"></i>
                    <span>Expedientes</span>
                </a>
                @endpuede

                @puede('Ingresos', 'Ver')
                <a class="nav-link @yield('menu-atencion')" href="{{ route('atencion.index') }}">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <span>Atención del paciente</span>
                </a>
                @endpuede

                @puede('Personal', 'Ver')
                <a class="nav-link @yield('menu-personal')" href="{{ route('personal.index') }}">
                    <i class="bi bi-person-badge"></i>
                    <span>Personal</span>
                </a>
                @endpuede

                @puede('Habitaciones', 'Ver')
                <a class="nav-link @yield('menu-habitaciones')" href="{{ route('habitaciones.index') }}">
                    <i class="bi bi-door-open"></i>
                    <span>Habitaciones</span>
                </a>
                @endpuede

                @administrador
                <a class="nav-link @yield('menu-usuarios')" href="{{ route('usuarios.index') }}">
                    <i class="bi bi-person-lock"></i>
                    <span>Usuarios</span>
                </a>
                @endadministrador

                @puede('Bitácora', 'Ver')
                <a class="nav-link @yield('menu-bitacora')" href="{{ url('/bitacora') }}">
                    <i class="bi bi-clipboard-data"></i>
                    <span>Bitácora</span>
                </a>
                @endpuede

            </nav>

            <div class="sidebar-footer">

                <div class="turno-actual" aria-label="Turno actual">
                    <p class="turno-titulo"><i class="bi bi-clock" aria-hidden="true"></i> Turno actual</p>
                    <p class="turno-detalle">
                        {{ $turnoActual['nombre'] }} · {{ $turnoActual['horario'] }}<br>
                        {{ $internadosActuales }} {{ $internadosActuales === 1 ? 'paciente internado' : 'pacientes internados' }}
                    </p>
                </div>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-cerrar-sesion">
                        <i class="bi bi-box-arrow-left" aria-hidden="true"></i>
                        Cerrar sesión
                    </button>
                </form>

                <p class="sistema-pie">Sistema de Información Clínica<br><strong>Sanatorio La Promesa</strong> · © 2026</p>

            </div>

        </aside>

        <!-- Contenido principal -->
        <div class="area-principal">

            <!-- Barra superior -->
            <header class="barra-superior">
                <button type="button" class="sidebar-toggle" aria-controls="barra-lateral" aria-expanded="true">
                    <i class="bi bi-list" aria-hidden="true"></i>
                    <span>Ocultar menú</span>
                </button>

                <div class="barra-titulo">
                    <p>Sanatorio La Promesa</p>
                    <strong>{{ $tituloPagina }}</strong>
                </div>

                <div class="dropdown cuenta-menu">
                    <button class="usuario cuenta-boton" type="button" id="menu-cuenta" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir menú de mi cuenta">
                        @include('perfil.avatar',['personaAvatar'=>$cuentaActual])
                        <span class="cuenta-texto">
                            <span class="cuenta-nombre">{{ $cuentaActual ? trim($cuentaActual->nombres.' '.$cuentaActual->apellidos) : 'Mi cuenta' }}</span>
                            @if($cuentaActual?->nombre_cargo)<small class="cuenta-cargo">{{ $cuentaActual->nombre_cargo }}</small>@endif
                        </span>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end cuenta-opciones" aria-labelledby="menu-cuenta">
                        <li class="px-3 py-2"><strong class="d-block">{{ $cuentaActual?->nombre_usuario }}</strong><small class="text-muted">{{ $cuentaActual?->nombre_cargo }}</small></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('perfil.show') }}"><i class="bi bi-person" aria-hidden="true"></i> Mi perfil</a></li>
                        <li><a class="dropdown-item" href="{{ route('perfil.foto.editar') }}"><i class="bi bi-camera" aria-hidden="true"></i> Cambiar foto</a></li>
                        <li><a class="dropdown-item" href="{{ route('perfil.contrasena.editar') }}"><i class="bi bi-key" aria-hidden="true"></i> Cambiar contraseña</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesión</button></form></li>
                    </ul>
                </div>
            </header>

            <main class="contenido-principal">
                @if(session('permiso_denegado'))
                <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert"><i class="bi bi-shield-lock" aria-hidden="true"></i><span>{{ session('permiso_denegado') }}</span></div>
                @endif
                @yield('content')
            </main>

        </div>

    </div>

    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript propio -->
    <script src="{{ asset('js/scripts.js') }}"></script>

    <script src="{{ asset('js/bitacora-salida.js') }}" data-salida="{{ route('sesion.salida-pagina') }}" data-csrf="{{ csrf_token() }}" data-pagina="{{ (string) \Illuminate\Support\Str::uuid() }}" defer></script>
    @yield('scripts')

</body>

</html>
