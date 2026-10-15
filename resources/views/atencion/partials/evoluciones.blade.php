@php
    $registro = $registroSeleccionado;
    $primero = $ingreso->evoluciones->first();
    $idValoracion = $primero && $primero->ordenes_medicas === null ? $primero->getKey() : null;
    $formulario = $registro ? 'evolucion-'.$registro->getKey() : 'evolucion-nueva';
    $recuperar = old('_formulario') === $formulario;
    $valorCampo = fn ($campo, $defecto = '') => $recuperar ? old($campo, $defecto) : ($registro?->{$campo} ?? $defecto);
    $ordenes = $recuperar ? old('ordenes', []) : ($registro?->ordenes_medicas ?? []);
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
    <form method="POST" action="{{ $registro ? route('evolucion.actualizar', $registro) : route('evolucion.guardar') }}" class="border rounded-bottom p-3" id="form-evolucion">
        @csrf @if($registro) @method('PUT') @endif
        <input type="hidden" name="_formulario" value="{{ $formulario }}">
        <input type="hidden" name="id_ingreso" value="{{ $ingreso->getKey() }}">
        <div class="row g-4">
            <div class="col-xl-6">
                <h5>Evolución clínica</h5>
                <label class="form-label" for="evo-fecha">Fecha y hora</label><input id="evo-fecha" class="form-control mb-3" type="datetime-local" name="fecha_hora" required max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ $recuperar ? old('fecha_hora') : ($registro?->fecha_hora ?? now())->format('Y-m-d\TH:i') }}">
                <label class="form-label" for="evo-medico">Médico responsable</label><select id="evo-medico" name="id_personal_medico" class="form-select mb-3" required><option value="">Seleccione un médico</option>@foreach($medicos as $medico)<option value="{{ $medico->getKey() }}" @selected(($recuperar ? old('id_personal_medico') : ($registro?->id_personal ?? $personalSesion)) == $medico->getKey())>{{ $medico->nombres }} {{ $medico->apellidos }}</option>@endforeach</select>
                <input type="hidden" name="motivo_atencion" value="{{ $registro?->motivo_atencion ?: ($ingreso->motivo_ingreso ?: 'Seguimiento clínico') }}">
                <label class="form-label" for="evo-sintomas">Signos y síntomas</label><textarea id="evo-sintomas" class="form-control mb-3" name="sintomas" maxlength="1000">{{ $valorCampo('sintomas') }}</textarea>
                @include('atencion.partials.examen-fisico', ['registro'=>$registro, 'formulario'=>$formulario])
                <label class="form-label" for="evo-diagnostico">Diagnóstico actual</label><textarea id="evo-diagnostico" class="form-control mb-3" name="diagnostico" maxlength="1000" required>{{ $valorCampo('diagnostico') }}</textarea>
                <label class="form-label" for="evo-observaciones">Observaciones</label><textarea id="evo-observaciones" class="form-control mb-3" name="observaciones" maxlength="1000">{{ $valorCampo('observaciones') }}</textarea>
                <fieldset>
                    <legend class="float-none fs-6 mb-2">Evolución del paciente</legend>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach(['Mejoró'=>'success', 'Sin cambios'=>'secondary', 'Empeoró'=>'danger'] as $estado=>$color)
                        <div class="flex-fill">
                            <input type="radio" class="btn-check" name="estado_mejoria" id="evo-mejoria-{{ $loop->index }}" value="{{ $estado }}" @checked($valorCampo('estado_mejoria') === $estado) required>
                            <label class="btn btn-outline-{{ $color }} w-100" for="evo-mejoria-{{ $loop->index }}">{{ $estado }}</label>
                        </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            <div class="col-xl-6 border-start">
                <h5>Órdenes médicas</h5><p class="text-muted">Indicaciones para enfermería vinculadas a esta evolución.</p>
                @if($registro?->ordenes_medicas)<div class="border rounded p-2 mb-3"><strong>Órdenes guardadas de este registro</strong>@include('atencion.partials.ordenes-resumen', ['resumenOrdenes'=>$registro->ordenes_medicas])</div>@endif
                <p><strong>Ingreso #{{ $ingreso->getKey() }}</strong> · Habitación {{ $ingreso->hospitalizacion?->habitacionActual?->habitacion?->numero_habitacion ?? 'Sin asignar' }}</p>
                <fieldset id="campos-ordenes">
                    <label class="form-label" for="orden-dieta">Dieta <span class="text-danger">*</span></label><select id="orden-dieta" class="form-select mb-3" name="ordenes[dieta]" required><option value="">Seleccione</option>@foreach(['Libre','Blanda','Líquida'] as $dieta)<option @selected(($ordenes['dieta'] ?? '') === $dieta)>{{ $dieta }}</option>@endforeach</select>
                    <h6>Solución</h6><div id="filas-soluciones">
                    @foreach($ordenes['soluciones'] ?? [] as $indice=>$fila)@include('atencion.partials.fila-solucion')@endforeach
                    </div><button type="button" class="btn btn-outline-primary btn-sm mb-3" data-agregar="soluciones">Agregar solución</button>
                    <h6>Medicamentos</h6><div id="filas-medicamentos">
                    @foreach($ordenes['medicamentos'] ?? [] as $indice=>$fila)@include('atencion.partials.fila-medicamento')@endforeach
                    </div><button type="button" class="btn btn-outline-primary btn-sm mb-3" data-agregar="medicamentos">Agregar medicamento</button>
                    <div><label class="form-label" for="orden-indicaciones">Otras indicaciones para enfermería</label><textarea id="orden-indicaciones" class="form-control" name="ordenes[indicaciones]" maxlength="2000" rows="4">{{ $ordenes['indicaciones'] ?? '' }}</textarea></div>
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
