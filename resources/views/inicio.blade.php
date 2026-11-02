@extends('layouts.app')

@section('title', 'Inicio - Sanatorio La Promesa')

@section('menu-inicio', 'activo')

@section('content')

    <!-- Encabezado -->
    <div class="inicio-encabezado">

        <div class="inicio-icono">
            <i class="bi bi-house-door"></i>
        </div>

        <div>
            <h1>Inicio</h1>

            <p>
                Resumen del turno de {{ mb_strtolower($turnoDelDia) }} · {{ \Illuminate\Support\Str::ucfirst($ahora->locale('es')->isoFormat('dddd D [de] MMMM')) }}
            </p>
        </div>

    </div>

    <!-- Cifras del día -->
    <div class="inicio-cifras" aria-label="Cifras del día">
        <div class="cifra-card">
            <i class="bi bi-hospital" aria-hidden="true"></i>
            <strong>{{ $cifras['internados'] }}</strong>
            <span>{{ $cifras['internados'] === 1 ? 'Paciente internado' : 'Pacientes internados' }}</span>
        </div>
        <div class="cifra-card">
            <i class="bi bi-person-walking" aria-hidden="true"></i>
            <strong>{{ $cifras['consultasHoy'] }}</strong>
            <span>{{ $cifras['consultasHoy'] === 1 ? 'Consulta externa hoy' : 'Consultas externas hoy' }}</span>
        </div>
        <div class="cifra-card">
            <i class="bi bi-door-open" aria-hidden="true"></i>
            <strong>{{ $cifras['ocupacion'] === null ? '—' : $cifras['ocupacion'].' %' }}</strong>
            <span>Ocupación · {{ $cifras['habitacionesOcupadas'] }} de {{ $cifras['habitacionesActivas'] }} habitaciones</span>
        </div>
    </div>

    <!-- Pendientes por cerrar -->
    <section class="pendientes-card" aria-labelledby="titulo-pendientes">
        <div class="pendientes-encabezado">
            <div class="pendientes-icono"><i class="bi bi-clock-history" aria-hidden="true"></i></div>
            <div class="pendientes-titulo">
                <h2 id="titulo-pendientes">Pendientes por cerrar</h2>
                @if($emergenciasPendientes->isNotEmpty())
                <p>Consultas externas abiertas de días anteriores. Finalícelas si el paciente ya se retiró o hospitalícelo si se quedó.</p>
                @else
                <p>No hay consultas externas abiertas de días anteriores.</p>
                @endif
            </div>
            @if($emergenciasPendientes->isNotEmpty())
            <span class="pendientes-cantidad">{{ $emergenciasPendientes->count() }} {{ $emergenciasPendientes->count() === 1 ? 'pendiente' : 'pendientes' }}</span>
            @endif
        </div>

        @if($emergenciasPendientes->isNotEmpty())
        <ul class="pendientes-lista">
            @foreach($emergenciasPendientes as $emergencia)
            @php
                $pacientePendiente = $emergencia->paciente;
                $identificado = $pacientePendiente->estado_identificacion === 'Identificado';
                $iniciales = $identificado ? mb_strtoupper(mb_substr($pacientePendiente->nombres, 0, 1).mb_substr($pacientePendiente->apellidos, 0, 1)) : '?';
                $llegada = \Illuminate\Support\Carbon::parse($emergencia->fecha_ingreso->format('Y-m-d H:i:s'));
                $dias = (int) round($llegada->copy()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($ahora->format('Y-m-d'))));
            @endphp
            <li>
                <span class="pendiente-iniciales" aria-hidden="true">{{ $iniciales }}</span>
                <div class="pendiente-datos">
                    <strong>{{ $identificado ? $pacientePendiente->nombres.' '.$pacientePendiente->apellidos : 'No identificado' }}</strong>
                    <small>Consulta externa · {{ $identificado ? $pacientePendiente->numero_expediente : $pacientePendiente->codigo_temporal }}</small>
                </div>
                <span class="pendiente-tiempo" title="Llegó el {{ $llegada->format('d/m/Y H:i') }}"><i class="bi bi-clock" aria-hidden="true"></i> {{ $dias === 1 ? 'Ayer '.$llegada->format('H:i') : 'Hace '.$dias.' días' }}</span>
                <a href="{{ route('atencion.show', $emergencia) }}" class="btn btn-outline-primary btn-sm pendiente-revisar">Revisar <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            </li>
            @endforeach
        </ul>
        @else
        <div class="pendientes-vacio">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
            <strong>Todo al día</strong>
        </div>
        @endif
    </section>

    <!-- Accesos -->
    <div class="inicio-accesos">

        @puede('Pacientes', 'Ver')
        <div class="inicio-card">
            <div class="card-icono"><i class="bi bi-people"></i></div>
            <h3>Pacientes</h3>
            <p>Registre pacientes, inicie consultas externas u hospitalice.</p>
            <a href="{{ route('pacientes.index') }}" class="btn btn-primary">Ver pacientes <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
        @endpuede

        @puede('Ingresos', 'Ver')
        <div class="inicio-card">
            <div class="card-icono"><i class="bi bi-clipboard2-pulse"></i></div>
            <h3>Atención del paciente</h3>
            <p>Pacientes internados: órdenes médicas, notas de enfermería y signos vitales.</p>
            <a href="{{ route('atencion.index') }}" class="btn btn-primary">Ver atención <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
        @endpuede

        @puede('Personal', 'Ver')
        <div class="inicio-card">
            <div class="card-icono"><i class="bi bi-person-badge"></i></div>
            <h3>Personal</h3>
            <p>Registro y gestión del personal del sanatorio.</p>
            <a href="{{ route('personal.index') }}" class="btn btn-primary">Ver personal <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
        @endpuede

    </div>

@endsection
