{{--
    Detalles de una cirugía en ventana flotante: datos, personal, controles de recuperación (tomados en las notas
    de enfermería), cambio de estado y corrección durante las primeras 12 horas.
    Variables: $cirugia (con ingreso, equipo y controles), $ingreso, $permisosCirugia, $soloLecturaCirugia, $personalQuirurgico.
--}}
@php
    $tieneEditorRecuperacion = false;
    $idModal = 'cirugia-detalle-'.$cirugia->getKey();
    $formularioCirugia = 'cirugia-'.$cirugia->getKey();
    $recuperar = old('_formulario') === $formularioCirugia;
    $recuperarEdicion = $recuperar && old('_accion_cirugia') === 'editar';
    $abierto = $ingreso->estado === 'Activo';
    $gestionQuirurgica = $cirugia->atencionQuirurgicaDisponible();
    $soloLectura = $soloLecturaCirugia ?? false;
    $editable = $cirugia->estado !== 'En cirugía' && ! ($consultaEnfermeria ?? false) && $permisosCirugia['editar'] && $cirugia->puedeCorregirDatos();
    $limiteEdicion = $editable && $cirugia->estado === 'Realizada' ? \App\Support\EdicionClinica::limiteLocal($cirugia) : null;
    $puedeFinalizar = $gestionQuirurgica && $cirugia->estado === 'En cirugía' && $cirugia->permiteAccion('terminar');
    $programacionGestionable = $cirugia->programacionGestionable();
    $puedeCancelar = ($abierto || $programacionGestionable) && $cirugia->permiteAccion('cancelar');
    $puedeIniciar = $gestionQuirurgica && $cirugia->permiteAccion('iniciar');
    $puedeCambiarEstado = ! ($consultaEnfermeria ?? false) && $gestionQuirurgica && ($cirugia->estado === 'En cirugía' ? $puedeFinalizar : (($puedeIniciar || $puedeCancelar) && in_array($cirugia->estado, ['Indicada', 'Programada', 'Aprobada', 'Evaluación preoperatoria'], true)));
    $puedeReprogramar = ! ($consultaEnfermeria ?? false) && ($abierto || $programacionGestionable) && $cirugia->permiteAccion('reprogramar') && in_array($cirugia->estado, ['Indicada', 'Programada', 'Aprobada', 'Evaluación preoperatoria'], true);
    $puedeAnotarControl = ! $soloLectura && $abierto && $permisosCirugia['nota'] && $cirugia->estado === 'Realizada' && $ingreso->hospitalizacion?->estado === 'Activa'
        && $cirugia->getKey() === ($idCirugiaControl ?? null);
    $claseEstado = match ($cirugia->estado) { 'En cirugía' => 'doc-pastilla-peligro', 'Realizada' => 'doc-pastilla-exito', default => 'doc-pastilla-neutra' };
    $nombrePersona = fn ($persona) => trim(($persona?->nombres ?? '').' '.($persona?->apellidos ?? '')) ?: 'Sin registro';
    $equipoSeleccionado = array_map('intval', (array) ($recuperarEdicion ? old('personal', []) : $cirugia->equipo->modelKeys()));
@endphp
<div class="modal fade" id="{{ $idModal }}" tabindex="-1" aria-labelledby="{{ $idModal }}-titulo" aria-hidden="true" data-editor-cirugia>
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title h5" id="{{ $idModal }}-titulo"><i class="bi bi-hospital text-primary me-1" aria-hidden="true"></i> {{ $cirugia->tipo_operacion }}</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
            <a class="btn btn-outline-primary mb-3" target="_blank" rel="noopener" href="{{ route('reportes.documento', ['cirugias', $cirugia->getKey()]) }}"><i class="bi bi-file-earmark-pdf"></i> PDF de cirugía y controles</a>
            @if($recuperar && $errors->any())
            <div class="alert alert-danger" role="alert"><strong>No se guardaron los cambios.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @elseif((int) session('abrir_cirugia') === $cirugia->getKey() && session('exito'))
            <div class="alert alert-success" role="status">{{ session('exito') }}</div>
            @endif

            <article class="doc-clinico">
                <dl class="doc-meta cirugia-meta">
                    <div><dt><i class="bi bi-flag" aria-hidden="true"></i> Estado</dt><dd><span class="doc-pastilla {{ $claseEstado }}">{{ $cirugia->estado }}</span></dd></div>
                    @if($cirugia->fecha_programada || ! $cirugia->motivo_registro_posterior)
                    <div><dt><i class="bi bi-calendar-event" aria-hidden="true"></i> Programada</dt><dd>@if($cirugia->fecha_programada){{ $cirugia->fecha_programada->format('d/m/Y') }} <span>{{ $cirugia->fecha_programada->format('H:i') }}</span>@else Sin agendar @endif</dd></div>
                    @endif
                    <div><dt><i class="bi bi-play-circle" aria-hidden="true"></i> Inicio real</dt><dd>@if($cirugia->fecha_inicio_real){{ $cirugia->fecha_inicio_real->format('d/m/Y') }} <span>{{ $cirugia->fecha_inicio_real->format('H:i') }}</span>@else Sin iniciar @endif</dd></div>
                    <div><dt><i class="bi bi-stop-circle" aria-hidden="true"></i> Fin real</dt><dd>@if($cirugia->fecha_fin_real){{ $cirugia->fecha_fin_real->format('d/m/Y') }} <span>{{ $cirugia->fecha_fin_real->format('H:i') }}</span>@else Sin finalizar @endif</dd></div>
                </dl>

                @if($cirugia->motivo_cancelacion)
                <div class="doc-aviso"><i class="bi bi-x-octagon-fill" aria-hidden="true"></i><div><strong>Cancelada</strong><span class="d-block">{{ $cirugia->motivo_cancelacion }}</span></div></div>
                @endif
                @if($cirugia->motivo_registro_posterior)
                <section class="doc-seccion">
                    <h6 class="doc-seccion-titulo"><i class="bi bi-clock-history" aria-hidden="true"></i> Registrada después de operar</h6>
                    <p class="doc-texto">{{ $cirugia->motivo_registro_posterior }}</p>
                    <p class="small text-muted mb-0">Se registró en el sistema el {{ $cirugia->fecha_registro?->timezone('America/Guatemala')->format('d/m/Y H:i') }}.</p>
                </section>
                @endif

                <section class="doc-seccion">
                    <h6 class="doc-seccion-titulo"><i class="bi bi-people" aria-hidden="true"></i> Personal</h6>
                    @if($cirugia->equipo->isNotEmpty())
                    <ul class="doc-chips">@foreach($cirugia->equipo as $persona)<li><i class="bi bi-person" aria-hidden="true"></i> {{ $nombrePersona($persona) }} <small class="text-muted">· {{ $persona->cargo?->nombre_cargo }}</small></li>@endforeach</ul>
                    @else
                    <p class="doc-vacio">Sin personal asignado.</p>
                    @endif
                </section>

                <section class="doc-seccion">
                    <h6 class="doc-seccion-titulo"><i class="bi bi-chat-left-text" aria-hidden="true"></i> Observaciones</h6>
                    @if(filled($cirugia->observaciones))<p class="doc-texto">{{ $cirugia->observaciones }}</p>@else<p class="doc-vacio">Sin observaciones.</p>@endif
                </section>

                <section class="doc-seccion">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h6 class="doc-seccion-titulo mb-0"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Controles de recuperación ({{ $cirugia->controles->count() }})</h6>
                        @if($puedeAnotarControl)
                        <a class="btn btn-sm btn-primary" href="{{ route('atencion.enfermeria', [$ingreso, 'control_cirugia' => $cirugia->getKey()]) }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Registrar control en nota de enfermería</a>
                        @endif
                    </div>
                    @forelse($cirugia->controles as $control)
                    @php
                        $signosControl = array_values(array_filter([
                            ['PA', 'Presión arterial', $control->presion_sistolica ? $control->presion_sistolica.'/'.$control->presion_diastolica : null, 'mmHg'],
                            ['FC', 'Frecuencia cardíaca', $control->frecuencia_cardiaca, 'lpm'],
                            ['FR', 'Frecuencia respiratoria', $control->frecuencia_respiratoria, 'rpm'],
                            ['Temp.', 'Temperatura', $control->temperatura, '°C'],
                            ['SpO₂', 'Saturación de oxígeno', $control->saturacion_oxigeno, '%'],
                        ], fn ($signo) => filled($signo[2])));
                    @endphp
                    <div class="cirugia-control">
                        <p class="mb-2"><strong>{{ $control->fecha_hora->format('d/m/Y H:i') }}</strong> · {{ $nombrePersona($control->personal) }}
                            @if($control->nota)<span class="badge text-bg-light border ms-1"><i class="bi bi-journal-text" aria-hidden="true"></i> Nota de enfermería</span>@endif
                        </p>
                        @include('expedientes.partials.doc-signos', ['signos' => $signosControl])
                        @if(filled($textoControl = $control->nota?->observaciones ?? $control->observaciones))<p class="doc-texto mt-2 mb-0">{{ $textoControl }}</p>@endif
                    </div>
                    @empty
                    <p class="doc-vacio">Todavía no hay controles. @if($cirugia->estado === 'Realizada')Se registran desde <strong>Nueva nota</strong> en la hoja de enfermería, marcando que es un control de esta cirugía.@else Se registran cuando la cirugía haya terminado.@endif</p>
                    @endforelse
                </section>
            </article>

            {{-- Mientras está en cirugía, la orden de recuperación se agrega al finalizarla (formulario de abajo). --}}
            @if($cirugia->estado === 'Realizada')
            @php
                $puedeGestionarRecuperacion = $gestionQuirurgica && ! ($consultaEnfermeria ?? false) && $cirugia->permiteAccion('terminar') && $abierto && $ingreso->hospitalizacion?->estado === 'Activa';
                $ordenRecuperacion = $cirugia->ordenRecuperacion();
                $ultimaOrden = \App\Services\OrdenesMedicas::vigentes($ingreso);
                $vigente = $ultimaOrden && ! \App\Services\OrdenesMedicas::cancelacion($ultimaOrden->ordenes_medicas);
            @endphp
            <section class="border rounded p-3 my-3" data-recuperacion-cirugia><h3 class="h5"><i class="bi bi-clipboard2-pulse"></i> Órdenes para recuperación</h3>
            @if($ingreso->hospitalizacion?->estado !== 'Activa')<p class="text-muted"><i class="bi bi-info-circle"></i> Las órdenes de recuperación se habilitan cuando el paciente queda internado.</p>@endif
            <div data-resumen-recuperacion>
            @if($vigente)
            <details class="border rounded p-3 mb-3" data-orden-vigente-recuperacion>
                <summary class="text-primary fw-semibold">Hay una orden vigente · Ver detalles</summary>
                <div class="mt-3">
                    <p class="text-muted">Indicada por {{ $ultimaOrden->personal?->nombres }} {{ $ultimaOrden->personal?->apellidos }} el {{ $ultimaOrden->fecha_hora->format('d/m/Y H:i') }}.</p>
                    @include('atencion.partials.ordenes-resumen', ['resumenOrdenes' => $ultimaOrden->ordenes_medicas])
                    @if($puedeGestionarRecuperacion && ((int)$ultimaOrden->id_personal === (int)$permisosCirugia['personal'] || filled($cirugia->motivo_registro_posterior)) && app(\App\Support\Permisos::class)->puede('Evolución clínica','Editar'))
                    <details class="mt-3"><summary class="text-danger">Cancelar orden vigente</summary>
                        <form method="POST" action="{{ route('evolucion.cancelar-ordenes', $ultimaOrden) }}" data-cancelar-orden-cirugia>@csrf
                            <input type="hidden" name="cirugia_posoperatoria" value="{{ $cirugia->getKey() }}">
                            <button class="btn btn-outline-danger mt-2">Confirmar cancelación</button>
                        </form>
                    </details>
                    @endif
                </div>
            </details>
            @else
            <p>Sin orden médica vigente.</p>
            @endif
            @if($ordenRecuperacion && (! $vigente || $ultimaOrden->getKey() !== $ordenRecuperacion->getKey()))
            <details class="border rounded p-3 mb-3"><summary class="fw-semibold">Orden de recuperación registrada · Ver detalles</summary>
                <div class="mt-3">@include('atencion.partials.ordenes-resumen', ['resumenOrdenes' => $ordenRecuperacion->ordenes_medicas])</div>
            </details>
            @endif
            </div>
            @php
                $editarRecuperacion = $ordenRecuperacion && (int)$ordenRecuperacion->id_usuario_creacion === (int)session('id_usuario') && (\App\Support\EdicionClinica::editableHasta($ordenRecuperacion)?->isFuture() ?? false);
            @endphp
            @if($puedeGestionarRecuperacion && $cirugia->estado === 'Realizada' && ((!$ordenRecuperacion && app(\App\Support\Permisos::class)->puede('Evolución clínica','Ingresar')) || ($editarRecuperacion && app(\App\Support\Permisos::class)->puede('Evolución clínica','Editar'))))
            @php
                $tieneEditorRecuperacion = true;
            @endphp
            <details class="mt-3" data-editor-recuperacion><summary class="text-primary fw-semibold"><i class="bi bi-clipboard2-plus"></i> {{ $ordenRecuperacion ? 'Editar orden de recuperación' : 'Agregar orden posoperatoria' }}</summary>
            <form method="POST" action="{{ route('cirugias.recuperacion', $cirugia) }}" class="mt-3" data-form-recuperacion data-validar-en-linea data-validar-al-enviar>
                @csrf
                <input type="hidden" name="_formulario" value="{{ $formularioCirugia }}">
                <input type="hidden" name="editar_recuperacion" value="{{ $ordenRecuperacion ? 1 : 0 }}">
                @include('atencion.partials.campos-ordenes', ['prefijo' => $idModal.'-recuperacion', 'ordenes' => $recuperar && old('editar_recuperacion') !== null ? old('ordenes', []) : ($ordenRecuperacion?->ordenes_medicas ?? [])])
                <button type="submit" hidden></button>
            </form></details>
            @elseif($ordenRecuperacion && !$editarRecuperacion)
            <p class="small text-muted mt-2"><i class="bi bi-lock"></i> Solo su autor puede editar la orden durante las primeras 12 horas.</p>
            @endif
            </section>
            @endif
            @if($abierto && $cirugia->estado === 'En cirugía' && ! $puedeFinalizar)
            <p class="alert alert-info mt-3"><i class="bi bi-info-circle" aria-hidden="true"></i> Solo un médico del personal participante puede finalizar esta cirugía.</p>
            @endif
            @if($gestionQuirurgica && in_array($cirugia->estado, ['Programada', 'Aprobada', 'Evaluación preoperatoria', 'En cirugía'], true))
            <form method="POST" action="{{ route('cirugias.estado', $cirugia) }}" class="border rounded p-3 mt-3" data-estado-cirugia data-permite-estado="{{ $puedeCambiarEstado ? '1' : '0' }}" data-permite-horario="{{ $puedeReprogramar ? '1' : '0' }}" data-validar-en-linea data-validar-al-enviar>
                @csrf
                <input type="hidden" name="_formulario" value="{{ $formularioCirugia }}">
                <h3 class="h5"><i class="bi bi-arrow-repeat text-primary" aria-hidden="true"></i> Actualizar estado</h3>
                @unless($puedeCambiarEstado)<p class="small text-muted"><i class="bi bi-lock"></i> La acción está bloqueada. Solo el personal participante puede iniciar o cancelar; solo un médico participante puede finalizar.</p>@endunless
                <p class="small text-muted">Registre el inicio y el final cuando ocurran. El horario programado es una estimación.</p>
                <fieldset class="border-0 p-0 m-0" disabled data-campos-estado><div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label" for="{{ $idModal }}-accion">Acción</label><select id="{{ $idModal }}-accion" name="accion" class="form-select" required><option value="" @selected(! $recuperar || ! old('accion'))>Seleccione una acción</option>@if($cirugia->estado === 'En cirugía')<option value="terminar">Finalizar cirugía</option>@else @if($puedeIniciar && $cirugia->estado !== 'Indicada')<option value="iniciar">Iniciar cirugía</option>@endif
@if($puedeCancelar)<option value="cancelar" @selected($recuperar && old('accion') === 'cancelar')>Cancelar cirugía</option>@endif @endif</select></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="{{ $idModal }}-hora" data-etiqueta-fecha-cirugia>Fecha y hora programada</label><input id="{{ $idModal }}-hora" type="datetime-local" name="fecha_hora" class="form-control" value="{{ $recuperar && old('fecha_hora') ? old('fecha_hora') : ($cirugia->fecha_programada ?? now('America/Guatemala'))->format('Y-m-d\TH:i') }}" required></div>
                    @if($cirugia->estado !== 'En cirugía')
                    <div class="col-12" data-motivo-cancelacion hidden><label class="form-label" for="{{ $idModal }}-motivo-cancelar">Motivo (obligatorio para cancelar)</label><input id="{{ $idModal }}-motivo-cancelar" class="form-control" name="motivo_cancelacion" maxlength="255" value="{{ $recuperar ? old('motivo_cancelacion') : '' }}"></div>
                    @endif
                </div>
                </fieldset>
                @if($puedeFinalizar && $ingreso->hospitalizacion?->estado !== 'Activa')
                <fieldset class="border rounded p-3 mt-3" data-internar-al-final hidden disabled>
                    <legend class="h6"><i class="bi bi-hospital"></i> Después de la cirugía</legend>
                    @if(app(\App\Support\Permisos::class)->puede('Hospitalización','Ingresar'))
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="{{ $idModal }}-internar" name="internar_al_finalizar" value="1" @checked(old('internar_al_finalizar'))><label class="form-check-label fw-semibold" for="{{ $idModal }}-internar">Internar después de la cirugía</label></div>
                    <div class="mt-3" data-habitacion-al-final hidden><label class="form-label" for="{{ $idModal }}-habitacion">Habitación *</label>
                    <select class="form-select" id="{{ $idModal }}-habitacion" name="habitacion_recuperacion" disabled><option value="">Seleccione una habitación disponible</option>
                    @foreach(\App\Models\Habitacion::where('estado_operativo','Activa')->whereDoesntHave('asignacionesHospitalizacion', fn ($q) => $q->whereNull('fecha_hora_fin'))->orderBy('numero_habitacion')->get() as $disponible)<option value="{{ $disponible->getKey() }}" @selected((int)old('habitacion_recuperacion') === $disponible->getKey())>{{ $disponible->numero_habitacion }} · {{ $disponible->tipo }}</option>@endforeach
                    </select><p class="small text-muted mt-2 mb-0">Al confirmar se asignará la habitación y se habilitarán las órdenes de recuperación.</p></div>
                    @else<p class="text-muted mb-0"><i class="bi bi-lock"></i> Necesita permiso de hospitalización para internar al paciente.</p>@endif
                </fieldset>
                @endif
                @if($puedeFinalizar && $permisosCirugia['editar'])
                @php
                    $personalFinalElegido = $recuperar && filled(old('personal_final'));
                @endphp
                <fieldset class="border rounded p-3 mt-3" data-personal-al-final hidden disabled>
                    <legend class="h6"><i class="bi bi-people"></i> Personal que participó</legend>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="{{ $idModal }}-editar-personal" data-mostrar-personal-final @checked($personalFinalElegido)><label class="form-check-label fw-semibold" for="{{ $idModal }}-editar-personal">Editar el personal que participó</label></div>
                    <fieldset class="border-0 p-0 m-0 mt-3" data-lista-personal-final @unless($personalFinalElegido) hidden disabled @endunless>
                    <p class="small text-muted">Marque a quienes se incorporaron durante la cirugía. El equipo ya registrado se conserva.</p>
                    <div class="cirugia-personal">@foreach($personalQuirurgico->reject(fn ($persona) => $cirugia->equipo->contains('id_personal', $persona->getKey())) as $persona)
                    <label class="cirugia-persona"><input type="checkbox" class="form-check-input me-2" name="personal_final[]" value="{{ $persona->getKey() }}" @checked(in_array($persona->getKey(), (array) old('personal_final', [])))><span><strong>{{ $nombrePersona($persona) }}</strong><small class="d-block text-muted">{{ $persona->cargo?->nombre_cargo }}</small></span></label>
                    @endforeach</div>
                    </fieldset>
                </fieldset>
                @endif
                @if($puedeFinalizar && app(\App\Support\Permisos::class)->puede('Evolución clínica', 'Ingresar'))
                @php
                    $agregarOrdenFinal = $recuperar && old('agregar_orden_recuperacion');
                    // Orden que enfermería tiene ahora como vigente (solo si la atención sigue abierta; al internar desde una consulta cerrada se abre una atención nueva).
                    $ordenActual = $abierto ? \App\Services\OrdenesMedicas::vigentes($ingreso) : null;
                    $ordenActual = $ordenActual && ! \App\Services\OrdenesMedicas::cancelacion($ordenActual->ordenes_medicas) ? $ordenActual : null;
                @endphp
                <fieldset class="border rounded p-3 mt-3" data-orden-al-final data-internado="{{ $ingreso->hospitalizacion?->estado === 'Activa' ? '1' : '0' }}" hidden disabled>
                    <legend class="h6"><i class="bi bi-clipboard2-pulse"></i> Orden de recuperación</legend>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="{{ $idModal }}-agregar-orden" name="agregar_orden_recuperacion" value="1" @checked($agregarOrdenFinal)><label class="form-check-label fw-semibold" for="{{ $idModal }}-agregar-orden">Agregar orden de recuperación</label></div>
                    <p class="small text-muted mb-0">Es la orden que enfermería verá y marcará en sus notas mientras el paciente se recupera.</p>
                    <fieldset class="border-0 p-0 m-0 mt-3" data-campos-orden-final @unless($agregarOrdenFinal) hidden disabled @endunless>
                        @if($ordenActual)
                        <div class="border rounded p-3 mb-3 bg-body-tertiary">
                            <p class="fw-semibold mb-1"><i class="bi bi-exclamation-circle text-warning" aria-hidden="true"></i> {{ $ordenActual->motivo_atencion === 'Orden de recuperación posoperatoria' ? 'Hay una orden de recuperación vigente' : 'Hay una orden médica vigente' }}</p>
                            <p class="small text-muted">Indicada el {{ $ordenActual->fecha_hora->format('d/m/Y H:i') }} por {{ trim(($ordenActual->personal?->nombres ?? '').' '.($ordenActual->personal?->apellidos ?? '')) ?: 'Sin registro' }}. Al guardar la orden de recuperación, enfermería verá la nueva.</p>
                            @include('atencion.partials.ordenes-resumen', ['resumenOrdenes' => $ordenActual->ordenes_medicas])
                            <input type="hidden" name="id_orden_vigente" value="{{ $ordenActual->getKey() }}">
                            <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="{{ $idModal }}-cancelar-orden" name="cancelar_orden_vigente" value="1" @checked($recuperar && old('cancelar_orden_vigente'))><label class="form-check-label" for="{{ $idModal }}-cancelar-orden">Cancelar esta orden al guardar la de recuperación</label></div>
                        </div>
                        @endif
                        @include('atencion.partials.campos-ordenes', ['prefijo' => $idModal.'-orden-final', 'ordenes' => $agregarOrdenFinal ? old('ordenes', []) : []])
                    </fieldset>
                </fieldset>
                @endif
                <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">

                    <button type="submit" hidden></button>
                </div>
            </form>
            @endif

            @if($editable)
            <div class="collapse @if($recuperarEdicion) show @endif" id="{{ $idModal }}-editar">
                <form method="POST" action="{{ route('cirugias.actualizar', $cirugia) }}" class="border rounded p-3 mt-3" data-correccion-cirugia data-validar-en-linea data-validar-al-enviar>
                    @csrf @method('PUT')
                    <input type="hidden" name="_formulario" value="{{ $formularioCirugia }}">
                    <input type="hidden" name="_accion_cirugia" value="editar">
                    <h3 class="h5"><i class="bi bi-pencil text-primary" aria-hidden="true"></i> {{ $cirugia->estado === 'Realizada' ? 'Agregar personal participante' : 'Corregir cirugía' }}</h3>
                    @if($limiteEdicion)<p class="small text-muted">Puede corregirla hasta el {{ $limiteEdicion->format('d/m/Y') }} a las {{ $limiteEdicion->format('H:i') }}.</p>@else<p class="small text-muted">Puede editarla mientras siga programada y la atención esté activa.</p>@endif
                    <div class="row g-3">
                        @if($cirugia->estado !== 'Realizada')
                        <div class="col-12"><label class="form-label" for="{{ $idModal }}-nombre">Nombre de la cirugía *</label><input id="{{ $idModal }}-nombre" class="form-control @if($recuperarEdicion) @error('tipo_operacion') is-invalid @enderror @endif" name="tipo_operacion" maxlength="150" required value="{{ $recuperarEdicion ? old('tipo_operacion') : $cirugia->tipo_operacion }}"></div>
                        @foreach(['fecha_inicio_real' => 'Inicio real', 'fecha_fin_real' => 'Fin real'] as $campo => $etiqueta)
                        @if($cirugia->{$campo})
                        <div class="col-12 col-md-6"><label class="form-label" for="{{ $idModal }}-{{ $campo }}">{{ $etiqueta }} *</label><input id="{{ $idModal }}-{{ $campo }}" type="datetime-local" class="form-control @if($recuperarEdicion) @error($campo) is-invalid @enderror @endif" name="{{ $campo }}" required min="{{ $ingreso->fecha_ingreso->format('Y-m-d\TH:i') }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" value="{{ $recuperarEdicion ? old($campo) : $cirugia->{$campo}->format('Y-m-d\TH:i') }}"></div>
                        @endif
                        @endforeach
                        @if($cirugia->motivo_registro_posterior !== null)
                        <div class="col-12"><label class="form-label" for="{{ $idModal }}-motivo">¿Por qué se registró después? *</label><textarea id="{{ $idModal }}-motivo" name="motivo_registro_posterior" class="form-control" required maxlength="500" rows="2">{{ $recuperarEdicion ? old('motivo_registro_posterior') : $cirugia->motivo_registro_posterior }}</textarea></div>
                        @endif
                        @endif
                        @if($cirugia->estado !== 'Realizada')
                        <div class="col-12"><label class="form-label" for="{{ $idModal }}-obs">Observaciones (opcional)</label><textarea id="{{ $idModal }}-obs" class="form-control" name="observaciones" rows="3" maxlength="2000">{{ $recuperarEdicion ? old('observaciones') : $cirugia->observaciones }}</textarea></div>
                        @endif
                        <fieldset class="col-12" data-filtro-personal-casillas>
                            <legend class="form-label fs-6"><i class="bi bi-people" aria-hidden="true"></i> Personal <span class="text-muted fw-normal">(opcional)</span></legend>
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-5"><label class="form-label" for="{{ $idModal }}-cargo-equipo"><i class="bi bi-funnel" aria-hidden="true"></i> Cargo</label><select id="{{ $idModal }}-cargo-equipo" class="form-select" data-filtrar-cargo><option value="">Todos los cargos</option>@foreach($personalQuirurgico->map(fn ($persona) => $persona->cargo?->nombre_cargo)->filter()->unique()->sort() as $cargoEquipo)<option value="{{ $cargoEquipo }}">{{ $cargoEquipo }}</option>@endforeach</select></div>
                                <div class="col-12 col-md-7"><label class="form-label" for="{{ $idModal }}-buscar-equipo"><i class="bi bi-search" aria-hidden="true"></i> Buscar personal</label><input id="{{ $idModal }}-buscar-equipo" type="search" class="form-control" placeholder="Escriba el nombre" data-filtrar-nombre></div>
                            </div>
                            <p class="small text-muted" data-personal-sin-resultados hidden>No hay personal que coincida con la búsqueda.</p>
                            <div class="cirugia-personal">@foreach($personalQuirurgico as $persona)<label class="cirugia-persona" data-persona-filtrable data-cargo="{{ $persona->cargo?->nombre_cargo }}"><input class="form-check-input me-2" type="checkbox" name="personal[]" value="{{ $persona->getKey() }}" @checked(in_array($persona->getKey(), $equipoSeleccionado, true)) @if($cirugia->estado === 'Realizada' && $cirugia->equipo->contains('id_personal', $persona->getKey())) disabled data-participante-guardado @endif><span><strong>{{ $nombrePersona($persona) }}</strong><small class="d-block text-muted">{{ $persona->cargo?->nombre_cargo }}</small></span></label>@endforeach</div>
                        </fieldset>
                    </div>
                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#{{ $idModal }}-editar">Cancelar</button>
                        <button type="submit" hidden></button>
                    </div>
                </form>
            </div>
            @endif

            @include('archivos.adjuntos', ['ingreso' => $ingreso, 'tipoHojaArchivo' => 'Cirugía', 'referenciaArchivo' => $cirugia->getKey(), 'fechaHojaArchivo' => $cirugia->fecha_fin_real ?? $cirugia->fecha_inicio_real ?? $cirugia->fecha_programada, 'permitirSubir' => ! $soloLectura && $abierto])
        </div>
        <div class="modal-footer">
            @if($editable)
            @if($limiteEdicion)<span class="small text-muted me-auto">Puede editarla hasta el {{ $limiteEdicion->format('d/m/Y') }} a las {{ $limiteEdicion->format('H:i') }}</span>@else<span class="small text-muted me-auto">Editable mientras siga programada.</span>@endif
            
            @endif
            @if($editable || $puedeCambiarEstado || $puedeReprogramar || $tieneEditorRecuperacion)
            <button type="button" class="btn btn-primary" data-editar-cirugia data-en-curso="{{ $cirugia->estado === 'En cirugía' ? '1' : '0' }}"><i class="bi bi-pencil" aria-hidden="true"></i> {{ $cirugia->estado === 'En cirugía' ? 'Finalizar cirugía' : 'Editar' }}</button>
            @endif
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
    </div></div>
</div>

@once
@push('modales')
@include('atencion.partials.ordenes-scripts')
@endpush
@endonce
