@php($valoracionInicial = $ingreso->evoluciones->first())
<div class="{{ $valoracionInicial ? 'evolucion-card' : 'valoracion-formulario' }}">
    @php($nombreValoracion = 'valoración inicial')
    <h4 class="{{ $valoracionInicial ? 'mb-4' : 'bg-primary text-white p-3 rounded-top mb-0 fs-6 fw-normal' }}">{{ $valoracionInicial ? ucfirst($nombreValoracion) : 'Registrar '.$nombreValoracion }}</h4>
    @if(!$valoracionInicial)
    <form method="POST" action="{{ route('evolucion.guardar') }}" class="border rounded-bottom p-3" data-validar-en-linea data-validar-al-enviar>
        @csrf
        @include('atencion.partials.formulario-evolucion', ['registro'=>null, 'formulario'=>'valoracion'])
        <button class="btn btn-primary w-100 mt-4"><i class="bi bi-check-lg" aria-hidden="true"></i> Guardar valoración</button>
    </form>
    @else
    @php($puedeEditarValoracion = \App\Support\EdicionClinica::puedeEditar($valoracionInicial) && app(\App\Support\Permisos::class)->puede('Evolución clínica', 'Editar'))
    @php($abrirEditorValoracion = $puedeEditarValoracion && (old('_formulario') === 'valoracion-editar' || request('editar') === 'valoracion'))
    <div id="resumen-valoracion" @if($abrirEditorValoracion) hidden @endif>
    <p><strong>Fecha:</strong> {{ $valoracionInicial->fecha_hora->format('d/m/Y H:i') }}</p>
    <p><strong>Motivo de consulta:</strong> {{ $valoracionInicial->motivo_atencion }}</p>
    <p><strong>Síntomas:</strong> {{ $valoracionInicial->sintomas ?: 'Sin registro' }}</p>
    @include('atencion.partials.examen-fisico-resumen', ['registro'=>$valoracionInicial])
    <p><strong>Diagnóstico:</strong> {{ $valoracionInicial->diagnostico }}</p>
    <p><strong>Tratamiento:</strong> {{ $valoracionInicial->indicaciones }}</p>
    <p><strong>Observaciones:</strong> {{ $valoracionInicial->observaciones }}</p>
    @if($valoracionInicial->ordenes_medicas)<div class="border rounded p-2 mb-3"><strong>Órdenes médicas</strong>@include('atencion.partials.ordenes-resumen', ['resumenOrdenes'=>$valoracionInicial->ordenes_medicas])</div>@endif
    @if($puedeEditarValoracion)
    <button type="button" class="btn btn-outline-primary" data-editar-valoracion><i class="bi bi-pencil"></i> Editar {{ $nombreValoracion }}</button>
    <span class="d-block small text-muted mt-1" data-limite-valoracion>Puede editarla hasta el {{ \App\Support\EdicionClinica::limiteLocal($valoracionInicial)?->format('d/m/Y \a \l\a\s H:i') }}.</span>
    @elseif($ingreso->estado === 'Activo')
    <p class="small text-muted mb-0"><i class="bi bi-lock" aria-hidden="true"></i> {{ \App\Support\EdicionClinica::MENSAJE }}</p>
    @endif
    </div>
    @if($puedeEditarValoracion)
    <div id="editor-valoracion" @if(! $abrirEditorValoracion) hidden @endif>
        <form method="POST" action="{{ route('evolucion.actualizar', $valoracionInicial) }}">
            @csrf @method('PUT')
            @include('atencion.partials.formulario-evolucion', ['registro'=>$valoracionInicial, 'formulario'=>'valoracion-editar'])
            <button type="submit" hidden></button>
        </form>
    </div>
    @endif
    @endif
</div>

@if($valoracionInicial)
@include('archivos.adjuntos',['tipoHojaArchivo'=>$ingreso->esConsultaExterna()?'Consulta externa':'Valoración inicial','referenciaArchivo'=>$valoracionInicial->getKey(),'fechaHojaArchivo'=>$valoracionInicial->fecha_hora])
@endif
