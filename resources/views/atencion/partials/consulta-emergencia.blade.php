@php
    $consulta = $ingreso->evoluciones->first();
    $formulario = 'consulta-emergencia';
    $recuperar = old('_formulario') === $formulario;
    $valor = fn ($campo, $defecto = '') => $recuperar ? old($campo) : ($consulta?->{$campo} ?? $defecto);
    $fechaConsulta = $recuperar ? old('fecha_hora') : ($consulta?->fecha_hora?->format('Y-m-d\TH:i') ?? now('America/Guatemala')->format('Y-m-d\TH:i'));
    $consultasAnteriores = $consultasAnteriores ?? collect();
    $anteriorConsulta = $ingreso->atencionAnterior ?? $consultasAnteriores->first();
    $medicamentosCasa = $recuperar ? array_filter((array) old('medicamentos_casa', []), 'is_array') : [];
@endphp

<div class="consulta-con-historial" data-consulta-historial>
<div class="consulta-actual">
@if($ingreso->estado === 'Activo')
@php
    $ahoraLocal = now('America/Guatemala')->format('Y-m-d\TH:i');
    $llegada = ($minimoConsulta ?? $ingreso->fecha_ingreso)->format('Y-m-d\TH:i');
@endphp
<form method="POST" action="{{ $accionConsulta ?? route('emergencia.guardar', $ingreso) }}" id="form-consulta-emergencia" class="evolucion-card mb-3 consulta-emergencia" novalidate data-validar-en-linea>
    @csrf
    <input type="hidden" name="_formulario" value="{{ $formulario }}">
    <input type="hidden" name="accion" value="alta" disabled data-accion-alta>

    <h2 class="h4 mb-1"><i class="bi bi-person-walking text-consulta" aria-hidden="true"></i> Consulta externa @if($ingreso->id_ingreso_anterior)<span class="badge bg-primary ms-2 fs-6">Seguimiento</span>@endif</h2>
    <p class="text-muted mb-4">Registre la consulta y, al terminar, dé de alta al paciente o intérnelo.</p>

    @if($anteriorConsulta)
    <div class="consulta-referencia border rounded-3 p-3 mb-4 bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><strong>Consulta anterior · {{ $anteriorConsulta->fecha_ingreso->format('d/m/Y') }}</strong><span class="d-block text-muted text-break">{{ $anteriorConsulta->altaMedica?->diagnostico_final ?: 'Sin diagnóstico registrado' }}</span></div>
        <button type="button" class="btn btn-outline-primary" data-ver-consultas aria-expanded="false" aria-controls="historial-consultas"><i class="bi bi-clock-history" aria-hidden="true"></i> <span>Ver consultas anteriores</span></button>
    </div>
    @endif

    <section class="mb-4">
        <h3 class="h5 border-bottom pb-2 mb-3">Consulta</h3>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="emergencia-fecha">Fecha y hora</label>
                <input id="emergencia-fecha" type="datetime-local" name="fecha_hora" class="form-control @error('fecha_hora') is-invalid @enderror" value="{{ $fechaConsulta }}" min="{{ $llegada }}" max="{{ $ahoraLocal }}" data-mensaje-requerido="Indique la fecha y hora de la consulta." data-mensaje-minimo="No puede ser anterior a la llegada ({{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }})." data-max-ahora data-mensaje-maximo="La fecha y hora no pueden ser futuras." required>
                @error('fecha_hora')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8">
                <label class="form-label" for="emergencia-motivo">Motivo de consulta</label>
                <input id="emergencia-motivo" name="motivo_atencion" class="form-control @error('motivo_atencion') is-invalid @enderror" value="{{ $valor('motivo_atencion', $ingreso->motivo_ingreso) }}" maxlength="255" data-mensaje-requerido="Escriba el motivo de consulta." required>
                @error('motivo_atencion')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12"><label class="form-label" for="emergencia-sintomas">Síntomas</label><textarea id="emergencia-sintomas" name="sintomas" class="form-control" rows="3" maxlength="1000">{{ $valor('sintomas') }}</textarea></div>
        </div>
    </section>

    @include('atencion.partials.examen-fisico', ['registro' => $consulta, 'formulario' => $formulario, 'signosSeleccionables' => true])

    <section class="mb-4">
        <h3 class="h5 border-bottom pb-2 mb-3">Diagnóstico y tratamiento</h3>
        <div class="mb-3">
            <label class="form-label" for="emergencia-diagnostico">Diagnóstico</label>
            <textarea id="emergencia-diagnostico" name="diagnostico" class="form-control @error('diagnostico') is-invalid @enderror" rows="2" maxlength="1000" data-mensaje-requerido="Escriba el diagnóstico." required>{{ $valor('diagnostico') }}</textarea>
            @error('diagnostico')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <label class="form-label" for="emergencia-tratamiento">Tratamiento aplicado en el sanatorio</label>
        <textarea id="emergencia-tratamiento" name="indicaciones" class="form-control" rows="2" maxlength="2000" placeholder="Ej. se aplicó diclofenaco 75 mg IM">{{ $valor('indicaciones') }}</textarea>
    </section>

    <section class="mb-4" data-solo-alta>
        <h3 class="h5 border-bottom pb-2 mb-3">Ambulatorio</h3>
        <p class="form-label mb-1">Medicamentos</p>
        <p class="text-muted small mb-2">Opcional. Un medicamento por fila con su dosis, frecuencia y los días que debe tomarlo.</p>
        @error('medicamentos_casa')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        <div id="filas-medicamentos-casa">
            @foreach($medicamentosCasa as $indice => $fila)
            @include('atencion.partials.fila-medicamento-casa', ['indice' => $indice, 'fila' => $fila])
            @endforeach
        </div>
        <button type="button" class="btn btn-outline-primary mb-3" data-agregar-medicamento-casa><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar medicamento</button>
        <template id="plantilla-medicamento-casa">@include('atencion.partials.fila-medicamento-casa', ['indice' => '__INDEX__', 'fila' => []])</template>
        <label class="form-label d-block" for="emergencia-indicaciones-casa">Otras indicaciones</label>
        <textarea id="emergencia-indicaciones-casa" name="indicaciones_casa" class="form-control" rows="3" maxlength="2000" placeholder="Ej. reposo 3 días, no correr, regresar si sube la fiebre">{{ $recuperar ? old('indicaciones_casa') : '' }}</textarea>
    </section>

    <div class="alert alert-danger" role="alert" hidden data-resumen-errores>Revise los campos marcados en rojo.</div>

    <div class="d-flex flex-column flex-md-row gap-2 justify-content-md-end border-top pt-3">
        <button type="button" class="btn btn-success btn-lg" data-pedir-alta><i class="bi bi-house-door" aria-hidden="true"></i> Dar de alta</button>
        <button type="submit" name="accion" value="internar" class="btn btn-outline-primary btn-lg"><i class="bi bi-door-open" aria-hidden="true"></i> Internar paciente</button>
    </div>
</form>

<div class="modal fade modal-confirmacion" id="modalConfirmarAlta" tabindex="-1" aria-labelledby="modalConfirmarAlta-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalConfirmarAlta-titulo"><i class="bi bi-house-door text-success" aria-hidden="true"></i> ¿Dar de alta al paciente?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">La consulta externa quedará cerrada y ya no se podrá modificar.</p>
                <p class="text-muted small mb-0" data-resumen-alta></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Revisar otra vez</button>
                <button type="button" class="btn btn-success" data-confirmar-alta>Sí, dar de alta</button>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const form = document.getElementById('form-consulta-emergencia');
    const contenedor = document.getElementById('filas-medicamentos-casa');
    const plantilla = document.getElementById('plantilla-medicamento-casa');
    if (!form || !contenedor || !plantilla) return;
    let siguiente = [...contenedor.querySelectorAll('[name]')].reduce((maximo, campo) => {
        const indice = Number(campo.name.split('[')[1]?.split(']')[0]);
        return Number.isFinite(indice) ? Math.max(maximo, indice + 1) : maximo;
    }, 0);
    form.addEventListener('click', evento => {
        const quitar = evento.target.closest('[data-quitar-medicamento-casa]');
        if (quitar) {
            quitar.closest('.fila-medicamento-casa').remove();
            return;
        }
        if (!evento.target.closest('[data-agregar-medicamento-casa]') || contenedor.children.length >= 20) return;
        const fila = plantilla.content.cloneNode(true);
        fila.querySelectorAll('[name]').forEach(campo => { campo.name = campo.name.replace('__INDEX__', siguiente); });
        siguiente++;
        contenedor.append(fila);
        contenedor.lastElementChild?.querySelector('input')?.focus();
    });

    const accionAlta = form.querySelector('[data-accion-alta]');
    const modalAlta = document.getElementById('modalConfirmarAlta');
    form.addEventListener('submit', evento => {
        if (accionAlta.disabled === false) return;
        if (!window.validacionEnLinea.validar(form, '[data-solo-alta]')) evento.preventDefault();
    });
    form.querySelector('[data-pedir-alta]').addEventListener('click', () => {
        if (!window.validacionEnLinea.validar(form)) return;
        const medicamentos = contenedor.querySelectorAll('.fila-medicamento-casa').length;
        modalAlta.querySelector('[data-resumen-alta]').textContent = medicamentos
            ? (medicamentos === 1 ? 'Se registrará 1 medicamento ambulatorio.' : `Se registrarán ${medicamentos} medicamentos ambulatorios.`)
            : 'No se indicaron medicamentos ambulatorios.';
        bootstrap.Modal.getOrCreateInstance(modalAlta).show();
    });
    modalAlta.querySelector('[data-confirmar-alta]').addEventListener('click', evento => {
        evento.currentTarget.disabled = true;
        accionAlta.disabled = false;
        form.submit();
    });
})();
</script>
@else
@php
    $alta = $ingreso->altaMedica;
@endphp
<section class="evolucion-card mb-3">
    <h2 class="h4 mb-1"><i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i> Consulta externa</h2>
    <p class="text-muted mb-4">
        @if($alta)Dado de alta el {{ $alta->fecha_hora->format('d/m/Y H:i') }} @if($alta->medico)por {{ $alta->medico->nombres }} {{ $alta->medico->apellidos }}@endif @endif
    </p>
    @if($consulta)
    <dl class="row g-3 mb-0">
        <div class="col-md-4"><dt>Fecha de la consulta</dt><dd class="mb-0">{{ $consulta->fecha_hora->format('d/m/Y H:i') }}</dd></div>
        <div class="col-md-8"><dt>Motivo de consulta</dt><dd class="mb-0 text-break">{{ $consulta->motivo_atencion }}</dd></div>
        <div class="col-12"><dt>Síntomas</dt><dd class="mb-0 text-break">{{ $consulta->sintomas ?: 'Sin registro' }}</dd></div>
        @php
            $signosConsulta = collect(['presion_arterial' => 'PA (mmHg)', 'frecuencia_cardiaca' => 'FC (lpm)', 'frecuencia_respiratoria' => 'FR (rpm)', 'temperatura' => 'Temp. (°C)', 'saturacion_oxigeno' => 'SpO₂ (%)', 'peso' => 'Peso (lb)', 'talla' => 'Talla (cm)'])
                ->map(fn ($etiqueta, $campo) => [$etiqueta, $campo === 'peso' ? \App\Services\ExamenFisico::pesoEnLibras($consulta->examen_fisico) : ($consulta->examen_fisico[$campo] ?? null)])
                ->filter(fn ($signo) => filled($signo[1]));
        @endphp
        <div class="col-12"><dt>Signos vitales</dt><dd class="mb-0">
            @forelse($signosConsulta as [$etiqueta, $medida])
            <span class="badge text-bg-light border me-1 mb-1 fs-6 fw-normal">{{ $etiqueta }}: {{ $medida }}</span>
            @empty
            No se tomaron signos vitales.
            @endforelse
        </dd></div>
        @foreach(['cabeza' => 'Cabeza', 'tronco' => 'Tronco', 'extremidades' => 'Extremidades'] as $region => $etiquetaRegion)
        @if(filled($consulta->examen_fisico[$region] ?? null))
        <div class="col-md-4"><dt>{{ $etiquetaRegion }}</dt><dd class="mb-0 text-break">{{ $consulta->examen_fisico[$region] }}</dd></div>
        @endif
        @endforeach
        @if($consulta->evaluacion_medica)
        <div class="col-12"><dt>Descripción adicional del examen</dt><dd class="mb-0 text-break">{{ $consulta->evaluacion_medica }}</dd></div>
        @endif
        <div class="col-md-6"><dt>Diagnóstico</dt><dd class="mb-0 text-break">{{ $consulta->diagnostico }}</dd></div>
        <div class="col-md-6"><dt>Tratamiento aplicado en el sanatorio</dt><dd class="mb-0 text-break">{{ $consulta->indicaciones ?: 'Sin registro' }}</dd></div>
    </dl>
    @endif
    @if($alta)
    <h3 class="h5 border-bottom pb-2 mt-4 mb-3">Ambulatorio</h3>
    @if($alta->medicamentos->isNotEmpty())
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle">
            <thead><tr><th>Medicamento</th><th>Dosis</th><th>Frecuencia</th><th>Días</th></tr></thead>
            <tbody>
                @foreach($alta->medicamentos as $medicamento)
                <tr><td class="text-break">{{ $medicamento->medicamento }}</td><td>{{ $medicamento->dosis }}</td><td>{{ $medicamento->frecuencia }}</td><td>{{ $medicamento->duracion ?: '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <p class="text-muted">Sin medicamentos ambulatorios.</p>
    @endif
    <p class="mb-0"><strong>Otras indicaciones:</strong> {{ $alta->indicaciones_alta ?: 'Sin indicaciones' }}</p>
    @endif
</section>
@endif

</div>
@if($consultasAnteriores->isNotEmpty())
@include('seguimientos.historial')
@endif
</div>
