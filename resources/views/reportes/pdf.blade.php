<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
@page { margin: 108px 34px 52px; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #18253a; line-height: 1.45; }
.membrete { position: fixed; top: -85px; left: 0; right: 0; height: 68px; border-bottom: 2px solid #ad1739; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.membrete td { border: 0; padding: 0 0 10px; vertical-align: middle; }
.logo { width: 165px; height: auto; }
.marca { text-align: center; }
.marca strong { font-size: 16px; color: #ad1739; }
.marca span { font-size: 9px; color: #526077; }
.contacto { text-align: right; font-size: 8px; color: #526077; }
h1 { font-size: 16px; line-height: 1.25; color: #18253a; margin: 0 0 12px; text-transform: uppercase; }
h2 { font-size: 11px; color: #0755b8; margin: 16px 0 8px; padding: 7px 10px; background: #eaf2ff; border-left: 3px solid #ad1739; page-break-after: avoid; }
.metadatos { background: #f3f6fb; border: 1px solid #d5deeb; border-radius: 5px; padding: 8px 10px; margin-bottom: 12px; }
.metadatos td { border: 0; padding: 2px 8px 2px 0; font-size: 8px; }
.identidad { border: 1px solid #d5deeb; border-left: 3px solid #1765cb; padding: 10px 12px; margin: 0 0 14px; background: #f7faff; }
.identidad strong { font-size: 12px; }
.identidad td { border: 0; padding: 2px 0; }
.meta { color: #526077; font-size: 8px; }
.listado th { background: #eaf2ff; color: #074eaa; text-transform: uppercase; font-size: 8px; text-align: left; }
.listado td, .listado th { border: 1px solid #d5deeb; padding: 6px; vertical-align: top; overflow-wrap: break-word; }
.listado tbody tr:nth-child(even) { background: #f5f7fa; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
.total { background: #eaf2ff; font-weight: bold; padding: 7px; border: 1px solid #d5deeb; border-top: 0; }
.estado { display: inline-block; font-size: 7px; font-weight: bold; border-radius: 4px; padding: 3px 5px; background: #edf1f5; color: #42536a; }
.estado-verde { background: #e5f3eb; color: #176544; }
.estado-rojo { background: #fce8ed; color: #a21636; }
.estado-azul { background: #eaf2ff; color: #0755b8; }
.etiqueta { font-weight: bold; font-size: 9px; color: #42536a; margin: 8px 0 3px; page-break-after: avoid; }
.valor { white-space: pre-wrap; overflow-wrap: break-word; margin: 0 0 9px; }
.pie { position: fixed; bottom: -33px; left: 0; right: 0; height: 22px; border-top: 1px solid #ad1739; padding-top: 7px; font-size: 7px; color: #526077; }
.detalle-auditoria { font-size: 8px; color: #526077; margin-top: 4px; white-space: pre-wrap; }
</style>
</head>
<body>
<header class="membrete">
<table><tr>
<td style="width:25%"><img class="logo" src="{{ $logo }}" alt="Sanatorio La Promesa"></td>
<td class="marca" style="width:49%"><strong>SANATORIO LA PROMESA</strong><br><span>Sistema de Información Clínica</span></td>
<td class="contacto" style="width:26%">Teléfono: 7830-5174<br>Farmacia: 5718-6325</td>
</tr></table>
</header>
<footer class="pie">Sanatorio La Promesa · {{ now('America/Guatemala')->year }} · Documento confidencial</footer>
<h1>{{ $titulo }}</h1>
<div class="metadatos">
<table><tr><td><strong>Fecha de emisión:</strong> {{ $fechaGeneracion }} (Guatemala)</td><td><strong>Generado por:</strong> {{ $generadoPor }}</td></tr>
@foreach(array_chunk($filtros, 2, true) as $grupo)<tr>@foreach($grupo as $nombre=>$valor)<td><strong>{{ $nombre }}:</strong> {{ $valor }}</td>@endforeach</tr>@endforeach
@if(!$documento)<tr><td colspan="2"><strong>{{ count($filas) }} {{ isset($filas[0]['indicador']) ? 'indicadores' : 'registros' }}</strong></td></tr>@endif
</table>
</div>
@if($documento)
<div class="identidad"><strong>{{ $documento['paciente'] }}</strong>
<table><tr><td>Expediente: <b>{{ $documento['expediente'] }}</b></td><td>Registro: #{{ $documento['id'] }}</td></tr>
<tr><td>{{ $documento['etiqueta_fecha'] ?? 'Fecha de la hoja' }}: {{ $documento['fecha'] }}</td><td>Registrado por: <b>{{ $documento['responsable'] }}</b></td></tr></table>
</div>
@if(isset($documento['imagen']))<div style="text-align:center;page-break-inside:avoid"><img src="{{ $documento['imagen'] }}" style="max-width:100%;max-height:570px" alt="Hoja escaneada"></div>@endif
@foreach($documento['secciones'] as $seccion=>$campos)
<h2>{{ $seccion }}</h2>
@foreach($campos as $etiqueta=>$valor)<div class="etiqueta">{{ $etiqueta }}</div><div class="valor">{{ filled($valor) ? $valor : 'Sin registro' }}</div>@endforeach
@endforeach
@elseif(isset($filas[0]['bitacora']))
<table class="listado"><thead><tr><th style="width:3%">#</th><th style="width:14%">Fecha y hora</th><th style="width:17%">Usuario</th><th style="width:17%">Acción / resultado</th><th style="width:18%">Registro</th><th>Detalle</th></tr></thead><tbody>
@foreach($filas as $fila)<tr><td>{{ $loop->iteration }}</td><td>{{ $fila['fecha'] }}</td><td>{{ $fila['usuario'] }}<div class="meta">Nombre intentado: {{ $fila['intentado'] }}</div></td><td>{{ $fila['accion'] }}<br><span class="estado">{{ $fila['resultado'] }}</span></td><td>{{ $fila['registro'] }}</td><td><div class="valor">{{ $fila['detalle'] }}</div><div class="detalle-auditoria">IP: {{ $fila['ip'] ?: 'Sin registro' }}<br>Navegador: {{ $fila['navegador'] ?: 'Sin registro' }}<br>Sistema: {{ $fila['sistema'] ?: 'Sin registro' }}</div></td></tr>@endforeach
</tbody></table><div class="total">TOTAL DE REGISTROS: {{ count($filas) }}</div>
<p class="meta">El nombre intentado no acredita la identidad de quien intentó ingresar.</p>
@elseif(isset($filas[0]['indicador']))
<table class="listado"><thead><tr><th style="width:5%">#</th><th>Indicador</th><th style="width:20%">Cantidad</th></tr></thead><tbody>
@foreach($filas as $fila)<tr><td>{{ $loop->iteration }}</td><td>{{ $fila['indicador'] }}</td><td><strong>{{ $fila['cantidad'] }}</strong></td></tr>@endforeach
</tbody></table><div class="total">TOTAL DE INDICADORES: {{ count($filas) }}</div>
@else
<table class="listado"><thead><tr><th style="width:3%">#</th><th style="width:12%">Fecha y hora</th><th style="width:23%">Paciente / expediente</th><th style="width:18%">Responsable</th><th style="width:13%">Estado</th><th>Resumen</th></tr></thead><tbody>
@forelse($filas as $fila)
@php
    $estado = $fila['estado'] ?? 'Sin registro';
    $color = match ($estado) {
        'Activo', 'Activa', 'Realizada', 'Mejoró', 'Dado de alta' => 'verde',
        'Cancelada', 'Cancelado', 'Inactivo', 'Empeoró', 'En cirugía' => 'rojo',
        'Programada', 'Hospitalizado', 'Evaluación preoperatoria' => 'azul',
        default => '',
    };
@endphp
<tr><td>{{ $loop->iteration }}</td><td>{{ $fila['fecha'] }}</td><td><strong>{{ $fila['paciente'] }}</strong><br><span class="meta">{{ $fila['expediente'] }}</span></td><td>{{ $fila['responsable'] }}</td><td><span class="estado estado-{{ $color }}">{{ $estado }}</span></td><td>{{ $fila['detalle'] }}</td></tr>
@empty<tr><td colspan="6">No se encontraron registros con estos filtros.</td></tr>@endforelse
</tbody></table><div class="total">TOTAL DE REGISTROS: {{ count($filas) }}</div>
@endif
</body>
</html>
