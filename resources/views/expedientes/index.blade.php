@extends('layouts.app')

@section('title', 'Expedientes - Sanatorio La Promesa')

@section('menu-expedientes', 'activo')

@section('content')

<div class="inicio-encabezado">
    <div class="inicio-icono"><i class="bi bi-folder2-open" aria-hidden="true"></i></div>
    <div>
        <h1>Expedientes</h1>
        <p>Datos completos de cada paciente y su historial de atenciones</p>
    </div>
</div>

<div class="pacientes-buscador mb-4">
    <form action="{{ route('expedientes.index') }}" method="GET">
        <div class="row g-3">
            <div class="col-md-6">
                <input type="text" name="buscar" class="form-control @error('buscar') is-invalid @enderror" placeholder="Buscar por nombre, expediente, DPI o código temporal" aria-label="Buscar expediente" value="{{ $buscar }}">
                @error('buscar')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <select name="estado" class="form-select" aria-label="Estado del paciente">
                    <option value="Activo" @selected($estado === 'Activo')>Pacientes activos</option>
                    <option value="Inactivo" @selected($estado === 'Inactivo')>Pacientes inactivos</option>
                    <option value="Todos" @selected($estado === 'Todos')>Todos</option>
                </select>
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
            <h3>Expedientes</h3>
            <p>{{ $pacientes->total() }} {{ $pacientes->total() === 1 ? 'paciente' : 'pacientes' }}</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Expediente</th>
                    <th>Paciente</th>
                    <th>DPI</th>
                    <th>Edad</th>
                    <th>Situación</th>
                    <th>Última atención</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pacientes as $paciente)
                @php
                    $ultima = $paciente->ingresos->first();
                    $activa = $paciente->ingresos->firstWhere('estado', 'Activo');
                @endphp
                <tr>
                    <td>{{ $paciente->numero_expediente }}</td>
                    <td>
                        @if($paciente->estado_identificacion === 'Identificado')
                            {{ $paciente->nombres }} {{ $paciente->apellidos }}
                        @else
                            No identificado
                            <small class="d-block text-muted">{{ $paciente->codigo_temporal }}</small>
                        @endif
                        @if($paciente->estado !== 'Activo')<span class="badge bg-secondary">Inactivo</span>@endif
                    </td>
                    <td>{{ $paciente->dpi ?: '—' }}</td>
                    <td>{{ $paciente->fecha_nacimiento ? $paciente->fecha_nacimiento->age.' años' : '—' }}</td>
                    <td>
                        @if($activa && $activa->hospitalizacion?->estado === 'Activa')
                            <span class="badge bg-danger">Internado</span>
                        @elseif($activa)
                            <span class="badge bg-consulta"><i class="bi bi-person-walking" aria-hidden="true"></i> En consulta</span>
                        @else
                            <span class="badge bg-secondary">Sin atención activa</span>
                        @endif
                    </td>
                    <td>
                        @if($ultima)
                            {{ $ultima->fecha_ingreso->format('d/m/Y') }}
                            <small class="d-block text-muted">{{ $paciente->ingresos_count }} {{ $paciente->ingresos_count === 1 ? 'atención' : 'atenciones' }}</small>
                        @else
                            <span class="text-muted">Sin atenciones</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('pacientes.expediente', $paciente) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder2-open" aria-hidden="true"></i> Ver expediente</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-4">{{ $buscar !== '' ? 'Ningún expediente coincide con la búsqueda.' : 'No hay expedientes registrados.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pacientes->hasPages())
    <nav class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3" aria-label="Paginación de expedientes">
        <span class="text-muted">Página {{ $pacientes->currentPage() }} de {{ $pacientes->lastPage() }}</span>
        <div class="d-flex gap-2">
            @if($pacientes->previousPageUrl())<a class="btn btn-outline-secondary btn-sm" href="{{ $pacientes->previousPageUrl() }}">Anterior</a>@endif
            @if($pacientes->nextPageUrl())<a class="btn btn-outline-secondary btn-sm" href="{{ $pacientes->nextPageUrl() }}">Siguiente</a>@endif
        </div>
    </nav>
    @endif
</div>

@endsection
