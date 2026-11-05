{{--
    Corrección de una nota de enfermería. Usa los mismos controles que «Nueva nota» (estado con botones,
    signos a elección, actividades como botones, órdenes con Se aplicó / No se aplicó) para que todo siga el mismo estándar.
    Variables: $nota (con signosVitales, actividades y ordenes).
--}}
@if($nota->puedeEditar((int)session('id_usuario')) && app(\App\Support\Permisos::class)->puede('Enfermería','Editar'))
@php
    $recuperarNota = old('_nota') == (string) $nota->getKey();
    $prefijoNota = 'editar-nota-'.$nota->getKey();
    $valorNota = fn (string $campo) => $recuperarNota ? old($campo) : $nota->{$campo};
    $estadoEditado = $valorNota('estado_paciente');
    $actividadesGuardadas = $nota->actividades->pluck('actividad');
    $actividadesEditadas = $recuperarNota ? (array) old('actividades', []) : $actividadesGuardadas->intersect(\App\Models\NotaEnfermeria::ACTIVIDADES)->values()->all();
    $otraEditada = $recuperarNota ? (string) old('actividad_otra', '') : $actividadesGuardadas->diff(\App\Models\NotaEnfermeria::ACTIVIDADES)->implode('; ');
    $avisoEditado = (bool) $valorNota('aviso_medico');
    $medicosNota = \App\Models\Personal::whereHas('cargo', fn ($q) => $q->whereIn('nombre_cargo', \App\Models\Personal::CARGOS_MEDICOS))->where('estado', 'Activo')->orderBy('nombres')->get();
    $iconosOrdenNota = ['Dieta' => 'bi-cup-hot', 'Solución' => 'bi-droplet', 'Medicamento' => 'bi-capsule', 'Indicaciones' => 'bi-list-check'];
@endphp
<form id="{{ $prefijoNota }}" hidden method="POST" action="{{ route('notas-enfermeria.actualizar',$nota) }}" class="border rounded-3 p-3 mb-3" data-editor-nota novalidate data-validar-en-linea data-validar-al-enviar>
    @csrf @method('PUT')
    <input type="hidden" name="_nota" value="{{ $nota->getKey() }}">
    <h3 class="h5 mb-3">Corregir nota de enfermería</h3>
    @if($recuperarNota && $errors->any())
    <div class="alert alert-danger" role="alert"><strong>No se guardaron los cambios.</strong> Corrija lo siguiente:<ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="{{ $prefijoNota }}-fecha">Fecha y hora</label>
            <input id="{{ $prefijoNota }}-fecha" type="datetime-local" name="fecha_hora" class="form-control @if($recuperarNota) @error('fecha_hora') is-invalid @enderror @endif" value="{{ $recuperarNota ? old('fecha_hora') : $nota->fecha_hora->format('Y-m-d\TH:i') }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" data-fecha-nota data-mensaje-requerido="Indique la fecha y hora de la nota." required>
            @if($recuperarNota) @error('fecha_hora')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
            <small class="text-muted" data-turno-texto aria-live="polite"></small>
        </div>
        <fieldset class="col-md-6">
            <legend class="form-label fs-6 mb-2">¿Cómo está el paciente?</legend>
            <div class="d-flex gap-2">
                @foreach(\App\Models\NotaEnfermeria::ESTADOS as $estadoNota)
                <input type="radio" class="btn-check" name="estado_paciente" id="{{ $prefijoNota }}-estado-{{ $loop->index }}" value="{{ $estadoNota }}" @checked($estadoEditado === $estadoNota) data-mensaje-requerido="Indique cómo está el paciente." required>
                <label class="btn {{ ['Mejoró' => 'btn-outline-success', 'Sin cambios' => 'btn-outline-secondary', 'Empeoró' => 'btn-outline-danger'][$estadoNota] }} flex-fill" for="{{ $prefijoNota }}-estado-{{ $loop->index }}">{{ $estadoNota }}</label>
                @endforeach
            </div>
            @if($recuperarNota) @error('estado_paciente')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
        </fieldset>
        @if($nota->signosVitales?->id_cirugia)
        <input type="hidden" name="id_cirugia" value="{{ $nota->signosVitales->id_cirugia }}">
        <div class="col-12"><p class="nota-control-cirugia mb-0"><i class="bi bi-hospital text-primary" aria-hidden="true"></i> Control de recuperación · {{ $nota->signosVitales->cirugia?->tipo_operacion }}. Debe conservar al menos un signo vital.</p></div>
        @endif
        <div class="col-12">
            <fieldset class="border rounded p-3">
                <legend class="float-none w-auto px-2 fs-6 mb-1">Signos vitales <span class="text-muted fw-normal">(opcional)</span></legend>
                @include('atencion.partials.signos-seleccionables', ['prefijo' => $prefijoNota, 'valor' => fn ($campo) => $recuperarNota ? old($campo) : $nota->signosVitales?->{$campo}, 'mostrarErrores' => $recuperarNota])
            </fieldset>
        </div>
        @if($nota->ordenes->isNotEmpty())
        <fieldset class="col-12">
            <legend class="form-label fs-6 mb-1">Órdenes médicas</legend>
            <p class="text-muted small mb-2">Indique en cada orden si se aplicó o no; si no se aplicó, escriba por qué.</p>
            <div class="nota-ordenes">
                @foreach($nota->ordenes as $indice => $orden)
                @php
                    $estadoOrden = $recuperarNota ? old('ordenes.'.$indice.'.estado') : ($orden->aplicada ? 'Aplicada' : 'No aplicada');
                @endphp
                <fieldset class="nota-orden" data-orden>
                    <div class="nota-orden-texto">
                        <span class="nota-orden-tipo"><i class="bi {{ $iconosOrdenNota[$orden->tipo] ?? 'bi-journal-medical' }}" aria-hidden="true"></i> {{ $orden->tipo }}</span>
                        <strong id="{{ $prefijoNota }}-orden-{{ $indice }}">{{ $orden->descripcion }}</strong>
                    </div>
                    <div class="nota-orden-opciones" role="group" aria-labelledby="{{ $prefijoNota }}-orden-{{ $indice }}">
                        <input type="radio" class="btn-check" name="ordenes[{{ $indice }}][estado]" id="{{ $prefijoNota }}-orden-{{ $indice }}-si" value="Aplicada" data-orden-estado required data-mensaje-requerido="Indique si se aplicó o no." @checked($estadoOrden === 'Aplicada')>
                        <label class="btn btn-outline-success" for="{{ $prefijoNota }}-orden-{{ $indice }}-si"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ \App\Models\NotaEnfermeriaOrden::textoResultado($orden->tipo, true) }}</label>
                        <input type="radio" class="btn-check" name="ordenes[{{ $indice }}][estado]" id="{{ $prefijoNota }}-orden-{{ $indice }}-no" value="No aplicada" data-orden-estado required @checked($estadoOrden === 'No aplicada')>
                        <label class="btn btn-outline-danger" for="{{ $prefijoNota }}-orden-{{ $indice }}-no"><i class="bi bi-x-lg" aria-hidden="true"></i> {{ \App\Models\NotaEnfermeriaOrden::textoResultado($orden->tipo, false) }}</label>
                    </div>
                    @if($recuperarNota) @error('ordenes.'.$indice.'.estado')<div class="invalid-feedback d-block nota-orden-error">{{ $message }}</div>@enderror @endif
                    <div class="nota-orden-motivo" data-orden-motivo @unless($estadoOrden === 'No aplicada') hidden @endunless>
                        <label class="form-label" for="{{ $prefijoNota }}-orden-{{ $indice }}-motivo">¿Por qué no?</label>
                        <input type="text" id="{{ $prefijoNota }}-orden-{{ $indice }}-motivo" name="ordenes[{{ $indice }}][motivo]" class="form-control @if($recuperarNota) @error('ordenes.'.$indice.'.motivo') is-invalid @enderror @endif" maxlength="255" placeholder="Ej. Paciente en ayuno para estudio" value="{{ $recuperarNota ? old('ordenes.'.$indice.'.motivo') : $orden->motivo }}" data-mensaje-requerido="Escriba por qué no se aplicó.">
                        @if($recuperarNota) @error('ordenes.'.$indice.'.motivo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                    </div>
                </fieldset>
                @endforeach
            </div>
        </fieldset>
        @endif
        <fieldset class="col-12">
            <legend class="form-label fs-6 mb-2">Actividades realizadas</legend>
            <div class="d-flex flex-wrap gap-2">
                @foreach(\App\Models\NotaEnfermeria::ACTIVIDADES as $actividad)
                <input type="checkbox" class="btn-check" name="actividades[]" id="{{ $prefijoNota }}-actividad-{{ $loop->index }}" value="{{ $actividad }}" @checked(in_array($actividad, $actividadesEditadas, true))>
                <label class="btn btn-outline-primary" for="{{ $prefijoNota }}-actividad-{{ $loop->index }}">{{ $actividad }}</label>
                @endforeach
                <input type="checkbox" class="btn-check" id="{{ $prefijoNota }}-actividad-otra" data-mostrar-otra-actividad @checked($otraEditada !== '')>
                <label class="btn btn-outline-secondary" for="{{ $prefijoNota }}-actividad-otra"><i class="bi bi-plus-lg" aria-hidden="true"></i> Otra</label>
            </div>
            <div class="mt-2" data-otra-actividad @if($otraEditada === '') hidden @endif>
                <label class="form-label" for="{{ $prefijoNota }}-actividad-otra-texto">Otra actividad</label>
                <input id="{{ $prefijoNota }}-actividad-otra-texto" type="text" name="actividad_otra" class="form-control @if($recuperarNota) @error('actividad_otra') is-invalid @enderror @endif" maxlength="100" placeholder="Ej. Control de glucosa" value="{{ $otraEditada }}">
            </div>
        </fieldset>
        <div class="col-12">
            <label class="form-label" for="{{ $prefijoNota }}-observaciones">Nota de enfermería</label>
            <textarea id="{{ $prefijoNota }}-observaciones" name="observaciones" class="form-control @if($recuperarNota) @error('observaciones') is-invalid @enderror @endif" rows="3" maxlength="2000" placeholder="Ej. Paciente refiere menos dolor, tolera dieta blanda. Se cambió suero salino 500 ml.">{{ $valorNota('observaciones') }}</textarea>
            @if($recuperarNota) @error('observaciones')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="aviso_medico" value="1" id="{{ $prefijoNota }}-aviso-medico" data-aviso-medico @checked($avisoEditado)>
                <label class="form-check-label" for="{{ $prefijoNota }}-aviso-medico">Se avisó al médico</label>
            </div>
            <div class="mt-2" data-medico-avisado @unless($avisoEditado) hidden @endunless>
                <label class="form-label" for="{{ $prefijoNota }}-medico-avisado">¿A qué médico se avisó? *</label>
                <select id="{{ $prefijoNota }}-medico-avisado" name="id_personal_avisado" class="form-select @if($recuperarNota) @error('id_personal_avisado') is-invalid @enderror @endif" data-mensaje-requerido="Seleccione a qué médico se avisó." @if($avisoEditado) required @else disabled @endif>
                    <option value="">Seleccione el médico</option>
                    @foreach($medicosNota as $medico)
                    <option value="{{ $medico->getKey() }}" @selected((string) $valorNota('id_personal_avisado') === (string) $medico->getKey())>{{ $medico->nombres }} {{ $medico->apellidos }}</option>
                    @endforeach
                </select>
                @if($recuperarNota) @error('id_personal_avisado')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
            </div>
        </div>
    </div>
    <button type="submit" hidden></button>
</form>
@endif

@if(isset($recuperarNota) && (session('nota_editada')==$nota->getKey() || old('_nota')==(string)$nota->getKey()))
<script>document.addEventListener('DOMContentLoaded',()=>{const form=document.getElementById(@json('editar-nota-'.$nota->getKey()));const modal=form?.closest('.modal');if(modal){bootstrap.Modal.getOrCreateInstance(modal).show();@if($recuperarNota)modal.querySelector('[data-editar-nota]')?.click();@endif}});</script>
@endif

@once
@push('modales')
<script>
// Corrección de notas: mismos comportamientos que en «Nueva nota» (turno, otra actividad y motivo de las órdenes no aplicadas).
(() => {
    const turnoDe = valor => {
        const hora = Number((valor.split('T')[1] || '').slice(0, 2));
        return valor ? 'Turno ' + (hora >= 7 && hora < 13 ? 'mañana' : (hora >= 13 && hora < 19 ? 'tarde' : 'noche')) : '';
    };
    const sincronizar = form => {
        const fecha = form.querySelector('[data-fecha-nota]');
        const turno = form.querySelector('[data-turno-texto]');
        if (fecha && turno) turno.textContent = turnoDe(fecha.value);
        const otra = form.querySelector('[data-mostrar-otra-actividad]');
        const bloqueOtra = form.querySelector('[data-otra-actividad]');
        if (otra && bloqueOtra) bloqueOtra.hidden = !otra.checked;
        form.querySelectorAll('[data-orden]').forEach(orden => {
            const noAplicada = orden.querySelector('[data-orden-estado][value="No aplicada"]')?.checked;
            const motivo = orden.querySelector('[data-orden-motivo]');
            if (motivo) motivo.hidden = !noAplicada;
        });
    };
    const alCambiar = evento => {
        const form = evento.target.closest?.('[data-editor-nota]');
        if (!form) return;
        if (evento.target.matches('[data-mostrar-otra-actividad]') && !evento.target.checked) {
            const campo = form.querySelector('[data-otra-actividad] input');
            if (campo) campo.value = '';
        }
        sincronizar(form);
    };
    document.addEventListener('change', alCambiar);
    document.addEventListener('input', alCambiar);
    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-editor-nota]').forEach(sincronizar));
})();
</script>
@endpush
@endonce
