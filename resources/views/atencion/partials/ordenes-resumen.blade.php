<p class="mt-2"><strong>Dieta:</strong> {{ $resumenOrdenes['dieta'] ?? 'Sin registro' }}</p>
@foreach($resumenOrdenes['soluciones'] ?? [] as $solucion)<p>Suero {{ $solucion['tipo'] }} · {{ $solucion['volumen'] }} ml · {{ $solucion['velocidad'] }}</p>@endforeach
@foreach($resumenOrdenes['medicamentos'] ?? [] as $medicamento)<p>{{ $medicamento['nombre'] }} · {{ $medicamento['dosis'] }} · {{ $medicamento['via'] }} · {{ $medicamento['frecuencia'] }}</p>@endforeach
<p>{{ $resumenOrdenes['indicaciones'] ?? '' }}</p>
