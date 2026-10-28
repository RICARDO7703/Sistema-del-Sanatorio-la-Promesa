<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\Paciente;
use App\Services\RegistroIngreso;
use App\Services\ReglasAtencion;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class IngresoController extends Controller
{
    public function index()
    {
        return redirect()->route('atencion.index');
    }

    /**
     * Inicia una consulta externa con la hora actual y lleva directo a la pantalla de la consulta.
     */
    public function iniciarConsulta(Paciente $paciente): RedirectResponse
    {
        $idUsuario = (int) session('id_usuario');
        $idPersonal = (int) DB::table('tbl_usuario')->where('id_usuario', $idUsuario)->value('id_personal');

        if (! $idPersonal) {
            return back()->with('error', 'El usuario no tiene personal asociado.');
        }

        try {
            $ingreso = RegistroIngreso::registrar([
                'id_paciente' => $paciente->id_paciente,
                'tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA,
                'fecha_ingreso' => now('America/Guatemala')->format('Y-m-d H:i:s'),
            ], $idUsuario, $idPersonal);
        } catch (RuntimeException $error) {
            return back()->with('error', $error->getMessage());
        }

        return redirect()->route('atencion.show', $ingreso)->with('exito', 'Consulta externa iniciada. Registre la consulta del paciente.');
    }

    public function guardar(Request $request)
    {
        $idUsuario = session('id_usuario');

        if (! $idUsuario) {
            return redirect('/');
        }

        $idPersonal = DB::table('tbl_usuario')
            ->where('id_usuario', $idUsuario)
            ->value('id_personal');

        if (! $idPersonal) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'El usuario no tiene personal asociado.'
                );
        }

        $request->mergeIfMissing(['tipo_atencion' => Ingreso::TIPO_HOSPITALIZACION]);

        $datos = $request->validate(
            [
                'id_paciente' => [
                    'bail',
                    'required',
                    'integer',
                    'exists:tbl_paciente,id_paciente',
                ],

                'tipo_atencion' => [
                    'required',
                    Rule::in(Ingreso::TIPOS_ATENCION),
                ],

                'id_habitacion' => [
                    'exclude_if:tipo_atencion,'.Ingreso::TIPO_CONSULTA_EXTERNA,
                    'bail',
                    'nullable',
                    'integer',
                    'exists:tbl_habitacion,id_habitacion',
                ],

                'fecha_ingreso' => [
                    'required',
                    'date',
                    'before_or_equal:now',
                ],

                'motivo_ingreso' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'documento_firmado' => [
                    'nullable',
                    'boolean',
                ],
            ],
            [
                'id_paciente.required' => 'El paciente es obligatorio.',

                'tipo_atencion.required' => 'Seleccione el tipo de atención.',

                'tipo_atencion.in' => 'El tipo de atención no es válido.',

                'id_habitacion.required' => 'Debe seleccionar una habitación.',

                'id_habitacion.exists' => 'La habitación seleccionada no existe.',

                'fecha_ingreso.required' => 'La fecha de llegada es obligatoria.',

                'fecha_ingreso.before_or_equal' => 'La fecha de llegada no puede ser futura.',
            ]
        );

        try {
            $ingreso = RegistroIngreso::registrar([
                'id_paciente' => (int) $datos['id_paciente'],
                'tipo_atencion' => $datos['tipo_atencion'],
                'fecha_ingreso' => Carbon::parse($datos['fecha_ingreso'])->format('Y-m-d H:i:s'),
                'motivo_ingreso' => $datos['motivo_ingreso'] ?? null,
                'documento_firmado' => $request->boolean('documento_firmado'),
                'id_habitacion' => isset($datos['id_habitacion']) ? (int) $datos['id_habitacion'] : null,
            ], (int) $idUsuario, (int) $idPersonal);
        } catch (QueryException $e) {
            report($e);

            return back()->withInput()->with('error', 'No se pudo registrar la atención. Revise los datos e intente nuevamente.');
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route(
                'atencion.show',
                $ingreso->id_ingreso
            )
            ->with(
                'exito',
                $ingreso->esConsultaExterna()
                    ? 'Consulta externa registrada correctamente.'
                    : 'Paciente hospitalizado correctamente.'
            );
    }

    public function actualizar(Request $request, Ingreso $ingreso)
    {
        $idUsuario = session('id_usuario');

        if (! $idUsuario) {
            return redirect('/');
        }

        if ($ingreso->estado !== 'Activo') {
            return back()
                ->with('error', 'El ingreso ya no se encuentra activo.');
        }

        $datos = $request->validate(
            [
                'fecha_ingreso' => [
                    'required',
                    'date',
                    'before_or_equal:now',
                ],

                'motivo_ingreso' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'documento_firmado' => [
                    'nullable',
                    'boolean',
                ],
            ],
            [
                'fecha_ingreso.required' => 'La fecha de ingreso es obligatoria.',

                'fecha_ingreso.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            ]
        );

        $fechaNueva = Carbon::parse($datos['fecha_ingreso']);
        if ($ingreso->atencionAnterior?->altaMedica && $fechaNueva->lt($ingreso->atencionAnterior->altaMedica->fecha_hora)) {
            throw ValidationException::withMessages(['fecha_ingreso' => 'El seguimiento no puede comenzar antes de la salida de la atención anterior.']);
        }

        foreach (['evoluciones' => 'fecha_hora', 'signosVitales' => 'fecha_hora', 'tratamientos' => 'fecha_indicacion'] as $relacion => $campo) {
            if ($ingreso->$relacion()->where($campo, '<', $fechaNueva)->exists()) {
                throw ValidationException::withMessages(['fecha_ingreso' => 'El ingreso no puede ser posterior a sus registros clínicos.']);
            }
        }
        if ($ingreso->hospitalizacion && $ingreso->hospitalizacion->historialHabitaciones()->where('fecha_hora_inicio', '<', $fechaNueva)->exists()) {
            throw ValidationException::withMessages(['fecha_ingreso' => 'El ingreso no puede ser posterior a la asignación de habitación.']);
        }
        DB::transaction(function () use (
            $ingreso,
            $datos,
            $request,
            $idUsuario
        ) {
            $ingreso->update([
                'fecha_ingreso' => $datos['fecha_ingreso'],

                'motivo_ingreso' => $datos['motivo_ingreso'] ?? null,

                'documento_firmado' => $request->boolean('documento_firmado'),

                'id_usuario_modificacion' => $idUsuario,
            ]);

            if ($ingreso->hospitalizacion) {
                $ingreso->hospitalizacion->update([
                    'id_usuario_modificacion' => $idUsuario,
                ]);
            }
        });

        return redirect()
            ->route(
                'atencion.show',
                $ingreso->id_ingreso
            )
            ->with(
                'exito',
                'Ingreso actualizado correctamente.'
            );
    }

    public function cancelar(Ingreso $ingreso)
    {
        $idUsuario = session('id_usuario');

        if (! $idUsuario) {
            return redirect('/');
        }

        if ($ingreso->estado !== 'Activo') {
            return back()
                ->with(
                    'error',
                    'El ingreso ya no se encuentra activo.'
                );
        }

        DB::transaction(function () use (
            $ingreso,
            $idUsuario
        ) {

            $ingreso = Ingreso::where(
                'id_ingreso',
                $ingreso->id_ingreso
            )
                ->lockForUpdate()
                ->firstOrFail();

            $hospitalizacion = $ingreso
                ->hospitalizacion;

            if (
                $hospitalizacion &&
                $hospitalizacion->estado === 'Activa'
            ) {

                $asignacionActual =
                    $hospitalizacion->habitacionActual;

                ReglasAtencion::validarCierre(now(), $hospitalizacion->fecha_inicio);
                if ($asignacionActual) {
                    ReglasAtencion::validarCierre(now(), $asignacionActual->fecha_hora_inicio);
                    $asignacionActual->update([
                        'fecha_hora_fin' => now(),
                    ]);
                }

                $hospitalizacion->update([
                    'fecha_fin' => now(),
                    'estado' => 'Cancelada',
                    'id_usuario_modificacion' => $idUsuario,
                ]);
            }

            $ingreso->update([
                'estado' => 'Cancelado',
                'id_usuario_modificacion' => $idUsuario,
            ]);
        });

        return redirect()
            ->route('atencion.index')
            ->with(
                'exito',
                'Ingreso cancelado correctamente.'
            );
    }
}
