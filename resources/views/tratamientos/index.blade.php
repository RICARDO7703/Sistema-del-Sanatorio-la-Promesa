@extends('layouts.app')

@section('title', 'Tratamientos - Sanatorio La Promesa')

@section('content')

    <!-- Encabezado -->
    <div class="inicio-encabezado">

        <div class="inicio-icono">
            <i class="bi bi-capsule"></i>
        </div>

        <div>
            <h1>Tratamientos</h1>

            <p>
                Registro de tratamientos asociados a la evolución clínica
            </p>
        </div>

    </div>

    @if(session('exito'))
        <div class="alert alert-success">
            {{ session('exito') }}
        </div>
    @endif

    <!-- Información de la evolución -->
    @if($evolucionSeleccionada)

        <div class="evolucion-card mb-4">

            <h4 class="mb-3">
                <i class="bi bi-person"></i>
                Información del paciente
            </h4>

            <div class="row g-3">

                <div class="col-md-4">
                    <p class="dato-label">Paciente</p>

                    <strong>
                        {{ $evolucionSeleccionada->paciente->nombres }}
                        {{ $evolucionSeleccionada->paciente->apellidos }}
                    </strong>
                </div>

                <div class="col-md-4">
                    <p class="dato-label">Expediente</p>

                    <strong>
                        {{ $evolucionSeleccionada->paciente->numero_expediente }}
                    </strong>
                </div>

                <div class="col-md-4">
                    <p class="dato-label">Fecha de evolución</p>

                    <strong>
                        {{ \Carbon\Carbon::parse($evolucionSeleccionada->fecha_hora)->format('d/m/Y H:i') }}
                    </strong>
                </div>

                <div class="col-12">
                    <p class="dato-label">Diagnóstico</p>

                    <strong>
                        {{ $evolucionSeleccionada->diagnostico }}
                    </strong>
                </div>

            </div>

        </div>

    @endif

    <!-- Nuevo tratamiento -->
    @if($evolucionSeleccionada)

        <div class="evolucion-card mb-4">

            <h4 class="mb-4">
                <i class="bi bi-plus-circle"></i>
                Nuevo tratamiento
            </h4>

            <form action="{{ route('tratamientos.guardar') }}" method="POST">

                @csrf

                <input
                    type="hidden"
                    name="id_evolucion"
                    value="{{ $evolucionSeleccionada->id_evolucion }}"
                >

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">
                            Medicamento
                        </label>

                        <input
                            type="text"
                            name="medicamento"
                            class="form-control @error('medicamento') is-invalid @enderror"
                            value="{{ old('medicamento') }}"
                            maxlength="150"
                            required
                        >

                        @error('medicamento')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Dosis
                        </label>

                        <input
                            type="text"
                            name="dosis"
                            class="form-control"
                            value="{{ old('dosis') }}"
                            maxlength="100"
                            placeholder="Ejemplo: 500 mg"
                        >

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Frecuencia
                        </label>

                        <input
                            type="text"
                            name="frecuencia"
                            class="form-control"
                            value="{{ old('frecuencia') }}"
                            maxlength="100"
                            placeholder="Ejemplo: Cada 8 horas"
                        >

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Duración
                        </label>

                        <input
                            type="text"
                            name="duracion"
                            class="form-control"
                            value="{{ old('duracion') }}"
                            maxlength="100"
                            placeholder="Ejemplo: 5 días"
                        >

                    </div>

                    <div class="col-12">

                        <label class="form-label">
                            Indicaciones
                        </label>

                        <textarea
                            name="indicaciones"
                            class="form-control"
                            rows="3"
                            maxlength="1000"
                        >{{ old('indicaciones') }}</textarea>

                    </div>

                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">

                    <a
                        href="{{ route('evolucion.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Regresar
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-floppy"></i>
                        Guardar tratamiento
                    </button>

                </div>

            </form>

        </div>

    @endif

    <!-- Tratamientos registrados -->
    <div class="evolucion-card">

        <h4 class="mb-4">
            <i class="bi bi-clock-history"></i>
            Tratamientos registrados
        </h4>

        <div class="table-responsive">

            <table class="table align-middle">

                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Medicamento</th>
                        <th>Dosis</th>
                        <th>Frecuencia</th>
                        <th>Duración</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($tratamientos as $tratamiento)

                        <tr>

                            <td>
                                {{ $tratamiento->evolucion->paciente->nombres }}
                                {{ $tratamiento->evolucion->paciente->apellidos }}
                            </td>

                            <td>
                                {{ $tratamiento->medicamento }}
                            </td>

                            <td>
                                {{ $tratamiento->dosis ?: '-' }}
                            </td>

                            <td>
                                {{ $tratamiento->frecuencia ?: '-' }}
                            </td>

                            <td>
                                {{ $tratamiento->duracion ?: '-' }}
                            </td>

                            <td>

                                <button
                                    class="btn btn-outline-warning btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditar{{ $tratamiento->id_tratamiento }}"
                                >
                                    <i class="bi bi-pencil"></i>
                                    Editar
                                </button>

                                <form
                                    action="{{ route('tratamientos.eliminar', $tratamiento->id_tratamiento) }}"
                                    method="POST"
                                    class="d-inline"
                                    data-confirmar="¿Desea desactivar este tratamiento?"
                                >

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-sm"
                                    >
                                        <i class="bi bi-trash"></i>
                                        Eliminar
                                    </button>

                                </form>

                            </td>

                        </tr>

                        <!-- Modal editar -->
                        <div
                            class="modal fade"
                            id="modalEditar{{ $tratamiento->id_tratamiento }}"
                            tabindex="-1"
                            aria-hidden="true"
                        >

                            <div class="modal-dialog modal-lg">

                                <div class="modal-content">

                                    <form
                                        action="{{ route('tratamientos.actualizar', $tratamiento->id_tratamiento) }}"
                                        method="POST"
                                    >

                                        @csrf
                                        @method('PUT')

                                        <input
                                            type="hidden"
                                            name="id_evolucion"
                                            value="{{ $tratamiento->id_evolucion }}"
                                        >

                                        <div class="modal-header">

                                            <h5 class="modal-title">
                                                Editar tratamiento
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
                                                        Medicamento
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="medicamento"
                                                        class="form-control"
                                                        value="{{ $tratamiento->medicamento }}"
                                                        maxlength="150"
                                                        required
                                                    >

                                                </div>

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Dosis
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="dosis"
                                                        class="form-control"
                                                        value="{{ $tratamiento->dosis }}"
                                                        maxlength="100"
                                                    >

                                                </div>

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Frecuencia
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="frecuencia"
                                                        class="form-control"
                                                        value="{{ $tratamiento->frecuencia }}"
                                                        maxlength="100"
                                                    >

                                                </div>

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Duración
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="duracion"
                                                        class="form-control"
                                                        value="{{ $tratamiento->duracion }}"
                                                        maxlength="100"
                                                    >

                                                </div>

                                                <div class="col-12">

                                                    <label class="form-label">
                                                        Indicaciones
                                                    </label>

                                                    <textarea
                                                        name="indicaciones"
                                                        class="form-control"
                                                        rows="3"
                                                        maxlength="1000"
                                                    >{{ $tratamiento->indicaciones }}</textarea>

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
                            <td colspan="6" class="text-center py-4">
                                No hay tratamientos registrados.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

@endsection