@php($emergenciaDocumento = $ingreso->esConsultaExterna())
<div class="bg-light border rounded p-3 mb-3">
    <strong>{{ $paciente->estado_identificacion === 'Identificado' ? trim($paciente->nombres.' '.$paciente->apellidos) : 'Paciente no identificado' }}</strong>
    <span class="d-block text-muted">{{ $paciente->numero_expediente }} · {{ $emergenciaDocumento ? 'Consulta externa del '.$ingreso->fecha_ingreso->format('d/m/Y') : 'Ingreso #'.$ingreso->getKey() }}</span>
</div>
@if($tipo === 'ingreso')
<dl class="row g-3 mb-0">
    <div class="col-md-6"><dt>{{ $emergenciaDocumento ? 'Fecha y hora de llegada' : 'Fecha de ingreso' }}</dt><dd>{{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}</dd></div>
    <div class="col-md-6"><dt>Registrado por</dt><dd>{{ $ingreso->personalRegistra?->nombres }} {{ $ingreso->personalRegistra?->apellidos }}</dd></div>
    <div class="col-12"><dt>{{ $emergenciaDocumento ? 'Motivo de consulta' : 'Motivo de ingreso' }}</dt><dd style="white-space: pre-wrap">{{ $ingreso->motivo_ingreso ?: 'Sin registro' }}</dd></div>
    <div class="col-md-6"><dt>Documento firmado</dt><dd>{{ $ingreso->documento_firmado ? 'Sí' : 'No' }}</dd></div>
    <div class="col-md-6"><dt>Estado</dt><dd>{{ $emergenciaDocumento && $ingreso->estado === 'Dado de alta' ? 'Atención finalizada' : $ingreso->estado }}</dd></div>
</dl>
@unless($emergenciaDocumento)
@include('expedientes.partials.habitaciones', ['ingreso'=>$ingreso])
@endunless
@elseif($tipo === 'nota')
<dl class="row g-3 mb-3">
    <div class="col-sm-6"><dt>Fecha y hora</dt><dd class="mb-0">{{ $nota->fecha_hora->format('d/m/Y H:i') }} · Turno {{ mb_strtolower($nota->turno()) }}</dd></div>
    <div class="col-sm-6"><dt>Enfermería</dt><dd class="mb-0">{{ $nota->personal?->nombres }} {{ $nota->personal?->apellidos }}</dd></div>
    <div class="col-12"><dt>Estado del paciente</dt><dd class="mb-0">@if($nota->estado_paciente)<span class="badge {{ $nota->claseEstado() }} fs-6">{{ $nota->estado_paciente }}</span>@else Sin registro @endif</dd></div>
</dl>
@include('atencion.partials.nota-enfermeria', ['nota'=>$nota])
@if(! $nota->signosVitales && $nota->listaActividades() === [] && blank($nota->observaciones))
<p class="text-muted mb-0">Sin detalles registrados.</p>
@endif
@elseif($tipo === 'alta')
<dl class="row g-3 mb-0">
    <div class="col-md-6"><dt>{{ $emergenciaDocumento ? 'Fecha y hora de salida' : 'Fecha de egreso' }}</dt><dd>{{ $ingreso->altaMedica->fecha_hora->format('d/m/Y H:i') }}</dd></div>
    <div class="col-md-6"><dt>Médico responsable</dt><dd>{{ $ingreso->altaMedica->medico?->nombres }} {{ $ingreso->altaMedica->medico?->apellidos }}</dd></div>
    <div class="col-12"><dt>{{ $emergenciaDocumento ? 'Diagnóstico' : 'Diagnóstico final' }}</dt><dd style="white-space: pre-wrap">{{ $ingreso->altaMedica->diagnostico_final }}</dd></div>
    @unless($emergenciaDocumento)<div class="col-12"><dt>Condición de egreso</dt><dd>{{ $ingreso->altaMedica->condicion_egreso }}</dd></div>@endunless
    @if($ingreso->altaMedica->medicamentos->isNotEmpty())
    <div class="col-12"><dt>Medicamentos para casa</dt><dd>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Medicamento</th><th>Dosis</th><th>Frecuencia</th><th>Días</th></tr></thead>
            <tbody>
                @foreach($ingreso->altaMedica->medicamentos as $medicamento)
                <tr><td class="fw-semibold text-break">{{ $medicamento->medicamento }}</td><td>{{ $medicamento->dosis }}</td><td>{{ $medicamento->frecuencia }}</td><td>{{ $medicamento->duracion ?: '—' }}</td></tr>
                @endforeach
            </tbody>
        </table></div>
    </dd></div>
    @endif
    @if($ingreso->altaMedica->indicaciones_alta)<div class="col-12"><dt>{{ $emergenciaDocumento ? 'Otras indicaciones' : 'Indicaciones de alta' }}</dt><dd style="white-space: pre-wrap">{{ $ingreso->altaMedica->indicaciones_alta }}</dd></div>@endif
    @if($ingreso->altaMedica->observaciones)<div class="col-12"><dt>Observaciones</dt><dd style="white-space: pre-wrap">{{ $ingreso->altaMedica->observaciones }}</dd></div>@endif
</dl>
@else
<div class="row g-4">
    <div class="{{ $tipo === 'evolucion' ? 'col-xl-7' : 'col-12' }}">
        <h5 class="mb-3">{{ $tipo === 'valoracion' ? ($emergenciaDocumento ? 'Consulta externa' : 'Valoración inicial') : 'Evolución clínica' }}</h5>
        <dl class="row g-3 mb-3">
            <div class="col-md-6"><dt>Fecha y hora</dt><dd>{{ $registro->fecha_hora->format('d/m/Y H:i') }}</dd></div>
            <div class="col-md-6"><dt>Médico responsable</dt><dd>{{ $registro->personal?->nombres }} {{ $registro->personal?->apellidos }}</dd></div>
            @if($tipo === 'valoracion')<div class="col-12"><dt>Motivo de consulta</dt><dd style="white-space: pre-wrap">{{ $registro->motivo_atencion }}</dd></div>@endif
            <div class="col-12"><dt>Síntomas</dt><dd style="white-space: pre-wrap">{{ $registro->sintomas ?: 'Sin registro' }}</dd></div>
        </dl>
        @include('atencion.partials.examen-fisico-resumen', ['registro'=>$registro])
        <dl class="mb-0">
            <dt>{{ $tipo === 'valoracion' ? 'Diagnóstico' : 'Diagnóstico actual' }}</dt><dd style="white-space: pre-wrap">{{ $registro->diagnostico }}</dd>
            @if($tipo === 'valoracion' && $registro->indicaciones)<dt>{{ $emergenciaDocumento ? 'Tratamiento aplicado en el sanatorio' : 'Tratamiento' }}</dt><dd style="white-space: pre-wrap">{{ $registro->indicaciones }}</dd>@endif
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
