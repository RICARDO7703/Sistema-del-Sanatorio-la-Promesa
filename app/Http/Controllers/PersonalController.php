<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Especialidad;
use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonalController extends Controller
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

                'estado' => [
                    'nullable',
                    'in:Activo,Inactivo',
                ],
            ],
            [
                'buscar.max' => 'La búsqueda no puede superar los 100 caracteres.',

                'estado.in' => 'El estado seleccionado no es válido.',
            ]
        );

        $buscar = trim(
            (string) $request->input('buscar', '')
        );

        $estado = $request->input(
            'estado',
            'Activo'
        );

        $personal = Personal::with([
            'cargo',
            'especialidad',
        ])
            ->where('estado', $estado)
            ->when($buscar !== '', function ($consulta) use ($buscar) {

                $consulta->where(
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
                                'dpi',
                                'like',
                                '%'.$buscar.'%'
                            )
                            ->orWhere(
                                'correo',
                                'like',
                                '%'.$buscar.'%'
                            );
                    }
                );
            }
            )
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();

        $cargos = Cargo::with([
            'especialidades' => function ($consulta) {
                $consulta
                    ->where(
                        'tbl_especialidad.estado',
                        'Activo'
                    )
                    ->orderBy(
                        'nombre_especialidad'
                    );
            },
        ])
            ->where(
                'estado',
                'Activo'
            )
            ->orderBy(
                'nombre_cargo'
            )
            ->get();

        $especialidades = Especialidad::where(
            'estado',
            'Activo'
        )
            ->orderBy(
                'nombre_especialidad'
            )
            ->get();

        return view(
            'personal.index',
            compact(
                'personal',
                'cargos',
                'especialidades',
                'buscar',
                'estado'
            )
        );
    }

    public function guardar(Request $request)
    {
        $datos = $this->validarPersonal(
            $request
        );

        $datos = $this->prepararDatos(
            $datos,
            $request
        );

        $datos['estado'] = 'Activo';

        Personal::create(
            $datos
        );

        return redirect()
            ->route(
                'personal.index'
            )
            ->with(
                'exito',
                'Personal registrado correctamente.'
            );
    }

    public function actualizar(
        Request $request,
        Personal $personal
    ) {
        $datos = $this->validarPersonal(
            $request,
            $personal
        );

        $datos = $this->prepararDatos(
            $datos,
            $request
        );

        $personal->update(
            $datos
        );

        return redirect()
            ->route(
                'personal.index'
            )
            ->with(
                'exito',
                'Personal actualizado correctamente.'
            );
    }

    public function eliminar(
        Personal $personal
    ) {
        if (
            $personal->estado
            === 'Inactivo'
        ) {
            return back()
                ->with(
                    'error',
                    'El personal ya se encuentra inactivo.'
                );
        }

        $personal->update([
            'estado' => 'Inactivo',
        ]);

        return redirect()
            ->route(
                'personal.index'
            )
            ->with(
                'exito',
                'Personal desactivado correctamente.'
            );
    }

    public function activar(
        Personal $personal
    ) {
        if (
            $personal->estado
            === 'Activo'
        ) {
            return back()
                ->with(
                    'error',
                    'El personal ya se encuentra activo.'
                );
        }

        $personal->update([
            'estado' => 'Activo',
        ]);

        return redirect()
            ->route(
                'personal.index',
                [
                    'estado' => 'Inactivo',
                ]
            )
            ->with(
                'exito',
                'Personal activado correctamente.'
            );
    }

    private function validarPersonal(
        Request $request,
        ?Personal $personal = null
    ) {
        $request->validate(['correo' => ['nullable', 'string'], 'numero_colegiado' => ['nullable', 'string'], 'id_cargo' => ['required', 'integer'], 'id_especialidad' => ['nullable', 'integer']]);
        $this->normalizarAntesDeValidar(
            $request
        );

        $reglaDpi = Rule::unique(
            'tbl_personal',
            'dpi'
        );

        $reglaCorreo = Rule::unique(
            'tbl_personal',
            'correo'
        );

        if ($personal) {

            $reglaDpi->ignore(
                $personal->id_personal,
                'id_personal'
            );

            $reglaCorreo->ignore(
                $personal->id_personal,
                'id_personal'
            );
        }

        return $request->validate(
            [
                'nombres' => [
                    'required',
                    'string',
                    'max:100',
                    "regex:/^(?=.*\pL)[\pL\s'-]+$/u",
                ],

                'apellidos' => [
                    'required',
                    'string',
                    'max:100',
                    "regex:/^(?=.*\pL)[\pL\s'-]+$/u",
                ],

                'dpi' => [
                    'required',
                    'digits:13',
                    $reglaDpi,
                ],

                'telefono' => [
                    'nullable',
                    'regex:/^(?:[0-9]{8}|[0-9]{4}-[0-9]{4})$/',
                ],

                'correo' => [
                    'nullable',
                    'email',
                    'max:150',
                    'ends_with:.com',
                    $reglaCorreo,
                ],

                'id_cargo' => [
                    'required',
                    'integer',

                    Rule::exists(
                        'tbl_cargo',
                        'id_cargo'
                    )->where(
                        function ($consulta) {
                            $consulta->where(
                                'estado',
                                'Activo'
                            );
                        }
                    ),
                ],

                'id_especialidad' => [
                    'nullable',
                    'integer',

                    Rule::exists(
                        'tbl_especialidad',
                        'id_especialidad'
                    )->where(
                        function ($consulta) {
                            $consulta->where(
                                'estado',
                                'Activo'
                            );
                        }
                    ),

                    Rule::exists(
                        'tbl_cargo_especialidad',
                        'id_especialidad'
                    )->where(
                        function ($consulta) use ($request) {
                            $consulta->where(
                                'id_cargo',
                                $request->input(
                                    'id_cargo'
                                )
                            );
                        }
                    ),
                ],

                'numero_colegiado' => [
                    Rule::requiredIf(fn () => in_array(Cargo::find($request->input('id_cargo'))?->nombre_cargo, Personal::CARGOS_CON_COLEGIADO, true)),
                    'nullable', 'string', 'regex:/^[1-9][0-9]{0,4}$/',
                ],
            ],
            [
                'numero_colegiado.required' => 'El número de colegiado es obligatorio para este cargo.',
                'numero_colegiado.regex' => 'Ingrese de 1 a 5 dígitos, sin letras, guiones ni cero inicial.',
                'nombres.required' => 'Los nombres son obligatorios.',

                'nombres.max' => 'Los nombres no pueden superar los 100 caracteres.',

                'nombres.regex' => 'Los nombres solo pueden contener letras, espacios, apóstrofes y guiones.',

                'apellidos.required' => 'Los apellidos son obligatorios.',

                'apellidos.max' => 'Los apellidos no pueden superar los 100 caracteres.',

                'apellidos.regex' => 'Los apellidos solo pueden contener letras, espacios, apóstrofes y guiones.',

                'dpi.required' => 'El DPI es obligatorio.',

                'dpi.digits' => 'El DPI debe contener exactamente 13 dígitos.',

                'dpi.unique' => 'El DPI ya se encuentra registrado.',

                'telefono.regex' => 'El teléfono debe contener 8 dígitos.',

                'correo.email' => 'Ingrese un correo válido.',

                'correo.max' => 'El correo no puede superar los 150 caracteres.',

                'correo.ends_with' => 'El correo debe terminar en .com.',

                'correo.unique' => 'El correo ya se encuentra registrado.',

                'id_cargo.required' => 'Debe seleccionar un cargo.',

                'id_cargo.integer' => 'El cargo seleccionado no es válido.',

                'id_cargo.exists' => 'El cargo seleccionado no es válido o está inactivo.',

                'id_especialidad.integer' => 'La especialidad seleccionada no es válida.',

                'id_especialidad.exists' => 'La especialidad seleccionada no corresponde al cargo o está inactiva.',

                'numero_colegiado.max' => 'El número de colegiado no puede superar los 30 caracteres.',
            ]
        );
    }

    private function normalizarAntesDeValidar(
        Request $request
    ): void {
        if ($request->filled('correo')) {

            $request->merge([
                'correo' => mb_strtolower(
                    trim(
                        (string) $request->input(
                            'correo'
                        )
                    )
                ),
            ]);
        }

        if (
            $request->filled(
                'numero_colegiado'
            )
        ) {
            $request->merge([
                'numero_colegiado' => mb_strtoupper(
                    trim(
                        (string) $request->input(
                            'numero_colegiado'
                        )
                    ),
                    'UTF-8'
                ),
            ]);
        }
    }

    private function prepararDatos(
        array $datos,
        Request $request
    ): array {
        $datos['nombres'] =
            $this->formatearNombre(
                $datos['nombres']
            );

        $datos['apellidos'] =
            $this->formatearNombre(
                $datos['apellidos']
            );

        $datos['telefono'] =
            $this->formatearTelefono(
                $datos['telefono']
                    ?? null
            );

        $datos['correo'] =
            ! empty($datos['correo'])
            ? mb_strtolower(
                trim(
                    $datos['correo']
                )
            )
            : null;

        $datos['numero_colegiado'] =
            ! empty(
                $datos['numero_colegiado']
            )
            ? mb_strtoupper(
                trim(
                    $datos[
                        'numero_colegiado'
                    ]
                ),
                'UTF-8'
            )
            : null;

        return $datos;
    }

    private function formatearTelefono(
        ?string $telefono
    ): ?string {
        if (! $telefono) {
            return null;
        }

        $telefono = str_replace(
            '-',
            '',
            trim($telefono)
        );

        return substr(
            $telefono,
            0,
            4
        )
            .'-'
            .substr(
                $telefono,
                4,
                4
            );
    }

    private function formatearNombre(
        ?string $nombre
    ): ?string {
        if (! $nombre) {
            return null;
        }

        $nombre = preg_replace(
            '/\s+/',
            ' ',
            trim($nombre)
        );

        return mb_convert_case(
            $nombre,
            MB_CASE_TITLE,
            'UTF-8'
        );
    }
}
