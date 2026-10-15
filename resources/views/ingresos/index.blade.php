@extends('layouts.app')

@section('title', 'Ingresos - Sanatorio La Promesa')

@section('menu-ingresos', 'activo')

@section('content')

    <!-- Encabezado -->
    <div class="inicio-encabezado">

        <div class="inicio-icono">
            <i class="bi bi-box-arrow-in-right"></i>
        </div>

        <div>
            <h1>Ingresos</h1>

            <p>
                Registro de ingreso de pacientes al sanatorio
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

    <!-- Buscador -->
    <div class="pacientes-buscador mb-4">

        <form action="{{ route('ingresos.index') }}" method="GET">

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
                <h3>Ingresos registrados</h3>
                <p>Pacientes ingresados al sanatorio</p>
            </div>

            <button
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalIngreso"
            >
                <i class="bi bi-plus-circle"></i>
                Nuevo ingreso
            </button>

        </div>

        <div class="table-responsive">

            <table class="table align-middle">

                <thead>
                    <tr>
                        <th>Expediente</th>
                        <th>Paciente</th>
                        <th>Fecha</th>
                        <th>Responsable</th>
                        <th>Documento</th>
                        <th>Estado</th>
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
                                {{ $ingreso->paciente->nombres }}
                                {{ $ingreso->paciente->apellidos }}
                            </td>

                            <td>
                                {{ \Carbon\Carbon::parse($ingreso->fecha_ingreso)->format('d/m/Y H:i') }}
                            </td>

                            <td>
                                {{ $ingreso->personal->nombres }}
                                {{ $ingreso->personal->apellidos }}
                            </td>

                            <td>
                                {{ $ingreso->documento_firmado ? 'Firmado' : 'Pendiente' }}
                            </td>

                            <td>

                                @if($ingreso->estado == 'Activo')
                                    <span class="badge text-bg-success">
                                        Activo
                                    </span>
                                @elseif($ingreso->estado == 'Dado de alta')
                                    <span class="badge text-bg-primary">
                                        Dado de alta
                                    </span>
                                @else
                                    <span class="badge text-bg-secondary">
                                        Cancelado
                                    </span>
                                @endif

                            </td>

                            <td>

                                @if($ingreso->estado == 'Activo')

                                    <form
                                        action="{{ route('ingresos.cancelar', $ingreso->id_ingreso) }}"
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('¿Desea cancelar este ingreso?');"
                                    >

                                        @csrf
                                        @method('PATCH')

                                        <button
                                            type="submit"
                                            class="btn btn-outline-danger btn-sm"
                                        >
                                            <i class="bi bi-x-circle"></i>
                                            Cancelar
                                        </button>

                                    </form>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="7" class="text-center py-4">
                                No hay ingresos registrados.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

    <!-- Modal nuevo ingreso -->
    <div
        class="modal fade"
        id="modalIngreso"
        tabindex="-1"
        aria-hidden="true"
    >

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <form action="{{ route('ingresos.guardar') }}" method="POST">

                    @csrf

                    <div class="modal-header">

                        <h5 class="modal-title">
                            Registrar ingreso
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

                                @error('id_paciente')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror

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

                                            @if($persona->cargo)
                                                - {{ $persona->cargo->nombre_cargo }}
                                            @endif
                                        </option>

                                    @endforeach

                                </select>

                                @error('id_personal')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror

                            </div>

                            <div class="col-md-6">

                                <label class="form-label">
                                    Fecha y hora de ingreso
                                </label>

                                <input
                                    type="datetime-local"
                                    name="fecha_ingreso"
                                    class="form-control @error('fecha_ingreso') is-invalid @enderror"
                                    value="{{ old('fecha_ingreso', now()->format('Y-m-d\TH:i')) }}"
                                    max="{{ now()->format('Y-m-d\TH:i') }}"
                                    required
                                >

                                @error('fecha_ingreso')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror

                            </div>

                            <div class="col-md-6 d-flex align-items-end">

                                <div class="form-check mb-2">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="documento_firmado"
                                        value="1"
                                        id="documentoFirmado"
                                        {{ old('documento_firmado') ? 'checked' : '' }}
                                    >

                                    <label
                                        class="form-check-label"
                                        for="documentoFirmado"
                                    >
                                        Documento de ingreso firmado
                                    </label>

                                </div>

                            </div>

                            <div class="col-12">

                                <label class="form-label">
                                    Motivo de ingreso
                                </label>

                                <textarea
                                    name="motivo_ingreso"
                                    class="form-control @error('motivo_ingreso') is-invalid @enderror"
                                    rows="4"
                                    maxlength="1000"
                                    required
                                >{{ old('motivo_ingreso') }}</textarea>

                                @error('motivo_ingreso')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror

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
                            Registrar ingreso
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
                    document.getElementById('modalIngreso')
                );

                modal.show();
            });
        </script>

    @endif

@endsection