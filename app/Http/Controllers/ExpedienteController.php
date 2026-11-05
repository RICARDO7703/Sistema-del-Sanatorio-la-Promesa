<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use App\Models\Ingreso;
use App\Models\Paciente;
use App\Support\DocumentosExpediente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpedienteController extends Controller
{
    /**
     * Listado de todos los pacientes para consultar su expediente completo, del registrado más reciente al más antiguo.
     */
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['Activo', 'Inactivo', 'Todos'])],
        ], [
            'buscar.max' => 'La búsqueda no puede superar los 100 caracteres.',
        ]);

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $estado = $filtros['estado'] ?? 'Activo';

        $pacientes = Paciente::query()
            ->with(['ingresos' => fn ($consulta) => $consulta->with('hospitalizacion')->orderByDesc('fecha_ingreso')->orderByDesc('id_ingreso')])
            ->withCount('ingresos')
            ->when($estado !== 'Todos', fn ($consulta) => $consulta->where('estado', $estado))
            ->when($buscar !== '', function ($consulta) use ($buscar): void {
                $consulta->where(function ($q) use ($buscar): void {
                    $q->where('nombres', 'like', '%'.$buscar.'%')
                        ->orWhere('apellidos', 'like', '%'.$buscar.'%')
                        ->orWhere('numero_expediente', 'like', '%'.$buscar.'%')
                        ->orWhere('dpi', 'like', '%'.$buscar.'%')
                        ->orWhere('codigo_temporal', 'like', '%'.$buscar.'%');
                });
            })
            ->orderByDesc('fecha_registro')
            ->orderByDesc('id_paciente')
            ->paginate(20)
            ->withQueryString();

        return view('expedientes.index', compact('pacientes', 'buscar', 'estado'));
    }

    public function show(Paciente $paciente)
    {
        $paciente->load('contactos');

        $ingresos = $paciente->ingresos()->with(['cirugias', 'documentosClinicos.personalResponsable', 'atencionAnterior', 'altaMedica.medico', 'altaMedica.medicamentos', 'personalRegistra', 'hospitalizacion.habitacionActual.habitacion', 'hospitalizacion.historialHabitaciones' => fn ($consulta) => $consulta->with('habitacion')->orderBy('fecha_hora_inicio')->orderBy('id_hospitalizacion_habitacion'), 'evoluciones' => fn ($consulta) => $consulta->where('estado', 'Activo')->with('personal')->orderBy('fecha_hora')->orderBy('id_evolucion'), 'controlesSinNota' => fn ($consulta) => $consulta->with(['personal', 'cirugia'])->orderBy('fecha_hora')->orderBy('id_signos_vitales'), 'notasEnfermeria' => fn ($consulta) => $consulta->with(['personal', 'actividades', 'ordenes', 'signosVitales.cirugia', 'medicoAvisado'])->orderBy('fecha_hora')->orderBy('id_atencion_enfermeria')])->withCount(['evoluciones' => fn ($consulta) => $consulta->where('estado', 'Activo')])
            ->orderBy('fecha_ingreso', 'desc')
            ->orderByDesc('id_ingreso')
            ->get();

        $archivosPaciente = $paciente->documentosClinicos()->whereNull('id_ingreso')->where('estado', 'Activo')
            ->with('personalResponsable')->orderByDesc('fecha_documento')->orderByDesc('id_documento')->get();
        $ediciones = DocumentosExpediente::ediciones($ingresos, $archivosPaciente);
        $documentosFirmados = $archivosPaciente->filter->esDocumentoIngresoFirmado()->keyBy('id_registro_origen');
        // Documentos de cada atención, agrupados por día; se despliegan con la flecha de la atención.
        $documentosPorAtencion = $ingresos->mapWithKeys(fn (Ingreso $ingreso) => [$ingreso->getKey() => DocumentosExpediente::porDia(collect([$ingreso]), $ediciones)]);

        $ingresoActivo = $paciente->ingresos()->with('hospitalizacion.habitacionActual.habitacion')
            ->where('estado', 'Activo')
            ->orderBy('fecha_ingreso', 'desc')
            ->first();

        $habitacionesDisponibles = Habitacion::where(
            'estado_operativo',
            'Activa'
        )
            ->whereDoesntHave(
                'asignacionesHospitalizacion',
                function ($consulta) {
                    $consulta->whereNull('fecha_hora_fin');
                }
            )
            ->orderBy('numero_habitacion')
            ->get();

        return view(
            'expedientes.show',
            compact(
                'paciente',
                'ingresos',
                'ediciones',
                'archivosPaciente',
                'documentosFirmados',
                'documentosPorAtencion',
                'ingresoActivo',
                'habitacionesDisponibles'
            )
        );
    }
}
