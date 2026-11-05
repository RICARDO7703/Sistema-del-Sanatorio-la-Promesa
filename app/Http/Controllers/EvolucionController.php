<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Services\ExamenFisico;
use App\Services\OrdenesMedicas;
use App\Services\ReglasAtencion;
use App\Support\EdicionClinica;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvolucionController extends Controller
{
    public function index()
    {
        return redirect()
            ->route('atencion.index');
    }

    public function guardar(Request $request)
    {
        $idUsuario = session('id_usuario');

        if (! $idUsuario) {
            return redirect('/')
                ->with(
                    'error',
                    'Debe iniciar sesión.'
                );
        }

        $datos = $request->validate(
            [
                'cirugia_recuperacion' => ['nullable', 'integer', 'exists:tbl_cirugia,id_cirugia'],
                'id_ingreso' => [
                    'required',
                    'integer',
                    'exists:tbl_ingreso,id_ingreso',
                ],

                'fecha_hora' => [
                    'required',
                    'date',
                    ReglasAtencion::reglaNoFutura(),
                ],

                'motivo_atencion' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'sintomas' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'evaluacion_medica' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'diagnostico' => [
                    'required',
                    'string',
                    'max:1000',
                ],

                'indicaciones' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'observaciones' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ],
            [
                'id_ingreso.required' => 'El ingreso es obligatorio.',

                'id_ingreso.integer' => 'El ingreso seleccionado no es válido.',

                'id_ingreso.exists' => 'El ingreso seleccionado no existe.',

                'fecha_hora.required' => 'La fecha y hora son obligatorias.',

                'fecha_hora.date' => 'La fecha y hora no son válidas.',

                'fecha_hora.before_or_equal' => 'La fecha y hora no pueden ser futuras.',

                'motivo_atencion.required' => 'El motivo de atención es obligatorio.',

                'motivo_atencion.max' => 'El motivo de atención no puede superar los 255 caracteres.',

                'sintomas.max' => 'Los síntomas no pueden superar los 1000 caracteres.',

                'evaluacion_medica.max' => 'El examen físico no puede superar los 2000 caracteres.',

                'diagnostico.required' => 'El diagnóstico es obligatorio.',

                'diagnostico.max' => 'El diagnóstico no puede superar los 1000 caracteres.',

                'indicaciones.max' => 'Las indicaciones no pueden superar los 2000 caracteres.',

                'observaciones.max' => 'Las observaciones no pueden superar los 1000 caracteres.',
            ]
        );

        $ingreso = Ingreso::findOrFail(
            $datos['id_ingreso']
        );

        if ($ingreso->estado !== 'Activo') {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'No se puede registrar una evolución en un ingreso inactivo.'
                );
        }

        $examenFisico = ExamenFisico::validar($request);

        $fechaHora = Carbon::parse(
            $datos['fecha_hora']
        );

        if (
            $fechaHora->lt(
                $ingreso->fecha_ingreso
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'fecha_hora' => 'La evolución no puede tener una fecha anterior al ingreso.',
                ]);
        }

        $idPersonal = DB::table(
            'tbl_usuario'
        )
            ->where(
                'id_usuario',
                $idUsuario
            )
            ->value(
                'id_personal'
            );

        if (! $idPersonal) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'El usuario no tiene personal asociado.'
                );
        }

        $ordenes = OrdenesMedicas::validar($request, $ingreso);

        DB::transaction(function () use ($request, $ingreso, $ordenes, $examenFisico, $idPersonal, $fechaHora, $datos, $idUsuario): void {
            Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            $cirugia = null;
            if ($request->filled('cirugia_recuperacion')) {
                $cirugia = Cirugia::whereKey($datos['cirugia_recuperacion'])->where('id_ingreso', $ingreso->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($cirugia->estado === 'Realizada' && $cirugia->permiteAccion('terminar'), 403);
                if ($cirugia->ordenRecuperacion()) {
                    throw ValidationException::withMessages(['cirugia_recuperacion' => 'Esta cirugía ya tiene una orden de recuperación. Edite o cancele el registro existente.']);
                }
                if (empty($ordenes['ordenes_medicas']) || $fechaHora->lt($cirugia->fecha_fin_real)) {
                    throw ValidationException::withMessages(['cirugia_recuperacion' => 'Registre una orden con fecha igual o posterior a la finalización de la cirugía.']);
                }
            }
            $registro = EvolucionClinica::create($ordenes + [
                'examen_fisico' => $examenFisico,
                'id_ingreso' => $ingreso->id_ingreso,

                'id_personal' => $idPersonal,

                'fecha_hora' => $fechaHora,

                'motivo_atencion' => trim($datos['motivo_atencion']),

                'sintomas' => isset($datos['sintomas'])
                        ? trim($datos['sintomas'])
                        : null,

                'evaluacion_medica' => isset($datos['evaluacion_medica'])
                        ? trim($datos['evaluacion_medica'])
                        : null,

                'diagnostico' => trim($datos['diagnostico']),

                'indicaciones' => isset($datos['indicaciones'])
                        ? trim($datos['indicaciones'])
                        : null,

                'observaciones' => isset($datos['observaciones'])
                        ? trim($datos['observaciones'])
                        : null,

                'estado' => 'Activo',

                'id_usuario_creacion' => $idUsuario,
            ]);

            if ($cirugia) {
                $cirugia->update(['id_evolucion' => $registro->getKey(), 'id_usuario_modificacion' => $idUsuario]);
            }
        });

        return redirect()
            ->route(
                $request->filled('cirugia_recuperacion') ? 'pacientes.expediente' : 'atencion.show',
                $request->filled('cirugia_recuperacion') ? $ingreso->id_paciente : $ingreso->id_ingreso
            )
            ->with(
                'exito',
                $ingreso->esConsultaExterna()
                    ? 'Consulta externa registrada correctamente.'
                    : ($request->input('_formulario') === 'valoracion' ? 'Valoración inicial registrada correctamente.' : 'Evolución clínica registrada correctamente.')
            )->with('borrador_clinico_guardado', is_string($request->input('_formulario')) ? $request->input('_formulario') : null);
    }

    public function actualizar(
        Request $request,
        EvolucionClinica $evolucion
    ) {
        $idUsuario = session('id_usuario');

        if (! $idUsuario) {
            return redirect('/')
                ->with(
                    'error',
                    'Debe iniciar sesión.'
                );
        }

        if ($evolucion->estado !== 'Activo') {
            return back()
                ->with(
                    'error',
                    'La evolución ya no se encuentra activa.'
                );
        }

        $evolucion->load('ingreso');

        if (
            ! $evolucion->ingreso ||
            $evolucion->ingreso->estado !== 'Activo'
        ) {
            return back()
                ->with(
                    'error',
                    'No se puede modificar una evolución de un ingreso inactivo.'
                );
        }

        if (! EdicionClinica::puedeEditar($evolucion, (int) $idUsuario)) {
            return back()->with('error', EdicionClinica::MENSAJE);
        }

        $datos = $request->validate(
            [
                'fecha_hora' => [
                    'required',
                    'date',
                    ReglasAtencion::reglaNoFutura(),
                ],

                'motivo_atencion' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'sintomas' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'evaluacion_medica' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'diagnostico' => [
                    'required',
                    'string',
                    'max:1000',
                ],

                'indicaciones' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'observaciones' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ],
            [
                'fecha_hora.required' => 'La fecha y hora son obligatorias.',

                'fecha_hora.date' => 'La fecha y hora no son válidas.',

                'fecha_hora.before_or_equal' => 'La fecha y hora no pueden ser futuras.',

                'motivo_atencion.required' => 'El motivo de atención es obligatorio.',

                'motivo_atencion.max' => 'El motivo de atención no puede superar los 255 caracteres.',

                'sintomas.max' => 'Los síntomas no pueden superar los 1000 caracteres.',

                'evaluacion_medica.max' => 'El examen físico no puede superar los 2000 caracteres.',

                'diagnostico.required' => 'El diagnóstico es obligatorio.',

                'diagnostico.max' => 'El diagnóstico no puede superar los 1000 caracteres.',

                'indicaciones.max' => 'Las indicaciones no pueden superar los 2000 caracteres.',

                'observaciones.max' => 'Las observaciones no pueden superar los 1000 caracteres.',
            ]
        );

        $examenFisico = ExamenFisico::validar($request);

        $fechaHora = Carbon::parse(
            $datos['fecha_hora']
        );

        if (
            $fechaHora->lt(
                $evolucion
                    ->ingreso
                    ->fecha_ingreso
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'fecha_hora' => 'La evolución no puede tener una fecha anterior al ingreso.',
                ]);
        }

        $cancelacion = OrdenesMedicas::cancelacion($evolucion->ordenes_medicas);
        $reactivar = $request->boolean('reactivar_ordenes');
        if ($cancelacion && ! $reactivar) {
            // Las órdenes bloqueadas se conservan, incluso ante campos manipulados en la petición.
            $ordenes = ['ordenes_medicas' => $evolucion->ordenes_medicas];
            if ($evolucion->estado_mejoria !== null) {
                $ordenes += $request->validate(['estado_mejoria' => ['required', Rule::in(['Mejoró', 'Sin cambios', 'Empeoró'])]]);
            }
        } else {
            if ($reactivar) {
                if (! $cancelacion || (int) $evolucion->id_personal !== (int) DB::table('tbl_usuario')->where('id_usuario', $idUsuario)->value('id_personal')
                    || OrdenesMedicas::vigentes($evolucion->ingreso)?->getKey() !== $evolucion->getKey()) {
                    throw ValidationException::withMessages(['reactivar_ordenes' => 'Solo el autor puede reactivar sus últimas órdenes canceladas.']);
                }
                $request->merge(['con_ordenes' => 1]);
            }
            $ordenes = OrdenesMedicas::validar($request, $evolucion->ingreso, $evolucion);
        }
        $cirugiasRecuperacion = Cirugia::where('id_ingreso', $evolucion->id_ingreso)->where('estado', 'Realizada')->get()
            ->filter(fn ($operacion) => $operacion->ordenRecuperacion()?->getKey() === $evolucion->getKey());
        if ($cirugiasRecuperacion->isNotEmpty() && empty($ordenes['ordenes_medicas'])) {
            throw ValidationException::withMessages(['ordenes' => 'La orden de recuperación debe conservarse. Para dejarla sin efecto, utilice Cancelar orden.']);
        }
        $request->attributes->set('edicion_documento', true);
        DB::transaction(function () use ($cirugiasRecuperacion, $evolucion, $ordenes, $request, $examenFisico, $fechaHora, $datos, $idUsuario): void {
            foreach ($cirugiasRecuperacion as $operacion) {
                if (! $operacion->id_evolucion) {
                    $operacion->update(['id_evolucion' => $evolucion->getKey()]);
                }
            }
            $evolucion->update($ordenes + [
                'examen_fisico' => $request->exists('examen_fisico') ? $examenFisico : $evolucion->examen_fisico,
                'fecha_hora' => $fechaHora,

                'motivo_atencion' => trim($datos['motivo_atencion']),

                'sintomas' => isset($datos['sintomas'])
                        ? trim($datos['sintomas'])
                        : null,

                'evaluacion_medica' => isset($datos['evaluacion_medica'])
                        ? trim($datos['evaluacion_medica'])
                        : null,

                'diagnostico' => trim($datos['diagnostico']),

                'indicaciones' => $request->exists('indicaciones') ? ($datos['indicaciones'] ?? null) : $evolucion->indicaciones,

                'observaciones' => isset($datos['observaciones'])
                        ? trim($datos['observaciones'])
                        : null,

                'id_usuario_modificacion' => $idUsuario,
            ]);

        });

        return redirect()
            ->route(
                $cirugiasRecuperacion->isNotEmpty() ? 'pacientes.expediente' : 'atencion.show',
                $cirugiasRecuperacion->isNotEmpty() ? $evolucion->ingreso->id_paciente : $evolucion->id_ingreso
            )
            ->with(
                'exito',
                $evolucion->ingreso?->esConsultaExterna()
                    ? 'Consulta externa actualizada correctamente.'
                    : ($request->input('_formulario') === 'valoracion-editar' ? 'Valoración inicial actualizada correctamente.' : 'Evolución clínica actualizada correctamente.')
            )->with('borrador_clinico_guardado', is_string($request->input('_formulario')) ? $request->input('_formulario') : null);
    }

    /**
     * El médico que indicó unas órdenes puede cancelarlas mientras la atención sigue abierta.
     * Enfermería deja de verlas como vigentes; la hoja conserva lo que se ordenó y por qué se canceló.
     */
    public function cancelarOrdenes(Request $request, EvolucionClinica $evolucion): RedirectResponse|JsonResponse
    {
        $cancelacionPosoperatoria = false;
        if ($request->filled('cirugia_posoperatoria')) {
            $contexto = $request->validate(['cirugia_posoperatoria' => ['required', 'integer']]);
            $cirugia = Cirugia::whereKey($contexto['cirugia_posoperatoria'])
                ->where('id_ingreso', $evolucion->id_ingreso)->where('estado', 'Realizada')->firstOrFail();
            abort_unless($cirugia->permiteAccion('terminar'), 403);
            $ordenVigente = OrdenesMedicas::vigentes($cirugia->ingreso);
            $cancelacionPosoperatoria = filled($cirugia->motivo_registro_posterior)
                && ($ordenVigente?->getKey() === $evolucion->getKey() || $cirugia->ordenRecuperacion()?->getKey() === $evolucion->getKey());
            $request->merge(['motivo_cancelacion' => 'Cambio de órdenes después de la cirugía #'.$cirugia->getKey().' · '.$cirugia->tipo_operacion]);
        }
        $datos = $request->validate(
            ['motivo_cancelacion' => ['required', 'string', 'max:255']],
            ['motivo_cancelacion.required' => 'Escriba por qué se cancelan las órdenes.', 'motivo_cancelacion.max' => 'El motivo no puede superar los 255 caracteres.'],
        );
        $idPersonal = (int) DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');
        $ordenes = $evolucion->ordenes_medicas;
        $error = match (true) {
            $evolucion->estado !== 'Activo' || empty($ordenes) => 'Esta hoja no tiene órdenes médicas que cancelar.',
            OrdenesMedicas::cancelacion($ordenes) !== null => 'Estas órdenes ya estaban canceladas.',
            $evolucion->ingreso?->estado !== 'Activo' && ! ($request->filled('cirugia_posoperatoria') && $cirugia->atencionQuirurgicaDisponible()) => 'La atención ya está cerrada; sus órdenes no se pueden cancelar.',
            ! $cancelacionPosoperatoria && (int) $evolucion->id_personal !== $idPersonal => 'Solo el médico que indicó estas órdenes puede cancelarlas.',
            default => null,
        };
        if ($error) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $error], 422);
            }

            return back()->with('error', $error);
        }
        $medico = DB::table('tbl_personal')->where('id_personal', $idPersonal)->first(['nombres', 'apellidos']);
        $ordenes['cancelacion'] = [
            'fecha' => now('America/Guatemala')->format('Y-m-d H:i:s'),
            'id_personal' => $idPersonal,
            'medico' => trim(($medico->nombres ?? '').' '.($medico->apellidos ?? '')),
            'motivo' => trim($datos['motivo_cancelacion']),
        ];
        $evolucion->update(['ordenes_medicas' => $ordenes, 'id_usuario_modificacion' => session('id_usuario')]);

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Orden médica cancelada.', 'vista' => route('pacientes.expediente', $evolucion->ingreso->id_paciente)]);
        }

        return ($request->filled('cirugia_posoperatoria') ? redirect()->route('pacientes.expediente', $evolucion->ingreso->id_paciente) : back())->with('exito', 'Órdenes médicas canceladas. Enfermería ya no las verá como vigentes.');
    }

    /**
     * Reactiva órdenes canceladas por error. Solo el médico que las indicó, con la atención abierta,
     * y solo si no hay órdenes más recientes (enfermería vuelve a verlas como vigentes).
     */
    public function reactivarOrdenes(Request $request, EvolucionClinica $evolucion): RedirectResponse
    {
        $idPersonal = (int) DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');
        $ordenes = $evolucion->ordenes_medicas;
        $error = match (true) {
            $evolucion->estado !== 'Activo' || empty($ordenes) => 'Esta hoja no tiene órdenes médicas.',
            OrdenesMedicas::cancelacion($ordenes) === null => 'Estas órdenes ya están vigentes.',
            $evolucion->ingreso?->estado !== 'Activo' => 'La atención ya está cerrada; sus órdenes no se pueden reactivar.',
            (int) $evolucion->id_personal !== $idPersonal => 'Solo el médico que indicó estas órdenes puede reactivarlas.',
            OrdenesMedicas::vigentes($evolucion->ingreso)?->getKey() !== $evolucion->getKey() => 'Hay órdenes más recientes; estas ya no se pueden reactivar.',
            default => null,
        };
        if ($error) {
            return back()->with('error', $error);
        }
        // Con el lápiz se pueden corregir las órdenes al reactivarlas (solo dentro del plazo de edición de la hoja).
        $editar = $request->boolean('editar_ordenes');
        if ($editar) {
            abort_unless(EdicionClinica::puedeEditar($evolucion), 403, EdicionClinica::MENSAJE);
            $ordenes = OrdenesMedicas::validarOrdenes($request);
            $request->attributes->set('edicion_documento', true);
        } else {
            unset($ordenes['cancelacion']);
        }
        $evolucion->update(['ordenes_medicas' => $ordenes, 'id_usuario_modificacion' => session('id_usuario')]);

        return back()->with('exito', $editar ? 'Órdenes médicas reactivadas y corregidas. Enfermería ya ve las nuevas.' : 'Órdenes médicas reactivadas. Enfermería vuelve a verlas como vigentes.');
    }

    public function eliminar(
        EvolucionClinica $evolucion
    ) {
        $idUsuario = session('id_usuario');

        if (! $idUsuario) {
            return redirect('/')
                ->with(
                    'error',
                    'Debe iniciar sesión.'
                );
        }

        if ($evolucion->estado !== 'Activo') {
            return back()
                ->with(
                    'error',
                    'La evolución ya se encuentra inactiva.'
                );
        }

        $evolucion->load('ingreso');

        if (
            ! $evolucion->ingreso ||
            $evolucion->ingreso->estado !== 'Activo'
        ) {
            return back()
                ->with(
                    'error',
                    'No se puede modificar una evolución de un ingreso inactivo.'
                );
        }

        $evolucion->update([
            'estado' => 'Inactivo',

            'id_usuario_modificacion' => $idUsuario,
        ]);

        return redirect()
            ->route(
                'atencion.show',
                $evolucion->id_ingreso
            )
            ->with(
                'exito',
                'Evolución clínica desactivada correctamente.'
            );
    }
}
