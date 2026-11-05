@extends('layouts.app')

@section('title', 'Signos vitales - Sanatorio La Promesa')

@section('menu-signos', 'activo')

@section('content')

    <!-- Encabezado -->
    <div class="inicio-encabezado">

        <div class="inicio-icono">
            <i class="bi bi-heart-pulse"></i>
        </div>

        <div>
            <h1>Signos vitales</h1>

            <p>
                Registro y control de signos vitales de los pacientes
            </p>
        </div>

    </div>

    @if(session('exito'))
        <div class="alert alert-success">
            {{ session('exito') }}
        </div>
    @endif

    <!-- Buscador -->
    <div class="pacientes-buscador mb-4">

        <form action="{{ route('signos.index') }}" method="GET">

            <div class="row g-3">

                <div class="col-md-9">

                    <input
                        type="text"
                        name="buscar"
                        class="form-control"
                        placeholder="Buscar por paciente o expediente"
                        value="{{ $buscar ?? '' }}"
                        maxlength="100"
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

    <!-- Tabla -->
    <div class="pacientes-tabla">

        <div class="tabla-titulo">

            <div>
                <h3>Registros de signos vitales</h3>
                <p>Historial de controles realizados</p>
            </div>

            <button
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalSignos"
            >
                <i class="bi bi-plus-circle"></i>
                Nuevo registro
            </button>

        </div>

        <div class="table-responsive">

            <table class="table align-middle">

                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>Tipo</th>
                        <th>Presión</th>
                        <th>F. cardíaca</th>
                        <th>Temperatura</th>
                        <th>Saturación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($signos as $signo)

                        <tr>

                            <td>
                                {{ \Carbon\Carbon::parse($signo->fecha_hora)->format('d/m/Y H:i') }}
                            </td>

                            <td>
                                {{ $signo->paciente->nombres }}
                                {{ $signo->paciente->apellidos }}
                            </td>

                            <td>
                                {{ $signo->tipo_control }}
                            </td>

                            <td>
                                {{ $signo->presion_arterial ?: '-' }}
                            </td>

                            <td>
                                {{ $signo->frecuencia_cardiaca ?: '-' }}
                            </td>

                            <td>
                                {{ $signo->temperatura ? $signo->temperatura . ' °C' : '-' }}
                            </td>

                            <td>
                                {{ $signo->saturacion_oxigeno ? $signo->saturacion_oxigeno . '%' : '-' }}
                            </td>

                            <td>

                                <button
                                    class="btn btn-outline-warning btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditar{{ $signo->id_signos_vitales }}"
                                >
                                    <i class="bi bi-pencil"></i>
                                    Editar
                                </button>

                            </td>

                        </tr>

                        <!-- Modal editar -->
                        <div
                            class="modal fade"
                            id="modalEditar{{ $signo->id_signos_vitales }}"
                            tabindex="-1"
                            aria-hidden="true"
                        >

                            <div class="modal-dialog modal-lg">

                                <div class="modal-content">

                                    <form
                                        action="{{ route('signos.actualizar', $signo->id_signos_vitales) }}"
                                        method="POST"
                                    >

                                        @csrf
                                        @method('PUT')

                                        <div class="modal-header">

                                            <h5 class="modal-title">
                                                Editar signos vitales
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

                                                    <select
                                                        name="id_paciente"
                                                        class="form-select"
                                                        required
                                                    >

                                                        @foreach($pacientes as $paciente)

                                                            <option
                                                                value="{{ $paciente->id_paciente }}"
                                                                {{ $signo->id_paciente == $paciente->id_paciente ? 'selected' : '' }}
                                                            >
                                                                {{ $paciente->numero_expediente }}
                                                                -
                                                                {{ $paciente->nombres }}
                                                                {{ $paciente->apellidos }}
                                                            </option>

                                                        @endforeach

                                                    </select>

                                                </div>

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Personal responsable
                                                    </label>

                                                    <select
                                                        name="id_personal"
                                                        class="form-select"
                                                        required
                                                    >

                                                        @foreach($personal as $persona)

                                                            <option
                                                                value="{{ $persona->id_personal }}"
                                                                {{ $signo->id_personal == $persona->id_personal ? 'selected' : '' }}
                                                            >
                                                                {{ $persona->nombres }}
                                                                {{ $persona->apellidos }}
                                                            </option>

                                                        @endforeach

                                                    </select>

                                                </div>

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Tipo de control
                                                    </label>

                                                    <select
                                                        name="tipo_control"
                                                        class="form-select"
                                                        required
                                                    >

                                                        @foreach([
                                                            'Ingreso',
                                                            'Consulta',
                                                            'Preoperatorio',
                                                            'Recuperación',
                                                            'Rutina'
                                                        ] as $tipo)

                                                            <option
                                                                value="{{ $tipo }}"
                                                                {{ $signo->tipo_control == $tipo ? 'selected' : '' }}
                                                            >
                                                                {{ $tipo }}
                                                            </option>

                                                        @endforeach

                                                    </select>

                                                </div>

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Fecha y hora
                                                    </label>

                                                    <input
                                                        type="datetime-local"
                                                        name="fecha_hora"
                                                        class="form-control"
                                                        value="{{ \Carbon\Carbon::parse($signo->fecha_hora)->format('Y-m-d\TH:i') }}"
                                                        max="{{ now()->format('Y-m-d\TH:i') }}"
                                                        required
                                                    >

                                                </div>

                                                <div class="col-md-4">

                                                    <label class="form-label">
                                                        Presión arterial
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="presion_arterial"
                                                        class="form-control"
                                                        value="{{ $signo->presion_arterial }}"
                                                        placeholder="120/80"
                                                        maxlength="10"
                                                    >

                                                </div>

                                                <div class="col-md-4">

                                                    <label class="form-label">
                                                        Frecuencia cardíaca
                                                    </label>

                                                    <input
                                                        type="number"
                                                        name="frecuencia_cardiaca"
                                                        class="form-control"
                                                        value="{{ $signo->frecuencia_cardiaca }}"
                                                        min="20"
                                                        max="250"
                                                    >

                                                </div>

                                                <div class="col-md-4">

                                                    <label class="form-label">
                                                        Frecuencia respiratoria
                                                    </label>

                                                    <input
                                                        type="number"
                                                        name="frecuencia_respiratoria"
                                                        class="form-control"
                                                        value="{{ $signo->frecuencia_respiratoria }}"
                                                        min="5"
                                                        max="80"
                                                    >

                                                </div>

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Temperatura
                                                    </label>

                                                    <input
                                                        type="number"
                                                        step="0.1"
                                                        name="temperatura"
                                                        class="form-control"
                                                        value="{{ $signo->temperatura }}"
                                                        min="30"
                                                        max="45"
                                                    >

                                                </div>

                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Saturación de oxígeno
                                                    </label>

                                                    <input
                                                        type="number"
                                                        name="saturacion_oxigeno"
                                                        class="form-control"
                                                        value="{{ $signo->saturacion_oxigeno }}"
                                                        min="50"
                                                        max="100"
                                                    >

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
                                                    >{{ $signo->observaciones }}</textarea>

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

                            <td colspan="8" class="text-center py-4">
                                No hay signos vitales registrados.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

    <!-- Modal nuevo registro -->
    <div
        class="modal fade"
        id="modalSignos"
        tabindex="-1"
        aria-hidden="true"
    >

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <form action="{{ route('signos.guardar') }}" method="POST">

                    @csrf

                    <div class="modal-header">

                        <h5 class="modal-title">
                            Registrar signos vitales
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

                                <select
                                    name="id_paciente"
                                    class="form-select @error('id_paciente') is-invalid @enderror"
                                    required
                                >

                                    <option value="">
                                        Seleccione
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

                            </div>

                            <div class="col-md-6">

                                <label class="form-label">
                                    Personal responsable
                                </label>

                                <select
                                    name="id_personal"
                                    class="form-select @error('id_personal') is-invalid @enderror"
                                    required
                                >

                                    <option value="">
                                        Seleccione
                                    </option>

                                    @foreach($personal as $persona)

                                        <option
                                            value="{{ $persona->id_personal }}"
                                            {{ old('id_personal') == $persona->id_personal ? 'selected' : '' }}
                                        >
                                            {{ $persona->nombres }}
                                            {{ $persona->apellidos }}
                                        </option>

                                    @endforeach

                                </select>

                            </div>

                            <div class="col-md-6">

                                <label class="form-label">
                                    Tipo de control
                                </label>

                                <select
                                    name="tipo_control"
                                    class="form-select @error('tipo_control') is-invalid @enderror"
                                    required
                                >

                                    <option value="">
                                        Seleccione
                                    </option>

                                    <option value="Ingreso">Ingreso</option>
                                    <option value="Consulta">Consulta</option>
                                    <option value="Preoperatorio">Preoperatorio</option>
                                    <option value="Recuperación">Recuperación</option>
                                    <option value="Rutina">Rutina</option>

                                </select>

                            </div>

                            <div class="col-md-6">

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

                            </div>

                            <div class="col-md-4">

                                <label class="form-label">
                                    Presión arterial
                                </label>

                                <input
                                    type="text"
                                    name="presion_arterial"
                                    class="form-control @error('presion_arterial') is-invalid @enderror"
                                    value="{{ old('presion_arterial') }}"
                                    placeholder="120/80"
                                    maxlength="10"
                                >

                            </div>

                            <div class="col-md-4">

                                <label class="form-label">
                                    Frecuencia cardíaca
                                </label>

                                <input
                                    type="number"
                                    name="frecuencia_cardiaca"
                                    class="form-control"
                                    value="{{ old('frecuencia_cardiaca') }}"
                                    min="20"
                                    max="250"
                                >

                            </div>

                            <div class="col-md-4">

                                <label class="form-label">
                                    Frecuencia respiratoria
                                </label>

                                <input
                                    type="number"
                                    name="frecuencia_respiratoria"
                                    class="form-control"
                                    value="{{ old('frecuencia_respiratoria') }}"
                                    min="5"
                                    max="80"
                                >

                            </div>

                            <div class="col-md-6">

                                <label class="form-label">
                                    Temperatura
                                </label>

                                <input
                                    type="number"
                                    step="0.1"
                                    name="temperatura"
                                    class="form-control"
                                    value="{{ old('temperatura') }}"
                                    min="30"
                                    max="45"
                                >

                            </div>

                            <div class="col-md-6">

                                <label class="form-label">
                                    Saturación de oxígeno
                                </label>

                                <input
                                    type="number"
                                    name="saturacion_oxigeno"
                                    class="form-control"
                                    value="{{ old('saturacion_oxigeno') }}"
                                    min="50"
                                    max="100"
                                >

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
                            Guardar registro
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

    @if($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = new bootstrap.Modal(
                    document.getElementById('modalSignos')
                );

                modal.show();
            });
        </script>
    @endif

@endsection