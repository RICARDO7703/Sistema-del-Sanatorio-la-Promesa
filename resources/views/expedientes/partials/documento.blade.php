<div class="bg-light border rounded p-3 mb-3">
    <strong>{{ $paciente->estado_identificacion === 'Identificado' ? trim($paciente->nombres.' '.$paciente->apellidos) : 'Paciente no identificado' }}</strong>
    <span class="d-block text-muted">{{ $paciente->numero_expediente }} · Ingreso #{{ $ingreso->getKey() }}</span>
</div>
@if($tipo === 'ingreso')
<dl class="row g-3 mb-0">
    <div class="col-md-6"><dt>Fecha de ingreso</dt><dd>{{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}</dd></div>
    <div class="col-md-6"><dt>Registrado por</dt><dd>{{ $ingreso->personalRegistra?->nombres }} {{ $ingreso->personalRegistra?->apellidos }}</dd></div>
    <div class="col-12"><dt>Motivo de ingreso</dt><dd style="white-space: pre-wrap">{{ $ingreso->motivo_ingreso ?: 'Sin registro' }}</dd></div>
    <div class="col-md-6"><dt>Documento firmado</dt><dd>{{ $ingreso->documento_firmado ? 'Sí' : 'No' }}</dd></div>
    <div class="col-md-6"><dt>Estado</dt><dd>{{ $ingreso->estado }}</dd></div>
</dl>
@include('expedientes.partials.habitaciones', ['ingreso'=>$ingreso])
@elseif($tipo === 'alta')
<dl class="row g-3 mb-0">
    <div class="col-md-6"><dt>Fecha de egreso</dt><dd>{{ $ingreso->altaMedica->fecha_hora->format('d/m/Y H:i') }}</dd></div>
    <div class="col-md-6"><dt>Médico responsable</dt><dd>{{ $ingreso->altaMedica->medico?->nombres }} {{ $ingreso->altaMedica->medico?->apellidos }}</dd></div>
    <div class="col-12"><dt>Diagnóstico final</dt><dd style="white-space: pre-wrap">{{ $ingreso->altaMedica->diagnostico_final }}</dd></div>
    <div class="col-12"><dt>Condición de egreso</dt><dd>{{ $ingreso->altaMedica->condicion_egreso }}</dd></div>
    @if($ingreso->altaMedica->indicaciones_alta)<div class="col-12"><dt>Indicaciones de alta</dt><dd style="white-space: pre-wrap">{{ $ingreso->altaMedica->indicaciones_alta }}</dd></div>@endif
    @if($ingreso->altaMedica->observaciones)<div class="col-12"><dt>Observaciones</dt><dd style="white-space: pre-wrap">{{ $ingreso->altaMedica->observaciones }}</dd></div>@endif
</dl>
@else
<div class="row g-4">
    <div class="{{ $tipo === 'evolucion' ? 'col-xl-7' : 'col-12' }}">
        <h5 class="mb-3">{{ $tipo === 'valoracion' ? 'Valoración inicial' : 'Evolución clínica' }}</h5>
        <dl class="row g-3 mb-3">
            <div class="col-md-6"><dt>Fecha y hora</dt><dd>{{ $registro->fecha_hora->format('d/m/Y H:i') }}</dd></div>
            <div class="col-md-6"><dt>Médico responsable</dt><dd>{{ $registro->personal?->nombres }} {{ $registro->personal?->apellidos }}</dd></div>
            @if($tipo === 'valoracion')<div class="col-12"><dt>Motivo de consulta</dt><dd style="white-space: pre-wrap">{{ $registro->motivo_atencion }}</dd></div>@endif
            <div class="col-12"><dt>Signos y síntomas</dt><dd style="white-space: pre-wrap">{{ $registro->sintomas ?: 'Sin registro' }}</dd></div>
        </dl>
        @include('atencion.partials.examen-fisico-resumen', ['registro'=>$registro])
        <dl class="mb-0">
            <dt>{{ $tipo === 'valoracion' ? 'Diagnóstico' : 'Diagnóstico actual' }}</dt><dd style="white-space: pre-wrap">{{ $registro->diagnostico }}</dd>
            @if($tipo === 'valoracion' && $registro->indicaciones)<dt>Tratamiento</dt><dd style="white-space: pre-wrap">{{ $registro->indicaciones }}</dd>@endif
            @if($registro->observaciones)<dt>Observaciones</dt><dd style="white-space: pre-wrap">{{ $registro->observaciones }}</dd>@endif
            @if($tipo === 'evolucion')<dt>Evolución del paciente</dt><dd>{{ $registro->estado_mejoria ?: 'Sin registro' }}</dd>@endif
        </dl>
    </div>
    @if($tipo === 'evolucion')
    <div class="col-xl-5">
        <div class="border rounded p-3">
            <h5>Órdenes médicas</h5>
            @if($registro->ordenes_medicas)
                @include('atencion.partials.ordenes-resumen', ['resumenOrdenes'=>$registro->ordenes_medicas])
            @else
                <p class="mb-0" style="white-space: pre-wrap">{{ $registro->indicaciones ?: 'Sin órdenes registradas' }}</p>
            @endif
        </div>
    </div>
    @endif
</div>
@endif
