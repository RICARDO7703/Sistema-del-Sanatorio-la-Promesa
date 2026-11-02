{{-- Órdenes médicas de una valoración o evolución, ordenadas para leerlas de un vistazo. Variable: $ordenes --}}
<div class="doc-orden">
    <span class="doc-orden-etiqueta"><i class="bi bi-cup-straw" aria-hidden="true"></i> Dieta</span>
    <span class="doc-pastilla doc-pastilla-primaria">{{ $ordenes['dieta'] ?? 'Sin registro' }}</span>
</div>

<div class="doc-orden">
    <span class="doc-orden-etiqueta"><i class="bi bi-droplet-half" aria-hidden="true"></i> Solución</span>
    @forelse($ordenes['soluciones'] ?? [] as $solucion)
    <p class="mb-0"><strong>Suero {{ $solucion['tipo'] }}</strong> <span class="doc-chips doc-chips-compactos"><span>{{ $solucion['volumen'] }} ml</span><span>{{ $solucion['velocidad'] }}{{ isset($solucion['unidad']) ? ' '.$solucion['unidad'] : '' }}</span></span></p>
    @empty
    <p class="doc-vacio mb-0">Sin solución indicada.</p>
    @endforelse
</div>

<div class="doc-orden">
    <span class="doc-orden-etiqueta"><i class="bi bi-capsule" aria-hidden="true"></i> Medicamentos</span>
    @if(! empty($ordenes['medicamentos']))
    <ul class="doc-lista-medicamentos">
        @foreach($ordenes['medicamentos'] as $medicamento)
        <li>
            <strong>{{ $medicamento['nombre'] }}</strong>
            <span class="doc-chips doc-chips-compactos">
                <span>{{ is_numeric($medicamento['dosis']) ? $medicamento['dosis'].' mg' : $medicamento['dosis'] }}</span>
                <span>{{ $medicamento['via'] }}</span>
                <span>{{ is_numeric($medicamento['frecuencia']) ? 'cada '.$medicamento['frecuencia'].' h' : $medicamento['frecuencia'] }}</span>
            </span>
        </li>
        @endforeach
    </ul>
    @else
    <p class="doc-vacio mb-0">Sin medicamentos indicados.</p>
    @endif
</div>

@if(filled($ordenes['indicaciones'] ?? null))
<div class="doc-orden">
    <span class="doc-orden-etiqueta"><i class="bi bi-card-checklist" aria-hidden="true"></i> Indicaciones para enfermería</span>
    <p class="doc-texto mb-0">{{ $ordenes['indicaciones'] }}</p>
</div>
@endif

@if(filled($ordenes['plan_educacional'] ?? null))
<div class="doc-orden">
    <span class="doc-orden-etiqueta"><i class="bi bi-lightbulb" aria-hidden="true"></i> Plan educacional</span>
    <p class="doc-texto mb-0">{{ $ordenes['plan_educacional'] }}</p>
</div>
@endif
