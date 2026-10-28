<aside id="historial-consultas" class="evolucion-card historial-consultas" hidden aria-label="Consultas anteriores">
    <div class="d-flex justify-content-between align-items-start gap-2"><div><h2 class="h4">Consultas anteriores</h2><p class="text-muted">{{ $consultasAnteriores->count() }} atenciones · Solo lectura</p></div><button type="button" class="btn-close" data-cerrar-consultas aria-label="Cerrar consultas anteriores"></button></div>
    @foreach($consultasAnteriores as $previa)
    <details class="border-top py-3">
        <summary class="resumen-consulta-previa"><strong>{{ $previa->fecha_ingreso->format('d/m/Y H:i') }}</strong><span class="d-block text-break">{{ $previa->altaMedica?->diagnostico_final ?: ($previa->evoluciones->last()?->diagnostico ?: 'Sin diagnóstico registrado') }}</span>@if($loop->first)<span class="badge bg-primary mt-2">Última consulta</span>@endif @if($previa->getKey() === $ingreso->id_ingreso_anterior)<span class="badge bg-light text-dark border mt-2">Referencia del seguimiento</span>@endif</summary>
        <div class="pt-3">
            <p class="text-muted">{{ $previa->nombreTipoAtencion() }} · {{ $previa->estado }}</p>
            <dl class="mb-0"><dt>Motivo de consulta</dt><dd class="text-break">{{ $previa->motivo_ingreso ?: 'Sin registro' }}</dd>
            @foreach($previa->evoluciones as $registro)
            <dt>Valoración del {{ $registro->fecha_hora->format('d/m/Y H:i') }}</dt><dd class="text-break">{{ $registro->sintomas ?: 'Sin síntomas registrados' }}</dd>
            <dt>Diagnóstico</dt><dd class="text-break">{{ $registro->diagnostico ?: 'Sin registro' }}</dd>
            <dt>Tratamiento aplicado</dt><dd class="text-break">{{ $registro->indicaciones ?: 'Sin registro' }}</dd>
            @if($registro->examen_fisico || $registro->evaluacion_medica)<dd>@include('atencion.partials.examen-fisico-resumen', ['registro' => $registro])</dd>@endif
            @endforeach
            <dt>Indicaciones al alta</dt><dd class="text-break">{{ $previa->altaMedica?->indicaciones_alta ?: 'Sin indicaciones registradas' }}</dd>
            @if($previa->altaMedica?->medicamentos->isNotEmpty())<dt>Medicamentos para casa</dt><dd><ul class="ps-3">@foreach($previa->altaMedica->medicamentos as $medicamento)<li class="text-break">{{ $medicamento->medicamento }} · {{ $medicamento->dosis }} · {{ $medicamento->frecuencia }}@if($medicamento->duracion) · {{ $medicamento->duracion }}@endif</li>@endforeach</ul></dd>@endif
            </dl>
            <a href="{{ route('atencion.show', $previa) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">Abrir atención completa <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
        </div>
    </details>
    @endforeach
</aside>
