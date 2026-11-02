@php($esConsultaExterna = $ingreso->esConsultaExterna())
@if($ingreso->altaMedica)
<div class="evolucion-card">
    <h4>{{ $esConsultaExterna ? 'Atención finalizada' : 'Alta médica registrada' }}</h4>
    <p>{{ $ingreso->altaMedica->fecha_hora->format('d/m/Y H:i') }}</p>
    <p><strong>Diagnóstico final:</strong> {{ $ingreso->altaMedica->diagnostico_final }}</p>
    <p><strong>{{ $esConsultaExterna ? 'Condición de salida' : 'Condición de egreso' }}:</strong> {{ $ingreso->altaMedica->condicion_egreso }}</p>
    @if($ingreso->altaMedica->medicamentos->isNotEmpty())
    <p class="mb-2"><strong>Medicamentos para casa:</strong></p>
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle">
            <thead><tr><th>Medicamento</th><th>Dosis</th><th>Frecuencia</th><th>Días</th></tr></thead>
            <tbody>
                @foreach($ingreso->altaMedica->medicamentos as $medicamento)
                <tr><td class="text-break">{{ $medicamento->medicamento }}</td><td>{{ $medicamento->dosis }}</td><td>{{ $medicamento->frecuencia }}</td><td>{{ $medicamento->duracion ?: '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    <p><strong>{{ $esConsultaExterna ? 'Otras indicaciones' : 'Indicaciones de alta' }}:</strong> {{ $ingreso->altaMedica->indicaciones_alta }}</p>
    <p>{{ $ingreso->altaMedica->observaciones }}</p>
</div>
@else
<div class="evolucion-card">
    <h4>{{ $esConsultaExterna ? 'Finalizar atención' : 'Alta médica' }}</h4>
    <p>
        @if($esConsultaExterna)
        Cierra la consulta externa y finaliza los tratamientos activos. Si el paciente regresa otro día, se le abre una atención nueva.
        @else
        El alta cierra el ingreso y la hospitalización, libera la habitación y finaliza los tratamientos activos de este ingreso.
        @endif
    </p>
    <form method="POST" action="{{ route('alta.guardar', $ingreso) }}" id="form-alta">
        @csrf
        <input type="hidden" name="_formulario" value="alta">
        @php($recuperarAlta = old('_formulario') === 'alta')
        <label class="form-label" for="alta-medico">{{ $esConsultaExterna ? 'Médico que atendió' : 'Médico responsable del alta' }}</label><select id="alta-medico" class="form-select mb-3" name="id_personal_medico" required><option value="">Seleccione un médico</option>@foreach($medicos as $medico)<option value="{{ $medico->id_personal }}" @selected(($recuperarAlta ? old('id_personal_medico') : null) == $medico->id_personal)>{{ $medico->nombres }} {{ $medico->apellidos }}</option>@endforeach</select>
        <label class="form-label" for="alta-fecha">{{ $esConsultaExterna ? 'Fecha y hora de salida' : 'Fecha y hora' }}</label><input id="alta-fecha" class="form-control mb-3" name="fecha_hora" type="datetime-local" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" value="{{ ($recuperarAlta ? old('fecha_hora') : now('America/Guatemala')->format('Y-m-d\TH:i')) }}" required>
        @foreach(['diagnostico_final'=>['Diagnóstico final',2000,true], 'condicion_egreso'=>[$esConsultaExterna ? 'Condición de salida' : 'Condición de egreso',150,true]] as $campo=>$config)
        <label class="form-label" for="alta-{{ $campo }}">{{ $config[0] }}</label><textarea id="alta-{{ $campo }}" class="form-control mb-3" name="{{ $campo }}" maxlength="{{ $config[1] }}" @required($config[2])>{{ $recuperarAlta ? old($campo) : '' }}</textarea>
        @endforeach

        <fieldset class="mb-3">
            <legend class="form-label fs-6 mb-1">Medicamentos para casa</legend>
            @include('atencion.partials.aviso-alergias', ['paciente' => $ingreso->paciente, 'clase' => 'mb-2'])
            <p class="text-muted small mb-2">Opcional. Agregue un medicamento por fila con su dosis, frecuencia y los días que debe tomarlo.</p>
            <div id="filas-medicamentos-casa">
                @foreach(($recuperarAlta ? array_filter((array) old('medicamentos_casa', []), 'is_array') : []) as $indice => $fila)
                @include('atencion.partials.fila-medicamento-casa', ['indice' => $indice, 'fila' => $fila])
                @endforeach
            </div>
            <button type="button" class="btn btn-outline-primary" data-agregar-medicamento-casa><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar medicamento</button>
            <template id="plantilla-medicamento-casa">@include('atencion.partials.fila-medicamento-casa', ['indice' => '__INDEX__', 'fila' => []])</template>
        </fieldset>

        @foreach(['indicaciones_alta'=>[$esConsultaExterna ? 'Otras indicaciones (ej. reposo, no correr)' : 'Indicaciones de alta',2000,false], 'observaciones'=>['Observaciones',2000,false]] as $campo=>$config)
        <label class="form-label" for="alta-{{ $campo }}">{{ $config[0] }}</label><textarea id="alta-{{ $campo }}" class="form-control mb-3" name="{{ $campo }}" maxlength="{{ $config[1] }}" @required($config[2])>{{ $recuperarAlta ? old($campo) : '' }}</textarea>
        @endforeach
        <button class="btn btn-success">{{ $esConsultaExterna ? 'Finalizar atención' : 'Registrar alta médica' }}</button>
    </form>
</div>
<script>
(() => {
    const form = document.getElementById('form-alta');
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
})();
</script>
@endif

@include('archivos.adjuntos',['tipoHojaArchivo'=>'Alta médica','referenciaArchivo'=>$ingreso->altaMedica?->getKey()])
