<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Sanatorio La Promesa')</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Estilos propios -->
    <link rel="stylesheet" href="{{ asset('css/estilos.css') }}">
</head>

<body>

    <div class="sistema-contenedor">

        <!-- Menú lateral -->
        <aside class="sidebar" id="barra-lateral" aria-label="Menú principal">

            <div class="sidebar-logo">
                <img
                    src="{{ asset('imagenes/logo-sanatorio.jpg') }}"
                    alt="Logo Sanatorio La Promesa"
                >
            </div>

            <nav class="nav flex-column sidebar-menu">

                <a class="nav-link @yield('menu-inicio')" href="{{ url('/inicio') }}">
                    <i class="bi bi-house-door"></i>
                    <span>Inicio</span>
                </a>

                <a class="nav-link @yield('menu-pacientes')" href="{{ url('/pacientes') }}">
                    <i class="bi bi-people"></i>
                    <span>Pacientes</span>
                </a>

                <a class="nav-link @yield('menu-expedientes')" href="{{ route('expedientes.index') }}">
                    <i class="bi bi-folder2-open"></i>
                    <span>Expedientes</span>
                </a>

                <a class="nav-link @yield('menu-atencion')" href="{{ route('atencion.index') }}">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <span>Atención del paciente</span>
                </a>

                <a class="nav-link @yield('menu-personal')" href="{{ route('personal.index') }}">
                    <i class="bi bi-person-badge"></i>
                    <span>Personal</span>
                </a>

                <a class="nav-link @yield('menu-habitaciones')" href="{{ route('habitaciones.index') }}">
                    <i class="bi bi-door-open"></i>
                    <span>Habitaciones</span>
                </a>

                <a class="nav-link @yield('menu-bitacora')" href="{{ url('/bitacora') }}">
                    <i class="bi bi-clipboard-data"></i>
                    <span>Bitácora</span>
                </a>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf

                    <button
                        type="submit"
                        class="menu-item border-0 bg-transparent w-100 text-start"
                    >
                        <i class="bi bi-box-arrow-left"></i>
                        Cerrar sesión
                    </button>
                </form>

            </nav>

            <div class="sidebar-footer">

                <div class="linea-footer"></div>

                <div class="sistema-info">
                    <i class="bi bi-shield-check"></i>

                    <div>
                        <span>Sistema de Información Clínica</span>
                        <strong>Sanatorio La Promesa</strong>
                    </div>
                </div>

                <p>© 2026 Todos los derechos reservados.</p>

            </div>

        </aside>

        <!-- Contenido principal -->
        <main class="contenido-principal">

            <!-- Usuario -->
            <div class="barra-superior">
                <button type="button" class="btn btn-outline-primary sidebar-toggle" aria-controls="barra-lateral" aria-expanded="true"><i class="bi bi-layout-sidebar" aria-hidden="true"></i> <span>Ocultar menú</span></button>

                <div class="usuario">
                    <i class="bi bi-person-circle"></i>
                    <span>Usuario</span>
                    <i class="bi bi-chevron-down"></i>
                </div>

            </div>

            @yield('content')

        </main>

    </div>

    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript propio -->
    <script src="{{ asset('js/scripts.js') }}"></script>

    @yield('scripts')

</body>

</html>