<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use App\Models\Hospitalizacion;
use App\Models\HospitalizacionHabitacion;
use App\Models\Ingreso;
use App\Models\Paciente;
use App\Services\ReglasAtencion;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IngresoController extends Controller
{
    public function index()
    {
        return redirect()->route('atencion.index');
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

        $datos = $request->validate(
            [
                'id_paciente' => [
                    'bail',
                    'required',
                    'integer',
                    'exists:tbl_paciente,id_paciente',
                ],

                'id_habitacion' => [
                    'bail',
                    'required',
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

                'id_habitacion.required' => 'Debe seleccionar una habitación.',

                'id_habitacion.exists' => 'La habitación seleccionada no existe.',

                'fecha_ingreso.required' => 'La fecha de ingreso es obligatoria.',

                'fecha_ingreso.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            ]
        );

        $fechaIngreso = Carbon::parse(
            $datos['fecha_ingreso']
        )->format('Y-m-d H:i:s');

        try {

            $ingreso = DB::transaction(function () use (
                $datos,
                $fechaIngreso,
                $idUsuario,
                $idPersonal,
                $request
            ) {

                $paciente = Paciente::where(
                    'id_paciente',
                    $datos['id_paciente']
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($paciente->estado !== 'Activo') {
                    throw new \Exception(
                        'El paciente no se encuentra activo.'
                    );
                }

                $ingresoActivo = Ingreso::where(
                    'id_paciente',
                    $paciente->id_paciente
                )
                    ->where('estado', 'Activo')
                    ->exists();

                if ($ingresoActivo) {
                    throw new \Exception(
                        'El paciente ya tiene un ingreso activo.'
                    );
                }

                $habitacion = Habitacion::where(
                    'id_habitacion',
                    $datos['id_habitacion']
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($habitacion->estado_operativo !== 'Activa') {
                    throw new \Exception(
                        'La habitación seleccionada no está disponible.'
                    );
                }

                $ocupacionActual = HospitalizacionHabitacion::where(
                    'id_habitacion',
                    $habitacion->id_habitacion
                )
                    ->whereNull('fecha_hora_fin')
                    ->lockForUpdate()
                    ->first();

                if ($ocupacionActual) {
                    throw new \Exception(
                        'La habitación seleccionada ya está ocupada.'
                    );
                }

                $ingreso = Ingreso::create([
                    'id_paciente' => $paciente->id_paciente,

                    'id_personal_registra' => $idPersonal,

                    'fecha_ingreso' => $fechaIngreso,

                    'motivo_ingreso' => $datos['motivo_ingreso'] ?? null,

                    'documento_firmado' => $request->boolean(
                        'documento_firmado'
                    ),

                    'estado' => 'Activo',

                    'id_usuario_creacion' => $idUsuario,
                ]);

                $hospitalizacion = Hospitalizacion::create([
                    'id_ingreso' => $ingreso->id_ingreso,

                    'fecha_inicio' => $fechaIngreso,

                    'motivo' => $datos['motivo_ingreso'] ?? null,

                    'estado' => 'Activa',

                    'id_usuario_creacion' => $idUsuario,
                ]);

                HospitalizacionHabitacion::create([
                    'id_hospitalizacion' => $hospitalizacion->id_hospitalizacion,

                    'id_habitacion' => $habitacion->id_habitacion,

                    'fecha_hora_inicio' => $fechaIngreso,

                    'id_usuario_creacion' => $idUsuario,
                ]);

                return $ingreso;
            });

        } catch (QueryException $e) {
            report($e);

            return back()->withInput()->with('error', 'No se pudo registrar el ingreso. Revise los datos e intente nuevamente.');
        } catch (\Exception $e) {

            return back()
                ->withInput()
                ->with(
                    'error',
                    $e->getMessage()
                );
        }

        return redirect()
            ->route(
                'atencion.show',
                $ingreso->id_ingreso
            )
            ->with(
                'exito',
                'Ingreso registrado correctamente.'
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
