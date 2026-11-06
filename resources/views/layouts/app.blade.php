<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.tema')

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
    <link rel="stylesheet" href="{{ asset('css/estilos.css') }}?v={{ filemtime(public_path('css/estilos.css')) }}">
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

            @php
                $permisosMenu = app(\App\Support\Permisos::class);
                $activo = fn (string $seccion): bool => trim($__env->yieldContent('menu-'.$seccion)) !== '';
                $verPermisos = request()->routeIs('usuarios.index') && request('seccion') === 'permisos';
                // Módulos en el orden de trabajo, agrupados; un grupo sin opciones permitidas no se muestra.
                $gruposMenu = array_filter([
                    ['clave' => 'datos', 'opciones' => array_filter([
                        $permisosMenu->puede('Personal', 'Ver') ? ['personal', route('personal.index'), 'bi-person-badge', 'Personal'] : null,
                        $permisosMenu->puede('Habitaciones', 'Ver') ? ['habitaciones', route('habitaciones.index'), 'bi-door-open', 'Habitaciones'] : null,
                    ])],
                    ['clave' => 'pacientes', 'opciones' => array_filter([
                        $permisosMenu->puede('Pacientes', 'Ver') ? ['pacientes', url('/pacientes'), 'bi-people', 'Pacientes'] : null,
                        $permisosMenu->puede('Ingresos', 'Ver') ? ['atencion', route('atencion.index'), 'bi-clipboard2-pulse', 'Atención del paciente'] : null,
                    ])],
                    ['clave' => 'consultas', 'opciones' => array_filter([
                        $permisosMenu->puede('Expedientes', 'Ver') ? ['expedientes', route('expedientes.index'), 'bi-folder2-open', 'Expedientes'] : null,
                        $permisosMenu->puede('Reportes', 'Ver') ? ['reportes', route('reportes.index'), 'bi-file-earmark-pdf', 'Reportes'] : null,
                    ])],
                    ['clave' => 'seguridad', 'opciones' => array_filter([
                        $permisosMenu->esAdministrador() ? ['usuarios', route('usuarios.index'), 'bi-person-lock', 'Usuarios', ! $verPermisos] : null,
                        $permisosMenu->esAdministrador() ? ['usuarios', route('usuarios.index', ['seccion' => 'permisos']), 'bi-shield-check', 'Asignación de permisos', $verPermisos] : null,
                        $permisosMenu->puede('Bitácora', 'Ver') ? ['bitacora', url('/bitacora'), 'bi-clipboard-data', 'Bitácora'] : null,
                    ])],
                ], fn (array $grupo): bool => $grupo['opciones'] !== []);
            @endphp
            <nav class="nav flex-column sidebar-menu" aria-label="Menú principal">

                <a class="nav-link @yield('menu-inicio')" href="{{ url('/inicio') }}" title="Inicio">
                    <i class="bi bi-house-door"></i>
                    <span>Inicio</span>
                </a>

                @foreach($gruposMenu as $grupo)
                {{-- Los módulos van en el orden de trabajo; una línea fina separa cada grupo. --}}
                <hr class="menu-separador" aria-hidden="true">
                @foreach($grupo['opciones'] as $opcion)
                @php
                    $opcionActiva = ($opcion[4] ?? true) && $activo($opcion[0]);
                @endphp
                <a class="nav-link {{ $opcionActiva ? 'activo' : '' }}" href="{{ $opcion[1] }}" title="{{ $opcion[3] }}" @if($opcionActiva) aria-current="page" @endif>
                    <i class="bi {{ $opcion[2] }}"></i>
                    <span>{{ $opcion[3] }}</span>
                </a>
                @endforeach
                @endforeach

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
                    <button type="submit" class="btn-cerrar-sesion" title="Cerrar sesión">
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

                {{-- En el teléfono, el emblema lleva al inicio; el menú se abre desde la barra de abajo. --}}
                <a class="barra-emblema" href="{{ url('/inicio') }}" aria-label="Ir al inicio"><img src="{{ asset('imagenes/emblema-sanatorio.png') }}" alt=""></a>

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
                        <li><button type="button" class="dropdown-item" data-tema-alternar><i class="bi bi-moon-stars" aria-hidden="true" data-tema-icono></i> <span data-tema-texto>Cambiar a modo oscuro</span></button></li>
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

    {{-- Fondo oscuro detrás del menú abierto en teléfono y tablet; tocarlo cierra el menú. --}}
    <div class="menu-fondo" data-cerrar-menu hidden></div>

    {{-- Teléfono: accesos a los módulos de uso diario; «Menú» abre la barra lateral con todos los demás. --}}
    @php
        $accesosInferiores = array_filter([
            ['inicio', url('/inicio'), 'bi-house-door', 'Inicio'],
            $permisosMenu->puede('Pacientes', 'Ver') ? ['pacientes', url('/pacientes'), 'bi-people', 'Pacientes'] : null,
            $permisosMenu->puede('Ingresos', 'Ver') ? ['atencion', route('atencion.index'), 'bi-clipboard2-pulse', 'Atención'] : null,
            $permisosMenu->puede('Expedientes', 'Ver') ? ['expedientes', route('expedientes.index'), 'bi-folder2-open', 'Expedientes'] : null,
        ]);
    @endphp
    <nav class="barra-inferior" aria-label="Accesos rápidos">
        @foreach($accesosInferiores as $acceso)
        @php
            $accesoActivo = $activo($acceso[0]);
        @endphp
        <a class="barra-inferior-opcion {{ $accesoActivo ? 'activo' : '' }}" href="{{ $acceso[1] }}" @if($accesoActivo) aria-current="page" @endif>
            <i class="bi {{ $acceso[2] }}" aria-hidden="true"></i><span>{{ $acceso[3] }}</span>
        </a>
        @endforeach
        <button type="button" class="barra-inferior-opcion" data-abrir-menu aria-controls="barra-lateral" aria-expanded="false">
            <i class="bi bi-list" aria-hidden="true"></i><span>Menú</span>
        </button>
    </nav>

    @stack('modales')
    {{-- Ventana de confirmación del sistema (reemplaza los avisos del navegador). Se usa con data-confirmar="mensaje" en un formulario. --}}
    <div class="modal fade" id="ventanaConfirmar" tabindex="-1" aria-labelledby="ventanaConfirmarTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="ventanaConfirmarTitulo"><i class="bi bi-exclamation-triangle text-warning" aria-hidden="true"></i> <span data-confirmar-titulo>Confirmar acción</span></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><p class="mb-0" data-confirmar-mensaje></p></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-confirmar-volver>Cancelar</button><button type="button" class="btn btn-danger" data-confirmar-aceptar>Aceptar</button></div>
        </div></div>
    </div>
    <div class="modal fade" id="visorDocumentoSubido" tabindex="-1" aria-labelledby="visorDocumentoTitulo" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5"><i class="bi bi-file-earmark-check text-primary" aria-hidden="true"></i> <span id="visorDocumentoTitulo">Documento del expediente</span></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><p class="text-break text-muted" data-visor-nombre></p><div data-visor-contenido></div></div>
            <div class="modal-footer"><a class="btn btn-outline-primary" data-visor-descargar><i class="bi bi-download" aria-hidden="true"></i> Descargar</a><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
        </div></div>
    </div>
    @if(session('abrir_archivo_subido'))
    <a hidden data-documento-inicial data-abrir-documento href="{{ session('abrir_archivo_subido.url') }}" data-tipo-archivo="{{ session('abrir_archivo_subido.tipo') }}" data-titulo-archivo="{{ session('abrir_archivo_subido.titulo') }}" data-nombre-archivo="{{ session('abrir_archivo_subido.nombre') }}"></a>
    @endif

    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript propio -->
    <script src="{{ asset('js/scripts.js') }}?v={{ filemtime(public_path('js/scripts.js')) }}"></script>

    <script src="{{ asset('js/bitacora-salida.js') }}?v={{ filemtime(public_path('js/bitacora-salida.js')) }}" data-salida="{{ route('sesion.salida-pagina') }}" data-csrf="{{ csrf_token() }}" data-pagina="{{ (string) \Illuminate\Support\Str::uuid() }}" defer></script>
    @yield('scripts')

</body>

</html>
