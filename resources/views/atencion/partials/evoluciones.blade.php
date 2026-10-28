@php
    $registro = $registroSeleccionado;
    $primero = $ingreso->evoluciones->first();
    $idValoracion = $primero && $primero->ordenes_medicas === null ? $primero->getKey() : null;
    $formulario = $registro ? 'evolucion-'.$registro->getKey() : 'evolucion-nueva';
    $recuperar = old('_formulario') === $formulario;
    $valorCampo = fn ($campo, $defecto = '') => $recuperar ? old($campo, $defecto) : ($registro?->{$campo} ?? $defecto);
    $ordenes = $recuperar ? old('ordenes', []) : ($registro?->ordenes_medicas ?? []);
    $otrasEvoluciones = $ingreso->evoluciones->reject(fn ($evolucion) => $registro && $evolucion->getKey() === $registro->getKey());
    $diagnosticoAnterior = $otrasEvoluciones->sortBy([['fecha_hora', 'desc'], ['id_evolucion', 'desc']])->first()?->diagnostico;
    $ordenesAnteriores = $otrasEvoluciones->filter(fn ($evolucion) => $evolucion->ordenes_medicas)->sortBy([['fecha_hora', 'desc'], ['id_evolucion', 'desc']])->first()?->ordenes_medicas;
    $medidasIniciales = $registro ? [] : collect(['talla', 'peso'])->mapWithKeys(fn ($medida) => [$medida => $ingreso->evoluciones->sortBy([['fecha_hora', 'desc'], ['id_evolucion', 'desc']])
        ->map(fn ($evolucion) => $medida === 'peso' ? \App\Services\ExamenFisico::pesoEnLibras($evolucion->examen_fisico) : ($evolucion->examen_fisico[$medida] ?? null))
        ->first(fn ($valor) => filled($valor))])->all();
    $diagnosticoActual = $valorCampo('diagnostico');
    $usarDiagnosticoAnterior = filled($diagnosticoAnterior) && ($recuperar ? old('diagnostico') === $diagnosticoAnterior : ! $registro);
    $dietaGuardada = $ordenes['dieta'] ?? '';
    $dietaEsOtra = $dietaGuardada === \App\Services\OrdenesMedicas::DIETA_OTRA || ($dietaGuardada !== '' && ! in_array($dietaGuardada, \App\Services\OrdenesMedicas::DIETAS, true));
    $textoDietaOtra = $recuperar ? ($ordenes['dieta_otra'] ?? '') : ($dietaEsOtra ? $dietaGuardada : '');
@endphp
<div class="evolucion-card">
    <h4>Evolución clínica y órdenes médicas</h4>
    <p class="text-muted">Seleccione un registro del historial para consultar o editar su evolución y sus órdenes. Cada registro conserva sus propias indicaciones.</p>
    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr><th>Fecha y hora</th><th>Médico</th><th>Tipo de registro</th><th>Evolución del paciente</th><th>Acciones</th></tr></thead>
        <tbody>@forelse($historial as $item)
        <tr @class(['table-primary' => $registro?->getKey() === $item->getKey()])>
            <td>{{ $item->fecha_hora->format('d/m/Y H:i') }}</td><td>{{ $item->personal?->nombres }} {{ $item->personal?->apellidos }}</td><td><span class="badge {{ $item->getKey() === $idValoracion ? 'bg-primary' : 'bg-success' }}">{{ $item->getKey() === $idValoracion ? 'Valoración inicial' : 'Evolución clínica' }}</span></td><td>{{ $item->getKey() === $idValoracion ? '—' : ($item->estado_mejoria ?: 'Sin registro') }}</td>
            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('atencion.show', ['ingreso'=>$ingreso->getKey(), 'registro'=>$item->getKey(), 'page'=>$historial->currentPage()]) }}#evoluciones">{{ $ingreso->estado === 'Activo' ? 'Ver / editar' : 'Ver' }} #{{ $item->getKey() }}</a></td>
        </tr>
        @empty<tr><td colspan="5">Sin registros clínicos.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="d-flex gap-3 align-items-center mb-3">
        @if($historial->previousPageUrl())<a href="{{ $historial->previousPageUrl() }}#evoluciones">Anterior</a>@endif
        <span>Página {{ $historial->currentPage() }} de {{ $historial->lastPage() }} · {{ $historial->total() }} registros</span>
        @if($historial->nextPageUrl())<a href="{{ $historial->nextPageUrl() }}#evoluciones">Siguiente</a>@endif
        @if($ingreso->estado === 'Activo')<a class="btn btn-primary ms-auto" href="{{ route('atencion.show', ['ingreso'=>$ingreso->getKey(), 'nuevo'=>1]) }}#evoluciones">Nueva evolución</a>@endif
    </div>
<div class="modal fade" id="modalEvolucion" tabindex="-1" aria-labelledby="modalEvolucion-titulo" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="modalEvolucion-titulo">Evolución clínica y órdenes médicas</h5><a href="#" role="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></a></div><div class="modal-body">@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="bg-primary text-white p-3 rounded-top">{{ $registro ? 'Registro #'.$registro->getKey().' · '.$registro->fecha_hora->format('d/m/Y H:i') : 'Nueva evolución' }}</div>
    @if($registro && !$registro->ordenes_medicas)<p class="alert alert-info">Este registro anterior no contiene órdenes estructuradas. Sus indicaciones originales se conservan: {{ $registro->indicaciones ?: 'Sin indicaciones' }}</p>@endif
    <form method="POST" action="{{ $registro ? route('evolucion.actualizar', $registro) : route('evolucion.guardar') }}" class="border rounded-bottom p-3" id="form-evolucion" novalidate data-validar-en-linea data-validar-al-enviar>
        @csrf @if($registro) @method('PUT') @endif
        <input type="hidden" name="_formulario" value="{{ $formulario }}">
        <input type="hidden" name="id_ingreso" value="{{ $ingreso->getKey() }}">
        <div class="row g-4">
            <div class="col-xl-6">
                <h5>Evolución clínica</h5>
                <label class="form-label" for="evo-fecha">Fecha y hora</label><input id="evo-fecha" class="form-control" type="datetime-local" name="fecha_hora" required min="{{ $ingreso->fecha_ingreso->format('Y-m-d\TH:i') }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" data-max-ahora data-mensaje-requerido="Indique la fecha y hora." data-mensaje-minimo="No puede ser anterior al ingreso." data-mensaje-maximo="La fecha y hora no pueden ser futuras." value="{{ $recuperar ? old('fecha_hora') : ($registro?->fecha_hora?->format('Y-m-d\TH:i') ?? now('America/Guatemala')->format('Y-m-d\TH:i')) }}"><div class="mb-3"></div>
                <label class="form-label" for="evo-medico">Médico responsable</label><select id="evo-medico" name="id_personal_medico" class="form-select mb-3" data-mensaje-requerido="Seleccione el médico responsable." required><option value="">Seleccione un médico</option>@foreach($medicos as $medico)<option value="{{ $medico->getKey() }}" @selected(($recuperar ? old('id_personal_medico') : ($registro?->id_personal ?? $personalSesion)) == $medico->getKey())>{{ $medico->nombres }} {{ $medico->apellidos }}</option>@endforeach</select>
                <input type="hidden" name="motivo_atencion" value="{{ $registro?->motivo_atencion ?: ($ingreso->motivo_ingreso ?: 'Seguimiento clínico') }}">
                <label class="form-label" for="evo-sintomas">Síntomas</label><textarea id="evo-sintomas" class="form-control mb-3" name="sintomas" maxlength="1000">{{ $valorCampo('sintomas') }}</textarea>
                @include('atencion.partials.examen-fisico', ['registro'=>$registro, 'formulario'=>$formulario, 'valoresIniciales'=>$medidasIniciales])
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
                @if($ordenesAnteriores && $ingreso->estado === 'Activo')
                <script type="application/json" id="ordenes-anteriores">@json($ordenesAnteriores)</script>
                <button type="button" class="btn btn-outline-primary w-100 mb-3" data-repetir-ordenes><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Seguir igual (copiar las órdenes anteriores)</button>
                @endif
                @if($registro?->ordenes_medicas)<div class="border rounded p-2 mb-3"><strong>Órdenes guardadas de este registro</strong>@include('atencion.partials.ordenes-resumen', ['resumenOrdenes'=>$registro->ordenes_medicas])</div>@endif
                <p><strong>Ingreso #{{ $ingreso->getKey() }}</strong> · Habitación {{ $ingreso->hospitalizacion?->habitacionActual?->habitacion?->numero_habitacion ?? 'Sin asignar' }}</p>
                <fieldset id="campos-ordenes">
                    <label class="form-label" for="orden-dieta">Dieta <span class="text-danger">*</span></label>
                    <select id="orden-dieta" class="form-select mb-2" name="ordenes[dieta]" data-mensaje-requerido="Seleccione la dieta." required>
                        <option value="">Seleccione</option>
                        @foreach(\App\Services\OrdenesMedicas::DIETAS as $dieta)<option @selected(! $dietaEsOtra && $dietaGuardada === $dieta)>{{ $dieta }}</option>@endforeach
                        <option value="{{ \App\Services\OrdenesMedicas::DIETA_OTRA }}" @selected($dietaEsOtra)>Otra (escribirla)</option>
                    </select>
                    <div class="mb-3" data-dieta-otra @unless($dietaEsOtra) hidden @endunless>
                        <input type="text" class="form-control" name="ordenes[dieta_otra]" value="{{ $textoDietaOtra }}" maxlength="100" placeholder="Ej. Hiposódica, diabética" aria-label="Otra dieta" data-mensaje-requerido="Escriba la dieta indicada." @disabled(! $dietaEsOtra) required>
                    </div>
                    <h6>Solución</h6><div id="filas-soluciones">
                    @foreach($ordenes['soluciones'] ?? [] as $indice=>$fila)@include('atencion.partials.fila-solucion')@endforeach
                    </div><button type="button" class="btn btn-outline-primary btn-sm mb-3" data-agregar="soluciones">Agregar solución</button>
                    <h6>Medicamentos</h6><div id="filas-medicamentos">
                    @foreach($ordenes['medicamentos'] ?? [] as $indice=>$fila)@include('atencion.partials.fila-medicamento')@endforeach
                    </div><button type="button" class="btn btn-outline-primary btn-sm mb-3" data-agregar="medicamentos">Agregar medicamento</button>
                    <div class="mb-3"><label class="form-label" for="orden-indicaciones">Otras indicaciones para enfermería</label><textarea id="orden-indicaciones" class="form-control" name="ordenes[indicaciones]" maxlength="2000" rows="4">{{ $ordenes['indicaciones'] ?? '' }}</textarea></div>
                    <div><label class="form-label" for="orden-plan-educacional">Plan educacional <span class="text-muted fw-normal">(opcional)</span></label><textarea id="orden-plan-educacional" class="form-control" name="ordenes[plan_educacional]" maxlength="2000" rows="3" placeholder="Orientación para el paciente y su familia. Ej. cuidados de la herida, signos de alarma">{{ $ordenes['plan_educacional'] ?? '' }}</textarea></div>
                </fieldset>
            </div>
        </div>
        <button class="btn btn-primary w-100 mt-4">{{ $registro ? 'Guardar cambios de evolución y órdenes' : 'Registrar evolución y órdenes' }}</button>
    </form>
    <template id="plantilla-soluciones">@include('atencion.partials.fila-solucion', ['indice'=>'__INDEX__', 'fila'=>[]])</template>
    <template id="plantilla-medicamentos">@include('atencion.partials.fila-medicamento', ['indice'=>'__INDEX__', 'fila'=>[]])</template>
    @if($registro)<form method="POST" action="{{ route('evolucion.eliminar', $registro) }}" class="mt-2">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Desactivar evolución #{{ $registro->getKey() }}</button></form>@endif
</div></div></div></div>
    @if($ingreso->signosVitales->isNotEmpty())
    <details class="mt-3"><summary>Historial de controles de signos vitales anteriores</summary><div class="table-responsive"><table class="table"><thead><tr><th>Fecha</th><th>PA</th><th>FC</th><th>FR</th><th>°C</th><th>SpO₂</th></tr></thead><tbody>@foreach($ingreso->signosVitales as $signo)<tr><td>{{ $signo->fecha_hora->format('d/m/Y H:i') }}</td><td>{{ $signo->presion_sistolica }}/{{ $signo->presion_diastolica }}</td><td>{{ $signo->frecuencia_cardiaca }}</td><td>{{ $signo->frecuencia_respiratoria }}</td><td>{{ $signo->temperatura }}</td><td>{{ $signo->saturacion_oxigeno }}</td></tr>@endforeach</tbody></table></div></details>
    @endif
</div>

@if($registroSeleccionado)
@include('archivos.adjuntos',['tipoHojaArchivo'=>'Evolución clínica','referenciaArchivo'=>$registroSeleccionado->getKey()])
@endif
