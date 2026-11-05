@if(\App\Services\OrdenesMedicas::cancelacion($resumenOrdenes))<p class="mt-2 mb-0"><span class="badge bg-danger">Canceladas</span> {{ \App\Services\OrdenesMedicas::cancelacion($resumenOrdenes)['motivo'] }}</p>@endif
<p class="mt-2"><strong>Dieta:</strong> {{ $resumenOrdenes['dieta'] ?? 'Sin registro' }}</p>
@foreach($resumenOrdenes['soluciones'] ?? [] as $solucion)<p>Suero {{ $solucion['tipo'] }} · {{ $solucion['volumen'] }} ml · {{ $solucion['velocidad'] }}{{ isset($solucion['unidad']) ? ' '.$solucion['unidad'] : '' }}</p>@endforeach
@foreach($resumenOrdenes['medicamentos'] ?? [] as $medicamento)<p>{{ $medicamento['nombre'] }} · {{ is_numeric($medicamento['dosis']) ? $medicamento['dosis'].' mg' : $medicamento['dosis'] }} · {{ $medicamento['via'] }} · {{ is_numeric($medicamento['frecuencia']) ? 'cada '.$medicamento['frecuencia'].' horas' : $medicamento['frecuencia'] }}</p>@endforeach
<p>{{ $resumenOrdenes['indicaciones'] ?? '' }}</p>
@if(filled($resumenOrdenes['plan_educacional'] ?? null))<p><strong>Plan educacional:</strong> {{ $resumenOrdenes['plan_educacional'] }}</p>@endif
