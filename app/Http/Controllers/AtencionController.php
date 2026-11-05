<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\Habitacion;
use App\Models\Ingreso;
use App\Models\Medicamento;
use App\Models\NotaEnfermeriaOrden;
use App\Models\Personal;
use App\Services\OrdenesMedicas;
use App\Support\DocumentosExpediente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AtencionController extends Controller
{
    /**
     * Vista de enfermería: solo pacientes internados con hospitalización activa.
     */
    public function index(Request $request)
    {
        $request->validate(
            [
                'buscar' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
            ],
            [
                'buscar.max' => 'La búsqueda no puede superar los 100 caracteres.',
            ]
        );

        $buscar = trim(
            (string) $request->input('buscar', '')
        );

        $ingresos = Ingreso::with([
            'paciente',
            'hospitalizacion.habitacionActual.habitacion',
        ])
            ->withMax('signosVitales as ultimo_control', 'fecha_hora')
            ->where('estado', 'Activo')
            ->whereHas('hospitalizacion', fn ($consulta) => $consulta->where('estado', 'Activa'))
            ->when(
                $buscar !== '',
                function ($consulta) use ($buscar) {
                    $consulta->whereHas(
                        'paciente',
                        function ($q) use ($buscar) {
                            $q->where('nombres', 'like', '%'.$buscar.'%')
                                ->orWhere('apellidos', 'like', '%'.$buscar.'%')
                                ->orWhere('numero_expediente', 'like', '%'.$buscar.'%')
                                ->orWhere('dpi', 'like', '%'.$buscar.'%')
                                ->orWhere('codigo_temporal', 'like', '%'.$buscar.'%');
                        }
                    );
                }
            )
            ->orderByDesc('fecha_ingreso')
            ->orderByDesc('id_ingreso')
            ->get();

        return view(
            'atencion.index',
            compact(
                'ingresos',
                'buscar'
            )
        );
    }

    /**
     * Hoja de enfermería: órdenes médicas vigentes (solo lectura), notas de enfermería y signos vitales.
     */
    public function enfermeria(Ingreso $ingreso): View|RedirectResponse
    {
        $ingreso->load([
            'paciente',
            'hospitalizacion.habitacionActual.habitacion',
            'evoluciones' => fn ($consulta) => $consulta->where('estado', 'Activo')->with('personal')->orderBy('fecha_hora')->orderBy('id_evolucion'),
            'signosVitales' => fn ($consulta) => $consulta->with('personal')->orderByDesc('fecha_hora')->orderByDesc('id_signos_vitales'),
            'notasEnfermeria' => fn ($consulta) => $consulta->with(['personal', 'actividades', 'ordenes', 'signosVitales.cirugia', 'medicoAvisado'])->orderByDesc('fecha_hora')->orderByDesc('id_atencion_enfermeria'),
        ]);

        if ($ingreso->estado !== 'Activo' || $ingreso->hospitalizacion?->estado !== 'Activa') {
            return redirect()->route('atencion.index')->with('error', 'La hoja de enfermería solo está disponible para pacientes internados.');
        }

        $registrosRecientes = $ingreso->evoluciones->sortBy([['fecha_hora', 'desc'], ['id_evolucion', 'desc']])->values();
        $conOrdenes = $registrosRecientes->filter(fn ($evolucion) => $evolucion->ordenes_medicas)->values();
        $ordenesVigentes = $conOrdenes->first();
        $ordenesAnteriores = $conOrdenes->slice(1)->values();
        $cirugiasPorOrden = Cirugia::where('id_ingreso', $ingreso->getKey())->where('estado', 'Realizada')
            ->whereNotNull('id_evolucion')->orderBy('id_cirugia')->get()->keyBy('id_evolucion');
        // Órdenes vigentes que enfermería marca en la nota, con la última vez que se aplicó cada una.
        $ultimas = NotaEnfermeriaOrden::query()
            ->join('tbl_atencion_enfermeria as n', 'n.id_atencion_enfermeria', '=', 'tbl_atencion_enfermeria_orden.id_atencion_enfermeria')
            ->where('n.id_ingreso', $ingreso->getKey())->where('tbl_atencion_enfermeria_orden.aplicada', true)
            ->groupBy('tbl_atencion_enfermeria_orden.tipo', 'tbl_atencion_enfermeria_orden.descripcion')
            ->selectRaw('tbl_atencion_enfermeria_orden.tipo, tbl_atencion_enfermeria_orden.descripcion, MAX(n.fecha_hora) as ultima')
            ->get()->mapWithKeys(fn ($fila) => [$fila->tipo.'|'.$fila->descripcion => Carbon::parse($fila->ultima)]);
        $ordenesNota = collect($ordenesVigentes ? OrdenesMedicas::paraEnfermeria($ordenesVigentes->ordenes_medicas) : [])
            ->map(function (array $item) use ($ultimas): array {
                $ultima = $ultimas[$item['tipo'].'|'.$item['descripcion']] ?? null;

                return $item + ['ultima' => $ultima, 'proxima' => $ultima && $item['frecuencia'] ? $ultima->copy()->addHours($item['frecuencia']) : null];
            })->all();
        $diagnosticoActual = $registrosRecientes->first();
        $ultimoControl = $ingreso->signosVitales->first();

        $medicos = Personal::where('estado', 'Activo')->whereHas('cargo', fn ($q) => $q->whereIn('nombre_cargo', Personal::CARGOS_MEDICOS))->orderBy('nombres')->orderBy('apellidos')->get();

        // La nota puede marcarse como control de recuperación de la cirugía terminada más reciente.
        $cirugiaRecuperacion = Cirugia::recienteParaControl($ingreso->getKey());
        $controlCirugia = $cirugiaRecuperacion && (int) request()->query('control_cirugia') === $cirugiaRecuperacion->getKey() ? $cirugiaRecuperacion : null;

        $habitacionesDisponibles = Habitacion::where('estado_operativo', 'Activa')->whereDoesntHave('asignacionesHospitalizacion', fn ($q) => $q->whereNull('fecha_hora_fin'))->orderBy('numero_habitacion')->get();

        return view('atencion.enfermeria', compact('habitacionesDisponibles', 'ingreso', 'ordenesVigentes', 'ordenesAnteriores', 'cirugiasPorOrden', 'diagnosticoActual', 'ultimoControl', 'medicos', 'cirugiaRecuperacion', 'controlCirugia', 'ordenesNota'));
    }

    public function show(Request $request, Ingreso $ingreso)
    {
        if ($ingreso->estado !== 'Activo' && ! ($ingreso->estado === 'Dado de alta' && $ingreso->altaMedica()->exists())) {
            return redirect()
                ->route('atencion.index')
                ->with(
                    'error',
                    'Este ingreso ya no se encuentra activo.'
                );
        }

        $ingreso->load([
            'atencionAnterior.altaMedica.medicamentos',
            'paciente',
            'personalRegistra',

            'hospitalizacion.habitacionActual.habitacion',

            'hospitalizacion.historialHabitaciones.habitacion',

            'evoluciones' => function ($consulta) {
                $consulta
                    ->where('estado', 'Activo')
                    ->orderBy('fecha_hora', 'asc');
            },

            'evoluciones.personal',

            'signosVitales' => function ($consulta) {
                $consulta
                    ->orderBy(
                        'fecha_hora',
                        'desc'
                    );
            },

            'signosVitales.personal',

            'tratamientos' => function ($consulta) {
                $consulta
                    ->orderBy(
                        'fecha_indicacion',
                        'desc'
                    );
            },

            'tratamientos.medicamento',

            'tratamientos.personalIndica',

            'cirugias' => function ($consulta) {
                $consulta
                    ->orderBy(
                        'fecha_programada',
                        'desc'
                    );
            },

            'cirugias.personalIndica',

            'documentosClinicos' => function ($consulta) {
                $consulta
                    ->where(
                        'estado',
                        'Activo'
                    )
                    ->orderBy(
                        'fecha_documento',
                        'desc'
                    );
            },

            'documentosClinicos.personalResponsable',

            'altaMedica.medicamentos',
            'altaMedica.medico',
            // Para la lista de documentos de la atención (los mismos del expediente).
            'controlesSinNota' => fn ($consulta) => $consulta->with(['personal', 'cirugia'])->orderBy('fecha_hora')->orderBy('id_signos_vitales'),
            'notasEnfermeria' => fn ($consulta) => $consulta->with(['personal', 'actividades', 'ordenes', 'signosVitales.cirugia', 'medicoAvisado'])->orderBy('fecha_hora')->orderBy('id_atencion_enfermeria'),
        ]);

        $habitacionesDisponibles =
            Habitacion::where(
                'estado_operativo',
                'Activa'
            )
                ->whereDoesntHave(
                    'asignacionesHospitalizacion',
                    function ($consulta) {
                        $consulta->whereNull(
                            'fecha_hora_fin'
                        );
                    }
                )
                ->orderBy(
                    'numero_habitacion'
                )
                ->get();

        $medicamentos = Medicamento::where('estado', 'Activo')->orderBy('nombre_medicamento')->get();
        $medicos = Personal::where('estado', 'Activo')->whereHas('cargo', fn ($q) => $q->whereIn('nombre_cargo', ['Médico general', 'Médico especialista', 'Cirujano', 'Cirujano externo', 'Anestesiólogo']))->orderBy('nombres')->get();

        $request->validate(['registro' => ['nullable', 'integer', 'min:1'], 'consulta' => ['nullable', 'in:valoracion,alta']]);
        // Documentos de la atención por día, del más nuevo al más viejo, con los mismos filtros del expediente.
        $documentosAtencion = DocumentosExpediente::porDia(collect([$ingreso]), DocumentosExpediente::ediciones(collect([$ingreso]), collect()));
        $documentosFirmados = $ingreso->paciente->documentosClinicos()->whereNull('id_ingreso')->where('estado', 'Activo')->get()
            ->filter->esDocumentoIngresoFirmado()->keyBy('id_registro_origen');
        $registroSeleccionado = $request->filled('registro')
            ? $ingreso->evoluciones()->where('estado', 'Activo')->with('personal')->findOrFail($request->integer('registro'))
            : null;
        $consultasAnteriores = $ingreso->historialPrevio();
        $personalSesion = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');

        return view(
            'atencion.show',
            compact(
                'ingreso',
                'habitacionesDisponibles', 'medicamentos', 'medicos', 'documentosAtencion', 'documentosFirmados', 'registroSeleccionado', 'personalSesion', 'consultasAnteriores'
            )
        );
    }
}
