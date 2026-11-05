@php
    $modo = 'agendar';
    $cirugia = null;
    $personal = \App\Http\Controllers\CirugiaController::personalQuirurgico();
    $programadasAnteriores = $cirugiasDelAlta->whereIn('estado', \App\Services\CirugiasAlAlta::PENDIENTES);
    $puedeCancelarAnteriores = $programadasAnteriores->every(fn ($anterior) => $anterior->programacionGestionable() && $anterior->permiteAccion('cancelar'));
@endphp
<div class="modal fade" id="modalProgramarConsulta" tabindex="-1" aria-labelledby="programacion-consulta-titulo" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="programacion-consulta-titulo"><i class="bi bi-calendar-plus text-primary"></i> Agendar cirugía</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
<form id="form-programacion-pendiente" class="d-flex flex-column overflow-hidden" novalidate>
<div class="modal-body"><p class="text-muted">La cirugía se guardará junto con la valoración al dar de alta o continuar al internamiento.</p>
<div class="row g-4">
@if($programadasAnteriores->isNotEmpty())
<div class="col-12"><div class="alert alert-warning mb-0">
<strong><i class="bi bi-exclamation-triangle me-2"></i>El paciente ya tiene una cirugía programada</strong>
<ul class="my-2">@foreach($programadasAnteriores as $anterior)<li>{{ $anterior->tipo_operacion }} · {{ $anterior->fecha_programada?->format('d/m/Y H:i') }}</li>@endforeach</ul>
<p>Al añadir esta cirugía, ¿qué desea hacer con la programación anterior?</p>
<div class="form-check mb-2"><input id="nueva-conservar-anteriores" class="form-check-input" type="radio" name="decision_existentes" value="mantener" required><label class="form-check-label" for="nueva-conservar-anteriores">Conservar la anterior y añadir esta cirugía</label></div>
<div class="form-check"><input id="nueva-cancelar-anteriores" class="form-check-input" type="radio" name="decision_existentes" value="cancelar" required @disabled(!$puedeCancelarAnteriores)><label class="form-check-label" for="nueva-cancelar-anteriores">Cancelar la anterior y añadir esta cirugía</label></div>
@unless($puedeCancelarAnteriores)<p class="small mt-2 mb-0"><i class="bi bi-lock"></i>Solo el personal participante puede cancelar la cirugía anterior.</p>@endunless
</div></div>
@endif
<div class="col-12"><label class="form-label" for="cirugia-nombre"><i class="bi bi-hospital" aria-hidden="true"></i> Nombre de la cirugía *</label><input id="cirugia-nombre" class="form-control" name="tipo_operacion" maxlength="150" required value="{{ '' }}"></div>
@if($modo === 'agendar')
<div class="col-md-6"><label class="form-label" for="cirugia-inicio"><i class="bi bi-calendar-event" aria-hidden="true"></i> Fecha y hora programada *</label><input id="cirugia-inicio" type="datetime-local" class="form-control" name="fecha_programada" required min="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" value="{{ '' }}"></div>
@endif
@if($modo === 'realizada')
<div class="col-12"><div class="alert alert-info mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Para atenciones de urgencia que se registran después de operar. Escriba las horas en que ocurrió la cirugía; el sistema conservará por separado la fecha de registro.</div></div>
@foreach(['fecha_inicio_real'=>'Inicio real de la cirugía','fecha_fin_real'=>'Final real de la cirugía'] as $campo=>$etiqueta)
<div class="col-12 col-md-6"><label class="form-label" for="{{ $campo }}"><i class="bi bi-clock-history" aria-hidden="true"></i> {{ $etiqueta }} *</label><input id="{{ $campo }}" type="datetime-local" class="form-control" name="{{ $campo }}" required min="{{ $ingreso->fecha_ingreso->format('Y-m-d\TH:i') }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" value="{{ old($campo) }}"></div>
@endforeach
<div class="col-12"><label class="form-label" for="motivo-posterior"><i class="bi bi-journal-text" aria-hidden="true"></i> ¿Por qué se registró después? *</label><textarea id="motivo-posterior" name="motivo_registro_posterior" class="form-control" required maxlength="500" rows="3" placeholder="Ej. Se priorizó la atención inmediata del paciente.">{{ old('motivo_registro_posterior') }}</textarea></div>
@endif
@if(in_array($modo,['agendar','realizada']))
<fieldset class="col-12" data-filtro-equipo><legend class="h5"><i class="bi bi-people" aria-hidden="true"></i> Personal participante <small class="text-muted">(opcional)</small></legend><p>Busque por cargo o nombre y pulse «+ Agregar». Puede incluir varias personas de distintos cargos.</p><div class="row g-3 mb-3"><div class="col-md-5"><label class="form-label" for="equipo-cargo"><i class="bi bi-funnel"></i> Cargo</label><select class="form-select" id="equipo-cargo" data-cargo-equipo><option value="">Todos los cargos</option>@foreach($personal->map(fn($p)=>$p->cargo?->nombre_cargo)->filter()->unique()->sort() as $cargo)<option value="{{ $cargo }}">{{ $cargo }}</option>@endforeach</select></div><div class="col-md-7"><label class="form-label" for="equipo-buscar">Buscar personal</label><input id="equipo-buscar" type="search" class="form-control" placeholder="Escriba el nombre" data-buscar-equipo></div></div><p class="text-muted small" data-total-equipo></p>
<div class="border rounded-3 p-3 mb-3"><strong><i class="bi bi-people"></i> Equipo seleccionado</strong><div data-equipo-seleccionado class="d-flex flex-column gap-2 mt-2"></div></div><div class="cirugia-personal">@foreach($personal as $persona)<div class="cirugia-persona" data-personal-cirugia data-cargo="{{ $persona->cargo?->nombre_cargo }}"><input hidden type="checkbox" name="personal[]" value="{{ $persona->getKey() }}" @checked(in_array($persona->getKey(),[]))><span><strong>{{ $persona->nombres }} {{ $persona->apellidos }}</strong><small class="d-block text-muted">{{ $persona->cargo?->nombre_cargo }}</small></span><button type="button" class="btn btn-outline-primary btn-sm ms-auto" data-agregar-equipo><i class="bi bi-plus-lg"></i> Agregar</button></div>@endforeach</div>
<p hidden class="text-muted" data-equipo-vacio>No hay personal que coincida con el cargo y la búsqueda.</p></fieldset>
@endif
<div class="col-12"><label for="cirugia-observaciones" class="form-label"><i class="bi bi-chat-left-text" aria-hidden="true"></i> Observaciones (opcional)</label><textarea id="cirugia-observaciones" class="form-control" name="observaciones" rows="3" maxlength="2000">{{ '' }}</textarea></div>
</div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver a la valoración</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Añadir a la valoración</button></div>
</form></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const clinica = document.getElementById('form-consulta-emergencia');
    const pendiente = clinica?.querySelector('[name="cirugia_pendiente"]');
    const ventana = document.getElementById('modalProgramarConsulta');
    const agenda = document.getElementById('form-programacion-pendiente');
    if (!pendiente || !agenda) return;
    const resumen = clinica.querySelector('[data-resumen-programacion]');
    const actualizar = () => {
        let datos = null;
        try { datos = JSON.parse(pendiente.value || 'null'); } catch {}
        resumen.hidden = !datos;
        resumen.querySelector('[data-nombre-programacion]').textContent = datos ? datos.tipo_operacion + ' · ' + datos.fecha_programada.replace('T', ' ') : '';
        clinica.querySelector('[data-texto-programacion]').textContent = datos ? 'Modificar programación' : 'Agendar cirugía';
    };
    actualizar();
    ventana.addEventListener('show.bs.modal', () => {
        let datos = {};
        try { datos = JSON.parse(pendiente.value || '{}'); } catch {}
        ['tipo_operacion', 'fecha_programada', 'observaciones'].forEach(nombre => { agenda.elements[nombre].value = datos[nombre] || ''; });
        agenda.querySelectorAll('[name="personal[]"]').forEach(campo => { campo.checked = (datos.personal || []).map(String).includes(campo.value); });
        agenda.querySelectorAll('[name="decision_existentes"]').forEach(campo => { campo.checked = campo.value === datos.decision_existentes; });
        agenda.querySelector('[data-cargo-equipo]')?.dispatchEvent(new Event('change', {bubbles:true}));
    });
    agenda.addEventListener('submit', evento => {
        evento.preventDefault();
        if (!agenda.reportValidity()) return;
        const datos = new FormData(agenda);
        pendiente.value = JSON.stringify({tipo_operacion:datos.get('tipo_operacion'), fecha_programada:datos.get('fecha_programada'), observaciones:datos.get('observaciones'), personal:datos.getAll('personal[]'), decision_existentes:datos.get('decision_existentes')});
        pendiente.dispatchEvent(new Event('input', {bubbles:true}));
        actualizar();
        bootstrap.Modal.getOrCreateInstance(ventana).hide();
    });
    clinica.querySelector('[data-quitar-programacion]').addEventListener('click', () => {
        pendiente.value = '';
        pendiente.dispatchEvent(new Event('input', {bubbles:true}));
        actualizar();
    });
});
</script>
