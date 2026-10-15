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
                            Evoluciones, órdenes médicas y alta del paciente.
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