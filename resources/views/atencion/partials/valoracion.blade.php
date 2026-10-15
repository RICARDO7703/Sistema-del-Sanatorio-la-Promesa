@php($valoracionInicial = $ingreso->evoluciones->first())
<div class="evolucion-card">
    <h4 class="mb-4">{{ $valoracionInicial ? 'Valoración inicial' : 'Registrar valoración inicial' }}</h4>
    @if(!$valoracionInicial)
    <form method="POST" action="{{ route('evolucion.guardar') }}">
        @csrf
        @include('atencion.partials.formulario-evolucion', ['registro'=>null, 'formulario'=>'valoracion'])
        <button class="btn btn-primary">Guardar valoración</button>
    </form>
    @else
    <div id="resumen-valoracion" @if(old('_formulario') === 'valoracion-editar') hidden @endif>
    <p><strong>Fecha:</strong> {{ $valoracionInicial->fecha_hora->format('d/m/Y H:i') }}</p>
    <p><strong>Motivo de consulta:</strong> {{ $valoracionInicial->motivo_atencion }}</p>
    <p><strong>Signos y síntomas:</strong> {{ $valoracionInicial->sintomas ?: 'Sin registro' }}</p>
    @include('atencion.partials.examen-fisico-resumen', ['registro'=>$valoracionInicial])
    <p><strong>Diagnóstico:</strong> {{ $valoracionInicial->diagnostico }}</p>
    <p><strong>Tratamiento:</strong> {{ $valoracionInicial->indicaciones }}</p>
    <p><strong>Observaciones:</strong> {{ $valoracionInicial->observaciones }}</p>
    <button type="button" class="btn btn-outline-primary" data-editar-valoracion><i class="bi bi-pencil"></i> Editar valoración inicial</button>
    </div>
    <div id="editor-valoracion" @if(old('_formulario') !== 'valoracion-editar') hidden @endif>
        <form method="POST" action="{{ route('evolucion.actualizar', $valoracionInicial) }}">
            @csrf @method('PUT')
            @include('atencion.partials.formulario-evolucion', ['registro'=>$valoracionInicial, 'formulario'=>'valoracion-editar'])
            <div class="d-flex gap-2"><button class="btn btn-primary">Guardar cambios</button><button type="button" class="btn btn-outline-secondary" data-cancelar-valoracion>Cancelar</button></div>
        </form>
    </div>
    @endif
</div>
