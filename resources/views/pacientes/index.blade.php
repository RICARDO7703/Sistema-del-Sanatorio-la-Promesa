@extends('layouts.app')

@section('title', 'Pacientes - Sanatorio La Promesa')

@section('menu-pacientes', 'activo')

@section('content')

<!-- Encabezado -->
<div class="inicio-encabezado">

    <div class="inicio-icono">
        <i class="bi bi-people"></i>
    </div>

    <div>
        <h1>Pacientes</h1>
        <p>Consulta y gestión de pacientes registrados</p>
    </div>

</div>


<!-- Mensajes -->
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

    <form
        action="{{ route('pacientes.index') }}"
        method="GET"
    >

        <input
            type="hidden"
            name="form_context"
            value="buscar"
        >

        <div class="row g-3">

            <div class="col-md-7">

                <input
                    type="text"
                    name="buscar"
                    class="form-control @error('buscar') is-invalid @enderror"
                    placeholder="Nombre, expediente, DPI o código temporal"
                    value="{{ old('buscar', $buscar ?? '') }}"
                    maxlength="100"
                >

                @error('buscar')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror

            </div>

            <div class="col-md-3">

                <select
                    name="estado"
                    class="form-select @error('estado') is-invalid @enderror"
                >

                    <option
                        value="Activo"
                        {{ old('estado', $estado ?? 'Activo') === 'Activo'
                            ? 'selected'
                            : '' }}
                    >
                        Activos
                    </option>

                    <option
                        value="Inactivo"
                        {{ old('estado', $estado ?? '') === 'Inactivo'
                            ? 'selected'
                            : '' }}
                    >
                        Inactivos
                    </option>

                </select>

                @error('estado')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror

            </div>

            <div class="col-md-2">

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

            <h3>Listado de pacientes</h3>

            <p>
                {{ ($estado ?? 'Activo') === 'Activo'
                    ? 'Pacientes activos'
                    : 'Pacientes inactivos' }}
            </p>

        </div>

        @if(($estado ?? 'Activo') === 'Activo')

            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalPaciente"
            >
                <i class="bi bi-person-plus"></i>
                Nuevo paciente
            </button>

        @endif

    </div>


    <div class="table-responsive">

        <table class="table align-middle">

            <thead>
                <tr>
                    <th>Expediente</th>
                    <th>Paciente</th>
                    <th>Identificación</th>
                    <th>Edad</th>
                    <th>Teléfono</th>
                    <th>Situación actual</th>
                    <th>Habitación / Alta</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                @forelse($pacientes as $paciente)

                    @php
                        $editarConError =
                            old('form_context')
                            === 'editar-' . $paciente->id_paciente;

                        $contactosConError =
                            old('form_context')
                            === 'contactos-' . $paciente->id_paciente;

                        $estadoIdentificacionEditar =
                            $editarConError
                            ? old('estado_identificacion')
                            : $paciente->estado_identificacion;
                    @endphp

                    <tr>

                        <td>
                            {{ $paciente->numero_expediente }}
                        </td>


                        <td>

                            <div class="paciente-nombre">

                                <div class="paciente-avatar">

                                    @if(
                                        $paciente->nombres
                                        && $paciente->apellidos
                                    )

                                        {{
                                            mb_strtoupper(
                                                mb_substr(
                                                    $paciente->nombres,
                                                    0,
                                                    1
                                                )
                                            )
                                        }}

                                        {{
                                            mb_strtoupper(
                                                mb_substr(
                                                    $paciente->apellidos,
                                                    0,
                                                    1
                                                )
                                            )
                                        }}

                                    @else

                                        <i class="bi bi-person-question"></i>

                                    @endif

                                </div>

                                <span>

                                    @if(
                                        $paciente->estado_identificacion
                                        === 'Identificado'
                                    )

                                        {{ $paciente->nombres }}
                                        {{ $paciente->apellidos }}

                                    @else

                                        No identificado

                                        @if($paciente->codigo_temporal)

                                            <small class="d-block text-muted">
                                                {{ $paciente->codigo_temporal }}
                                            </small>

                                        @endif

                                    @endif

                                </span>

                            </div>

                        </td>


                        <td>

                            @if(
                                $paciente->estado_identificacion
                                === 'Identificado'
                            )

                                <span class="badge bg-success">
                                    Identificado
                                </span>

                            @else

                                <span class="badge bg-warning text-dark">
                                    No identificado
                                </span>

                            @endif

                        </td>


                        <td>

                            @if($paciente->fecha_nacimiento)

                                {{
                                    \Carbon\Carbon::parse(
                                        $paciente->fecha_nacimiento
                                    )->age
                                }}

                            @else
                                -
                            @endif

                        </td>


                        <td>
                            {{ $paciente->telefono ?: '-' }}
                        </td>


                        <td>

                            @if(
                                $paciente->situacion_actual
                                === 'Hospitalizado'
                            )

                                <span class="badge bg-danger">
                                    Hospitalizado
                                </span>

                            @elseif(
                                $paciente->situacion_actual
                                === 'En atención'
                            )

                                <span class="badge bg-primary">
                                    En atención
                                </span>

                            @elseif(
                                $paciente->situacion_actual
                                === 'Dado de alta'
                            )

                                <span class="badge bg-success">
                                    Dado de alta
                                </span>

                            @else

                                <span class="badge bg-secondary">
                                    Sin ingresos
                                </span>

                            @endif

                        </td>


                        <td>

                            @if(
                                $paciente->situacion_actual
                                === 'Hospitalizado'
                            )

                                @if($paciente->habitacion_actual)

                                    Habitación
                                    <strong>
                                        {{ $paciente->habitacion_actual }}
                                    </strong>

                                @else
                                    Sin habitación asignada
                                @endif

                            @elseif(
                                $paciente->situacion_actual
                                === 'Dado de alta'
                                && $paciente->fecha_ultima_alta
                            )

                                {{
                                    $paciente
                                        ->fecha_ultima_alta
                                        ->format('d/m/Y H:i')
                                }}

                            @else
                                -
                            @endif

                        </td>


                        <td>

                            <div class="d-grid gap-1" style="grid-template-columns: repeat(2, minmax(110px, 1fr)); min-width: 230px;">
    <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditar{{ $paciente->id_paciente }}" @disabled($paciente->estado !== 'Activo')><i class="bi bi-pencil"></i> Editar</button>
    <a href="{{ route('pacientes.expediente', $paciente->id_paciente) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder2-open"></i> Expediente</a>
    @if($paciente->estado === 'Activo')
    <form action="{{ route('pacientes.desactivar', $paciente->id_paciente) }}" method="POST" class="m-0" onsubmit="return confirm('¿Desea desactivar este paciente?');">
        @csrf @method('PATCH')
        <button type="submit" class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-person-x"></i> Desactivar</button>
    </form>
    @else
    <form action="{{ route('pacientes.activar', $paciente->id_paciente) }}" method="POST" class="m-0" onsubmit="return confirm('¿Desea activar nuevamente este paciente?');">
        @csrf @method('PATCH')
        <button type="submit" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-person-check"></i> Activar</button>
    </form>
    @endif
    <?php $ingresoParaAtencion = $paciente->estado === 'Activo' ? $paciente->ingresos->sortByDesc('fecha_ingreso')->firstWhere('estado', 'Activo') : null; ?>
    @if($ingresoParaAtencion)
    <a href="{{ route('atencion.show', $ingresoParaAtencion) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-clipboard2-pulse"></i> Atención al paciente</a>
    @else
    <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="El paciente no tiene un ingreso activo"><i class="bi bi-clipboard2-pulse"></i> Atención al paciente</button>
    @endif
    <button type="button" class="btn btn-outline-info btn-sm justify-self-center" style="grid-column: 1 / -1; justify-self: center;" data-bs-toggle="modal" data-bs-target="#modalContactos{{ $paciente->id_paciente }}"><i class="bi bi-person-lines-fill"></i> Contactos</button>
</div>

                        </td>

                    </tr>


                    <!-- Modal editar paciente -->
                    @if($paciente->estado === 'Activo')

                        <div
                            class="modal fade"
                            id="modalEditar{{ $paciente->id_paciente }}"
                            tabindex="-1"
                            aria-hidden="true"
                        >

                            <div class="modal-dialog modal-lg">

                                <div class="modal-content">

                                    <form
                                        action="{{ route(
                                            'pacientes.actualizar',
                                            $paciente->id_paciente
                                        ) }}"
                                        method="POST"
                                    >

                                        @csrf
                                        @method('PUT')

                                        <input
                                            type="hidden"
                                            name="form_context"
                                            value="editar-{{ $paciente->id_paciente }}"
                                        >


                                        <div class="modal-header">

                                            <h5 class="modal-title">
                                                Editar paciente
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
                                                        Estado de identificación
                                                    </label>

                                                    <select
                                                        name="estado_identificacion"
                                                        class="form-select estado-identificacion
                                                            @if($editarConError)
                                                                @error('estado_identificacion')
                                                                    is-invalid
                                                                @enderror
                                                            @endif
                                                        "
                                                        data-contenedor="editar{{ $paciente->id_paciente }}"
                                                        required
                                                    >

                                                        <option
                                                            value="Identificado"
                                                            {{
                                                                $estadoIdentificacionEditar
                                                                === 'Identificado'
                                                                ? 'selected'
                                                                : ''
                                                            }}
                                                        >
                                                            Identificado
                                                        </option>

                                                        <option
                                                            value="No identificado"
                                                            {{
                                                                $estadoIdentificacionEditar
                                                                === 'No identificado'
                                                                ? 'selected'
                                                                : ''
                                                            }}
                                                        >
                                                            No identificado
                                                        </option>

                                                    </select>

                                                    @if($editarConError)
                                                        @error('estado_identificacion')
                                                            <div class="invalid-feedback">
                                                                {{ $message }}
                                                            </div>
                                                        @enderror
                                                    @endif

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Expediente
                                                    </label>

                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        value="{{ $paciente->numero_expediente }}"
                                                        readonly
                                                    >

                                                </div>


                                                @if($paciente->codigo_temporal)

                                                    <div class="col-md-6">

                                                        <label class="form-label">
                                                            Código temporal
                                                        </label>

                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            value="{{ $paciente->codigo_temporal }}"
                                                            readonly
                                                        >

                                                    </div>

                                                @endif


                                                <div
                                                    id="editar{{ $paciente->id_paciente }}"
                                                    class="row g-3 m-0 p-0"
                                                >

                                                    <div class="col-md-6 campo-identificado">

                                                        <label class="form-label">
                                                            Nombres
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="nombres"
                                                            class="form-control solo-letras
                                                                @if($editarConError)
                                                                    @error('nombres')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            value="{{
                                                                $editarConError
                                                                ? old('nombres')
                                                                : $paciente->nombres
                                                            }}"
                                                            maxlength="100"
                                                            autocomplete="off"
                                                        >

                                                        @if($editarConError)
                                                            @error('nombres')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-6 campo-identificado">

                                                        <label class="form-label">
                                                            Apellidos
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="apellidos"
                                                            class="form-control solo-letras
                                                                @if($editarConError)
                                                                    @error('apellidos')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            value="{{
                                                                $editarConError
                                                                ? old('apellidos')
                                                                : $paciente->apellidos
                                                            }}"
                                                            maxlength="100"
                                                            autocomplete="off"
                                                        >

                                                        @if($editarConError)
                                                            @error('apellidos')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-6 campo-identificado">

                                                        <label class="form-label">
                                                            DPI
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="dpi"
                                                            class="form-control solo-dpi
                                                                @if($editarConError)
                                                                    @error('dpi')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            value="{{
                                                                $editarConError
                                                                ? old('dpi')
                                                                : $paciente->dpi
                                                            }}"
                                                            maxlength="13"
                                                            inputmode="numeric"
                                                            pattern="[0-9]{13}"
                                                            autocomplete="off"
                                                        >

                                                        @if($editarConError)
                                                            @error('dpi')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-6">

                                                        <label class="form-label">
                                                            Fecha de nacimiento
                                                        </label>

                                                        <input
                                                            type="date"
                                                            name="fecha_nacimiento"
                                                            class="form-control
                                                                @if($editarConError)
                                                                    @error('fecha_nacimiento')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            value="{{
                                                                $editarConError
                                                                ? old('fecha_nacimiento')
                                                                : (
                                                                    $paciente->fecha_nacimiento
                                                                    ? \Carbon\Carbon::parse(
                                                                        $paciente->fecha_nacimiento
                                                                    )->format('Y-m-d')
                                                                    : ''
                                                                )
                                                            }}"
                                                            max="{{ date('Y-m-d') }}"
                                                        >

                                                        @if($editarConError)
                                                            @error('fecha_nacimiento')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-6">

                                                        <label class="form-label">
                                                            Sexo
                                                        </label>

                                                        @php
                                                            $sexoEditar =
                                                                $editarConError
                                                                ? old('sexo')
                                                                : $paciente->sexo;
                                                        @endphp

                                                        <select
                                                            name="sexo"
                                                            class="form-select
                                                                @if($editarConError)
                                                                    @error('sexo')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                        >

                                                            <option value="">
                                                                Seleccione
                                                            </option>

                                                            <option
                                                                value="Masculino"
                                                                {{
                                                                    $sexoEditar === 'Masculino'
                                                                    ? 'selected'
                                                                    : ''
                                                                }}
                                                            >
                                                                Masculino
                                                            </option>

                                                            <option
                                                                value="Femenino"
                                                                {{
                                                                    $sexoEditar === 'Femenino'
                                                                    ? 'selected'
                                                                    : ''
                                                                }}
                                                            >
                                                                Femenino
                                                            </option>

                                                        </select>

                                                        @if($editarConError)
                                                            @error('sexo')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-6 campo-identificado">

                                                        <label class="form-label">
                                                            Teléfono
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="telefono"
                                                            class="form-control telefono
                                                                @if($editarConError)
                                                                    @error('telefono')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            value="{{
                                                                $editarConError
                                                                ? old('telefono')
                                                                : $paciente->telefono
                                                            }}"
                                                            maxlength="9"
                                                            inputmode="numeric"
                                                            placeholder="0000-0000"
                                                            autocomplete="off"
                                                        >

                                                        @if($editarConError)
                                                            @error('telefono')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-12">

                                                        <label class="form-label">
                                                            Dirección
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="direccion"
                                                            class="form-control
                                                                @if($editarConError)
                                                                    @error('direccion')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            value="{{
                                                                $editarConError
                                                                ? old('direccion')
                                                                : $paciente->direccion
                                                            }}"
                                                            maxlength="255"
                                                        >

                                                        @if($editarConError)
                                                            @error('direccion')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-6">

                                                        <label class="form-label">
                                                            Tipo de sangre
                                                        </label>

                                                        @php
                                                            $tipoSangreEditar =
                                                                $editarConError
                                                                ? old('tipo_sangre')
                                                                : $paciente->tipo_sangre;
                                                        @endphp

                                                        <select
                                                            name="tipo_sangre"
                                                            class="form-select
                                                                @if($editarConError)
                                                                    @error('tipo_sangre')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                        >

                                                            <option value="">
                                                                Seleccione
                                                            </option>

                                                            @foreach([
                                                                'A+',
                                                                'A-',
                                                                'B+',
                                                                'B-',
                                                                'AB+',
                                                                'AB-',
                                                                'O+',
                                                                'O-'
                                                            ] as $tipo)

                                                                <option
                                                                    value="{{ $tipo }}"
                                                                    {{
                                                                        $tipoSangreEditar === $tipo
                                                                        ? 'selected'
                                                                        : ''
                                                                    }}
                                                                >
                                                                    {{ $tipo }}
                                                                </option>

                                                            @endforeach

                                                        </select>

                                                        @if($editarConError)
                                                            @error('tipo_sangre')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-6">

                                                        <label class="form-label">
                                                            Alergias
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="alergias"
                                                            class="form-control
                                                                @if($editarConError)
                                                                    @error('alergias')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            value="{{
                                                                $editarConError
                                                                ? old('alergias')
                                                                : $paciente->alergias
                                                            }}"
                                                            maxlength="1000"
                                                        >

                                                        @if($editarConError)
                                                            @error('alergias')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-6">

                                                        <label class="form-label">
                                                            Antecedentes médicos
                                                        </label>

                                                        <textarea
                                                            name="antecedentes_medicos"
                                                            class="form-control
                                                                @if($editarConError)
                                                                    @error('antecedentes_medicos')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            rows="3"
                                                            maxlength="2000"
                                                        >{{ $editarConError
                                                            ? old('antecedentes_medicos')
                                                            : $paciente->antecedentes_medicos }}</textarea>

                                                        @if($editarConError)
                                                            @error('antecedentes_medicos')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-6">

                                                        <label class="form-label">
                                                            Antecedentes familiares
                                                        </label>

                                                        <textarea
                                                            name="antecedentes_familiares"
                                                            class="form-control
                                                                @if($editarConError)
                                                                    @error('antecedentes_familiares')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            rows="3"
                                                            maxlength="2000"
                                                        >{{ $editarConError
                                                            ? old('antecedentes_familiares')
                                                            : $paciente->antecedentes_familiares }}</textarea>

                                                        @if($editarConError)
                                                            @error('antecedentes_familiares')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-12">

                                                        <label class="form-label">
                                                            Observaciones generales
                                                        </label>

                                                        <textarea
                                                            name="observaciones_generales"
                                                            class="form-control
                                                                @if($editarConError)
                                                                    @error('observaciones_generales')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            rows="3"
                                                            maxlength="2000"
                                                        >{{ $editarConError
                                                            ? old('observaciones_generales')
                                                            : $paciente->observaciones_generales }}</textarea>

                                                        @if($editarConError)
                                                            @error('observaciones_generales')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>

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
                                                Guardar cambios
                                            </button>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        </div>

                    @endif


                    <!-- Modal contactos -->
                    <div
                        class="modal fade"
                        id="modalContactos{{ $paciente->id_paciente }}"
                        tabindex="-1"
                        aria-hidden="true"
                    >

                        <div class="modal-dialog modal-lg">

                            <div class="modal-content">

                                <div class="modal-header">

                                    <div>
                                        <h5 class="modal-title">
                                            Contactos del paciente
                                        </h5>

                                        <small class="text-muted">
                                            {{ $paciente->numero_expediente }}
                                            -
                                            @if(
                                                $paciente->estado_identificacion
                                                === 'Identificado'
                                            )
                                                {{ $paciente->nombres }}
                                                {{ $paciente->apellidos }}
                                            @else
                                                {{ $paciente->codigo_temporal }}
                                            @endif
                                        </small>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                    ></button>

                                </div>


                                <div class="modal-body">

                                    <!-- Contactos registrados -->
                                    <h6 class="mb-3">
                                        Contactos registrados
                                    </h6>

                                    @forelse($paciente->contactos as $contacto)

                                        @php
                                            $contactoEditarConError =
                                                old('form_context')
                                                === 'contacto-editar-'
                                                . $paciente->id_paciente
                                                . '-'
                                                . $contacto->id_contacto;
                                        @endphp

                                        <div class="border rounded p-3 mb-3">

                                            <form
                                                id="formContactoEditar{{ $contacto->id_contacto }}"
                                                action="{{ route(
                                                    'pacientes.contactos.actualizar',
                                                    $contacto->id_contacto
                                                ) }}"
                                                method="POST"
                                            >

                                                @csrf
                                                @method('PUT')

                                                <input
                                                    type="hidden"
                                                    name="form_context"
                                                    value="contacto-editar-{{ $paciente->id_paciente }}-{{ $contacto->id_contacto }}"
                                                >

                                                <div class="row g-3">

                                                    <div class="col-md-4">

                                                        <label class="form-label">
                                                            Nombre
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="nombre"
                                                            class="form-control solo-letras
                                                                @if($contactoEditarConError)
                                                                    @error('nombre')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            value="{{
                                                                $contactoEditarConError
                                                                ? old('nombre')
                                                                : $contacto->nombre
                                                            }}"
                                                            maxlength="150"
                                                            autocomplete="off"
                                                            required
                                                        >

                                                        @if($contactoEditarConError)
                                                            @error('nombre')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-4">

                                                        <label class="form-label">
                                                            Parentesco
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="parentesco"
                                                            class="form-control solo-letras
                                                                @if($contactoEditarConError)
                                                                    @error('parentesco')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            value="{{
                                                                $contactoEditarConError
                                                                ? old('parentesco')
                                                                : $contacto->parentesco
                                                            }}"
                                                            maxlength="50"
                                                            autocomplete="off"
                                                        >

                                                        @if($contactoEditarConError)
                                                            @error('parentesco')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>


                                                    <div class="col-md-4">

                                                        <label class="form-label">
                                                            Teléfono
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="telefono"
                                                            class="form-control telefono
                                                                @if($contactoEditarConError)
                                                                    @error('telefono')
                                                                        is-invalid
                                                                    @enderror
                                                                @endif
                                                            "
                                                            value="{{
                                                                $contactoEditarConError
                                                                ? old('telefono')
                                                                : $contacto->telefono
                                                            }}"
                                                            maxlength="9"
                                                            inputmode="numeric"
                                                            placeholder="0000-0000"
                                                            autocomplete="off"
                                                        >

                                                        @if($contactoEditarConError)
                                                            @error('telefono')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        @endif

                                                    </div>

                                                </div>

                                            </form>


                                            <div class="d-flex gap-2 mt-3">

                                                <button
                                                    type="submit"
                                                    form="formContactoEditar{{ $contacto->id_contacto }}"
                                                    class="btn btn-outline-primary btn-sm"
                                                >
                                                    <i class="bi bi-check-lg"></i>
                                                    Guardar cambios
                                                </button>

                                                <form
                                                    action="{{ route(
                                                        'pacientes.contactos.eliminar',
                                                        $contacto->id_contacto
                                                    ) }}"
                                                    method="POST"
                                                    class="d-inline"
                                                    onsubmit="return confirm('¿Desea quitar este contacto?');"
                                                >

                                                    @csrf
                                                    @method('DELETE')

                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-danger btn-sm"
                                                    >
                                                        <i class="bi bi-trash"></i>
                                                        Quitar
                                                    </button>

                                                </form>

                                            </div>

                                        </div>

                                    @empty

                                        <div class="alert alert-light border">
                                            El paciente no tiene contactos registrados.
                                        </div>

                                    @endforelse


                                    <hr class="my-4">


                                    <!-- Agregar contacto -->
                                    <h6 class="mb-3">
                                        Agregar contacto
                                    </h6>

                                    <form
                                        action="{{ route(
                                            'pacientes.contactos.guardar',
                                            $paciente->id_paciente
                                        ) }}"
                                        method="POST"
                                    >

                                        @csrf

                                        <input
                                            type="hidden"
                                            name="form_context"
                                            value="contactos-{{ $paciente->id_paciente }}"
                                        >

                                        <div class="row g-3">

                                            <div class="col-md-4">

                                                <label class="form-label">
                                                    Nombre
                                                </label>

                                                <input
                                                    type="text"
                                                    name="nombre"
                                                    class="form-control solo-letras
                                                        @if($contactosConError)
                                                            @error('nombre')
                                                                is-invalid
                                                            @enderror
                                                        @endif
                                                    "
                                                    value="{{
                                                        $contactosConError
                                                        ? old('nombre')
                                                        : ''
                                                    }}"
                                                    maxlength="150"
                                                    required
                                                >

                                                @if($contactosConError)
                                                    @error('nombre')
                                                        <div class="invalid-feedback">
                                                            {{ $message }}
                                                        </div>
                                                    @enderror
                                                @endif

                                            </div>


                                            <div class="col-md-4">

                                                <label class="form-label">
                                                    Parentesco
                                                </label>

                                                <input
                                                    type="text"
                                                    name="parentesco"
                                                    class="form-control solo-letras
                                                        @if($contactosConError)
                                                            @error('parentesco')
                                                                is-invalid
                                                            @enderror
                                                        @endif
                                                    "
                                                    value="{{
                                                        $contactosConError
                                                        ? old('parentesco')
                                                        : ''
                                                    }}"
                                                    maxlength="50"
                                                >

                                                @if($contactosConError)
                                                    @error('parentesco')
                                                        <div class="invalid-feedback">
                                                            {{ $message }}
                                                        </div>
                                                    @enderror
                                                @endif

                                            </div>


                                            <div class="col-md-4">

                                                <label class="form-label">
                                                    Teléfono
                                                </label>

                                                <input
                                                    type="text"
                                                    name="telefono"
                                                    class="form-control telefono
                                                        @if($contactosConError)
                                                            @error('telefono')
                                                                is-invalid
                                                            @enderror
                                                        @endif
                                                    "
                                                    value="{{
                                                        $contactosConError
                                                        ? old('telefono')
                                                        : ''
                                                    }}"
                                                    maxlength="9"
                                                    inputmode="numeric"
                                                    placeholder="0000-0000"
                                                >

                                                @if($contactosConError)
                                                    @error('telefono')
                                                        <div class="invalid-feedback">
                                                            {{ $message }}
                                                        </div>
                                                    @enderror
                                                @endif

                                            </div>


                                            <div class="col-12">

                                                <button
                                                    type="submit"
                                                    class="btn btn-primary"
                                                >
                                                    <i class="bi bi-plus-lg"></i>
                                                    Agregar contacto
                                                </button>

                                            </div>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        </div>

                    </div>

                @empty

                    <tr>
                        <td
                            colspan="8"
                            class="text-center py-4"
                        >
                            No hay pacientes registrados.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>


<!-- Modal nuevo paciente -->
<div
    class="modal fade"
    id="modalPaciente"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form
                action="{{ route('pacientes.guardar') }}"
                method="POST"
            >

                @csrf

                <input
                    type="hidden"
                    name="form_context"
                    value="nuevo"
                >


                <div class="modal-header">

                    <h5 class="modal-title">
                        Registrar paciente
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
                                Estado de identificación
                            </label>

                            <select
                                name="estado_identificacion"
                                id="estadoIdentificacionNuevo"
                                class="form-select
                                    @error('estado_identificacion')
                                        is-invalid
                                    @enderror
                                "
                                required
                            >

                                <option
                                    value="Identificado"
                                    {{
                                        old(
                                            'estado_identificacion',
                                            'Identificado'
                                        ) === 'Identificado'
                                        ? 'selected'
                                        : ''
                                    }}
                                >
                                    Identificado
                                </option>

                                <option
                                    value="No identificado"
                                    {{
                                        old('estado_identificacion')
                                        === 'No identificado'
                                        ? 'selected'
                                        : ''
                                    }}
                                >
                                    No identificado
                                </option>

                            </select>

                            @error('estado_identificacion')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-12">

                            <div
                                id="avisoNoIdentificado"
                                class="alert alert-warning d-none"
                            >
                                Se generará automáticamente un código temporal
                                para este paciente.
                            </div>

                        </div>


                        <div class="col-md-6 campo-nuevo-identificado">

                            <label class="form-label">
                                Nombres
                            </label>

                            <input
                                type="text"
                                name="nombres"
                                class="form-control solo-letras
                                    @error('nombres')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('nombres') }}"
                                maxlength="100"
                                autocomplete="off"
                            >

                            @error('nombres')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6 campo-nuevo-identificado">

                            <label class="form-label">
                                Apellidos
                            </label>

                            <input
                                type="text"
                                name="apellidos"
                                class="form-control solo-letras
                                    @error('apellidos')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('apellidos') }}"
                                maxlength="100"
                                autocomplete="off"
                            >

                            @error('apellidos')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6 campo-nuevo-identificado">

                            <label class="form-label">
                                DPI
                            </label>

                            <input
                                type="text"
                                name="dpi"
                                class="form-control solo-dpi
                                    @error('dpi')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('dpi') }}"
                                maxlength="13"
                                inputmode="numeric"
                                pattern="[0-9]{13}"
                                autocomplete="off"
                            >

                            @error('dpi')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Fecha de nacimiento
                            </label>

                            <input
                                type="date"
                                name="fecha_nacimiento"
                                class="form-control
                                    @error('fecha_nacimiento')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('fecha_nacimiento') }}"
                                max="{{ date('Y-m-d') }}"
                            >

                            @error('fecha_nacimiento')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Sexo
                            </label>

                            <select
                                name="sexo"
                                class="form-select
                                    @error('sexo')
                                        is-invalid
                                    @enderror
                                "
                            >

                                <option value="">
                                    Seleccione
                                </option>

                                <option
                                    value="Masculino"
                                    {{
                                        old('sexo') === 'Masculino'
                                        ? 'selected'
                                        : ''
                                    }}
                                >
                                    Masculino
                                </option>

                                <option
                                    value="Femenino"
                                    {{
                                        old('sexo') === 'Femenino'
                                        ? 'selected'
                                        : ''
                                    }}
                                >
                                    Femenino
                                </option>

                            </select>

                            @error('sexo')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6 campo-nuevo-identificado">

                            <label class="form-label">
                                Teléfono
                            </label>

                            <input
                                type="text"
                                name="telefono"
                                class="form-control telefono
                                    @error('telefono')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('telefono') }}"
                                maxlength="9"
                                inputmode="numeric"
                                placeholder="0000-0000"
                                autocomplete="off"
                            >

                            @error('telefono')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-12">

                            <label class="form-label">
                                Dirección
                            </label>

                            <input
                                type="text"
                                name="direccion"
                                class="form-control
                                    @error('direccion')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('direccion') }}"
                                maxlength="255"
                            >

                            @error('direccion')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Tipo de sangre
                            </label>

                            <select
                                name="tipo_sangre"
                                class="form-select
                                    @error('tipo_sangre')
                                        is-invalid
                                    @enderror
                                "
                            >

                                <option value="">
                                    Seleccione
                                </option>

                                @foreach([
                                    'A+',
                                    'A-',
                                    'B+',
                                    'B-',
                                    'AB+',
                                    'AB-',
                                    'O+',
                                    'O-'
                                ] as $tipo)

                                    <option
                                        value="{{ $tipo }}"
                                        {{
                                            old('tipo_sangre') === $tipo
                                            ? 'selected'
                                            : ''
                                        }}
                                    >
                                        {{ $tipo }}
                                    </option>

                                @endforeach

                            </select>

                            @error('tipo_sangre')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Alergias
                            </label>

                            <input
                                type="text"
                                name="alergias"
                                class="form-control
                                    @error('alergias')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('alergias') }}"
                                maxlength="1000"
                            >

                            @error('alergias')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Antecedentes médicos
                            </label>

                            <textarea
                                name="antecedentes_medicos"
                                class="form-control
                                    @error('antecedentes_medicos')
                                        is-invalid
                                    @enderror
                                "
                                rows="3"
                                maxlength="2000"
                            >{{ old('antecedentes_medicos') }}</textarea>

                            @error('antecedentes_medicos')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Antecedentes familiares
                            </label>

                            <textarea
                                name="antecedentes_familiares"
                                class="form-control
                                    @error('antecedentes_familiares')
                                        is-invalid
                                    @enderror
                                "
                                rows="3"
                                maxlength="2000"
                            >{{ old('antecedentes_familiares') }}</textarea>

                            @error('antecedentes_familiares')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-12">

                            <label class="form-label">
                                Observaciones generales
                            </label>

                            <textarea
                                name="observaciones_generales"
                                class="form-control
                                    @error('observaciones_generales')
                                        is-invalid
                                    @enderror
                                "
                                rows="3"
                                maxlength="2000"
                            >{{ old('observaciones_generales') }}</textarea>

                            @error('observaciones_generales')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <!-- Contacto opcional -->
                        <div class="col-12 mt-4">

                            <hr>

                            <h5 class="mb-1">
                                <i class="bi bi-person-lines-fill"></i>
                                Contacto del paciente
                            </h5>

                            <small class="text-muted">
                                Opcional. Puede registrarse después.
                            </small>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Nombre del contacto
                            </label>

                            <input
                                type="text"
                                name="contacto[nombre]"
                                class="form-control solo-letras
                                    @error('contacto.nombre')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('contacto.nombre') }}"
                                maxlength="150"
                                autocomplete="off"
                            >

                            @error('contacto.nombre')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Parentesco
                            </label>

                            <input
                                type="text"
                                name="contacto[parentesco]"
                                class="form-control solo-letras
                                    @error('contacto.parentesco')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('contacto.parentesco') }}"
                                maxlength="50"
                                autocomplete="off"
                                placeholder="Ej. Madre, padre, esposo"
                            >

                            @error('contacto.parentesco')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Teléfono del contacto
                            </label>

                            <input
                                type="text"
                                name="contacto[telefono]"
                                class="form-control telefono
                                    @error('contacto.telefono')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('contacto.telefono') }}"
                                maxlength="9"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="0000-0000"
                            >

                            @error('contacto.telefono')
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
                        Guardar paciente
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection


@section('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    const estadoNuevo =
        document.getElementById(
            'estadoIdentificacionNuevo'
        );

    const avisoNoIdentificado =
        document.getElementById(
            'avisoNoIdentificado'
        );


    function actualizarPacienteNuevo() {

        if (!estadoNuevo) {
            return;
        }

        const identificado =
            estadoNuevo.value === 'Identificado';

        document
            .querySelectorAll('.campo-nuevo-identificado')
            .forEach(function (contenedor) {

                const campo =
                    contenedor.querySelector(
                        'input, select, textarea'
                    );

                if (!campo) {
                    return;
                }

                if (
                    campo.name === 'nombres'
                    || campo.name === 'apellidos'
                    || campo.name === 'telefono'
                ) {
                    campo.required = identificado;
                }

            });

        if (identificado) {
            avisoNoIdentificado.classList.add(
                'd-none'
            );
        } else {
            avisoNoIdentificado.classList.remove(
                'd-none'
            );
        }

    }


    if (estadoNuevo) {

        estadoNuevo.addEventListener(
            'change',
            actualizarPacienteNuevo
        );

        actualizarPacienteNuevo();

    }


    document
        .querySelectorAll('.estado-identificacion')
        .forEach(function (selector) {

            function actualizarEdicion() {

                const idContenedor =
                    selector.dataset.contenedor;

                const contenedor =
                    document.getElementById(
                        idContenedor
                    );

                if (!contenedor) {
                    return;
                }

                const identificado =
                    selector.value
                    === 'Identificado';

                contenedor
                    .querySelectorAll(
                        '.campo-identificado'
                    )
                    .forEach(function (grupo) {

                        const campo =
                            grupo.querySelector(
                                'input, select, textarea'
                            );

                        if (!campo) {
                            return;
                        }

                        if (
                            campo.name === 'nombres'
                            || campo.name === 'apellidos'
                    || campo.name === 'telefono'
                        ) {
                            campo.required =
                                identificado;
                        }

                    });

            }

            selector.addEventListener(
                'change',
                actualizarEdicion
            );

            actualizarEdicion();

        });


    // Solo letras
    document
        .querySelectorAll('.solo-letras')
        .forEach(function (campo) {

            campo.addEventListener(
                'input',
                function () {

                    campo.value =
                        campo.value
                            .replace(
                                /[^\p{L}\s'-]/gu,
                                ''
                            )
                            .replace(
                                /\s{2,}/g,
                                ' '
                            );

                }
            );

            campo.addEventListener(
                'blur',
                function () {

                    let texto =
                        campo.value
                            .trim()
                            .replace(
                                /\s{2,}/g,
                                ' '
                            );

                    texto =
                        texto
                            .toLocaleLowerCase('es')
                            .replace(
                                /(^|[\s'-])\p{L}/gu,
                                function (letra) {
                                    return letra
                                        .toLocaleUpperCase(
                                            'es'
                                        );
                                }
                            );

                    campo.value = texto;

                }
            );

        });


    // Solo números en DPI
    document
        .querySelectorAll('.solo-dpi')
        .forEach(function (campo) {

            campo.addEventListener(
                'input',
                function () {

                    campo.value =
                        campo.value
                            .replace(/\D/g, '')
                            .substring(0, 13);

                }
            );

        });


    // Formato de teléfono
    document
        .querySelectorAll('.telefono')
        .forEach(function (campo) {

            campo.addEventListener(
                'input',
                function () {

                    let numero =
                        campo.value
                            .replace(/\D/g, '')
                            .substring(0, 8);

                    if (numero.length > 4) {

                        numero =
                            numero.substring(0, 4)
                            + '-'
                            + numero.substring(4);

                    }

                    campo.value = numero;

                }
            );

        });


    // Abrir modal correcto
    @if($errors->any())

        const contexto =
            @json(old('form_context'));

        if (
            contexto
            && contexto.startsWith('editar-')
        ) {

            const idPaciente =
                contexto.replace(
                    'editar-',
                    ''
                );

            const modalEditar =
                document.getElementById(
                    'modalEditar'
                    + idPaciente
                );

            if (modalEditar) {

                new bootstrap.Modal(
                    modalEditar
                ).show();

            }

        } else if (
            contexto
            && contexto.startsWith('contacto-editar-')
        ) {

            const partes =
                contexto.split('-');

            const idPaciente =
                partes[2];

            const modalContactos =
                document.getElementById(
                    'modalContactos'
                    + idPaciente
                );

            if (modalContactos) {

                new bootstrap.Modal(
                    modalContactos
                ).show();

            }

        } else if (
            contexto
            && contexto.startsWith('contactos-')
        ) {

            const idPaciente =
                contexto.replace(
                    'contactos-',
                    ''
                );

            const modalContactos =
                document.getElementById(
                    'modalContactos'
                    + idPaciente
                );

            if (modalContactos) {

                new bootstrap.Modal(
                    modalContactos
                ).show();

            }

        } else if (contexto === 'nuevo') {

            const modalNuevo =
                document.getElementById(
                    'modalPaciente'
                );

            if (modalNuevo) {

                new bootstrap.Modal(
                    modalNuevo
                ).show();

            }

        }

    @endif

});
</script>

@endsection