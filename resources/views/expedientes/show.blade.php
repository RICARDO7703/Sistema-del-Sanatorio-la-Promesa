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


<!-- Acciones del paciente -->
    @php($seguimientoOrigen = $ingresos->first(fn ($atencion) => $atencion->estado === 'Dado de alta' && $atencion->altaMedica))
<section class="evolucion-card expediente-acciones mb-4" id="acciones-expediente" aria-label="Atención del paciente">
        <div class="expediente-guia"><h3>Atención del paciente</h3><p>{{ $ingresoActivo ? 'Ya tiene una atención abierta. Continúe donde se quedó.' : 'Elija qué necesita hacer hoy.' }}</p></div>
        @if($paciente->estado !== 'Activo')
            <p class="mb-0">Este paciente está inactivo. Actívelo desde Pacientes para registrar una atención.</p>
        @elseif($ingresoActivo)
            @puede('Ingresos', 'Ver')
            <a href="{{ route('atencion.show', $ingresoActivo) }}" class="btn btn-primary expediente-accion"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i><span><strong>Continuar atención</strong><small>Abrir la atención actual</small></span></a>
            @endpuede
        @elseif(app(\App\Support\Permisos::class)->puede('Ingresos', 'Ingresar'))
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
        @puede('Documentos clínicos', 'Ingresar')
    <button type="button" class="btn btn-outline-primary expediente-accion" data-bs-toggle="modal" data-bs-target="#modalArchivoPaciente"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i><span><strong>Subir archivo médico</strong><small>Laboratorio, radiografía, informe u otro (PDF, JPG o PNG)</small></span></button>
    @endpuede
</section>

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

<section class="evolucion-card mb-4" id="archivos-paciente" aria-labelledby="titulo-archivos-paciente">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h4 class="mb-0" id="titulo-archivos-paciente"><i class="bi bi-paperclip" aria-hidden="true"></i> Archivos médicos</h4>
        <span class="text-muted small">Quien sube un archivo puede corregirlo durante {{ \App\Models\DocumentoClinico::HORAS_PARA_EDITAR }} horas.</span>
    </div>
    @if($archivosPaciente->isEmpty())
    <p class="text-muted mb-0">Todavía no hay archivos médicos. Use «Subir archivo médico», arriba.</p>
    @else
    <div class="list-group">
        @foreach($archivosPaciente as $archivoPaciente)
        @php($editado = ($ediciones ?? collect())->get('tbl_documento_clinico:'.$archivoPaciente->getKey()))
        <div class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="text-break">
                <i class="bi bi-file-earmark-{{ $archivoPaciente->tipo_archivo === 'pdf' ? 'pdf' : 'image' }} text-primary me-2" aria-hidden="true"></i><strong>{{ $archivoPaciente->titulo }}</strong>
                <span class="badge bg-light text-dark border ms-1">{{ $archivoPaciente->categoria }}</span>
                <span class="d-block small text-muted">Subido el {{ $archivoPaciente->fecha_documento?->format('d/m/Y') }} a las {{ $archivoPaciente->fecha_documento?->format('H:i') }} por {{ trim($archivoPaciente->personalResponsable?->nombres.' '.$archivoPaciente->personalResponsable?->apellidos) ?: 'Sin registro' }}</span>
                @if($editado)<span class="d-block small documento-editado"><i class="bi bi-pencil-square" aria-hidden="true"></i> Se editó el {{ $editado->format('d/m/Y') }} a las {{ $editado->format('H:i') }}</span>@endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                @puede('Documentos clínicos', 'Ver')<a href="{{ route('archivos.show', $archivoPaciente) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye" aria-hidden="true"></i> Ver</a>@endpuede
                @if($archivoPaciente->puedeEditar((int) session('id_usuario')) && app(\App\Support\Permisos::class)->puede('Documentos clínicos', 'Editar'))
                <a href="{{ route('archivos.editar', $archivoPaciente) }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
                @endif
            </div>
        </div>
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
                <span class="badge {{ $ingreso->estado === 'Activo' ? 'bg-success' : ($ingreso->estado === 'Dado de alta' ? 'bg-primary' : 'bg-secondary') }}">{{ $ingreso->estado === 'Cancelado' ? 'Anulada' : ($ingreso->estado === 'Dado de alta' && $ingreso->esConsultaExterna() ? 'Atención finalizada' : $ingreso->estado) }}</span>
            </header>
            <div class="p-3">
                @if($ingreso->atencionAnterior)<p class="text-primary"><i class="bi bi-arrow-return-right" aria-hidden="true"></i> El paciente regresó por la consulta del {{ $ingreso->atencionAnterior->fecha_ingreso->format('d/m/Y') }}@if($ingreso->atencionAnterior->altaMedica?->diagnostico_final) ({{ $ingreso->atencionAnterior->altaMedica->diagnostico_final }})@endif.</p>@endif
                <dl class="row g-3 mb-3">
                    <div class="col-sm-6"><dt class="text-muted fw-normal">{{ $ingreso->esConsultaExterna() ? 'Fecha de llegada' : 'Fecha de ingreso' }}</dt><dd class="fw-semibold mb-0">{{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}</dd></div>
                    <div class="col-sm-6"><dt class="text-muted fw-normal">{{ $ingreso->esConsultaExterna() ? 'Fecha de salida' : 'Fecha de egreso' }}</dt><dd class="fw-semibold mb-0">{{ $ingreso->altaMedica?->fecha_hora?->format('d/m/Y H:i') ?? ($ingreso->estado === 'Activo' ? 'En atención' : ($ingreso->estado === 'Cancelado' ? 'Atención anulada' : 'Sin egreso médico registrado')) }}</dd></div>
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
                @unless($ingreso->esConsultaExterna())
                <p class="small mb-3 {{ $ingreso->documento_firmado ? 'text-success' : 'text-muted' }}"><i class="bi {{ $ingreso->documento_firmado ? 'bi-check-circle-fill' : 'bi-info-circle' }} me-1"></i>{{ $ingreso->documento_firmado ? 'Documento de ingreso firmado' : 'Documento de ingreso pendiente de firma' }}</p>
                @endunless
                @include('cirugias.panel', ['soloLecturaCirugia'=>true])
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

<!-- Modal subir archivo médico del paciente -->
@php($recuperarArchivo = old('_formulario') === 'archivo-paciente')
<div class="modal fade" id="modalArchivoPaciente" tabindex="-1" aria-labelledby="modalArchivoPaciente-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('archivos.paciente.guardar', $paciente) }}" method="POST" enctype="multipart/form-data" novalidate data-validar-en-linea data-validar-al-enviar>
                @csrf
                <input type="hidden" name="_formulario" value="archivo-paciente">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalArchivoPaciente-titulo"><i class="bi bi-file-earmark-arrow-up text-primary" aria-hidden="true"></i> Subir archivo médico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($recuperarArchivo && $errors->any())<div class="alert alert-danger" role="alert"><strong>No se guardó el archivo.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="archivo-paciente-categoria">Tipo *</label>
                            <select id="archivo-paciente-categoria" name="categoria" class="form-select" required data-mensaje-requerido="Seleccione el tipo de archivo.">
                                <option value="">Seleccione</option>
                                @foreach(\App\Models\DocumentoClinico::CATEGORIAS as $categoria)
                                <option value="{{ $categoria }}" @selected($recuperarArchivo && old('categoria') === $categoria)>{{ $categoria }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label" for="archivo-paciente-titulo">Nombre del archivo *</label>
                            <input id="archivo-paciente-titulo" name="titulo" class="form-control" maxlength="150" required placeholder="Ej. Hematología completa, rayos X de tórax" value="{{ $recuperarArchivo ? old('titulo') : '' }}" data-mensaje-requerido="Escriba el nombre del archivo.">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="archivo-paciente-archivo">PDF o imagen *</label>
                            <input id="archivo-paciente-archivo" type="file" name="archivo" class="form-control" accept="application/pdf,image/jpeg,image/png,image/webp" required data-mensaje-requerido="Seleccione el PDF o la imagen.">
                            <small class="text-muted">PDF, JPG o PNG. Máximo 10 MB. La fecha y hora de subida se guardan solas.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="archivo-paciente-descripcion">Observaciones (opcional)</label>
                            <textarea id="archivo-paciente-descripcion" name="descripcion" class="form-control" rows="2" maxlength="2000" placeholder="Ej. Resultado traído por el familiar">{{ $recuperarArchivo ? old('descripcion') : '' }}</textarea>
                        </div>
                    </div>
                    <p class="text-muted small mt-3 mb-0">Después de guardarlo, usted podrá corregirlo durante {{ \App\Models\DocumentoClinico::HORAS_PARA_EDITAR }} horas. Los demás usuarios solo podrán verlo.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> Guardar en el expediente</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($recuperarArchivo)
<script>
    document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('modalArchivoPaciente')).show());
</script>
@elseif(($errors->any() || (session('error') && old('id_paciente'))) && !$ingresoActivo)

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