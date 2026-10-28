@extends('layouts.app')

@section('title', 'Expediente del paciente - Sanatorio La Promesa')

@section(request('desde') === 'pacientes' ? 'menu-pacientes' : 'menu-expedientes', 'activo')

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
    @if(request('desde') === 'pacientes')
    <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a pacientes</a>
    @else
    <a href="{{ route('expedientes.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a expedientes</a>
    @endif
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


<!-- Ficha unificada del paciente -->
<section class="evolucion-card ficha-paciente mb-4" id="datos-paciente" aria-label="Datos del paciente">
    <header class="ficha-identidad">
        <div class="ficha-avatar"><i class="bi bi-person" aria-hidden="true"></i></div>
        <div class="ficha-nombre">
            <h2>{{ $paciente->estado_identificacion === 'Identificado' ? trim($paciente->nombres.' '.$paciente->apellidos) : 'Paciente no identificado' }}</h2>
            <p><i class="bi bi-folder2-open" aria-hidden="true"></i> Expediente <strong>{{ $paciente->numero_expediente }}</strong></p>
        </div>
        <div class="ficha-estados">
            <span class="badge {{ $paciente->estado_identificacion === 'Identificado' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $paciente->estado_identificacion }}</span>
            <span class="badge {{ $paciente->estado === 'Activo' ? 'bg-primary' : 'bg-secondary' }}">{{ $paciente->estado }}</span>
        </div>
    </header>
    @php($seguimientoOrigen = $ingresos->first(fn ($atencion) => $atencion->estado === 'Dado de alta' && $atencion->altaMedica))
    <div class="expediente-acciones" id="acciones-expediente">
        <div class="expediente-guia"><h3>Atención del paciente</h3><p>{{ $ingresoActivo ? 'Ya tiene una atención abierta. Continúe donde se quedó.' : 'Elija qué necesita hacer hoy.' }}</p></div>
        @if($paciente->estado !== 'Activo')
            <p class="mb-0">Este paciente está inactivo. Actívelo desde Pacientes para registrar una atención.</p>
        @elseif($ingresoActivo)
            <a href="{{ route('atencion.show', $ingresoActivo) }}" class="btn btn-primary expediente-accion"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i><span><strong>Continuar atención</strong><small>Abrir la atención actual</small></span></a>
        @else
            @if($seguimientoOrigen)
                <a href="{{ route('seguimientos.crear', $seguimientoOrigen) }}" class="btn btn-primary expediente-accion"><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span><strong>Nuevo seguimiento</strong><small>Registrar el regreso del paciente</small></span></a>
            @else
                <form action="{{ route('consultas.iniciar', $paciente->id_paciente) }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-primary expediente-accion w-100"><i class="bi bi-person-walking" aria-hidden="true"></i><span><strong>Primera consulta</strong><small>Registrar una consulta externa</small></span></button>
                </form>
            @endif
            <button type="button" class="btn btn-danger expediente-accion" data-bs-toggle="modal" data-bs-target="#modalNuevoIngreso"><i class="bi bi-hospital" aria-hidden="true"></i><span><strong>Hospitalizar paciente</strong><small>Registrar ingreso y habitación</small></span></button>
        @endif
    </div>
    <dl class="ficha-datos">
        @foreach([
            ['calendar3', 'Edad', $paciente->fecha_nacimiento ? $paciente->fecha_nacimiento->age.' años' : 'Sin registro'],
            ['person', 'Sexo', $paciente->sexo],
            ['calendar-date', 'Fecha de nacimiento', $paciente->fecha_nacimiento?->format('d/m/Y')],
            ['person-vcard', 'DPI', $paciente->dpi],
            ['telephone', 'Teléfono del paciente', $paciente->telefono],
            ['droplet', 'Tipo de sangre', $paciente->tipo_sangre],
        ] as [$icono, $etiqueta, $valor])
            <div class="ficha-dato"><dt><i class="bi bi-{{ $icono }}" aria-hidden="true"></i> {{ $etiqueta }}</dt><dd>{{ $valor ?: 'Sin registro' }}</dd></div>
        @endforeach
        @if($paciente->codigo_temporal)
            <div class="ficha-dato"><dt><i class="bi bi-upc-scan" aria-hidden="true"></i> Código temporal</dt><dd>{{ $paciente->codigo_temporal }}</dd></div>
        @endif
        <div class="ficha-dato ficha-ancho"><dt><i class="bi bi-geo-alt" aria-hidden="true"></i> Dirección</dt><dd>{{ $paciente->direccion ?: 'Sin registro' }}</dd></div>
    </dl>
    @if($paciente->contactos->isNotEmpty())
        <div class="ficha-contactos" id="contactos-paciente">
            <h3><i class="bi bi-person-lines-fill" aria-hidden="true"></i> Personas de contacto</h3>
            <ul>
                @foreach($paciente->contactos as $contacto)
                    <li><div><strong>{{ $contacto->nombre ?: 'Sin registro' }}</strong><span>{{ $contacto->parentesco ?: 'Parentesco sin registro' }}</span></div><div class="ficha-telefono"><i class="bi bi-telephone" aria-hidden="true"></i> <span>{{ $contacto->telefono ?: 'Teléfono sin registro' }}</span></div></li>
                @endforeach
            </ul>
        </div>
    @endif
    @if(collect([$paciente->alergias, $paciente->antecedentes_medicos, $paciente->antecedentes_familiares, $paciente->observaciones_generales])->contains(fn ($dato) => trim((string) $dato) !== ''))
        <div class="ficha-clinica" aria-label="Información clínica general">
            @foreach([
                ['exclamation-triangle', 'Alergias', $paciente->alergias, 'ficha-alergias'],
                ['clipboard2-pulse', 'Antecedentes médicos', $paciente->antecedentes_medicos, ''],
                ['people', 'Antecedentes familiares', $paciente->antecedentes_familiares, ''],
                ['journal-text', 'Observaciones generales', $paciente->observaciones_generales, ''],
            ] as [$icono, $etiqueta, $valor, $clase])
                @if(trim((string) $valor) !== '')
                    <div class="ficha-nota {{ $clase }}"><h3><i class="bi bi-{{ $icono }}" aria-hidden="true"></i> {{ $etiqueta }}</h3><p>{{ $valor }}</p></div>
                @endif
            @endforeach
        </div>
    @endif
</section>

<section class="evolucion-card mb-4" id="historial-evoluciones">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
        <h4 class="mb-0"><i class="bi bi-clock-history"></i> Historial de atenciones</h4>
   </div>
    <p class="text-muted mb-4">La atención más reciente aparece primero. Pulse «Ver detalles» para consultar sus documentos.</p>
    <div class="d-flex flex-column gap-3">
        @forelse($ingresos as $ingreso)
        <details class="historial-atencion border rounded-3 overflow-hidden">
            <summary class="historial-resumen p-3">
                <span class="historial-fecha"><i class="bi bi-calendar-check me-2" aria-hidden="true"></i>Atendido el {{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}
                    <span class="badge {{ $ingreso->esConsultaExterna() ? 'bg-consulta' : 'bg-danger' }} ms-1">{{ $ingreso->nombreTipoAtencion() }}</span>@if($ingreso->id_ingreso_anterior)<span class="badge bg-info text-dark ms-1">Regreso</span>@endif
                </span>
                <span class="historial-diagnostico"><strong>Diagnóstico:</strong> {{ $ingreso->altaMedica?->diagnostico_final ?: ($ingreso->evoluciones->last()?->diagnostico ?: 'Pendiente de registrar') }}</span>
                <span class="historial-ver text-primary"><span class="historial-abrir">Ver detalles</span><span class="historial-cerrar">Ocultar detalles</span><i class="bi bi-chevron-down ms-2" aria-hidden="true"></i></span>
            </summary>
            <header class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 bg-light border-bottom">
                <h5 class="mb-0">
                    @if($ingreso->id_ingreso_anterior)<span class="badge bg-info text-dark me-2">Seguimiento</span>@endif
                    @if($ingreso->esConsultaExterna())
                    <i class="bi bi-person-walking text-consulta me-2" aria-hidden="true"></i>{{ $ingreso->estado === 'Activo' ? 'Consulta externa actual' : 'Consulta externa' }} del {{ $ingreso->fecha_ingreso->format('d/m/Y') }}
                    @else
                    <i class="bi bi-hospital text-danger me-2" aria-hidden="true"></i>{{ $ingreso->estado === 'Activo' ? 'Ingreso actual' : 'Ingreso' }} #{{ $ingreso->getKey() }}
                    @endif
                </h5>
                <span class="badge {{ $ingreso->estado === 'Activo' ? 'bg-success' : ($ingreso->estado === 'Dado de alta' ? 'bg-primary' : 'bg-secondary') }}">{{ $ingreso->estado === 'Dado de alta' && $ingreso->esConsultaExterna() ? 'Atención finalizada' : $ingreso->estado }}</span>
            </header>
            <div class="p-3">
                @if($ingreso->atencionAnterior)<p class="text-primary"><i class="bi bi-arrow-return-right" aria-hidden="true"></i> El paciente regresó por la consulta del {{ $ingreso->atencionAnterior->fecha_ingreso->format('d/m/Y') }}@if($ingreso->atencionAnterior->altaMedica?->diagnostico_final) ({{ $ingreso->atencionAnterior->altaMedica->diagnostico_final }})@endif.</p>@endif
                <dl class="row g-3 mb-3">
                    <div class="col-sm-6"><dt class="text-muted fw-normal">{{ $ingreso->esConsultaExterna() ? 'Fecha de llegada' : 'Fecha de ingreso' }}</dt><dd class="fw-semibold mb-0">{{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}</dd></div>
                    <div class="col-sm-6"><dt class="text-muted fw-normal">{{ $ingreso->esConsultaExterna() ? 'Fecha de salida' : 'Fecha de egreso' }}</dt><dd class="fw-semibold mb-0">{{ $ingreso->altaMedica?->fecha_hora?->format('d/m/Y H:i') ?? ($ingreso->estado === 'Activo' ? 'En atención' : 'Sin egreso médico registrado') }}</dd></div>
                    <div class="col-12"><dt class="text-muted fw-normal">{{ $ingreso->esConsultaExterna() ? 'Motivo de consulta' : 'Motivo de ingreso' }}</dt><dd class="mb-0 text-break">{{ $ingreso->motivo_ingreso ?: 'Sin registro' }}</dd></div>
                    @if($ingreso->altaMedica?->medicamentos->isNotEmpty())
                    <div class="col-12"><dt class="text-muted fw-normal">Medicamentos para casa</dt><dd class="mb-0">
                        <ul class="mb-0 ps-3">
                            @foreach($ingreso->altaMedica->medicamentos as $medicamento)
                            <li class="text-break">{{ $medicamento->medicamento }} · {{ $medicamento->dosis }} · {{ $medicamento->frecuencia }}@if($medicamento->duracion) · {{ $medicamento->duracion }}@endif</li>
                            @endforeach
                        </ul>
                    </dd></div>
                    @endif

                </dl>
                @unless($ingreso->esConsultaExterna())
                @include('expedientes.partials.habitaciones', ['ingreso'=>$ingreso])
                @endunless
                <p class="small mb-3 {{ $ingreso->documento_firmado ? 'text-success' : 'text-muted' }}"><i class="bi {{ $ingreso->documento_firmado ? 'bi-check-circle-fill' : 'bi-info-circle' }} me-1"></i>{{ $ingreso->documento_firmado ? 'Documento de ingreso firmado' : 'Documento de ingreso pendiente de firma' }}</p>
                @include('expedientes.partials.documentos-ingreso', ['ingreso'=>$ingreso])

            </div>
        </details>
        @empty
        <p class="text-muted border rounded p-3 mb-0">El paciente todavía no tiene ingresos registrados.</p>
        @endforelse
    </div>
</section>

@include('expedientes.partials.modales-documentos')

<!-- Modal nueva atención -->
<div class="modal fade" id="modalNuevoIngreso" tabindex="-1" aria-labelledby="modalNuevoIngreso-titulo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('ingresos.guardar') }}" method="POST" novalidate data-validar-en-linea data-validar-al-enviar>
                @csrf
                <input type="hidden" name="id_paciente" value="{{ $paciente->id_paciente }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNuevoIngreso-titulo">Hospitalizar paciente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="expediente-paciente">Paciente</label>
                        <input type="text" id="expediente-paciente" class="form-control" value="{{ $paciente->estado_identificacion === 'Identificado' ? $paciente->nombres.' '.$paciente->apellidos : 'No identificado - '.$paciente->codigo_temporal }}" readonly>
                    </div>
                    @include('ingresos.partials.campos-nueva-atencion', ['prefijo' => 'expediente', 'recuperar' => true])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-hospital" aria-hidden="true"></i> Hospitalizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if(($errors->any() || (session('error') && old('id_paciente'))) && !$ingresoActivo)

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

@endsection