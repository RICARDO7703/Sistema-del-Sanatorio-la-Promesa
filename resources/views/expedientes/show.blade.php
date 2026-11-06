@extends('layouts.app')

@section('title', 'Expediente del paciente - Sanatorio La Promesa')

@section(request('desde') === 'pacientes' ? 'menu-pacientes' : 'menu-expedientes', 'activo')

@section('content')
@php($operacionEnCurso = $paciente->cirugiaEnCurso())

@if($errors->has('desde') || $errors->has('hasta') || $errors->has('archivo'))
<div class="alert alert-danger" role="alert"><strong>No se pudo generar la descarga.</strong><ul class="mb-0">@foreach(['desde','hasta','archivo'] as $campo) @foreach($errors->get($campo) as $mensaje)<li>{{ $mensaje }}</li>@endforeach @endforeach</ul></div>
@endif
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
    @if(app(\App\Support\Permisos::class)->puede('Cirugías','Ver') && app(\App\Support\Permisos::class)->puede('Documentos clínicos','Ver'))
    <a class="btn btn-primary" href="{{ route('reportes.expediente', [$paciente,'descargar'=>1]) }}"><i class="bi bi-file-earmark-pdf"></i> Descargar expediente completo</a>
    <a class="btn btn-outline-primary" href="{{ route('reportes.expediente.zip', $paciente) }}"><i class="bi bi-file-earmark-zip"></i> Descargar archivos por separado (.zip)</a>
    @endif
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


@if($operacionEnCurso && app(\App\Support\Permisos::class)->puede('Cirugías', 'Ver'))
@foreach(collect([$operacionEnCurso]) as $operacionActual)
<div class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-3" role="status">
    <div><strong><i class="bi bi-hospital" aria-hidden="true"></i> En cirugía · {{ $operacionActual->tipo_operacion }}</strong><span class="d-block">Inicio real: {{ $operacionActual->fecha_inicio_real?->format('d/m/Y H:i') ?? 'Pendiente de registrar' }} · Sin finalizar</span></div>
    <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#cirugia-detalle-{{ $operacionActual->getKey() }}"><i class="bi bi-eye" aria-hidden="true"></i> Ver cirugía</button>
</div>
@endforeach
@endif
<!-- Acciones del paciente -->
    @php($seguimientoOrigen = $ingresos->first(fn ($atencion) => $atencion->estado === 'Dado de alta' && $atencion->altaMedica))
<section class="evolucion-card expediente-acciones mb-4" id="acciones-expediente" aria-label="Atención del paciente">
        <div class="expediente-guia"><h3>Atención del paciente</h3><p>{{ $ingresoActivo ? 'Este paciente tiene una atención abierta.' : 'Elija qué necesita hacer hoy.' }}</p></div>
        @if($operacionEnCurso)
            <p class="mb-0 text-danger fw-semibold"><i class="bi bi-hospital me-2" aria-hidden="true"></i>Paciente en cirugía. Podrá registrar un seguimiento o una hospitalización cuando finalice.</p>
        @elseif($paciente->estado !== 'Activo')
            <p class="mb-0">Este paciente está inactivo. Actívelo desde Pacientes para registrar una atención.</p>
        @elseif($ingresoActivo)
            @if(app(\App\Support\Permisos::class)->puede('Ingresos','Ver') && app(\App\Support\Permisos::class)->puede('Evolución clínica','Ingresar'))
            <a href="{{ route('atencion.show', $ingresoActivo) }}" class="btn btn-primary expediente-accion"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i><span><strong>Continuar atención</strong><small>Abrir la atención actual</small></span></a>
            @endif
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
            ['rulers', 'Talla', $paciente->tallaTexto()],
            ['speedometer2', 'Peso', $paciente->pesoTexto()],
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
        <div>
            <h4 class="mb-0" id="titulo-archivos-paciente"><i class="bi bi-paperclip" aria-hidden="true"></i> Archivos médicos</h4>
            <span class="text-muted small">Laboratorios, radiografías e informes. Pulse «Ver» para consultar el archivo aquí.</span>
        </div>
        @puede('Documentos clínicos', 'Ingresar')
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalArchivoPaciente"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i> Subir archivo médico</button>
        @endpuede
    </div>
    @if($archivosPaciente->isEmpty())
    <p class="text-muted mb-0">Todavía no hay archivos médicos. Use el botón «Subir archivo médico».</p>
    @else
    <div class="list-group">
        @foreach($archivosPaciente as $archivoPaciente)
        @php($editado = ($ediciones ?? collect())->get('tbl_documento_clinico:'.$archivoPaciente->getKey()))
        <div class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="text-break">
                <i class="bi bi-file-earmark-{{ $archivoPaciente->tipo_archivo === 'pdf' ? 'pdf' : 'image' }} text-primary me-2" aria-hidden="true"></i><strong>{{ $archivoPaciente->titulo }}</strong>
                <span class="badge bg-light text-dark border ms-1">{{ $archivoPaciente->etiquetaCategoria() }}</span>
                <span class="d-block small text-muted">Subido el {{ $archivoPaciente->fecha_documento?->format('d/m/Y') }} a las {{ $archivoPaciente->fecha_documento?->format('H:i') }} por {{ trim($archivoPaciente->personalResponsable?->nombres.' '.$archivoPaciente->personalResponsable?->apellidos) ?: 'Sin registro' }}</span>
                @if($editado)<span class="d-block small documento-editado"><i class="bi bi-pencil-square" aria-hidden="true"></i> Se editó el {{ $editado->format('d/m/Y') }} a las {{ $editado->format('H:i') }}</span>@endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                @puede('Documentos clínicos', 'Ver')<button type="button" class="btn btn-outline-primary btn-sm" data-archivo-url="{{ route('archivos.contenido', $archivoPaciente) }}" data-abrir-documento data-tipo-archivo="{{ $archivoPaciente->tipo_archivo }}" data-titulo-archivo="{{ $archivoPaciente->titulo }}" data-nombre-archivo="{{ $archivoPaciente->nombre_archivo }}"><i class="bi bi-eye" aria-hidden="true"></i> Ver</button><a class="btn btn-outline-primary btn-sm" href="{{ route('archivos.contenido',$archivoPaciente) }}" download="{{ $archivoPaciente->nombre_archivo }}"><i class="bi bi-download" aria-hidden="true"></i> Descargar</a>@endpuede
                @if($archivoPaciente->puedeEditar((int) session('id_usuario')) && app(\App\Support\Permisos::class)->puede('Documentos clínicos', 'Editar'))
                <form method="POST" action="{{ route('archivos.quitar',$archivoPaciente) }}" data-confirmar="¿Quitar este archivo del expediente?">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Quitar archivo</button></form>
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
    <p class="text-muted mb-4">De la atención más reciente a la más antigua. Toque «Ver documentos» para ver las hojas de cada atención, ordenadas por día y con sus filtros.</p>
    <div class="d-flex flex-column gap-4">
        @forelse($ingresos as $ingreso)
        @php($consulta = $ingreso->esConsultaExterna())
        @php($estadoTexto = $ingreso->estado === 'Cancelado' ? 'Anulada' : ($ingreso->estado === 'Dado de alta' && $consulta ? 'Atención finalizada' : $ingreso->estado))
        @php($estadoClase = $ingreso->estado === 'Activo' ? 'bg-success' : ($ingreso->estado === 'Dado de alta' ? 'bg-primary' : 'bg-secondary'))
        @php($egreso = $ingreso->altaMedica?->fecha_hora?->format('d/m/Y H:i') ?? ($ingreso->estado === 'Activo' ? 'En atención' : ($ingreso->estado === 'Cancelado' ? 'Atención anulada' : 'Sin egreso médico registrado')))
        @php($diagnosticoAtencion = $ingreso->altaMedica?->diagnostico_final ?: ($ingreso->evoluciones->last()?->diagnostico ?: 'Pendiente de registrar'))
        @php($documentosAtencion = $documentosPorAtencion[$ingreso->getKey()] ?? collect())
        @php($totalDocumentos = $documentosAtencion->flatten(1)->count())
        {{-- Los datos de la atención siempre se ven; sus documentos empiezan ocultos y se despliegan con la flecha. --}}
        <article class="historial-atencion border rounded-3">
            <header class="historial-cabecera p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0">
                        @if($consulta)
                        <i class="bi bi-person-walking text-consulta me-2" aria-hidden="true"></i>{{ $ingreso->estado === 'Activo' ? 'Atención actual' : 'Atención' }} del {{ $ingreso->fecha_ingreso->format('d/m/Y') }}
                        @else
                        <i class="bi bi-hospital text-danger me-2" aria-hidden="true"></i>{{ $ingreso->estado === 'Activo' ? 'Ingreso actual' : 'Ingreso' }} #{{ $ingreso->getKey() }}
                        @endif
                    </h5>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge {{ $consulta ? 'bg-consulta' : 'bg-danger' }}">{{ $ingreso->nombreTipoAtencion() }}</span>
                        @if($ingreso->id_ingreso_anterior)<span class="badge bg-info text-dark">Seguimiento</span>@endif
                        <span class="badge {{ $estadoClase }}">{{ $estadoTexto }}</span>
                    </div>
                </div>
                @if($ingreso->atencionAnterior)<p class="text-primary mt-2 mb-0"><i class="bi bi-arrow-return-right" aria-hidden="true"></i> El paciente regresó por la consulta del {{ $ingreso->atencionAnterior->fecha_ingreso->format('d/m/Y') }}@if($ingreso->atencionAnterior->altaMedica?->diagnostico_final) ({{ $ingreso->atencionAnterior->altaMedica->diagnostico_final }})@endif.</p>@endif
                <div class="historial-datos mt-3">
                    <dl class="mb-0"><dt>{{ $consulta ? 'Fecha de llegada' : 'Fecha de ingreso' }}</dt><dd>{{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}</dd></dl>
                    <dl class="mb-0"><dt>{{ $consulta ? 'Fecha de salida' : 'Fecha de egreso' }}</dt><dd>{{ $egreso }}</dd></dl>
                    @unless($consulta)
                    @include('expedientes.partials.habitaciones', ['ingreso' => $ingreso, 'claseHabitaciones' => ''])
                    @endunless
                    <dl class="mb-0 historial-dato-ancho"><dt>{{ $consulta ? 'Motivo de consulta' : 'Motivo de ingreso' }}</dt><dd class="text-break">{{ $ingreso->motivo_ingreso ?: 'Sin registro' }}</dd></dl>
                    <dl class="mb-0 historial-dato-ancho"><dt>Diagnóstico</dt><dd class="text-break">{{ $diagnosticoAtencion }}</dd></dl>
                </div>
                @unless($consulta)
                <p class="small mt-3 mb-0 {{ $ingreso->documento_firmado ? 'text-success' : 'text-muted' }}"><i class="bi {{ $ingreso->documento_firmado ? 'bi-check-circle-fill' : 'bi-info-circle' }} me-1"></i>{{ $ingreso->documento_firmado ? 'Documento de ingreso firmado' : 'Documento de ingreso pendiente de firma' }}@if($documentosFirmados->has($ingreso->id_ingreso)) · <a href="{{ route('archivos.contenido', $documentosFirmados->get($ingreso->id_ingreso)) }}" data-abrir-documento data-tipo-archivo="{{ $documentosFirmados->get($ingreso->id_ingreso)->tipo_archivo }}" data-titulo-archivo="{{ $documentosFirmados->get($ingreso->id_ingreso)->titulo }}" data-nombre-archivo="{{ $documentosFirmados->get($ingreso->id_ingreso)->nombre_archivo }}">Ver documento</a>@endif</p>
                @endunless
            </header>
            <button type="button" class="historial-flecha collapsed" data-bs-toggle="collapse" data-bs-target="#documentos-atencion-{{ $ingreso->getKey() }}" aria-expanded="false" aria-controls="documentos-atencion-{{ $ingreso->getKey() }}">
                <span><i class="bi bi-folder2-open me-2" aria-hidden="true"></i>Documentos ({{ $totalDocumentos }})</span>
                <span class="historial-flecha-texto"><span class="historial-abrir">Ver<span class="d-none d-sm-inline"> documentos</span></span><span class="historial-cerrar">Ocultar<span class="d-none d-sm-inline"> documentos</span></span><i class="bi bi-chevron-down" aria-hidden="true"></i></span>
            </button>
            <div class="collapse" id="documentos-atencion-{{ $ingreso->getKey() }}">
                <div class="historial-dias">
                    @include('expedientes.partials.documentos-por-dia', ['documentosPorDia' => $documentosAtencion, 'mostrarAtencion' => false, 'prefijoFiltro' => $ingreso->getKey()])
                </div>
            </div>
            {{-- Las cirugías aparecen en los documentos, en su fecha; aquí solo se preparan sus ventanas de detalle. --}}
            @include('cirugias.panel', ['soloLecturaCirugia'=>true, 'soloVentanasCirugia'=>true, 'ocultarAvisoCirugia'=>true])
        </article>
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
            <form action="{{ route('ingresos.guardar') }}" method="POST" enctype="multipart/form-data" novalidate data-validar-en-linea data-validar-al-enviar>
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

@php($formularioFirma = (string) old('_formulario'))
@if(str_starts_with($formularioFirma, 'documento-firmado-'))
<script>
    // Si el documento firmado no se pudo subir, se vuelve a abrir la hoja de ingreso con el aviso.
    document.addEventListener('DOMContentLoaded', () => {
        const hoja = document.getElementById(@json('documento-ingreso-'.substr($formularioFirma, strlen('documento-firmado-'))));
        if (hoja) bootstrap.Modal.getOrCreateInstance(hoja).show();
    });
</script>
@endif
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