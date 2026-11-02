<?php

namespace App\Http\Controllers;

use App\Models\DocumentoClinico;
use App\Models\Habitacion;
use App\Models\Ingreso;
use App\Models\Paciente;
use App\Services\ReglasAtencion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        $ingresos = $paciente->ingresos()->with(['documentosClinicos.personalResponsable', 'atencionAnterior', 'altaMedica.medico', 'altaMedica.medicamentos', 'personalRegistra', 'hospitalizacion.habitacionActual.habitacion', 'hospitalizacion.historialHabitaciones' => fn ($consulta) => $consulta->with('habitacion')->orderBy('fecha_hora_inicio')->orderBy('id_hospitalizacion_habitacion'), 'evoluciones' => fn ($consulta) => $consulta->where('estado', 'Activo')->with('personal')->orderBy('fecha_hora')->orderBy('id_evolucion'), 'notasEnfermeria' => fn ($consulta) => $consulta->with(['personal', 'actividades', 'signosVitales', 'medicoAvisado'])->orderBy('fecha_hora')->orderBy('id_atencion_enfermeria')])->withCount(['evoluciones' => fn ($consulta) => $consulta->where('estado', 'Activo')])
            ->orderBy('fecha_ingreso', 'desc')
            ->orderByDesc('id_ingreso')
            ->get();

        $archivosPaciente = $paciente->documentosClinicos()->whereNull('id_ingreso')->where('estado', 'Activo')
            ->with('personalResponsable')->orderByDesc('fecha_documento')->orderByDesc('id_documento')->get();
        $ediciones = $this->ultimasEdiciones($ingresos, $archivosPaciente);
        $documentosFirmados = $archivosPaciente->filter->esDocumentoIngresoFirmado()->keyBy('id_registro_origen');

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
                'ingresoActivo',
                'habitacionesDisponibles'
            )
        );
    }

    /**
     * Fecha de la última edición hecha por una persona a la hoja de llegada o de ingreso
     * y a cada consulta, valoración o evolución, según la bitácora. Se devuelve en hora de Guatemala.
     *
     * @param  Collection<int, Ingreso>  $ingresos
     * @param  Collection<int, DocumentoClinico>  $archivosPaciente
     * @return Collection<string, Carbon>
     */
    private function ultimasEdiciones(Collection $ingresos, Collection $archivosPaciente): Collection
    {
        $idsIngresos = $ingresos->modelKeys();
        $idsEvoluciones = $ingresos->flatMap(fn (Ingreso $ingreso) => $ingreso->evoluciones->modelKeys())->all();
        $idsArchivos = $ingresos->flatMap(fn (Ingreso $ingreso) => $ingreso->documentosClinicos->modelKeys())->merge($archivosPaciente->modelKeys())->all();
        if ($idsIngresos === [] && $idsEvoluciones === [] && $idsArchivos === []) {
            return collect();
        }

        return DB::table('tbl_bitacora')
            ->where(function ($consulta) use ($idsIngresos, $idsEvoluciones, $idsArchivos): void {
                $consulta->where(fn ($ingreso) => $ingreso->where('tabla', 'tbl_ingreso')->whereIn('id_registro', $idsIngresos)->where('accion', 'Edición de ingreso'))
                    ->orWhere(fn ($evolucion) => $evolucion->where('tabla', 'tbl_evolucion_clinica')->whereIn('id_registro', $idsEvoluciones)
                        ->whereIn('accion', ['Edición de evolución clínica', 'Actualización de evolución clínica']))
                    ->orWhere(fn ($archivo) => $archivo->where('tabla', 'tbl_documento_clinico')->whereIn('id_registro', $idsArchivos)->where('accion', 'Edición de archivo clínico'));
            })
            ->where('resultado', 'Exitoso')
            ->groupBy('tabla', 'id_registro')
            ->selectRaw('tabla, id_registro, MAX(fecha_hora) AS ultima')
            ->get()
            ->mapWithKeys(fn ($fila) => [$fila->tabla.':'.$fila->id_registro => Carbon::parse($fila->ultima, 'UTC')->setTimezone(ReglasAtencion::ZONA_HORARIA)]);
    }
}
