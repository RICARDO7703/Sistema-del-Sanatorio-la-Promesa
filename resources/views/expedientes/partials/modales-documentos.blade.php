@foreach($ingresos as $ingresoDocumento)
<div class="modal fade" id="documento-ingreso-{{ $ingresoDocumento->getKey() }}" tabindex="-1" aria-labelledby="titulo-documento-ingreso-{{ $ingresoDocumento->getKey() }}" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="titulo-documento-ingreso-{{ $ingresoDocumento->getKey() }}">{{ $ingresoDocumento->esConsultaExterna() ? 'Hoja de llegada' : 'Hoja de ingreso' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">@include('expedientes.partials.documento', ['tipo'=>'ingreso', 'ingreso'=>$ingresoDocumento])</div>
        <div class="modal-footer">
            @if(\App\Support\EdicionClinica::puedeEditar($ingresoDocumento) && app(\App\Support\Permisos::class)->puede('Ingresos', 'Editar'))
            <span class="small text-muted me-auto">Puede editarla hasta el {{ \App\Support\EdicionClinica::limiteLocal($ingresoDocumento)->format('d/m/Y') }} a las {{ \App\Support\EdicionClinica::limiteLocal($ingresoDocumento)->format('H:i') }}</span>
            <button type="button" class="btn btn-primary" data-editar-ingreso-enlinea="editor-ingreso-{{ $ingresoDocumento->getKey() }}"><i class="bi bi-pencil"></i> Editar fecha o motivo</button>
            @endif
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
    </div></div>
</div>
@foreach($ingresoDocumento->evoluciones as $registroDocumento)
<div class="modal fade" id="documento-evolucion-{{ $registroDocumento->getKey() }}" tabindex="-1" aria-labelledby="titulo-documento-evolucion-{{ $registroDocumento->getKey() }}" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="titulo-documento-evolucion-{{ $registroDocumento->getKey() }}">{{ $loop->first && $registroDocumento->estado_mejoria === null ? (filled($registroDocumento->ordenes_medicas) ? 'Valoración inicial con orden médica' : 'Valoración inicial') : 'Evolución clínica y órdenes médicas' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">@include('expedientes.partials.documento', ['tipo'=>$loop->first && $registroDocumento->estado_mejoria === null ? 'valoracion' : 'evolucion', 'ingreso'=>$ingresoDocumento, 'registro'=>$registroDocumento])</div>
        <div class="modal-footer">
            @if(\App\Support\EdicionClinica::puedeEditar($registroDocumento->setRelation('ingreso', $ingresoDocumento)) && app(\App\Support\Permisos::class)->puede('Evolución clínica', 'Editar'))
            <span class="small text-muted me-auto">Puede editarla hasta el {{ \App\Support\EdicionClinica::limiteLocal($registroDocumento)->format('d/m/Y') }} a las {{ \App\Support\EdicionClinica::limiteLocal($registroDocumento)->format('H:i') }}</span>
            <a class="btn btn-primary" data-editar-documento href="{{ $ingresoDocumento->esConsultaExterna() ? route('atencion.show', $ingresoDocumento) : ($loop->first && $registroDocumento->estado_mejoria === null ? route('atencion.show', ['ingreso' => $ingresoDocumento->getKey(), 'editar' => 'valoracion']) : route('atencion.show', ['ingreso' => $ingresoDocumento->getKey(), 'registro' => $registroDocumento->getKey()]).'#evoluciones') }}"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
            @endif
            {{-- Al cancelar o reactivar las órdenes, «Editar» se cambia por «Confirmar cambios». --}}
            <button type="button" class="btn btn-primary" form="ordenes-doc-{{ $registroDocumento->getKey() }}" data-confirmar-ordenes="ordenes-doc-{{ $registroDocumento->getKey() }}" hidden><i class="bi bi-check-lg" aria-hidden="true"></i> Confirmar cambios</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
    </div></div>
</div>
@endforeach
@foreach($ingresoDocumento->notasEnfermeria as $notaDocumento)
<div class="modal fade" id="documento-nota-{{ $notaDocumento->getKey() }}" tabindex="-1" aria-labelledby="titulo-documento-nota-{{ $notaDocumento->getKey() }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="titulo-documento-nota-{{ $notaDocumento->getKey() }}">Nota de enfermería #{{ $loop->iteration }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">@include('expedientes.partials.documento', ['tipo'=>'nota', 'ingreso'=>$ingresoDocumento, 'nota'=>$notaDocumento])</div>
        <div class="modal-footer">@if($notaDocumento->puedeEditar((int)session('id_usuario')) && app(\App\Support\Permisos::class)->puede('Enfermería','Editar'))<span class="small text-muted me-auto">Puede editarla hasta el {{ \App\Support\EdicionClinica::limiteLocal($notaDocumento)->format('d/m/Y') }} a las {{ \App\Support\EdicionClinica::limiteLocal($notaDocumento)->format('H:i') }}</span><button type="button" class="btn btn-primary" data-editar-nota="editar-nota-{{ $notaDocumento->getKey() }}"><i class="bi bi-pencil"></i> Editar</button>@else<span class="small text-muted me-auto"><i class="bi bi-lock" aria-hidden="true"></i> Solo lectura. Solo quien la registró puede corregirla, durante las primeras 12 horas.</span>@endif<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
    </div></div>
</div>
@endforeach
@foreach($ingresoDocumento->controlesSinNota as $controlDocumento)
<div class="modal fade" id="documento-control-{{ $controlDocumento->getKey() }}" tabindex="-1" aria-labelledby="titulo-documento-control-{{ $controlDocumento->getKey() }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="titulo-documento-control-{{ $controlDocumento->getKey() }}">Control de recuperación</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">@include('expedientes.partials.documento', ['tipo'=>'control', 'ingreso'=>$ingresoDocumento, 'control'=>$controlDocumento])</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
    </div></div>
</div>
@endforeach
@if($ingresoDocumento->altaMedica)
<div class="modal fade" id="documento-alta-{{ $ingresoDocumento->getKey() }}" tabindex="-1" aria-labelledby="titulo-documento-alta-{{ $ingresoDocumento->getKey() }}" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="titulo-documento-alta-{{ $ingresoDocumento->getKey() }}">{{ $ingresoDocumento->esConsultaExterna() ? 'Alta e indicaciones para casa' : 'Alta médica' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">@include('expedientes.partials.documento', ['tipo'=>'alta', 'ingreso'=>$ingresoDocumento])</div>
        <div class="modal-footer">@puede('Ingresos', 'Ver')<a class="btn btn-outline-primary" href="{{ route('alta.receta', [$ingresoDocumento->altaMedica, 'imprimir' => 1]) }}" target="_blank" rel="noopener"><i class="bi bi-printer" aria-hidden="true"></i> Imprimir o descargar receta</a>@endpuede<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
    </div></div>
</div>
@endif
@endforeach
