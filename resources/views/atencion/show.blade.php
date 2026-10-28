@extends('layouts.app')

@section('title', 'Atención del paciente - Sanatorio La Promesa')

@section('menu-atencion', 'activo')

@section('content')
@if($ingreso->atencionAnterior && !$ingreso->esConsultaExterna())
@include('seguimientos.resumen', ['anterior' => $ingreso->atencionAnterior])
@endif
@if($ingreso->estado !== 'Activo')
<div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span><i class="bi bi-lock" aria-hidden="true"></i> {{ $ingreso->esConsultaExterna() ? 'Consulta externa finalizada.' : 'Ingreso dado de alta.' }} Historia clínica disponible en modo consulta.</span>
    @if($ingreso->estado === 'Dado de alta' && $ingreso->paciente->estado === 'Activo' && !$ingreso->paciente->ingresos()->where('estado', 'Activo')->exists())
    <a href="{{ route('seguimientos.crear', $ingreso) }}" class="btn btn-primary"><i class="bi bi-arrow-return-right" aria-hidden="true"></i> Nuevo seguimiento (el paciente regresó)</a>
    @endif
</div>
@endif
<fieldset class="atencion-contenido" @disabled($ingreso->estado !== 'Activo')>
@if($errors->any())
<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<!-- Encabezado -->
<div class="inicio-encabezado">

    <div class="inicio-icono">
        <i class="bi bi-clipboard2-pulse"></i>
    </div>

    <div>
        <h1>{{ $ingreso->id_ingreso_anterior ? 'Seguimiento' : 'Atención del paciente' }}</h1>
        <p>
            @if($ingreso->esConsultaExterna())
            <span class="badge bg-consulta fs-6"><i class="bi bi-person-walking" aria-hidden="true"></i> Consulta externa</span>
            Atención sin internamiento
            @else
            <span class="badge bg-danger fs-6"><i class="bi bi-hospital" aria-hidden="true"></i> Hospitalización</span>
            Expediente y seguimiento clínico del ingreso
            @endif
        </p>
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


<!-- Acciones del ingreso -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-4">

    <a
        href="{{ $ingreso->esConsultaExterna() ? route('pacientes.index') : route('atencion.index') }}"
        class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
        {{ $ingreso->esConsultaExterna() ? 'Volver a pacientes' : 'Volver a pacientes en atención' }}
    </a>

    @if($ingreso->estado === 'Activo')
    <button
        type="button"
        class="btn btn-outline-primary"
        data-bs-toggle="modal"
        data-bs-target="#modalEditarIngreso">
        <i class="bi bi-pencil"></i>
        {{ $ingreso->esConsultaExterna() ? 'Editar atención' : 'Editar ingreso' }}
    </button>
    @endif

    @if(! $ingreso->esConsultaExterna() && $ingreso->evoluciones->isNotEmpty() && ($ingreso->estado === 'Activo' || $ingreso->altaMedica))
    <a href="#modalAlta" role="button" class="btn btn-success accion-alta-medica" data-bs-toggle="modal" data-bs-target="#modalAlta"><i class="bi bi-clipboard2-check me-2" aria-hidden="true"></i>{{ $ingreso->altaMedica ? 'Ver alta médica' : 'Alta médica' }}</a>
    @endif
</div>


<!-- Datos del paciente -->
<div class="evolucion-card mb-3">
    <div class="row g-4">
        <div class="col-md-3">
            <strong>Expediente</strong>
            <p class="mb-0">{{ $ingreso->paciente->numero_expediente }}</p>
        </div>
        <div class="col-md-5">
            <strong>Paciente</strong>
            <p class="mb-0">
                @if($ingreso->paciente->estado_identificacion === 'Identificado')
                    {{ $ingreso->paciente->nombres }} {{ $ingreso->paciente->apellidos }}
                @else
                    No identificado
                    @if($ingreso->paciente->codigo_temporal)
                        <small class="d-block text-muted">{{ $ingreso->paciente->codigo_temporal }}</small>
                    @endif
                @endif
            </p>
        </div>
        <div class="col-6 col-md-2">
            <strong>Edad</strong>
            <p class="mb-0">{{ $ingreso->paciente->fecha_nacimiento ? $ingreso->paciente->fecha_nacimiento->age.' años' : 'Sin registro' }}</p>
        </div>
        <div class="col-6 col-md-2">
            <strong>Sexo</strong>
            <p class="mb-0">{{ $ingreso->paciente->sexo ?: 'Sin registro' }}</p>
        </div>
    </div>
</div>
<!-- Datos del ingreso -->
<div class="evolucion-card mb-3">
    <div class="row g-4">
        @unless($ingreso->esConsultaExterna())
        <!-- Habitación -->
        <div class="col-md-3">

            <strong>Habitación</strong>

            @if(
            $ingreso->hospitalizacion &&
            $ingreso->hospitalizacion->habitacionActual &&
            $ingreso->hospitalizacion
            ->habitacionActual
            ->habitacion
            )

            <p class="mb-2">

                Habitación

                <strong>
                    {{
                                $ingreso->hospitalizacion
                                    ->habitacionActual
                                    ->habitacion
                                    ->numero_habitacion
                            }}
                </strong>

            </p>

            @if($ingreso->hospitalizacion->estado === 'Activa')

            <button
                type="button"
                class="btn btn-outline-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalCambiarHabitacion">
                <i class="bi bi-arrow-left-right"></i>
                Cambiar habitación
            </button>

            @endif

            @elseif(!$ingreso->hospitalizacion)

            <p class="text-muted mb-2">
                {{ $ingreso->esConsultaExterna() ? 'Sin internamiento' : 'Sin habitación asignada' }}
            </p>

            @if($ingreso->estado === 'Activo')
            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalAsignarHabitacion">
                <i class="bi bi-door-open"></i>
                {{ $ingreso->esConsultaExterna() ? 'Internar paciente' : 'Asignar habitación' }}
            </button>
            @endif

            @else

            <p class="text-muted mb-0">
                Sin habitación asignada
            </p>

            @endif

        </div>
        @endunless

        <div class="{{ $ingreso->esConsultaExterna() ? 'col-md-9' : 'col-md-6' }}">
            <strong>{{ $ingreso->esConsultaExterna() ? 'Motivo de consulta' : 'Motivo de ingreso' }}</strong>
            <p class="mb-0 text-break">{{ $ingreso->motivo_ingreso ?: ($ingreso->esConsultaExterna() && $ingreso->estado === 'Activo' ? 'Se anota en la consulta, abajo' : 'Sin registro') }}</p>
        </div>
        <div class="col-md-3">
            <strong>{{ $ingreso->esConsultaExterna() ? 'Llegada' : 'Fecha de ingreso' }}</strong>
            <p class="mb-0">{{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}</p>
        </div>
    </div>
</div>
@php($ultimoExamen = $ingreso->evoluciones->sortByDesc('fecha_hora')->first())
@if(! $ingreso->esConsultaExterna() && $ultimoExamen && $ultimoExamen->examen_fisico)
<div class="alert alert-info d-flex flex-wrap gap-3">
    <strong>Últimos signos registrados · {{ $ultimoExamen->fecha_hora->format('d/m/Y H:i') }}</strong>
    @foreach(['presion_arterial'=>'PA (mmHg)', 'frecuencia_cardiaca'=>'FC (lpm)', 'frecuencia_respiratoria'=>'FR (rpm)', 'temperatura'=>'T (°C)', 'saturacion_oxigeno'=>'SpO₂ (%)'] as $campo=>$etiqueta)
    <span>{{ $etiqueta }}: {{ $ultimoExamen->examen_fisico[$campo] ?? '—' }}</span>
    @endforeach
</div>
@endif
@if($ingreso->esConsultaExterna())
@include('atencion.partials.consulta-emergencia')
@else
<div class="evolucion-card mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            @if($ingreso->evoluciones->isNotEmpty())
                <h5 class="mb-2"><i class="bi bi-check-circle-fill text-success me-2" aria-hidden="true"></i>{{ $ingreso->esConsultaExterna() ? 'Consulta externa registrada' : 'Valoración inicial registrada' }}</h5>
                <p class="text-muted mb-0">
                    {{ $ingreso->evoluciones->first()->fecha_hora->format('d/m/Y H:i') }}
                    · {{ $ingreso->evoluciones->first()->personal?->nombres }} {{ $ingreso->evoluciones->first()->personal?->apellidos }}
                </p>
            @else
                <div class="d-flex align-items-center gap-3 mb-2">
                    <h5 class="mb-0">{{ $ingreso->esConsultaExterna() ? 'Consulta externa' : 'Valoración inicial' }}</h5>
                    <span class="badge bg-warning text-dark">Pendiente</span>
                </div>
                <p class="text-muted mb-0">Registra la primera evaluación del paciente.</p>
            @endif
        </div>
        <a href="#modalValoracion" class="btn {{ $ingreso->evoluciones->isNotEmpty() ? 'btn-outline-primary' : 'btn-primary' }} ms-auto" role="button" data-bs-toggle="modal">
            {{ $ingreso->evoluciones->isNotEmpty() ? 'Ver valoración' : 'Iniciar valoración' }}
        </a>
    </div>
</div>

<div class="modal fade" id="modalValoracion" tabindex="-1" aria-labelledby="modalValoracion-titulo" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="modalValoracion-titulo">{{ $ingreso->esConsultaExterna() ? 'Consulta externa' : 'Valoración inicial' }}</h5><a href="#" role="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></a></div><div class="modal-body">@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@include('atencion.partials.valoracion')</div></div></div></div>
@if($ingreso->evoluciones->isNotEmpty())
@unless($ingreso->esConsultaExterna())
<section id="evoluciones">@include('atencion.partials.evoluciones')</section>
@endunless
@if($ingreso->tratamientos->isNotEmpty() && ! $ingreso->esConsultaExterna())
<details class="my-3"><summary>Tratamientos registrados anteriormente</summary>@include('atencion.partials.tratamientos')</details>
@endif
<section id="alta"><div class="modal fade" id="modalAlta" tabindex="-1" aria-labelledby="modalAlta-titulo" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="modalAlta-titulo">{{ $ingreso->esConsultaExterna() ? 'Finalizar consulta externa' : 'Alta médica' }}</h5><a href="#" role="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></a></div><div class="modal-body">@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@include('atencion.partials.alta')</div></div></div></div></section>
@endif
@endif
<!-- Modal asignar habitación -->
@if(!$ingreso->hospitalizacion)

<div
    class="modal fade"
    id="modalAsignarHabitacion"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog">

        <div class="modal-content">

            <form
                action="{{ route(
                            'hospitalizacion.guardar',
                            $ingreso->id_ingreso
                        ) }}"
                method="POST">

                @csrf

                <div class="modal-header">

                    <h5 class="modal-title">
                        {{ $ingreso->esConsultaExterna() ? 'Internar paciente' : 'Asignar habitación' }}
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

                    @if($ingreso->esConsultaExterna())
                    <p class="text-muted">La consulta externa pasará a ser una hospitalización. Se conservan los signos, valoraciones y medicamentos ya registrados.</p>
                    @endif

                    <div class="mb-3">

                        <label class="form-label">
                            Habitación disponible
                        </label>

                        <select
                            name="id_habitacion"
                            class="form-select"
                            required>

                            <option value="">
                                Seleccione una habitación
                            </option>

                            @foreach(
                            $habitacionesDisponibles
                            as $habitacion
                            )

                            <option
                                value="{{ $habitacion->id_habitacion }}">
                                Habitación
                                {{ $habitacion->numero_habitacion }}

                                @if($habitacion->tipo)
                                - {{ $habitacion->tipo }}
                                @endif
                            </option>

                            @endforeach

                        </select>

                        @if($habitacionesDisponibles->isEmpty())

                        <small class="text-danger d-block mt-2">
                            No hay habitaciones disponibles.
                        </small>

                        @endif

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Motivo de hospitalización
                        </label>

                        <textarea
                            name="motivo"
                            class="form-control"
                            rows="3"
                            maxlength="255">{{ old('motivo', $ingreso->esConsultaExterna() ? \Illuminate\Support\Str::limit((string) $ingreso->evoluciones->first()?->diagnostico, 255, '') : '') }}</textarea>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Observaciones
                        </label>

                        <textarea
                            name="observaciones"
                            class="form-control"
                            rows="3"
                            maxlength="1000">{{ old('observaciones') }}</textarea>

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
                        {{ $ingreso->esConsultaExterna() ? 'Internar paciente' : 'Asignar habitación' }}
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endif


<!-- Modal cambiar habitación -->
@if(
$ingreso->hospitalizacion &&
$ingreso->hospitalizacion->estado === 'Activa' &&
$ingreso->hospitalizacion->habitacionActual
)

<div
    class="modal fade"
    id="modalCambiarHabitacion"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog">

        <div class="modal-content">

            <form
                action="{{ route(
                            'hospitalizacion.cambiar-habitacion',
                            $ingreso->hospitalizacion->id_hospitalizacion
                        ) }}"
                method="POST">

                @csrf
                @method('PATCH')

                <div class="modal-header">

                    <h5 class="modal-title">
                        Cambiar habitación
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif


                    <div class="mb-3">

                        <label class="form-label">
                            Habitación actual
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="Habitación {{ $ingreso->hospitalizacion->habitacionActual->habitacion->numero_habitacion }}"
                            readonly>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Nueva habitación
                        </label>

                        <select
                            name="id_habitacion"
                            class="form-select"
                            required>

                            <option value="">
                                Seleccione una habitación
                            </option>

                            @foreach(
                            $habitacionesDisponibles
                            as $habitacion
                            )

                            <option
                                value="{{ $habitacion->id_habitacion }}">
                                Habitación
                                {{ $habitacion->numero_habitacion }}

                                @if($habitacion->tipo)
                                - {{ $habitacion->tipo }}
                                @endif
                            </option>

                            @endforeach

                        </select>

                        @if($habitacionesDisponibles->isEmpty())

                        <small class="text-danger d-block mt-2">
                            No hay otras habitaciones disponibles.
                        </small>

                        @endif

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Observaciones
                        </label>

                        <textarea
                            name="observaciones"
                            class="form-control"
                            rows="3"
                            maxlength="1000"></textarea>

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
                        Cambiar habitación
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endif

<!-- Modal editar ingreso -->
<div
    class="modal fade"
    id="modalEditarIngreso"
    tabindex="-1"
    aria-hidden="true">
    <div class="modal-dialog">

        <div class="modal-content">

            <form
                action="{{ route(
                        'ingresos.actualizar',
                        $ingreso->id_ingreso
                    ) }}"
                method="POST">

                @csrf
                @method('PUT')

                <div class="modal-header">

                    <h5 class="modal-title">
                        {{ $ingreso->esConsultaExterna() ? 'Editar atención' : 'Editar ingreso' }}
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif


                    <div class="mb-3">

                        <label class="form-label">
                            {{ $ingreso->esConsultaExterna() ? 'Fecha y hora de llegada' : 'Fecha y hora de ingreso' }}
                        </label>

                        <input
                            type="datetime-local"
                            name="fecha_ingreso"
                            class="form-control"
                            value="{{ $ingreso->fecha_ingreso->format('Y-m-d\TH:i') }}"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            {{ $ingreso->esConsultaExterna() ? 'Motivo de consulta' : 'Motivo de ingreso' }}
                        </label>

                        <textarea
                            name="motivo_ingreso"
                            class="form-control"
                            rows="3"
                            maxlength="255">{{ $ingreso->motivo_ingreso }}</textarea>

                    </div>

                    <div class="form-check">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="documento_firmado"
                            value="1"
                            id="editarDocumentoFirmado"
                            @checked($ingreso->documento_firmado)
                        >

                        <label
                            class="form-check-label"
                            for="editarDocumentoFirmado">
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
                        class="btn btn-primary">
                        Guardar cambios
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</fieldset>
@endsection

@section('scripts')
@include('atencion.partials.borradores-scripts')
@include('atencion.partials.ordenes-scripts')
<?php
    $formularioAnterior = old('_formulario', '');
    $modalAbierto = session('abrir_internamiento') && ! $ingreso->hospitalizacion ? 'modalAsignarHabitacion' : ((str_starts_with($formularioAnterior, 'valoracion') || request('consulta') === 'valoracion' || ($registroSeleccionado && $registroSeleccionado->getKey() === $ingreso->evoluciones->first()?->getKey() && $registroSeleccionado->ordenes_medicas === null)) ? 'modalValoracion'
        : ($formularioAnterior === 'alta' || request('consulta') === 'alta' ? 'modalAlta'
        : (str_starts_with($formularioAnterior, 'evolucion-') || $registroSeleccionado || request('nuevo') === '1' ? 'modalEvolucion' : null)));
?>
@if($modalAbierto)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalPendiente = document.getElementById(@json($modalAbierto));
    if (modalPendiente) bootstrap.Modal.getOrCreateInstance(modalPendiente).show();
});
</script>
@endif
@endsection
