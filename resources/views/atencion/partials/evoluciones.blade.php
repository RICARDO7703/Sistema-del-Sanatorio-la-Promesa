@php
    $registro = $registroSeleccionado;
    $primero = $ingreso->evoluciones->first();
    $idValoracion = $primero && $primero->estado_mejoria === null ? $primero->getKey() : null;
    $formulario = $registro ? 'evolucion-'.$registro->getKey() : 'evolucion-nueva';
    // Un registro guardado solo lo corrige quien lo hizo, durante 12 horas; para los demás queda de solo lectura.
    $soloLectura = $registro && ! (\App\Support\EdicionClinica::puedeEditar($registro) && app(\App\Support\Permisos::class)->puede('Evolución clínica', 'Editar'));
    $recuperar = old('_formulario') === $formulario;
    $valorCampo = fn ($campo, $defecto = '') => $recuperar ? old($campo, $defecto) : ($registro?->{$campo} ?? $defecto);
    $ordenes = $recuperar ? old('ordenes', []) : ($registro?->ordenes_medicas ?? []);
    $otrasEvoluciones = $ingreso->evoluciones->reject(fn ($evolucion) => $registro && $evolucion->getKey() === $registro->getKey());
    $diagnosticoAnterior = $otrasEvoluciones->sortBy([['fecha_hora', 'desc'], ['id_evolucion', 'desc']])->first()?->diagnostico;
    $ordenesAnteriores = $otrasEvoluciones->filter(fn ($evolucion) => $evolucion->ordenes_medicas)->sortBy([['fecha_hora', 'desc'], ['id_evolucion', 'desc']])->first()?->ordenes_medicas;
    $diagnosticoActual = $valorCampo('diagnostico');
    $usarDiagnosticoAnterior = filled($diagnosticoAnterior) && ($recuperar ? old('diagnostico') === $diagnosticoAnterior : ! $registro);
@endphp
<div class="evolucion-card">
    <div class="evolucion-historial-encabezado">
        <div>
            <h4 class="mb-2">Documentos de la atención</h4>
            <p class="text-muted mb-0">Evoluciones, notas de enfermería, cirugías y alta, del más nuevo al más viejo. Toque un documento para verlo; si todavía puede corregirlo, la ventana tiene el botón Editar.</p>
        </div>
        @if($ingreso->estado === 'Activo' && app(\App\Support\Permisos::class)->puede('Evolución clínica', 'Ingresar'))<a class="btn btn-primary evolucion-nueva-accion" href="{{ route('atencion.show', ['ingreso'=>$ingreso->getKey(), 'nuevo'=>1]) }}#evoluciones"><i class="bi bi-plus-circle me-2" aria-hidden="true"></i>Nueva evolución</a>@endif
    </div>
    <div class="d-grid gap-3">
        @include('expedientes.partials.documentos-por-dia', ['documentosPorDia' => $documentosAtencion, 'mostrarAtencion' => false, 'prefijoFiltro' => 'atencion'])
    </div>
    @include('expedientes.partials.modales-documentos', ['ingresos' => collect([$ingreso]), 'paciente' => $ingreso->paciente])
    @php($signosTomados = $ingreso->signosVitales->filter(fn ($signo) => collect([$signo->presion_sistolica, $signo->frecuencia_cardiaca, $signo->frecuencia_respiratoria, $signo->temperatura, $signo->saturacion_oxigeno])->contains(fn ($valor) => filled($valor))))
    @if($signosTomados->isNotEmpty())
    {{-- Solo los controles en que sí se tomó algún signo; los registros vacíos no se muestran. --}}
    <details class="mt-3"><summary>Historial de controles de signos vitales anteriores</summary><div class="table-responsive"><table class="table"><thead><tr><th>Fecha</th><th>PA</th><th>FC</th><th>FR</th><th>°C</th><th>SpO₂</th></tr></thead><tbody>@foreach($signosTomados as $signo)<tr><td>{{ $signo->fecha_hora->format('d/m/Y H:i') }}</td><td>{{ $signo->presion_sistolica ? $signo->presion_sistolica.'/'.$signo->presion_diastolica : '—' }}</td><td>{{ $signo->frecuencia_cardiaca ?? '—' }}</td><td>{{ $signo->frecuencia_respiratoria ?? '—' }}</td><td>{{ $signo->temperatura ?? '—' }}</td><td>{{ $signo->saturacion_oxigeno ?? '—' }}</td></tr>@endforeach</tbody></table></div></details>
    @endif
</div>
{{-- Con la atención cerrada, el formulario queda deshabilitado; la lista de documentos de arriba sigue disponible para consultar. --}}
<fieldset class="atencion-contenido" @disabled($ingreso->estado !== 'Activo')>
<div class="modal fade" id="modalEvolucion" tabindex="-1" aria-labelledby="modalEvolucion-titulo" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="modalEvolucion-titulo">Evolución clínica y órdenes médicas</h5><a href="#" role="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></a></div><div class="modal-body">@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="bg-primary text-white p-3 rounded-top">{{ $registro ? 'Registro #'.$registro->getKey().' · '.$registro->fecha_hora->format('d/m/Y H:i') : 'Nueva evolución' }}</div>
    @if($registro && !$registro->ordenes_medicas)<p class="alert alert-info">Este registro anterior no contiene órdenes estructuradas. Sus indicaciones originales se conservan: {{ $registro->indicaciones ?: 'Sin indicaciones' }}</p>@endif
    <form method="POST" action="{{ $registro ? route('evolucion.actualizar', $registro) : route('evolucion.guardar') }}" class="border rounded-bottom p-3" id="form-evolucion" novalidate data-validar-en-linea data-validar-al-enviar>
        @csrf @if($registro) @method('PUT') @endif
        @if(request('cirugia_recuperacion') || old('cirugia_recuperacion'))
<input type="hidden" name="cirugia_recuperacion" value="{{ old('cirugia_recuperacion', request('cirugia_recuperacion')) }}">
@endif
<input type="hidden" name="_formulario" value="{{ $formulario }}">
        <input type="hidden" name="id_ingreso" value="{{ $ingreso->getKey() }}">
        @if($soloLectura)
        <div class="alert alert-secondary d-flex gap-2" role="note"><i class="bi bi-lock" aria-hidden="true"></i><span>{{ \App\Support\EdicionClinica::MENSAJE }}</span></div>
        @elseif($registro)
        <p class="small text-muted"><i class="bi bi-clock-history" aria-hidden="true"></i> Puede corregir este registro hasta el {{ \App\Support\EdicionClinica::limiteLocal($registro)?->format('d/m/Y') }} a las {{ \App\Support\EdicionClinica::limiteLocal($registro)?->format('H:i') }}.</p>
        @endif
        <fieldset class="border-0 p-0 m-0" @disabled($soloLectura)>
        <div class="row g-4">
            <div class="col-xl-6">
                <h5>Evolución clínica</h5>
                <label class="form-label" for="evo-fecha">Fecha y hora</label><input id="evo-fecha" class="form-control" type="datetime-local" name="fecha_hora" required min="{{ $ingreso->fecha_ingreso->format('Y-m-d\TH:i') }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" data-max-ahora data-mensaje-requerido="Indique la fecha y hora." data-mensaje-minimo="No puede ser anterior al ingreso." data-mensaje-maximo="La fecha y hora no pueden ser futuras." value="{{ $recuperar ? old('fecha_hora') : ($registro?->fecha_hora?->format('Y-m-d\TH:i') ?? now('America/Guatemala')->format('Y-m-d\TH:i')) }}"><small class="form-text text-muted d-block">¿Se atendió antes y se registra ahora (por ejemplo, sin internet)? Ponga la hora en que se atendió.</small><div class="mb-3"></div>
                @php($responsable = $registro?->personal ?? \App\Models\Personal::find($personalSesion))
                {{-- El responsable es el personal del usuario que inició sesión; no se elige. --}}
                <p class="mb-3"><span class="form-label d-block mb-1">Médico responsable</span><span class="fw-semibold"><i class="bi bi-person-badge me-1" aria-hidden="true"></i>{{ trim($responsable?->nombres.' '.$responsable?->apellidos) ?: 'Sin personal asociado' }}</span></p>
                <input type="hidden" name="motivo_atencion" value="{{ $registro?->motivo_atencion ?: ($ingreso->motivo_ingreso ?: 'Seguimiento clínico') }}">
                <label class="form-label" for="evo-sintomas">Síntomas</label><textarea id="evo-sintomas" class="form-control mb-3" name="sintomas" maxlength="1000">{{ $valorCampo('sintomas') }}</textarea>
                @include('atencion.partials.examen-fisico', ['registro'=>$registro, 'formulario'=>$formulario])
                <fieldset class="mb-3" data-diagnostico-evolucion data-diagnostico-anterior="{{ $diagnosticoAnterior }}">
                    <legend class="form-label fs-6 mb-2">Diagnóstico actual</legend>
                    @if(filled($diagnosticoAnterior))
                    <div class="d-flex flex-wrap gap-2 mb-2" role="group" aria-label="Origen del diagnóstico">
                        <input type="radio" class="btn-check" name="origen_diagnostico" id="evo-diagnostico-anterior" value="anterior" @checked($usarDiagnosticoAnterior)>
                        <label class="btn btn-outline-primary" for="evo-diagnostico-anterior"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Mantener el anterior</label>
                        <input type="radio" class="btn-check" name="origen_diagnostico" id="evo-diagnostico-nuevo" value="nuevo" @checked(! $usarDiagnosticoAnterior)>
                        <label class="btn btn-outline-primary" for="evo-diagnostico-nuevo"><i class="bi bi-pencil" aria-hidden="true"></i> Escribir uno nuevo</label>
                    </div>
                    @endif
                    <textarea id="evo-diagnostico" class="form-control" name="diagnostico" maxlength="1000" aria-label="Diagnóstico actual" data-mensaje-requerido="Escriba el diagnóstico." @readonly($usarDiagnosticoAnterior) required>{{ $usarDiagnosticoAnterior ? $diagnosticoAnterior : $diagnosticoActual }}</textarea>
                </fieldset>
                <label class="form-label" for="evo-observaciones">Observaciones</label><textarea id="evo-observaciones" class="form-control mb-3" name="observaciones" maxlength="1000">{{ $valorCampo('observaciones') }}</textarea>
                <fieldset>
                    <legend class="float-none fs-6 mb-2">Evolución del paciente</legend>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach(['Mejoró'=>'success', 'Sin cambios'=>'secondary', 'Empeoró'=>'danger'] as $estado=>$color)
                        <div class="flex-fill">
                            <input type="radio" class="btn-check" name="estado_mejoria" id="evo-mejoria-{{ $loop->index }}" value="{{ $estado }}" @checked($valorCampo('estado_mejoria') === $estado) data-mensaje-requerido="Indique cómo evolucionó el paciente." required>
                            <label class="btn btn-outline-{{ $color }} w-100" for="evo-mejoria-{{ $loop->index }}">{{ $estado }}</label>
                        </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            <div class="col-xl-6 border-start">
                <h5>Órdenes médicas</h5><p class="text-muted">Indicaciones para enfermería vinculadas a esta evolución.</p>
                @include('atencion.partials.aviso-alergias', ['paciente' => $ingreso->paciente])
                @if($ordenesAnteriores && $ingreso->estado === 'Activo' && ! \App\Services\OrdenesMedicas::cancelacion($registro?->ordenes_medicas))
                <script type="application/json" id="ordenes-anteriores">@json($ordenesAnteriores)</script>
                <button type="button" class="btn btn-outline-primary w-100 mb-3" data-repetir-ordenes><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Seguir igual (copiar las órdenes anteriores)</button>
                @endif
                <p><strong>Ingreso #{{ $ingreso->getKey() }}</strong> · Habitación {{ $ingreso->hospitalizacion?->habitacionActual?->habitacion?->numero_habitacion ?? 'Sin asignar' }}</p>
                @include('atencion.partials.campos-ordenes', ['prefijo' => 'orden', 'ordenes' => $ordenes, 'idCampos' => 'campos-ordenes', 'registroOrdenesCanceladas' => $registro])
            </div>
        </div>
        </fieldset>
        @unless($soloLectura)
        <button class="btn btn-primary w-100 mt-4">{{ $registro ? 'Confirmar cambios de evolución y órdenes' : 'Registrar evolución y órdenes' }}</button>
        @endunless
    </form>
    @if($registro && app(\App\Support\Permisos::class)->puede('Evolución clínica', 'Desactivar'))<form method="POST" action="{{ route('evolucion.eliminar', $registro) }}" class="mt-2">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Desactivar evolución #{{ $registro->getKey() }}</button></form>@endif
</div></div></div></div>

@if($registroSeleccionado)
@include('archivos.adjuntos',['tipoHojaArchivo'=>'Evolución clínica','referenciaArchivo'=>$registroSeleccionado->getKey(),'fechaHojaArchivo'=>$registroSeleccionado->fecha_hora])
@endif
</fieldset>
