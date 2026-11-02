@extends('layouts.app')

@section('title', 'Hoja de enfermería - Sanatorio La Promesa')

@section('menu-atencion', 'activo')

@php
    $paciente = $ingreso->paciente;
    $habitacion = $ingreso->hospitalizacion?->habitacionActual?->habitacion;
    $ahora = \Illuminate\Support\Carbon::parse(now('America/Guatemala')->format('Y-m-d H:i:s'));
    $ordenes = $ordenesVigentes?->ordenes_medicas ?? [];
    $formularioNota = 'nota-enfermeria';
    $recuperarNota = old('_formulario') === $formularioNota;
    $actividadesMarcadas = $recuperarNota ? (array) old('actividades', []) : [];
    $otraActividad = $recuperarNota ? (string) old('actividad_otra', '') : '';
    $hace = fn ($fecha) => abs($ahora->diffInSeconds($fecha)) < 60 ? 'un momento' : $fecha->locale('es')->diffForHumans($ahora, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'minimumUnit' => 'minute']);
@endphp

@section('content')

<div class="inicio-encabezado">
    <div class="inicio-icono"><i class="bi bi-clipboard2-check" aria-hidden="true"></i></div>
    <div>
        <h1>Hoja de enfermería</h1>
        <p>Órdenes médicas vigentes, notas de enfermería y signos vitales</p>
    </div>
</div>

@puede('Documentos clínicos', 'Ingresar')<div class="mb-3"><a class="btn btn-outline-primary" href="{{ route('archivos.formulario',[$ingreso,'tipo'=>'Nota de enfermería']) }}"><i class="bi bi-camera" aria-hidden="true"></i> Subir hoja en papel (imagen o PDF)</a></div>@endpuede
@if(session('exito'))<div class="alert alert-success">{{ session('exito') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('atencion.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a pacientes internados</a>
</div>

<div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2" role="status" data-aviso-borrador-pagina="{{ $formularioNota }}" hidden>
    <span><i class="bi bi-clock-history" aria-hidden="true"></i> Hay una nota de enfermería que empezó a escribir y no se guardó.</span>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNotaEnfermeria">Continuar la nota</button>
</div>

{{-- Paciente --}}
<section class="evolucion-card mb-3" aria-label="Datos del paciente">
    <header class="ficha-identidad mb-3">
        <div class="ficha-avatar"><i class="bi bi-person" aria-hidden="true"></i></div>
        <div class="ficha-nombre"><h2>{{ $paciente->estado_identificacion === 'Identificado' ? trim($paciente->nombres.' '.$paciente->apellidos) : 'No identificado' }}</h2><p><i class="bi bi-folder2-open" aria-hidden="true"></i> {{ $paciente->numero_expediente }} @if($paciente->codigo_temporal) · {{ $paciente->codigo_temporal }} @endif</p></div>
        <div class="enfermeria-habitacion"><i class="bi bi-hospital" aria-hidden="true"></i><div><span>Habitación</span><strong>{{ $habitacion?->numero_habitacion ?? 'Sin asignar' }}</strong></div></div>
    </header>
    <dl class="enfermeria-datos">
        <div><dt><i class="bi bi-calendar3" aria-hidden="true"></i> Edad</dt><dd>{{ $paciente->fecha_nacimiento ? $paciente->fecha_nacimiento->age.' años' : 'Sin registro' }}</dd></div>
        <div><dt><i class="bi bi-person" aria-hidden="true"></i> Sexo</dt><dd>{{ $paciente->sexo ?: 'Sin registro' }}</dd></div>
        <div><dt><i class="bi bi-droplet" aria-hidden="true"></i> Tipo de sangre</dt><dd>{{ $paciente->tipo_sangre ?: 'Sin registro' }}</dd></div>
        <div><dt><i class="bi bi-clock-history" aria-hidden="true"></i> Internado desde</dt><dd>{{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}<small class="d-block text-muted">Hace {{ $hace($ingreso->fecha_ingreso) }}</small></dd></div>
        <div class="enfermeria-diagnostico"><dt><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i> Diagnóstico actual</dt><dd>{{ $diagnosticoActual?->diagnostico ?: ($ingreso->motivo_ingreso ?: 'Sin registro') }}</dd></div>
    </dl>
    @if(filled($paciente->alergias))
    <div class="alert alert-danger mt-3 mb-0 d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div><strong>Alergias:</strong> <span class="text-break">{{ $paciente->alergias }}</span></div>
    </div>
    @endif
</section>

@include('cirugias.panel')
{{-- Órdenes médicas vigentes --}}
<section class="evolucion-card mb-3" aria-labelledby="titulo-ordenes" id="enfermeria-ordenes">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1" id="titulo-ordenes"><i class="bi bi-journal-medical text-primary" aria-hidden="true"></i> Órdenes médicas vigentes</h2>
            @if($ordenesVigentes)
            <p class="text-muted mb-0">Indicadas por {{ $ordenesVigentes->personal?->nombres }} {{ $ordenesVigentes->personal?->apellidos }} el {{ $ordenesVigentes->fecha_hora->format('d/m/Y H:i') }}</p>
            @endif
        </div>
        <span class="badge text-bg-light border"><i class="bi bi-eye" aria-hidden="true"></i> Solo lectura</span>
    </div>

    @if(! $ordenesVigentes)
    <p class="text-muted mb-0">El médico todavía no ha registrado órdenes para este paciente.</p>
    @else
    <dl class="enfermeria-ordenes-grid mb-3">
        <div class="enfermeria-bloque"><dt><i class="bi bi-cup-hot" aria-hidden="true"></i> Dieta</dt><dd class="mb-0 fs-5">{{ $ordenes['dieta'] ?? 'Sin indicación' }}</dd></div>
        <div class="enfermeria-bloque"><dt><i class="bi bi-droplet-half" aria-hidden="true"></i> Solución</dt><dd class="mb-0">
            @forelse($ordenes['soluciones'] ?? [] as $solucion)
            <span class="fs-5">Suero {{ $solucion['tipo'] }} · {{ $solucion['volumen'] }} ml</span>
            <span class="d-block">Goteo: <strong>{{ $solucion['velocidad'] }}{{ isset($solucion['unidad']) ? ' '.$solucion['unidad'] : '' }}</strong></span>
            @empty
            <span class="text-muted">Sin solución indicada</span>
            @endforelse
        </dd></div>
    </dl>

    <h3 class="h5 mt-4"><i class="bi bi-capsule text-primary" aria-hidden="true"></i> Medicamentos</h3>
    @if(empty($ordenes['medicamentos']))
    <p class="text-muted">Sin medicamentos indicados.</p>
    @else
    <div class="table-responsive mb-3">
        <table class="table align-middle">
            <thead><tr><th>Medicamento</th><th>Dosis</th><th>Vía</th><th>Frecuencia</th></tr></thead>
            <tbody>
                @foreach($ordenes['medicamentos'] as $medicamento)
                <tr>
                    <td class="fw-semibold text-break">{{ $medicamento['nombre'] }}</td>
                    <td>{{ is_numeric($medicamento['dosis']) ? $medicamento['dosis'].' mg' : $medicamento['dosis'] }}</td>
                    <td>{{ $medicamento['via'] }}</td>
                    <td>{{ is_numeric($medicamento['frecuencia']) ? 'Cada '.$medicamento['frecuencia'].' horas' : $medicamento['frecuencia'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <dl class="enfermeria-ordenes-grid mb-0">
        <div class="enfermeria-bloque"><dt><i class="bi bi-list-check" aria-hidden="true"></i> Indicaciones para enfermería</dt><dd class="mb-0 text-break" style="white-space: pre-wrap">{{ $ordenes['indicaciones'] ?? '' ?: 'Sin indicaciones adicionales' }}</dd></div>
        <div class="enfermeria-bloque"><dt><i class="bi bi-chat-square-text" aria-hidden="true"></i> Plan educacional</dt><dd class="mb-0 text-break" style="white-space: pre-wrap">{{ $ordenes['plan_educacional'] ?? '' ?: 'Sin plan educacional' }}</dd></div>
    </dl>
    @endif

    @if($ordenesAnteriores->isNotEmpty())
    <details class="mt-3">
        <summary>Órdenes anteriores ({{ $ordenesAnteriores->count() }})</summary>
        @foreach($ordenesAnteriores as $anterior)
        <div class="border rounded p-2 mt-2">
            <strong>{{ $anterior->fecha_hora->format('d/m/Y H:i') }}</strong> · {{ $anterior->personal?->nombres }} {{ $anterior->personal?->apellidos }}
            @include('atencion.partials.ordenes-resumen', ['resumenOrdenes' => $anterior->ordenes_medicas])
        </div>
        @endforeach
    </details>
    @endif
</section>

{{-- Notas de enfermería --}}
<section class="evolucion-card mb-3" aria-labelledby="titulo-notas">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1" id="titulo-notas"><i class="bi bi-journal-text text-primary" aria-hidden="true"></i> Notas de enfermería</h2>
            <p class="text-muted mb-0">
                @if($ultimoControl)
                Último control de signos: {{ $ultimoControl->fecha_hora->format('d/m/Y H:i') }} · hace {{ $hace($ultimoControl->fecha_hora) }}
                @else
                Todavía no hay controles de signos vitales.
                @endif
            </p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNotaEnfermeria"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva nota</button>
    </div>

    <div class="d-flex flex-column gap-3">
        @forelse($ingreso->notasEnfermeria as $nota)
        <article class="border rounded-3 p-3 d-flex flex-wrap align-items-center justify-content-between gap-3" aria-label="Nota de enfermería del {{ $nota->fecha_hora->format('d/m/Y H:i') }}">
            <div>
                <strong><i class="bi bi-journal-text text-primary me-2" aria-hidden="true"></i>{{ $nota->fecha_hora->format('d/m/Y H:i') }}</strong>
                <span class="badge text-bg-light border">Turno {{ mb_strtolower($nota->turno()) }}</span>
                <span class="d-block text-muted mt-1">{{ $nota->personal?->nombres }} {{ $nota->personal?->apellidos }}</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                @if($nota->estado_paciente)<span class="badge {{ $nota->claseEstado() }}">{{ $nota->estado_paciente }}</span>@endif
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ver-nota-{{ $nota->getKey() }}"><i class="bi bi-eye me-1" aria-hidden="true"></i> Ver nota</button>
            </div>
        </article>
        <div class="modal fade" id="ver-nota-{{ $nota->getKey() }}" tabindex="-1" aria-labelledby="titulo-ver-nota-{{ $nota->getKey() }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
                <div class="modal-header"><h2 class="modal-title h5" id="titulo-ver-nota-{{ $nota->getKey() }}">Nota de enfermería</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body p-4" style="font-size:1rem; line-height:1.65">
                    @include('expedientes.partials.documento', ['tipo'=>'nota', 'ingreso'=>$ingreso, 'paciente'=>$ingreso->paciente, 'nota'=>$nota])
                </div>
                <div class="modal-footer"><span class="text-muted me-auto">Solo lectura</span><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
            </div></div>
        </div>
        @empty
        <p class="text-muted mb-0">Todavía no hay notas de enfermería. Use <strong>Nueva nota</strong> para registrar signos vitales, actividades y cómo está el paciente.</p>
        @endforelse
    </div>

    @if($ingreso->signosVitales->isNotEmpty())
    <details class="mt-3">
        <summary>Historial de signos vitales ({{ $ingreso->signosVitales->count() }})</summary>
        <div class="table-responsive mt-2">
            <table class="table align-middle">
                <thead><tr><th>Fecha y hora</th><th>PA (mmHg)</th><th>FC (lpm)</th><th>FR (rpm)</th><th>Temp. (°C)</th><th>SpO₂ (%)</th><th>Observaciones</th><th>Registró</th></tr></thead>
                <tbody>
                    @foreach($ingreso->signosVitales as $signo)
                    <tr>
                        <td>{{ $signo->fecha_hora->format('d/m/Y H:i') }}</td>
                        <td>{{ $signo->presion_sistolica ? $signo->presion_sistolica.'/'.$signo->presion_diastolica : '—' }}</td>
                        <td>{{ $signo->frecuencia_cardiaca ?? '—' }}</td>
                        <td>{{ $signo->frecuencia_respiratoria ?? '—' }}</td>
                        <td>{{ $signo->temperatura ?? '—' }}</td>
                        <td>{{ $signo->saturacion_oxigeno ?? '—' }}</td>
                        <td class="text-break">{{ $signo->observaciones ?: '—' }}</td>
                        <td>{{ $signo->personal?->nombres }} {{ $signo->personal?->apellidos }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
    @endif
</section>

{{-- Nueva nota de enfermería --}}
<div class="modal fade" id="modalNotaEnfermeria" tabindex="-1" aria-labelledby="modalNotaEnfermeria-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('notas-enfermeria.guardar', $ingreso) }}" novalidate data-validar-en-linea data-validar-al-enviar data-borrador data-al-menos-uno="presion_sistolica,presion_diastolica,frecuencia_cardiaca,frecuencia_respiratoria,temperatura,saturacion_oxigeno,actividades[],actividad_otra,observaciones">
                @csrf
                <input type="hidden" name="_formulario" value="{{ $formularioNota }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNotaEnfermeria-titulo">Nueva nota de enfermería · Habitación {{ $habitacion?->numero_habitacion }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($recuperarNota && $errors->any())
                    <div class="alert alert-danger" role="alert"><strong>No se guardó la nota.</strong> Corrija lo siguiente:<ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2" role="status" data-aviso-borrador hidden>
                        <span><i class="bi bi-clock-history" aria-hidden="true"></i> Se recuperó lo que estaba escribiendo y todavía no se ha guardado.</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-descartar-borrador>Descartar y empezar de nuevo</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="nota-fecha">Fecha y hora</label>
                            <input id="nota-fecha" type="datetime-local" name="fecha_hora" class="form-control @if($recuperarNota) @error('fecha_hora') is-invalid @enderror @endif" value="{{ $recuperarNota ? old('fecha_hora') : now('America/Guatemala')->format('Y-m-d\TH:i') }}" min="{{ $ingreso->fecha_ingreso->format('Y-m-d\TH:i') }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" data-max-ahora data-turno-nota data-mensaje-requerido="Indique la fecha y hora de la nota." data-mensaje-minimo="No puede ser anterior al ingreso." data-mensaje-maximo="La fecha y hora no pueden ser futuras." required>
                            @if($recuperarNota) @error('fecha_hora')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            <small class="text-muted" data-turno-texto aria-live="polite"></small>
                        </div>
                        <fieldset class="col-md-6">
                            <legend class="form-label fs-6 mb-2">¿Cómo está el paciente?</legend>
                            <div class="d-flex gap-2">
                                @foreach(\App\Models\NotaEnfermeria::ESTADOS as $estadoNota)
                                <input type="radio" class="btn-check" name="estado_paciente" id="nota-estado-{{ $loop->index }}" value="{{ $estadoNota }}" @checked($recuperarNota && old('estado_paciente') === $estadoNota) data-mensaje-requerido="Indique cómo está el paciente." required>
                                <label class="btn {{ ['Mejoró' => 'btn-outline-success', 'Sin cambios' => 'btn-outline-secondary', 'Empeoró' => 'btn-outline-danger'][$estadoNota] }} flex-fill" for="nota-estado-{{ $loop->index }}">{{ $estadoNota }}</label>
                                @endforeach
                            </div>
                            @if($recuperarNota) @error('estado_paciente')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                        </fieldset>
                        <div class="col-12">
                            <fieldset class="border rounded p-3">
                                <legend class="float-none w-auto px-2 fs-6 mb-1">Signos vitales <span class="text-muted fw-normal">(opcional)</span></legend>
                                @include('atencion.partials.signos-seleccionables', ['prefijo' => 'nota', 'valor' => fn ($campo) => $recuperarNota ? old($campo) : '', 'mostrarErrores' => $recuperarNota])
                            </fieldset>
                        </div>
                        <fieldset class="col-12">
                            <legend class="form-label fs-6 mb-2">Actividades realizadas</legend>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach(\App\Models\NotaEnfermeria::ACTIVIDADES as $actividad)
                                <input type="checkbox" class="btn-check" name="actividades[]" id="nota-actividad-{{ $loop->index }}" value="{{ $actividad }}" @checked(in_array($actividad, $actividadesMarcadas, true))>
                                <label class="btn btn-outline-primary" for="nota-actividad-{{ $loop->index }}">{{ $actividad }}</label>
                                @endforeach
                                <input type="checkbox" class="btn-check" id="nota-actividad-otra" data-mostrar-otra-actividad @checked($otraActividad !== '')>
                                <label class="btn btn-outline-secondary" for="nota-actividad-otra"><i class="bi bi-plus-lg" aria-hidden="true"></i> Otra</label>
                            </div>
                            <div class="mt-2" data-otra-actividad @if($otraActividad === '') hidden @endif>
                                <label class="form-label" for="nota-actividad-otra-texto">Otra actividad</label>
                                <input id="nota-actividad-otra-texto" type="text" name="actividad_otra" class="form-control @if($recuperarNota) @error('actividad_otra') is-invalid @enderror @endif" maxlength="100" placeholder="Ej. Control de glucosa" value="{{ $otraActividad }}" data-mensaje-requerido="Escriba la otra actividad o quite la opción Otra." required>
                            </div>
                        </fieldset>
                        <div class="col-12">
                            <label class="form-label" for="nota-observaciones">Nota de enfermería</label>
                            <textarea id="nota-observaciones" name="observaciones" class="form-control @if($recuperarNota) @error('observaciones') is-invalid @enderror @endif" rows="3" maxlength="2000" placeholder="Ej. Paciente refiere menos dolor, tolera dieta blanda. Se cambió suero salino 500 ml.">{{ $recuperarNota ? old('observaciones') : '' }}</textarea>
                            @if($recuperarNota) @error('observaciones')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            <div class="alert alert-warning mt-2 mb-0" role="alert" data-aviso-al-menos-uno @unless($recuperarNota && $errors->has('nota')) hidden @endunless>
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> La nota está vacía: registre signos vitales, marque una actividad o escriba la nota.
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="aviso_medico" value="1" id="nota-aviso-medico" data-aviso-medico @checked($recuperarNota && old('aviso_medico'))>
                                <label class="form-check-label" for="nota-aviso-medico">Se avisó al médico</label>
                            </div>
                            {{-- Al marcar el aviso hay que indicar a qué médico se avisó. --}}
                            <div class="mt-2" data-medico-avisado @unless($recuperarNota && old('aviso_medico')) hidden @endunless>
                                <label class="form-label" for="nota-medico-avisado">¿A qué médico se avisó? *</label>
                                <select id="nota-medico-avisado" name="id_personal_avisado" class="form-select @if($recuperarNota) @error('id_personal_avisado') is-invalid @enderror @endif" data-mensaje-requerido="Seleccione a qué médico se avisó." @if($recuperarNota && old('aviso_medico')) required @else disabled @endif>
                                    <option value="">Seleccione el médico</option>
                                    @foreach($medicos as $medico)
                                    <option value="{{ $medico->getKey() }}" @selected($recuperarNota && (string) old('id_personal_avisado') === (string) $medico->getKey())>{{ $medico->nombres }} {{ $medico->apellidos }}</option>
                                    @endforeach
                                </select>
                                @if($recuperarNota) @error('id_personal_avisado')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                @if($medicos->isEmpty())<small class="text-danger d-block mt-1">No hay médicos activos registrados en Personal.</small>@endif
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Una nota guardada no se modifica. Si hay que corregir algo, registre una nota nueva.</p>
                    <div class="alert alert-danger mt-3 mb-0" role="alert" hidden data-resumen-errores><strong>No se puede guardar todavía.</strong> Revise los avisos marcados en rojo o amarillo.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    @puede('Enfermería', 'Ingresar')<button type="submit" class="btn btn-primary">Guardar nota</button>@endpuede
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
@include('atencion.partials.borradores-scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modalNotaEnfermeria');
    if (!modal) return;
    const fecha = modal.querySelector('[data-turno-nota]');
    const turno = modal.querySelector('[data-turno-texto]');
    const mostrarTurno = () => {
        const hora = Number((fecha.value.split('T')[1] || '').slice(0, 2));
        turno.textContent = fecha.value ? 'Turno ' + (hora >= 7 && hora < 13 ? 'mañana' : (hora >= 13 && hora < 19 ? 'tarde' : 'noche')) : '';
    };
    fecha.addEventListener('input', mostrarTurno);
    mostrarTurno();

    const marcarOtra = modal.querySelector('[data-mostrar-otra-actividad]');
    const bloqueOtra = modal.querySelector('[data-otra-actividad]');
    // Un borrador recuperado puede traer la otra actividad escrita: se muestra.
    if (bloqueOtra.querySelector('input').value.trim() !== '') {
        marcarOtra.checked = true;
        bloqueOtra.hidden = false;
    }
    marcarOtra.addEventListener('change', () => {
        bloqueOtra.hidden = !marcarOtra.checked;
        const campo = bloqueOtra.querySelector('input');
        if (marcarOtra.checked) {
            campo.focus();
        } else {
            campo.value = '';
            campo.classList.remove('is-invalid');
            campo.nextElementSibling?.classList.remove('d-block');
        }
    });
@if($recuperarNota && $errors->any())
    bootstrap.Modal.getOrCreateInstance(modal).show();
@endif
});
</script>
@endsection
