@extends('layouts.app')

@section('title', 'Hospitalización - Sanatorio La Promesa')

@section('menu-hospitalizacion', 'activo')

@section('content')

    <!-- Encabezado -->
    <div class="inicio-encabezado">

        <div class="inicio-icono">
            <i class="bi bi-hospital"></i>
        </div>

        <div>
            <h1>Hospitalización</h1>

            <p>
                Gestión de pacientes hospitalizados
            </p>
        </div>

    </div>

    @if(session('exito'))
        <div class="alert alert-success">
            {{ session('exito') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <!-- Tabla -->
    <div class="pacientes-tabla">

        <div class="tabla-titulo">

            <div>
                <h3>Pacientes hospitalizados</h3>
                <p>Hospitalizaciones activas</p>
            </div>

            <button
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalHospitalizacion"
            >
                <i class="bi bi-plus-circle"></i>
                Nueva hospitalización
            </button>

        </div>

        <div class="table-responsive">

            <table class="table align-middle">

                <thead>
                    <tr>
                        <th>Expediente</th>
                        <th>Paciente</th>
                        <th>Habitación</th>
                        <th>Fecha inicio</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($hospitalizaciones as $hospitalizacion)

                        <tr>

                            <td>
                                {{ $hospitalizacion->ingreso->paciente->numero_expediente }}
                            </td>

                            <td>
                                {{ $hospitalizacion->ingreso->paciente->nombres }}
                                {{ $hospitalizacion->ingreso->paciente->apellidos }}
                            </td>

                            <td>
                                {{ $hospitalizacion->habitacion->numero_habitacion }}
                            </td>

                            <td>
                                {{ \Carbon\Carbon::parse($hospitalizacion->fecha_inicio)->format('d/m/Y H:i') }}
                            </td>

                            <td>
                                {{ $hospitalizacion->motivo }}
                            </td>

                            <td>
                                <span class="badge text-bg-success">
                                    {{ $hospitalizacion->estado }}
                                </span>
                            </td>

                            <td>

                                <button
                                    class="btn btn-outline-warning btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditar{{ $hospitalizacion->id_hospitalizacion }}"
                                >
                                    <i class="bi bi-pencil"></i>
                                    Editar
                                </button>

                                <form
                                    action="{{ route('hospitalizacion.eliminar', $hospitalizacion->id_hospitalizacion) }}"
                                    method="POST"
                                    class="d-inline"
                                    data-confirmar="¿Desea cancelar esta hospitalización?"
                                    data-confirmar-boton="Sí, cancelar hospitalización"
                                    data-confirmar-volver="No, volver"
                                >

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-sm"
                                    >
                                        <i class="bi bi-x-circle"></i>
                                        Cancelar
                                    </button>

                                </form>

                            </td>

                        </tr>

                        <!-- Modal editar -->
                        <div
                            class="modal fade"
                            id="modalEditar{{ $hospitalizacion->id_hospitalizacion }}"
                            tabindex="-1"
                            aria-hidden="true"
                        >

                            <div class="modal-dialog modal-lg">

                                <div class="modal-content">

                                    <form
                                        action="{{ route('hospitalizacion.actualizar', $hospitalizacion->id_hospitalizacion) }}"
                                        method="POST"
                                    >

                                        @csrf
                                        @method('PUT')

                                        <div class="modal-header">

                                            <h5 class="modal-title">
                                                Editar hospitalización
                                            </h5>

                                            <button
                                                type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"
                                            ></button>

                                        </div>

                                        <div class="modal-body">

                                            <div class="row g-3">

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Paciente
                                                    </label>

                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        value="{{ $hospitalizacion->ingreso->paciente->nombres }} {{ $hospitalizacion->ingreso->paciente->apellidos }}"
                                                        disabled
                                                    >

                                                </div>

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Habitación
                                                    </label>

                                                    <select
                                                        name="id_habitacion"
                                                        class="form-select"
                                                        required
                                                    >

                                                        <option
                                                            value="{{ $hospitalizacion->habitacion->id_habitacion }}"
                                                            selected
                                                        >
                                                            Habitación {{ $hospitalizacion->habitacion->numero_habitacion }}
                                                            - {{ $hospitalizacion->habitacion->tipo }}
                                                        </option>

                                                        @foreach($habitaciones as $habitacion)

                                                            @if($habitacion->id_habitacion != $hospitalizacion->id_habitacion)

                                                                <option value="{{ $habitacion->id_habitacion }}">
                                                                    Habitación {{ $habitacion->numero_habitacion }}
                                                                    - {{ $habitacion->tipo }}
                                                                </option>

                                                            @endif

                                                        @endforeach

                                                    </select>

                                                </div>

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Fecha y hora de inicio
                                                    </label>

                                                    <input
                                                        type="datetime-local"
                                                        name="fecha_inicio"
                                                        class="form-control"
                                                        value="{{ \Carbon\Carbon::parse($hospitalizacion->fecha_inicio)->format('Y-m-d\TH:i') }}"
                                                        max="{{ now()->format('Y-m-d\TH:i') }}"
                                                        required
                                                    >

                                                </div>

                                                <div class="col-12">

                                                    <label class="form-label">
                                                        Motivo de hospitalización
                                                    </label>

                                                    <textarea
                                                        name="motivo"
                                                        class="form-control"
                                                        rows="3"
                                                        maxlength="1000"
                                                        required
                                                    >{{ $hospitalizacion->motivo }}</textarea>

                                                </div>

                                                <div class="col-12">

                                                    <label class="form-label">
                                                        Observaciones
                                                    </label>

                                                    <textarea
                                                        name="observaciones"
                                                        class="form-control"
                                                        rows="3"
                                                        maxlength="1000"
                                                    >{{ $hospitalizacion->observaciones }}</textarea>

                                                </div>

                                            </div>

                                        </div>

                                        <div class="modal-footer">

                                            <button
                                                type="button"
                                                class="btn btn-secondary"
                                                data-bs-dismiss="modal"
                                            >
                                                Cancelar
                                            </button>

                                            <button
                                                type="submit"
                                                class="btn btn-primary"
                                            >
                                                Confirmar cambios
                                            </button>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        </div>

                    @empty

                        <tr>
                            <td colspan="7" class="text-center py-4">
                                No hay pacientes hospitalizados.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

    <!-- Modal nueva hospitalización -->
    <div
        class="modal fade"
        id="modalHospitalizacion"
        tabindex="-1"
        aria-hidden="true"
    >

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <form action="{{ route('hospitalizacion.guardar') }}" method="POST">

                    @csrf

                    <div class="modal-header">

                        <h5 class="modal-title">
                            Registrar hospitalización
                        </h5>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                        ></button>

                    </div>

                    <div class="modal-body">

                        <div class="row g-3">

                            <div class="col-md-6">

                                <label class="form-label">
                                    Ingreso
                                </label>

                                <select
                                    name="id_ingreso"
                                    class="form-select @error('id_ingreso') is-invalid @enderror"
                                    required
                                >

                                    <option value="">
                                        Seleccione un ingreso
                                    </option>

                                    @foreach($ingresos as $ingreso)

                                        <option
                                            value="{{ $ingreso->id_ingreso }}"
                                            {{ old('id_ingreso') == $ingreso->id_ingreso ? 'selected' : '' }}
                                        >
                                            {{ $ingreso->paciente->numero_expediente }}
                                            -
                                            {{ $ingreso->paciente->nombres }}
                                            {{ $ingreso->paciente->apellidos }}
                                        </option>

                                    @endforeach

                                </select>

                            </div>

                            <div class="col-md-6">

                                <label class="form-label">
                                    Habitación disponible
                                </label>

                                <select
                                    name="id_habitacion"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        Seleccione una habitación
                                    </option>

                                    @foreach($habitaciones as $habitacion)

                                        <option value="{{ $habitacion->id_habitacion }}">
                                            Habitación {{ $habitacion->numero_habitacion }}
                                            - {{ $habitacion->tipo }}
                                        </option>

                                    @endforeach

                                </select>

                            </div>

                            <div class="col-md-6">

                                <label class="form-label">
                                    Fecha y hora de inicio
                                </label>

                                <input
                                    type="datetime-local"
                                    name="fecha_inicio"
                                    class="form-control"
                                    value="{{ old('fecha_inicio', now()->format('Y-m-d\TH:i')) }}"
                                    max="{{ now()->format('Y-m-d\TH:i') }}"
                                    required
                                >

                            </div>

                            <div class="col-12">

                                <label class="form-label">
                                    Motivo de hospitalización
                                </label>

                                <textarea
                                    name="motivo"
                                    class="form-control"
                                    rows="3"
                                    maxlength="1000"
                                    required
                                >{{ old('motivo') }}</textarea>

                            </div>

                            <div class="col-12">

                                <label class="form-label">
                                    Observaciones
                                </label>

                                <textarea
                                    name="observaciones"
                                    class="form-control"
                                    rows="3"
                                    maxlength="1000"
                                >{{ old('observaciones') }}</textarea>

                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Registrar hospitalización
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

@endsection