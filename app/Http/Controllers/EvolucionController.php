<?php

namespace App\Http\Controllers;

use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Services\ExamenFisico;
use App\Services\OrdenesMedicas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'id_ingreso' => [
                    'required',
                    'integer',
                    'exists:tbl_ingreso,id_ingreso',
                ],

                'fecha_hora' => [
                    'required',
                    'date',
                    'before_or_equal:now',
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

        EvolucionClinica::create($ordenes + [
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

        return redirect()
            ->route(
                'atencion.show',
                $ingreso->id_ingreso
            )
            ->with(
                'exito',
                $ingreso->esConsultaExterna()
                    ? 'Consulta externa registrada correctamente.'
                    : 'Evolución clínica registrada correctamente.'
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

        $datos = $request->validate(
            [
                'fecha_hora' => [
                    'required',
                    'date',
                    'before_or_equal:now',
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

        $ordenes = OrdenesMedicas::validar($request, $evolucion->ingreso, $evolucion);
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

        return redirect()
            ->route(
                'atencion.show',
                $evolucion->id_ingreso
            )
            ->with(
                'exito',
                $evolucion->ingreso?->esConsultaExterna()
                    ? 'Consulta externa actualizada correctamente.'
                    : 'Evolución clínica actualizada correctamente.'
            )->with('borrador_clinico_guardado', is_string($request->input('_formulario')) ? $request->input('_formulario') : null);
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
