@extends('layouts.app')

@section('title', 'Atención del paciente - Sanatorio La Promesa')

@section('menu-atencion', 'activo')

@section('content')

    <div class="inicio-encabezado">

        <div class="inicio-icono">
            <i class="bi bi-clipboard2-pulse"></i>
        </div>

        <div>
            <h1>Atención del paciente</h1>
            <p>Pacientes internados: órdenes médicas y control de signos vitales</p>
        </div>

    </div>

    @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="pacientes-buscador mb-4">
        <form action="{{ route('atencion.index') }}" method="GET">
            <div class="row g-3">
                <div class="col-md-9">
                    <input type="text" name="buscar" class="form-control @error('buscar') is-invalid @enderror" placeholder="Buscar por paciente, expediente, DPI o código temporal" aria-label="Buscar paciente internado" value="{{ $buscar ?? '' }}">
                    @error('buscar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search" aria-hidden="true"></i> Buscar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="pacientes-tabla">

        <div class="tabla-titulo">
            <div>
                <h3>Pacientes internados</h3>
                <p>{{ $ingresos->count() }} {{ $ingresos->count() === 1 ? 'paciente' : 'pacientes' }}, del ingreso más reciente al más antiguo</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Habitación</th>
                        <th>Paciente</th>
                        <th>Expediente</th>
                        <th>Internado desde</th>
                        <th>Último control de signos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ingresos as $ingreso)
                    @php
                        $ultimoControl = $ingreso->ultimo_control ? \Illuminate\Support\Carbon::parse($ingreso->ultimo_control) : null;
                        $ahora = \Illuminate\Support\Carbon::parse(now('America/Guatemala')->format('Y-m-d H:i:s'));
                    @endphp
                    <tr>
                        <td><strong>{{ $ingreso->hospitalizacion?->habitacionActual?->habitacion?->numero_habitacion ?? 'Sin asignar' }}</strong></td>
                        <td>
                            @if($ingreso->paciente->estado_identificacion === 'Identificado')
                                {{ $ingreso->paciente->nombres }} {{ $ingreso->paciente->apellidos }}
                            @else
                                No identificado
                                <small class="d-block text-muted">{{ $ingreso->paciente->codigo_temporal }}</small>
                            @endif
                            @if(filled($ingreso->paciente->alergias))
                            <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle mt-1"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Alergias</span>
                            @endif
                        </td>
                        <td>{{ $ingreso->paciente->numero_expediente }}</td>
                        <td>{{ $ingreso->fecha_ingreso->format('d/m/Y H:i') }}</td>
                        <td>
                            @if($ultimoControl)
                                {{ $ultimoControl->format('d/m/Y H:i') }}
                                <small class="d-block text-muted">hace {{ abs($ahora->diffInSeconds($ultimoControl)) < 60 ? 'un momento' : $ultimoControl->locale('es')->diffForHumans($ahora, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'minimumUnit' => 'minute']) }}</small>
                            @else
                                <span class="text-muted">Sin controles</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('atencion.enfermeria', $ingreso) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-clipboard2-check" aria-hidden="true"></i>
                                Abrir hoja
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">{{ ($buscar ?? '') !== '' ? 'Ningún paciente internado coincide con la búsqueda.' : 'No hay pacientes internados.' }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

@endsection
