@extends('layouts.app')

@section('title', 'Evolución clínica - Sanatorio La Promesa')

@section('menu-evolucion', 'activo')

@section('content')

    <!-- Encabezado -->
    <div class="inicio-encabezado">

        <div class="inicio-icono">
            <i class="bi bi-graph-up-arrow"></i>
        </div>

        <div>
            <h1>Evolución clínica</h1>

            <p>
                Registro y seguimiento del estado clínico del paciente
            </p>
        </div>

    </div>

    @if(session('exito'))
        <div class="alert alert-success">
            {{ session('exito') }}
        </div>
    @endif

    <!-- Nueva evolución -->
    <form
        action="{{ route('evolucion.guardar') }}"
        method="POST"
    >

        @csrf

        <!-- Paciente -->
        <div class="evolucion-card mb-4">

            <h4 class="mb-4">
                <i class="bi bi-person"></i>
                Paciente
            </h4>

            <div class="row g-3">

                <div class="col-md-8">

                    <label class="form-label">
                        Seleccionar paciente
                    </label>

                    <select
                        name="id_paciente"
                        class="form-select @error('id_paciente') is-invalid @enderror"
                        required
                    >

                        <option value="">
                            Seleccione un paciente
                        </option>

                        @foreach($pacientes as $paciente)

                            <option
                                value="{{ $paciente->id_paciente }}"
                                {{ old('id_paciente') == $paciente->id_paciente ? 'selected' : '' }}
                            >
                                {{ $paciente->numero_expediente }}
                                -
                                {{ $paciente->nombres }}
                                {{ $paciente->apellidos }}
                            </option>

                        @endforeach

                    </select>

                    @error('id_paciente')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>

                <div class="col-md-4">

                    <label class="form-label">
                        Fecha y hora
                    </label>

                    <input
                        type="datetime-local"
                        name="fecha_hora"
                        class="form-control @error('fecha_hora') is-invalid @enderror"
                        value="{{ old('fecha_hora', now()->format('Y-m-d\TH:i')) }}"
                        max="{{ now()->format('Y-m-d\TH:i') }}"
                        required
                    >

                    @error('fecha_hora')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>

            </div>

        </div>

        <!-- Evaluación clínica -->
        <div class="evolucion-card mb-4">

            <h4 class="mb-4">
                <i class="bi bi-clipboard2-pulse"></i>
                Evaluación clínica
            </h4>

            <div class="mb-3">

                <label class="form-label">
                    Motivo de atención
                </label>

                <textarea
                    name="motivo_atencion"
                    class="form-control @error('motivo_atencion') is-invalid @enderror"
                    rows="3"
                    maxlength="255"
                    required
                >{{ old('motivo_atencion') }}</textarea>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Síntomas
                </label>

                <textarea
                    name="sintomas"
                    class="form-control"
                    rows="3"
                    maxlength="1000"
                >{{ old('sintomas') }}</textarea>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Evaluación médica
                </label>

                <textarea
                    name="evaluacion_medica"
                    class="form-control"
                    rows="4"
                    maxlength="2000"
                >{{ old('evaluacion_medica') }}</textarea>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Diagnóstico
                </label>

                <textarea
                    name="diagnostico"
                    class="form-control"
                    rows="3"
                    maxlength="1000"
                    required
                >{{ old('diagnostico') }}</textarea>

            </div>

        </div>

        <!-- Tratamiento e indicaciones -->
        <div class="evolucion-card mb-4">

            <h4 class="mb-4">
                <i class="bi bi-capsule"></i>
                Tratamiento e indicaciones
            </h4>

            <div class="mb-3">

                <label class="form-label">
                    Indicaciones
                </label>

                <textarea
                    name="indicaciones"
                    class="form-control"
                    rows="4"
                    maxlength="2000"
                >{{ old('indicaciones') }}</textarea>

            </div>

            <div class="mb-3">

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

        <!-- Acciones -->
        <div class="evolucion-acciones">

            <button
                type="reset"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-x-circle"></i>
                Limpiar
            </button>

            <button
                type="submit"
                class="btn btn-primary"
            >
                <i class="bi bi-floppy"></i>
                Guardar evolución
            </button>

        </div>

    </form>

    <!-- Historial -->
    <div class="evolucion-card mt-4">

        <h4 class="mb-4">
            <i class="bi bi-clock-history"></i>
            Evoluciones registradas
        </h4>

        <div class="table-responsive">

            <table class="table align-middle">

                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Expediente</th>
                        <th>Paciente</th>
                        <th>Motivo</th>
                        <th>Diagnóstico</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($evoluciones as $evolucion)

                        <tr>

                            <td>
                                {{ \Carbon\Carbon::parse($evolucion->fecha_hora)->format('d/m/Y H:i') }}
                            </td>

                            <td>
                                {{ $evolucion->paciente->numero_expediente }}
                            </td>

                            <td>
                                {{ $evolucion->paciente->nombres }}
                                {{ $evolucion->paciente->apellidos }}
                            </td>

                            <td>
                                {{ $evolucion->motivo_atencion }}
                            </td>

                            <td>
                                {{ $evolucion->diagnostico }}
                            </td>

                            <td>

                                <button
                                    class="btn btn-outline-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalVer{{ $evolucion->id_evolucion }}"
                                >
                                    <i class="bi bi-eye"></i>
                                    Ver
                                </button>

                                <button
                                    class="btn btn-outline-warning btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditar{{ $evolucion->id_evolucion }}"
                                >
                                    <i class="bi bi-pencil"></i>
                                    Editar
                                </button>

                                <a
                                    href="{{ route('tratamientos.index', ['evolucion' => $evolucion->id_evolucion]) }}"
                                    class="btn btn-outline-success btn-sm"
                                >
                                    <i class="bi bi-capsule"></i>
                                    Tratamiento
                                </a>

                                <form
                                    action="{{ route('evolucion.eliminar', $evolucion->id_evolucion) }}"
                                    method="POST"
                                    class="d-inline"
                                    onsubmit="return confirm('¿Desea eliminar esta evolución clínica?');"
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

                        <!-- Modal ver -->
                        <div
                            class="modal fade"
                            id="modalVer{{ $evolucion->id_evolucion }}"
                            tabindex="-1"
                            aria-hidden="true"
                        >

                            <div class="modal-dialog modal-lg">

                                <div class="modal-content">

                                    <div class="modal-header">

                                        <h5 class="modal-title">
                                            Detalle de evolución clínica
                                        </h5>

                                        <button
                                            type="button"
                                            class="btn-close"
                                            data-bs-dismiss="modal"
                                        ></button>

                                    </div>

                                    <div class="modal-body">

                                        <p>
                                            <strong>Paciente:</strong>
                                            {{ $evolucion->paciente->nombres }}
                                            {{ $evolucion->paciente->apellidos }}
                                        </p>

                                        <p>
                                            <strong>Fecha:</strong>
                                            {{ \Carbon\Carbon::parse($evolucion->fecha_hora)->format('d/m/Y H:i') }}
                                        </p>

                                        <p>
                                            <strong>Motivo:</strong>
                                            {{ $evolucion->motivo_atencion }}
                                        </p>

                                        <p>
                                            <strong>Síntomas:</strong>
                                            {{ $evolucion->sintomas ?: 'Sin registro' }}
                                        </p>

                                        <p>
                                            <strong>Evaluación médica:</strong>
                                            {{ $evolucion->evaluacion_medica ?: 'Sin registro' }}
                                        </p>

                                        <p>
                                            <strong>Diagnóstico:</strong>
                                            {{ $evolucion->diagnostico }}
                                        </p>

                                        <p>
                                            <strong>Indicaciones:</strong>
                                            {{ $evolucion->indicaciones ?: 'Sin registro' }}
                                        </p>

                                        <p>
                                            <strong>Observaciones:</strong>
                                            {{ $evolucion->observaciones ?: 'Sin registro' }}
                                        </p>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- Modal editar -->
                        <div
                            class="modal fade"
                            id="modalEditar{{ $evolucion->id_evolucion }}"
                            tabindex="-1"
                            aria-hidden="true"
                        >

                            <div class="modal-dialog modal-lg">

                                <div class="modal-content">

                                    <form
                                        action="{{ route('evolucion.actualizar', $evolucion->id_evolucion) }}"
                                        method="POST"
                                    >

                                        @csrf
                                        @method('PUT')

                                        <div class="modal-header">

                                            <h5 class="modal-title">
                                                Editar evolución clínica
                                            </h5>

                                            <button
                                                type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"
                                            ></button>

                                        </div>

                                        <div class="modal-body">

                                            <div class="row g-3">

                                                <div class="col-md-8">

                                                    <label class="form-label">
                                                        Paciente
                                                    </label>

                                                    <select
                                                        name="id_paciente"
                                                        class="form-select"
                                                        required
                                                    >

                                                        @foreach($pacientes as $paciente)

                                                            <option
                                                                value="{{ $paciente->id_paciente }}"
                                                                {{ $evolucion->id_paciente == $paciente->id_paciente ? 'selected' : '' }}
                                                            >
                                                                {{ $paciente->numero_expediente }}
                                                                -
                                                                {{ $paciente->nombres }}
                                                                {{ $paciente->apellidos }}
                                                            </option>

                                                        @endforeach

                                                    </select>

                                                </div>

                                                <div class="col-md-4">

                                                    <label class="form-label">
                                                        Fecha y hora
                                                    </label>

                                                    <input
                                                        type="datetime-local"
                                                        name="fecha_hora"
                                                        class="form-control"
                                                        value="{{ \Carbon\Carbon::parse($evolucion->fecha_hora)->format('Y-m-d\TH:i') }}"
                                                        max="{{ now()->format('Y-m-d\TH:i') }}"
                                                        required
                                                    >

                                                </div>

                                                <div class="col-12">

                                                    <label class="form-label">
                                                        Motivo de atención
                                                    </label>

                                                    <textarea
                                                        name="motivo_atencion"
                                                        class="form-control"
                                                        rows="3"
                                                        maxlength="255"
                                                        required
                                                    >{{ $evolucion->motivo_atencion }}</textarea>

                                                </div>

                                                <div class="col-12">

                                                    <label class="form-label">
                                                        Síntomas
                                                    </label>

                                                    <textarea
                                                        name="sintomas"
                                                        class="form-control"
                                                        rows="3"
                                                        maxlength="1000"
                                                    >{{ $evolucion->sintomas }}</textarea>

                                                </div>

                                                <div class="col-12">

                                                    <label class="form-label">
                                                        Evaluación médica
                                                    </label>

                                                    <textarea
                                                        name="evaluacion_medica"
                                                        class="form-control"
                                                        rows="4"
                                                        maxlength="2000"
                                                    >{{ $evolucion->evaluacion_medica }}</textarea>

                                                </div>

                                                <div class="col-12">

                                                    <label class="form-label">
                                                        Diagnóstico
                                                    </label>

                                                    <textarea
                                                        name="diagnostico"
                                                        class="form-control"
                                                        rows="3"
                                                        maxlength="1000"
                                                        required
                                                    >{{ $evolucion->diagnostico }}</textarea>

                                                </div>

                                                <div class="col-12">

                                                    <label class="form-label">
                                                        Indicaciones
                                                    </label>

                                                    <textarea
                                                        name="indicaciones"
                                                        class="form-control"
                                                        rows="4"
                                                        maxlength="2000"
                                                    >{{ $evolucion->indicaciones }}</textarea>

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
                                                    >{{ $evolucion->observaciones }}</textarea>

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
                                No hay evoluciones clínicas registradas.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

@endsection