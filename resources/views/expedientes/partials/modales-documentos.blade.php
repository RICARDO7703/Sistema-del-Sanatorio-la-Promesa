@foreach($ingresos as $ingresoDocumento)
<div class="modal fade" id="documento-ingreso-{{ $ingresoDocumento->getKey() }}" tabindex="-1" aria-labelledby="titulo-documento-ingreso-{{ $ingresoDocumento->getKey() }}" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="titulo-documento-ingreso-{{ $ingresoDocumento->getKey() }}">Hoja de ingreso</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">@include('expedientes.partials.documento', ['tipo'=>'ingreso', 'ingreso'=>$ingresoDocumento])</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
    </div></div>
</div>
@foreach($ingresoDocumento->evoluciones as $registroDocumento)
<div class="modal fade" id="documento-evolucion-{{ $registroDocumento->getKey() }}" tabindex="-1" aria-labelledby="titulo-documento-evolucion-{{ $registroDocumento->getKey() }}" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="titulo-documento-evolucion-{{ $registroDocumento->getKey() }}">{{ $loop->first && $registroDocumento->ordenes_medicas === null ? 'Valoración inicial' : 'Evolución clínica y órdenes médicas' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">@include('expedientes.partials.documento', ['tipo'=>$loop->first && $registroDocumento->ordenes_medicas === null ? 'valoracion' : 'evolucion', 'ingreso'=>$ingresoDocumento, 'registro'=>$registroDocumento])</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
    </div></div>
</div>
@endforeach
@if($ingresoDocumento->altaMedica)
<div class="modal fade" id="documento-alta-{{ $ingresoDocumento->getKey() }}" tabindex="-1" aria-labelledby="titulo-documento-alta-{{ $ingresoDocumento->getKey() }}" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="titulo-documento-alta-{{ $ingresoDocumento->getKey() }}">Alta médica</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">@include('expedientes.partials.documento', ['tipo'=>'alta', 'ingreso'=>$ingresoDocumento])</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
    </div></div>
</div>
@endif
@endforeach
