<?php
    $cirugiasDelAlta = \App\Models\Cirugia::where('id_ingreso', $ingreso->getKey())->whereIn('estado', array_merge(\App\Services\CirugiasAlAlta::PENDIENTES, ['En cirugía']))->get();
    $cirugiaEnCursoAlta = $cirugiasDelAlta->contains('estado', 'En cirugía');
?>
@if($cirugiasDelAlta->isNotEmpty())
<div class="alert {{ $cirugiaEnCursoAlta ? 'alert-danger' : 'alert-warning' }} mb-3" role="alert">
    <strong><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>{{ $cirugiaEnCursoAlta ? 'Cirugía en curso' : 'El paciente tiene cirugías pendientes' }}</strong>
    <ul class="mt-2 mb-2">@foreach($cirugiasDelAlta as $cirugiaDelAlta)<li>{{ $cirugiaDelAlta->tipo_operacion }} · {{ $cirugiaDelAlta->estado }}@if($cirugiaDelAlta->fecha_programada) · {{ $cirugiaDelAlta->fecha_programada->format('d/m/Y H:i') }}@endif</li>@endforeach</ul>
    @if($cirugiaEnCursoAlta)
    <p class="mb-0">Para dar el alta, primero registre la finalización de la cirugía.</p>
    @elseif($soloAvisoCirugias ?? false)
    <p class="mb-0">Al dar de alta podrá mantener la programación o cancelar las cirugías pendientes.</p>
    @else
    <p>Si registra el alta, se cancelarán estas cirugías y sus asignaciones de personal. La cancelación quedará en el expediente.</p>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="cancelar_cirugias" id="alta-cancelar-cirugias" value="1"><label class="form-check-label fw-semibold" for="alta-cancelar-cirugias">Confirmo dar de alta y cancelar las cirugías pendientes.</label></div>
    @endif
    @error('cancelar_cirugias')<p class="text-danger mt-2 mb-0">{{ $message }}</p>@enderror
</div>
@endif
