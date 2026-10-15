<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\SignoVital;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SignoVitalController extends Controller
{
    public function index()
    {
        return redirect()
            ->route('atencion.index');
    }

    public function guardar(Request $request)
    {
        $idUsuario = session('id_usuario');

        if (!$idUsuario) {
            return redirect('/')
                ->with('error', 'Debe iniciar sesión.');
        }

        $datos = $this->validar($request);

        $ingreso = Ingreso::findOrFail(
            $datos['id_ingreso']
        );

        if ($ingreso->estado !== 'Activo') {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'No se pueden registrar signos vitales en un ingreso inactivo.'
                );
        }

        $fechaHora = Carbon::parse(
            $datos['fecha_hora']
        );

        if ($fechaHora->lt($ingreso->fecha_ingreso)) {
            return back()
                ->withInput()
                ->withErrors([
                    'fecha_hora' =>
                        'La fecha de los signos vitales no puede ser anterior al ingreso.'
                ]);
        }

        $idPersonal = DB::table('tbl_usuario')
            ->where('id_usuario', $idUsuario)
            ->value('id_personal');

        if (!$idPersonal) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'El usuario no tiene personal asociado.'
                );
        }

        SignoVital::create([
            'id_ingreso' =>
                $ingreso->id_ingreso,

            'id_personal' =>
                $idPersonal,

            'tipo_control' =>
                $datos['tipo_control'],

            'fecha_hora' =>
                $fechaHora,

            'presion_sistolica' =>
                $datos['presion_sistolica'] ?? null,

            'presion_diastolica' =>
                $datos['presion_diastolica'] ?? null,

            'frecuencia_cardiaca' =>
                $datos['frecuencia_cardiaca'] ?? null,

            'frecuencia_respiratoria' =>
                $datos['frecuencia_respiratoria'] ?? null,

            'temperatura' =>
                $datos['temperatura'] ?? null,

            'saturacion_oxigeno' =>
                $datos['saturacion_oxigeno'] ?? null,

            'observaciones' =>
                isset($datos['observaciones'])
                    ? trim($datos['observaciones'])
                    : null,

            'id_usuario_creacion' =>
                $idUsuario
        ]);

        return redirect()
            ->route(
                'atencion.show',
                $ingreso->id_ingreso
            )
            ->with(
                'exito',
                'Signos vitales registrados correctamente.'
            );
    }

    public function actualizar(
        Request $request,
        SignoVital $signo
    ) {
        $idUsuario = session('id_usuario');

        if (!$idUsuario) {
            return redirect('/')
                ->with('error', 'Debe iniciar sesión.');
        }

        $signo->load('ingreso');

        if (
            !$signo->ingreso ||
            $signo->ingreso->estado !== 'Activo'
        ) {
            return back()
                ->with(
                    'error',
                    'No se pueden modificar signos vitales de un ingreso inactivo.'
                );
        }

        $datos = $this->validar(
            $request,
            false
        );

        $fechaHora = Carbon::parse(
            $datos['fecha_hora']
        );

        if (
            $fechaHora->lt(
                $signo->ingreso->fecha_ingreso
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'fecha_hora' =>
                        'La fecha de los signos vitales no puede ser anterior al ingreso.'
                ]);
        }

        $signo->update([
            'tipo_control' =>
                $datos['tipo_control'],

            'fecha_hora' =>
                $fechaHora,

            'presion_sistolica' =>
                $datos['presion_sistolica'] ?? null,

            'presion_diastolica' =>
                $datos['presion_diastolica'] ?? null,

            'frecuencia_cardiaca' =>
                $datos['frecuencia_cardiaca'] ?? null,

            'frecuencia_respiratoria' =>
                $datos['frecuencia_respiratoria'] ?? null,

            'temperatura' =>
                $datos['temperatura'] ?? null,

            'saturacion_oxigeno' =>
                $datos['saturacion_oxigeno'] ?? null,

            'observaciones' =>
                isset($datos['observaciones'])
                    ? trim($datos['observaciones'])
                    : null,

            'id_usuario_modificacion' =>
                $idUsuario
        ]);

        return redirect()
            ->route(
                'atencion.show',
                $signo->id_ingreso
            )
            ->with(
                'exito',
                'Signos vitales actualizados correctamente.'
            );
    }

    private function validar(
        Request $request,
        bool $validarIngreso = true
    ) {
        $reglas = [
            'tipo_control' => [
                'required',
                'in:Ingreso,Consulta,Preoperatorio,Recuperación,Rutina'
            ],

            'fecha_hora' => [
                'required',
                'date',
                'before_or_equal:now'
            ],

            'presion_sistolica' => [
                'nullable',
                'required_with:presion_diastolica',
                'integer',
                'between:30,300'
            ],

            'presion_diastolica' => [
                'nullable',
                'required_with:presion_sistolica',
                'integer',
                'between:20,200'
            ],

            'frecuencia_cardiaca' => [
                'nullable',
                'integer',
                'between:1,300'
            ],

            'frecuencia_respiratoria' => [
                'nullable',
                'integer',
                'between:1,100'
            ],

            'temperatura' => [
                'nullable',
                'numeric',
                'between:25,50'
            ],

            'saturacion_oxigeno' => [
                'nullable',
                'integer',
                'between:0,100'
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:1000'
            ]
        ];

        if ($validarIngreso) {
            $reglas['id_ingreso'] = [
                'required',
                'integer',
                'exists:tbl_ingreso,id_ingreso'
            ];
        }

        return $request->validate(
            $reglas,
            [
                'id_ingreso.required' =>
                    'El ingreso es obligatorio.',

                'id_ingreso.exists' =>
                    'El ingreso seleccionado no existe.',

                'tipo_control.required' =>
                    'Debe seleccionar el tipo de control.',

                'tipo_control.in' =>
                    'El tipo de control no es válido.',

                'fecha_hora.required' =>
                    'La fecha y hora son obligatorias.',

                'fecha_hora.before_or_equal' =>
                    'La fecha y hora no pueden ser futuras.',

                'presion_sistolica.required_with' =>
                    'Debe ingresar también la presión sistólica.',

                'presion_sistolica.between' =>
                    'La presión sistólica debe estar entre 30 y 300.',

                'presion_diastolica.required_with' =>
                    'Debe ingresar también la presión diastólica.',

                'presion_diastolica.between' =>
                    'La presión diastólica debe estar entre 20 y 200.',

                'frecuencia_cardiaca.between' =>
                    'La frecuencia cardíaca debe estar entre 1 y 300.',

                'frecuencia_respiratoria.between' =>
                    'La frecuencia respiratoria debe estar entre 1 y 100.',

                'temperatura.between' =>
                    'La temperatura debe estar entre 25 y 50.',

                'saturacion_oxigeno.between' =>
                    'La saturación debe estar entre 0 y 100.',

                'observaciones.max' =>
                    'Las observaciones no pueden superar los 1000 caracteres.'
            ]
        );
    }
}