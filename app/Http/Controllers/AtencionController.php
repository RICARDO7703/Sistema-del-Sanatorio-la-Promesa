<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use App\Models\Ingreso;
use App\Models\Medicamento;
use App\Models\Personal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'notasEnfermeria' => fn ($consulta) => $consulta->with(['personal', 'actividades', 'signosVitales', 'medicoAvisado'])->orderByDesc('fecha_hora')->orderByDesc('id_atencion_enfermeria'),
        ]);

        if ($ingreso->estado !== 'Activo' || $ingreso->hospitalizacion?->estado !== 'Activa') {
            return redirect()->route('atencion.index')->with('error', 'La hoja de enfermería solo está disponible para pacientes internados.');
        }

        $registrosRecientes = $ingreso->evoluciones->sortBy([['fecha_hora', 'desc'], ['id_evolucion', 'desc']])->values();
        $conOrdenes = $registrosRecientes->filter(fn ($evolucion) => $evolucion->ordenes_medicas)->values();
        $ordenesVigentes = $conOrdenes->first();
        $ordenesAnteriores = $conOrdenes->slice(1)->values();
        $diagnosticoActual = $registrosRecientes->first();
        $ultimoControl = $ingreso->signosVitales->first();

        $medicos = Personal::where('estado', 'Activo')->whereHas('cargo', fn ($q) => $q->whereIn('nombre_cargo', Personal::CARGOS_MEDICOS))->orderBy('nombres')->orderBy('apellidos')->get();

        return view('atencion.enfermeria', compact('ingreso', 'ordenesVigentes', 'ordenesAnteriores', 'diagnosticoActual', 'ultimoControl', 'medicos'));
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
        $historial = $ingreso->evoluciones()->where('estado', 'Activo')->with('personal')->orderByDesc('fecha_hora')->orderByDesc('id_evolucion')->paginate(10)->withQueryString();
        $registroSeleccionado = $request->filled('registro')
            ? $ingreso->evoluciones()->where('estado', 'Activo')->with('personal')->findOrFail($request->integer('registro'))
            : null;
        $consultasAnteriores = $ingreso->historialPrevio();
        $personalSesion = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');

        return view(
            'atencion.show',
            compact(
                'ingreso',
                'habitacionesDisponibles', 'medicamentos', 'medicos', 'historial', 'registroSeleccionado', 'personalSesion', 'consultasAnteriores'
            )
        );
    }
}
