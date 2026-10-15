<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use App\Models\Ingreso;
use App\Models\Medicamento;
use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtencionController extends Controller
{
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
            ->where('estado', 'Activo')
            ->when(
                $buscar !== '',
                function ($consulta) use ($buscar) {
                    $consulta->whereHas(
                        'paciente',
                        function ($q) use ($buscar) {
                            $q->where(
                                'nombres',
                                'like',
                                '%'.$buscar.'%'
                            )
                                ->orWhere(
                                    'apellidos',
                                    'like',
                                    '%'.$buscar.'%'
                                )
                                ->orWhere(
                                    'numero_expediente',
                                    'like',
                                    '%'.$buscar.'%'
                                )
                                ->orWhere(
                                    'dpi',
                                    'like',
                                    '%'.$buscar.'%'
                                )
                                ->orWhere(
                                    'codigo_temporal',
                                    'like',
                                    '%'.$buscar.'%'
                                );
                        }
                    );
                }
            )
            ->orderBy(
                'fecha_ingreso',
                'desc'
            )
            ->get();

        return view(
            'atencion.index',
            compact(
                'ingresos',
                'buscar'
            )
        );
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

            'altaMedica',
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

        $request->validate(['registro' => ['nullable', 'integer', 'min:1']]);
        $historial = $ingreso->evoluciones()->where('estado', 'Activo')->with('personal')->orderByDesc('fecha_hora')->orderByDesc('id_evolucion')->paginate(10)->withQueryString();
        $registroSeleccionado = $request->filled('registro')
            ? $ingreso->evoluciones()->where('estado', 'Activo')->with('personal')->findOrFail($request->integer('registro'))
            : null;
        $ultimasOrdenes = $ingreso->evoluciones()->where('estado', 'Activo')->whereNotNull('ordenes_medicas')->orderByDesc('fecha_hora')->orderByDesc('id_evolucion')->first();
        $personalSesion = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');

        return view(
            'atencion.show',
            compact(
                'ingreso',
                'habitacionesDisponibles', 'medicamentos', 'medicos', 'historial', 'registroSeleccionado', 'ultimasOrdenes', 'personalSesion'
            )
        );
    }
}
