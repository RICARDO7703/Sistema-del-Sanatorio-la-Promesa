@extends('layouts.app')

@section('title', 'Inicio - Sanatorio La Promesa')

@section('menu-inicio', 'activo')

@section('content')

    <!-- Encabezado -->
    <div class="inicio-encabezado">

        <div class="inicio-icono">
            <i class="bi bi-house-door"></i>
        </div>

        <div>
            <h1>Inicio</h1>

            <p>
                Bienvenido al sistema del Sanatorio La Promesa
            </p>
        </div>

    </div>


    @if($emergenciasPendientes->isNotEmpty())
    <div class="alert alert-warning" role="alert">
        <h2 class="h5"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Consultas externas abiertas de días anteriores</h2>
        <p class="mb-2">Estas consultas externas siguen activas. Finalícelas si el paciente ya se retiró o interne al paciente si se quedó.</p>
        <ul class="mb-0">
            @foreach($emergenciasPendientes as $emergencia)
            <li>
                <a href="{{ route('atencion.show', $emergencia) }}">{{ $emergencia->paciente->estado_identificacion === 'Identificado' ? $emergencia->paciente->nombres.' '.$emergencia->paciente->apellidos : 'No identificado - '.$emergencia->paciente->codigo_temporal }}</a>
                · llegó el {{ $emergencia->fecha_ingreso->format('d/m/Y H:i') }}
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Tarjetas -->
    <div class="row g-4">

        <!-- Pacientes -->
        <div class="col-md-6 col-lg-4">

            <div class="inicio-card">

                <div class="card-superior">

                    <div class="card-icono">
                        <i class="bi bi-people"></i>
                    </div>

                    <div>
                        <h3>Pacientes</h3>

                        <p>
                            Consulta y gestión de pacientes registrados.
                        </p>
                    </div>

                </div>

                <div class="card-acciones">

                    <a
                        href="{{ route('pacientes.index') }}"
                        class="btn btn-primary btn-pacientes"
                    >
                        Ver pacientes
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a
                        href="{{ route('pacientes.index') }}"
                        class="btn-flecha"
                    >
                        <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

            </div>

        </div>


        <!-- Atención del paciente -->
        <div class="col-md-6 col-lg-4">

            <div class="inicio-card">

                <div class="card-superior">

                    <div class="card-icono">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>

                    <div>
                        <h3>Atención del paciente</h3>

                        <p>
                            Pacientes internados: órdenes médicas y signos vitales.
                        </p>
                    </div>

                </div>

                <div class="card-acciones justify-content-end">

                    <a
                        href="{{ route('atencion.index') }}"
                        class="btn-flecha"
                    >
                        <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

            </div>

        </div>


        <!-- Personal -->
        <div class="col-md-6 col-lg-4">

            <div class="inicio-card">

                <div class="card-superior">

                    <div class="card-icono">
                        <i class="bi bi-person-badge"></i>
                    </div>

                    <div>
                        <h3>Personal</h3>

                        <p>
                            Registro y gestión del personal del sanatorio.
                        </p>
                    </div>

                </div>

                <div class="card-acciones justify-content-end">

                    <a
                        href="{{ route('personal.index') }}"
                        class="btn-flecha"
                    >
                        <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

            </div>

        </div>

    </div>

@endsection