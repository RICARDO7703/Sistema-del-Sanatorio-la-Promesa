@extends('layouts.app')

@section('title', 'Expediente del paciente - Sanatorio La Promesa')

@section('menu-pacientes', 'activo')

@section('content')

<!-- Encabezado -->
<div class="inicio-encabezado">

    <div class="inicio-icono">
        <i class="bi bi-folder2-open"></i>
    </div>

    <div>
        <h1>Expediente del paciente</h1>
        <p>Información general e historial de ingresos</p>
    </div>

</div>

<div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
    @if(!$ingresoActivo && $paciente->estado === 'Activo')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoIngreso"><i class="bi bi-box-arrow-in-right"></i> Nuevo ingreso</button>
    @endif
    <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a pacientes</a>
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


<!-- Datos principales -->

<div class="evolucion-card mb-4">

    <h4 class="mb-3">Datos del paciente</h4>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">

        <div>

            <h4 class="mb-1">

                @if($paciente->estado_identificacion === 'Identificado')

                {{ $paciente->nombres }}
                {{ $paciente->apellidos }}

                @else

                Paciente no identificado

                @endif

            </h4>

            <span class="text-muted">
                {{ $paciente->numero_expediente }}
            </span>

        </div>

        <div>

            @if($paciente->estado_identificacion === 'Identificado')

            <span class="badge bg-success">
                Identificado
            </span>

            @else

            <span class="badge bg-warning text-dark">
                No identificado
            </span>

            @endif

            @if($paciente->estado === 'Activo')

            <span class="badge bg-primary">
                Activo
            </span>

            @else

            <span class="badge bg-secondary">
                Inactivo
            </span>

            @endif

        </div>

    </div>

    <div class="row g-4">

        @if($paciente->codigo_temporal)

        <div class="col-md-4">
            <strong>Código temporal</strong>
            <p class="mb-0">
                {{ $paciente->codigo_temporal }}
            </p>
        </div>

        @endif

        <div class="col-md-4">
            <strong>DPI</strong>
            <p class="mb-0">
                {{ $paciente->dpi ?: 'Sin registro' }}
            </p>
        </div>

        <div class="col-md-4">
            <strong>Fecha de nacimiento</strong>

            <p class="mb-0">

                @if($paciente->fecha_nacimiento)

                {{ $paciente->fecha_nacimiento->format('d/m/Y') }}

                @else

                Sin registro

                @endif

            </p>
        </div>

        <div class="col-md-4">
            <strong>Edad</strong>

            <p class="mb-0">

                @if($paciente->fecha_nacimiento)

                {{ $paciente->fecha_nacimiento->age }} años

                @else

                Sin registro

                @endif

            </p>
        </div>

        <div class="col-md-4">
            <strong>Sexo</strong>
            <p class="mb-0">
                {{ $paciente->sexo ?: 'Sin registro' }}
            </p>
        </div>

        <div class="col-md-4">
            <strong>Teléfono</strong>
            <p class="mb-0">
                {{ $paciente->telefono ?: 'Sin registro' }}
            </p>
        </div>

        <div class="col-md-4">
            <strong>Tipo de sangre</strong>
            <p class="mb-0">
                {{ $paciente->tipo_sangre ?: 'Sin registro' }}
            </p>
        </div>

        <div class="col-md-8">
            <strong>Dirección</strong>
            <p class="mb-0">
                {{ $paciente->direccion ?: 'Sin registro' }}
            </p>
        </div>

    </div>


@if(collect([$paciente->alergias, $paciente->antecedentes_medicos, $paciente->antecedentes_familiares, $paciente->observaciones_generales])->contains(fn ($dato) => trim((string) $dato) !== ''))
<hr class="my-4">
<div>

    <h4 class="mb-4">
        <i class="bi bi-clipboard2-pulse"></i>
        Información clínica general
    </h4>

    <div class="row g-4">

        @if(trim((string) $paciente->alergias) !== '')
        <div class="col-md-6">

            <strong>Alergias</strong>

            <p class="mb-0">
                {{ $paciente->alergias }}
            </p>

        </div>
        @endif

        @if(trim((string) $paciente->antecedentes_medicos) !== '')
        <div class="col-md-6">

            <strong>Antecedentes médicos</strong>

            <p class="mb-0">
                {{ $paciente->antecedentes_medicos }}
            </p>

        </div>
        @endif

        @if(trim((string) $paciente->antecedentes_familiares) !== '')
        <div class="col-md-6">

            <strong>Antecedentes familiares</strong>

            <p class="mb-0">
                {{ $paciente->antecedentes_familiares }}
            </p>

        </div>
        @endif

        @if(trim((string) $paciente->observaciones_generales) !== '')
        <div class="col-md-6">

            <strong>Observaciones generales</strong>

            <p class="mb-0">
                {{ $paciente->observaciones_generales }}
            </p>

        </div>
        @endif

    </div>

</div>
@endif

</div>

<!-- Ingreso actual -->
<div class="evolucion-card mb-4" id="historial-evoluciones"><h4 class="mb-4"><i class="bi bi-clock-history"></i> Ingresos y evoluciones clínicas</h4>

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h5 class="mb-0">
            <i class="bi bi-hospital"></i>
            Ingreso actual
        </h5>



    </div>

    @if($ingresoActivo)

    <div class="row g-3 align-items-center">

        <div class="col-md-3">

            <strong>Fecha de ingreso</strong>

            <p class="mb-0">
                {{ $ingresoActivo->fecha_ingreso->format('d/m/Y H:i') }}
            </p>

        </div>

        <div class="col-md-4">

            <strong>Motivo</strong>

            <p class="mb-0">
                {{ $ingresoActivo->motivo_ingreso ?: 'Sin registro' }}
            </p>

        </div>

        <div class="col-md-2">

            <strong>Estado</strong>

            <p class="mb-0">
                <span class="badge bg-success">
                    {{ $ingresoActivo->estado }}
                </span>
            </p>

        </div>

                <div class="col-md-3"><strong>Habitación</strong><p class="mb-0">{{ $ingresoActivo->hospitalizacion?->habitacionActual?->habitacion?->numero_habitacion ?? 'Sin habitación asignada' }}</p></div>
<div class="col-12 text-md-end">

            <a
                href="{{ route(
                        'atencion.show',
                        $ingresoActivo->id_ingreso
                    ) }}"
                class="btn btn-primary">
                <i class="bi bi-clipboard2-pulse"></i>
                Continuar atención
            </a>

        </div>

    </div>

    @else

    <div class="alert alert-light border mb-0">

        <i class="bi bi-info-circle"></i>

        El paciente no tiene un ingreso activo.

    </div>

    @endif


<!-- Historial de ingresos -->
<hr class="my-4">

    <h5 class="mb-3">
        <i class="bi bi-clock-history"></i>
        Historial de ingresos y evoluciones
    </h5>

    <div class="table-responsive">

        <table class="table align-middle">

            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Motivo</th>
                    <th>Documento firmado</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                @forelse($ingresos as $ingreso)

                <tr>

                    <td>
                        {{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}
                    </td>

                    <td>
                        {{ $ingreso->motivo_ingreso ?: 'Sin registro' }}
                    </td>

                    <td>

                        @if($ingreso->documento_firmado)

                        <span class="badge bg-success">
                            Sí
                        </span>

                        @else

                        <span class="badge bg-secondary">
                            No
                        </span>

                        @endif

                    </td>

                    <td>

                        @if($ingreso->estado === 'Activo')

                        <span class="badge bg-success">
                            Activo
                        </span>

                        @elseif($ingreso->estado === 'Dado de alta')

                        <span class="badge bg-primary">
                            Dado de alta
                        </span>

                        @else

                        <span class="badge bg-secondary">
                            {{ $ingreso->estado }}
                        </span>

                        @endif

                    </td>

                    <td>

                        <a
                            href="{{ route(
                                        'atencion.show',
                                        $ingreso->id_ingreso
                                    ) }}#evoluciones"
                            class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye"></i>
                            Ver evoluciones
                        </a>

                    </td>

                </tr>

                @empty

                <tr>

                    <td
                        colspan="5"
                        class="text-center py-4">
                        El paciente todavía no tiene ingresos registrados.
                    </td>

                </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>



<!-- Contactos del paciente -->
<div class="evolucion-card mb-4">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1">
                <i class="bi bi-person-lines-fill"></i>
                Contactos
            </h4>

            <p class="text-muted mb-0">
                Familiares o personas de contacto
            </p>
        </div>

        <button
            type="button"
            class="btn btn-primary btn-sm"
            data-bs-toggle="modal"
            data-bs-target="#modalAgregarContacto">
            <i class="bi bi-plus-lg"></i>
            Agregar contacto
        </button>

    </div>

    <div class="table-responsive">

        <table class="table align-middle">

            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Parentesco</th>
                    <th>Teléfono</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                @forelse($paciente->contactos as $contacto)

                <tr>

                    <td>
                        {{ $contacto->nombre ?: '-' }}
                    </td>

                    <td>
                        {{ $contacto->parentesco ?: '-' }}
                    </td>

                    <td>
                        {{ $contacto->telefono ?: '-' }}
                    </td>

                    <td>

                        <div class="d-flex gap-2">

                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#modalEditarContacto{{ $contacto->id_contacto }}">
                                <i class="bi bi-pencil"></i>
                                Editar
                            </button>

                            <form
                                action="{{ route(
                                        'pacientes.contactos.eliminar',
                                        $contacto->id_contacto
                                    ) }}"
                                method="POST"
                                onsubmit="return confirm('¿Desea quitar este contacto?')">

                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash"></i>
                                    Quitar
                                </button>

                            </form>

                        </div>

                    </td>

                </tr>

                @empty

                <tr>
                    <td
                        colspan="4"
                        class="text-center text-muted py-3">
                        El paciente no tiene contactos registrados.
                    </td>
                </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>


<!-- Modal agregar contacto -->
<div
    class="modal fade"
    id="modalAgregarContacto"
    tabindex="-1"
    aria-hidden="true">
    <div class="modal-dialog">

        <div class="modal-content">

            <form
                action="{{ route(
                    'pacientes.contactos.guardar',
                    $paciente->id_paciente
                ) }}"
                method="POST">

                @csrf

                <div class="modal-header">

                    <h5 class="modal-title">
                        Agregar contacto
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            Nombre
                        </label>

                        <input
                            type="text"
                            name="nombre"
                            class="form-control"
                            maxlength="150"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Parentesco
                        </label>

                        <input
                            type="text"
                            name="parentesco"
                            class="form-control"
                            maxlength="50">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Teléfono
                        </label>

                        <input
                            type="text"
                            name="telefono"
                            class="form-control"
                            maxlength="20">

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary">
                        Guardar contacto
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- Modales editar contacto -->
@foreach($paciente->contactos as $contacto)

<div
    class="modal fade"
    id="modalEditarContacto{{ $contacto->id_contacto }}"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog">

        <div class="modal-content">

            <form
                action="{{ route(
                        'pacientes.contactos.actualizar',
                        $contacto->id_contacto
                    ) }}"
                method="POST">

                @csrf
                @method('PUT')

                <div class="modal-header">

                    <h5 class="modal-title">
                        Editar contacto
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            Nombre
                        </label>

                        <input
                            type="text"
                            name="nombre"
                            class="form-control"
                            value="{{ $contacto->nombre }}"
                            maxlength="150"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Parentesco
                        </label>

                        <input
                            type="text"
                            name="parentesco"
                            class="form-control"
                            value="{{ $contacto->parentesco }}"
                            maxlength="50">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Teléfono
                        </label>

                        <input
                            type="text"
                            name="telefono"
                            class="form-control"
                            value="{{ $contacto->telefono }}"
                            maxlength="20">

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary">
                        Guardar cambios
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endforeach


<!-- Modal nuevo ingreso -->
<div
    class="modal fade"
    id="modalNuevoIngreso"
    tabindex="-1"
    aria-hidden="true">
    <div class="modal-dialog">

        <div class="modal-content">

            <form
                action="{{ route('ingresos.guardar') }}"
                method="POST">

                @csrf

                <input
                    type="hidden"
                    name="id_paciente"
                    value="{{ $paciente->id_paciente }}">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Registrar ingreso
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            Paciente
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="
                                    @if($paciente->estado_identificacion === 'Identificado')
                                        {{ $paciente->nombres }} {{ $paciente->apellidos }}
                                    @else
                                        No identificado - {{ $paciente->codigo_temporal }}
                                    @endif
                                "
                            readonly>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Fecha y hora de ingreso
                        </label>

                        <input
                            type="datetime-local"
                            name="fecha_ingreso"
                            class="form-control @error('fecha_ingreso') is-invalid @enderror"
                            value="{{ old('fecha_ingreso') }}"
                            required>

                        @error('fecha_ingreso')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Habitación
                        </label>

                        <select
                            name="id_habitacion"
                            class="form-select @error('id_habitacion') is-invalid @enderror"
                            required>

                            <option value="">
                                Seleccione una habitación
                            </option>

                            @foreach($habitacionesDisponibles as $habitacion)

                            <option
                                value="{{ $habitacion->id_habitacion }}"
                                @selected(
                                old('id_habitacion')==$habitacion->id_habitacion
                                )
                                >
                                Habitación
                                {{ $habitacion->numero_habitacion }}

                                @if($habitacion->tipo)
                                - {{ $habitacion->tipo }}
                                @endif
                            </option>

                            @endforeach

                        </select>

                        @error('id_habitacion')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror

                        @if($habitacionesDisponibles->isEmpty())

                        <small class="text-danger d-block mt-2">
                            No hay habitaciones disponibles.
                        </small>

                        @endif

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Motivo de ingreso
                        </label>

                        <textarea
                            name="motivo_ingreso"
                            class="form-control @error('motivo_ingreso') is-invalid @enderror"
                            rows="3"
                            maxlength="255">{{ old('motivo_ingreso') }}</textarea>

                        @error('motivo_ingreso')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror

                    </div>

                    <div class="form-check">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="documento_firmado"
                            value="1"
                            id="documentoFirmado"
                            @checked(old('documento_firmado'))>

                        <label
                            class="form-check-label"
                            for="documentoFirmado">
                            Documento de ingreso firmado
                        </label>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        {{ $habitacionesDisponibles->isEmpty()
                                ? 'disabled'
                                : '' }}>
                        Registrar ingreso
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@if($errors->any() && !$ingresoActivo)

<script>
    document.addEventListener(
        'DOMContentLoaded',
        function() {
            const modal = new bootstrap.Modal(
                document.getElementById('modalNuevoIngreso')
            );

            modal.show();
        }
    );
</script>


@endif

@foreach($ingresos as $episodio)
@if($episodio->altaMedica)
<div class="evolucion-card mt-3"><h4>Alta médica — ingreso {{ $episodio->id_ingreso }}</h4><p>{{ $episodio->altaMedica->fecha_hora->format('d/m/Y H:i') }} · {{ $episodio->altaMedica->medico?->nombres }} {{ $episodio->altaMedica->medico?->apellidos }}</p><p><strong>Diagnóstico final:</strong> {{ $episodio->altaMedica->diagnostico_final }}</p><p><strong>Condición de egreso:</strong> {{ $episodio->altaMedica->condicion_egreso }}</p><p><strong>Indicaciones:</strong> {{ $episodio->altaMedica->indicaciones_alta }}</p></div>
@endif
@endforeach
@endsection