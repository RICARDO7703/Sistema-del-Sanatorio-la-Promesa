@extends('layouts.app')

@section('title', 'Atención del paciente - Sanatorio La Promesa')

@section('content')

    <div class="inicio-encabezado">

        <div class="inicio-icono">
            <i class="bi bi-clipboard2-pulse"></i>
        </div>

        <div>
            <h1>Atención del paciente</h1>
            <p>Pacientes con ingreso activo</p>
        </div>

    </div>

    <div class="pacientes-buscador mb-4">

        <form
            action="{{ route('atencion.index') }}"
            method="GET"
        >

            <div class="row g-3">

                <div class="col-md-9">

                    <input
                        type="text"
                        name="buscar"
                        class="form-control"
                        placeholder="Buscar por paciente, expediente o código temporal"
                        value="{{ $buscar ?? '' }}"
                    >

                </div>

                <div class="col-md-3">

                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        <i class="bi bi-search"></i>
                        Buscar
                    </button>

                </div>

            </div>

        </form>

    </div>

    <div class="pacientes-tabla">

        <div class="tabla-titulo">

            <div>
                <h3>Ingresos activos</h3>
                <p>Pacientes actualmente en atención</p>
            </div>

        </div>

        <div class="table-responsive">

            <table class="table align-middle">

                <thead>
                    <tr>
                        <th>Expediente</th>
                        <th>Paciente</th>
                        <th>Fecha de ingreso</th>
                        <th>Situación</th>
                        <th>Habitación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($ingresos as $ingreso)

                        <tr>

                            <td>
                                {{ $ingreso->paciente->numero_expediente }}
                            </td>

                            <td>

                                @if(
                                    $ingreso->paciente->estado_identificacion
                                    === 'Identificado'
                                )

                                    {{ $ingreso->paciente->nombres }}
                                    {{ $ingreso->paciente->apellidos }}

                                @else

                                    No identificado

                                    <small class="d-block text-muted">
                                        {{ $ingreso->paciente->codigo_temporal }}
                                    </small>

                                @endif

                            </td>

                            <td>
                                {{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}
                            </td>

                            <td>

                                @if(
                                    $ingreso->hospitalizacion &&
                                    $ingreso->hospitalizacion->estado === 'Activa'
                                )

                                    <span class="badge bg-danger">
                                        Hospitalizado
                                    </span>

                                @else

                                    <span class="badge bg-primary">
                                        En atención
                                    </span>

                                @endif

                            </td>

                            <td>

                                @if(
                                    $ingreso->hospitalizacion &&
                                    $ingreso->hospitalizacion->habitacionActual &&
                                    $ingreso->hospitalizacion
                                        ->habitacionActual
                                        ->habitacion
                                )

                                    {{
                                        $ingreso->hospitalizacion
                                            ->habitacionActual
                                            ->habitacion
                                            ->numero_habitacion
                                    }}

                                @else

                                    -

                                @endif

                            </td>

                            <td>

                                <a
                                    href="{{ route(
                                        'atencion.show',
                                        $ingreso->id_ingreso
                                    ) }}"
                                    class="btn btn-outline-primary btn-sm"
                                >
                                    <i class="bi bi-eye"></i>
                                    Abrir atención
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="text-center py-4"
                            >
                                No hay ingresos activos.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

@endsection