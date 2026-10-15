@extends('layouts.app')

@section('title', 'Bitácora - Sanatorio La Promesa')

@section('menu-bitacora', 'activo')

@section('content')

    <!-- Encabezado -->
    <div class="inicio-encabezado">

        <div class="inicio-icono">
            <i class="bi bi-clipboard-data"></i>
        </div>

        <div>
            <h1>Bitácora</h1>

            <p>
                Consulta de actividades realizadas dentro del sistema
            </p>
        </div>

    </div>

    <!-- Acciones -->
    <div class="bitacora-acciones mb-4">

        <button class="btn btn-primary">
            <i class="bi bi-search"></i>
            Consultar
        </button>

        <button class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-arrow-down"></i>
            Exportar
        </button>

        <div class="bitacora-fecha">

            <input
                type="date"
                class="form-control"
            >

            <button class="btn btn-outline-primary">
                <i class="bi bi-calendar3"></i>
                Buscar por fecha
            </button>

        </div>

        <button class="btn btn-outline-secondary">
            <i class="bi bi-printer"></i>
            Imprimir
        </button>

    </div>

    <!-- Tabla -->
    <div class="bitacora-card">

        <div class="bitacora-titulo">

            <div>
                <h3>Registro de actividades</h3>

                <p>
                    Historial de acciones realizadas por los usuarios
                </p>
            </div>

        </div>

        <div class="table-responsive">

            <table class="table align-middle bitacora-tabla">

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Usuario</th>
                        <th>Módulo</th>
                        <th>Fecha y hora</th>
                        <th>Acción</th>
                        <th>Detalle</th>
                    </tr>
                </thead>

                <tbody>

                    <tr>
                        <td>1</td>
                        <td>Dr. Javier Morales</td>
                        <td>
                            <span class="badge modulo-badge">
                                Expedientes
                            </span>
                        </td>
                        <td>27/08/2026 10:15</td>
                        <td>
                            <span class="accion-badge consulta">
                                Consulta
                            </span>
                        </td>
                        <td>
                            Consultó el expediente EXP-2024-01578
                        </td>
                    </tr>

                    <tr>
                        <td>2</td>
                        <td>Dra. Laura Sánchez</td>
                        <td>
                            <span class="badge modulo-badge">
                                Evolución clínica
                            </span>
                        </td>
                        <td>27/08/2026 10:30</td>
                        <td>
                            <span class="accion-badge registro">
                                Registro
                            </span>
                        </td>
                        <td>
                            Registró una nueva evolución clínica
                        </td>
                    </tr>

                    <tr>
                        <td>3</td>
                        <td>Secretaria</td>
                        <td>
                            <span class="badge modulo-badge">
                                Pacientes
                            </span>
                        </td>
                        <td>27/08/2026 11:05</td>
                        <td>
                            <span class="accion-badge registro">
                                Registro
                            </span>
                        </td>
                        <td>
                            Registró un nuevo paciente
                        </td>
                    </tr>

                    <tr>
                        <td>4</td>
                        <td>Dr. Javier Morales</td>
                        <td>
                            <span class="badge modulo-badge">
                                Evolución clínica
                            </span>
                        </td>
                        <td>27/08/2026 11:40</td>
                        <td>
                            <span class="accion-badge modificacion">
                                Modificación
                            </span>
                        </td>
                        <td>
                            Actualizó indicaciones del tratamiento
                        </td>
                    </tr>

                </tbody>

            </table>

        </div>

    </div>

@endsection