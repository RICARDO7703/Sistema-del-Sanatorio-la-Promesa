<?php

namespace App\Http\Controllers;

use App\Models\ContactoPaciente;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PacienteController extends Controller
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
            (string) $request->input(
                'buscar',
                ''
            )
        );

        $estado = $request->input(
            'estado',
            'Activo'
        );

        $pacientes = Paciente::with([
            'contactos',
            'ingresos.hospitalizacion.habitacionActual.habitacion',
            'ingresos.altaMedica',
        ])
            ->where(
                'estado',
                $estado
            )
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
                'id_paciente',
                'desc'
            )
            ->get();

        foreach ($pacientes as $paciente) {

            $ingresoActivo = $paciente
                ->ingresos
                ->where(
                    'estado',
                    'Activo'
                )
                ->sortByDesc(
                    'fecha_ingreso'
                )
                ->first();

            $paciente->situacion_actual =
                'Sin ingresos';

            $paciente->habitacion_actual =
                null;

            $paciente->fecha_ultima_alta =
                null;

            if ($ingresoActivo) {

                $hospitalizacion =
                    $ingresoActivo
                        ->hospitalizacion;

                if (
                    $hospitalizacion &&
                    $hospitalizacion->estado
                    === 'Activa'
                ) {

                    $paciente->situacion_actual =
                        'Hospitalizado';

                    if (
                        $hospitalizacion
                            ->habitacionActual &&
                        $hospitalizacion
                            ->habitacionActual
                            ->habitacion
                    ) {

                        $paciente
                            ->habitacion_actual =
                            $hospitalizacion
                                ->habitacionActual
                                ->habitacion
                                ->numero_habitacion;
                    }
                } else {

                    $paciente->situacion_actual =
                        'En atención';
                }

                continue;
            }

            $ultimoIngresoConAlta =
                $paciente
                    ->ingresos
                    ->filter(
                        function ($ingreso) {

                            return
                                $ingreso
                                    ->altaMedica
                                !== null;
                        }
                    )
                    ->sortByDesc(
                        function ($ingreso) {

                            return
                                $ingreso
                                    ->altaMedica
                                    ->fecha_hora;
                        }
                    )
                    ->first();

            if ($ultimoIngresoConAlta) {

                $paciente->situacion_actual =
                    'Dado de alta';

                $paciente
                    ->fecha_ultima_alta =
                    $ultimoIngresoConAlta
                        ->altaMedica
                        ->fecha_hora;
            }
        }

        return view(
            'pacientes.index',
            compact(
                'pacientes',
                'buscar',
                'estado'
            )
        );
    }

    public function guardar(
        Request $request
    ) {
        $idUsuario =
            session('id_usuario');

        if (! $idUsuario) {

            return redirect('/')
                ->with(
                    'error',
                    'Debe iniciar sesión.'
                );
        }

        $datos =
            $this->validarPaciente(
                $request
            );

        $contacto =
            $this->validarContactoOpcional(
                $request
            );

        $datos['nombres'] =
            $this->formatearNombre(
                $datos['nombres']
                    ?? null
            );

        $datos['apellidos'] =
            $this->formatearNombre(
                $datos['apellidos']
                    ?? null
            );

        $datos['telefono'] =
            $this->formatearTelefono(
                $datos['telefono']
                    ?? null
            );

        if (! empty($contacto['nombre'])) {

            $contacto['nombre'] =
                $this->formatearNombre(
                    $contacto['nombre']
                );

            $contacto['parentesco'] =
                $this->formatearNombre(
                    $contacto['parentesco']
                        ?? null
                );

            $contacto['telefono'] =
                $this->formatearTelefono(
                    $contacto['telefono']
                        ?? null
                );
        }

        $datos['estado'] =
            'Activo';

        $datos['id_usuario_creacion'] =
            $idUsuario;

        DB::transaction(
            function () use (
                &$datos,
                $contacto
            ) {

                $datos['numero_expediente'] =
                    $this
                        ->generarNumeroExpediente();

                if (
                    $datos[
                        'estado_identificacion'
                    ] === 'No identificado'
                ) {

                    $datos['codigo_temporal'] =
                        $this
                            ->generarCodigoTemporal();
                } else {

                    $datos['codigo_temporal'] =
                        null;
                }

                $paciente =
                    Paciente::create(
                        $datos
                    );

                if (
                    ! empty(
                        $contacto['nombre']
                    )
                ) {

                    ContactoPaciente::create([
                        'id_paciente' => $paciente
                            ->id_paciente,

                        'nombre' => $contacto[
                                'nombre'
                            ],

                        'parentesco' => $contacto[
                                'parentesco'
                            ] ?? null,

                        'telefono' => $contacto[
                                'telefono'
                            ] ?? null,
                    ]);
                }
            }
        );

        return redirect()
            ->route(
                'pacientes.index'
            )
            ->with(
                'exito',
                'Paciente registrado correctamente.'
            );
    }

    public function actualizar(
        Request $request,
        Paciente $paciente
    ) {
        $idUsuario =
            session('id_usuario');

        if (! $idUsuario) {

            return redirect('/')
                ->with(
                    'error',
                    'Debe iniciar sesión.'
                );
        }

        $datos =
            $this->validarPaciente(
                $request,
                $paciente
            );

        $datos['nombres'] =
            $this->formatearNombre(
                $datos['nombres']
                    ?? null
            );

        $datos['apellidos'] =
            $this->formatearNombre(
                $datos['apellidos']
                    ?? null
            );

        $datos['telefono'] =
            $this->formatearTelefono(
                $datos['telefono']
                    ?? null
            );

        if (
            $datos[
                'estado_identificacion'
            ] === 'No identificado'
        ) {

            $datos['codigo_temporal'] =
                $paciente
                    ->codigo_temporal
                ?: $this
                    ->generarCodigoTemporal();
        } else {

            $datos['codigo_temporal'] =
                null;
        }

        $datos[
            'id_usuario_modificacion'
        ] = $idUsuario;

        $paciente->update(
            $datos
        );

        return redirect()
            ->route(
                'pacientes.index'
            )
            ->with(
                'exito',
                'Paciente actualizado correctamente.'
            );
    }

    public function eliminar(
        Paciente $paciente
    ) {
        $idUsuario =
            session('id_usuario');

        if (! $idUsuario) {

            return redirect('/')
                ->with(
                    'error',
                    'Debe iniciar sesión.'
                );
        }

        if (
            $paciente->estado
            === 'Inactivo'
        ) {

            return back()
                ->with(
                    'error',
                    'El paciente ya se encuentra inactivo.'
                );
        }

        $tieneIngresoActivo =
            $paciente
                ->ingresos()
                ->where(
                    'estado',
                    'Activo'
                )
                ->exists();

        if ($tieneIngresoActivo) {

            return back()
                ->with(
                    'error',
                    'No se puede desactivar un paciente con un ingreso activo.'
                );
        }

        $paciente->update([
            'estado' => 'Inactivo',

            'id_usuario_modificacion' => $idUsuario,
        ]);

        return redirect()
            ->route(
                'pacientes.index'
            )
            ->with(
                'exito',
                'Paciente desactivado correctamente.'
            );
    }

    public function activar(
        Paciente $paciente
    ) {
        $idUsuario =
            session('id_usuario');

        if (! $idUsuario) {

            return redirect('/')
                ->with(
                    'error',
                    'Debe iniciar sesión.'
                );
        }

        if (
            $paciente->estado
            === 'Activo'
        ) {

            return back()
                ->with(
                    'error',
                    'El paciente ya se encuentra activo.'
                );
        }

        $paciente->update([
            'estado' => 'Activo',

            'id_usuario_modificacion' => $idUsuario,
        ]);

        return redirect()
            ->route(
                'pacientes.index',
                [
                    'estado' => 'Inactivo',
                ]
            )
            ->with(
                'exito',
                'Paciente activado correctamente.'
            );
    }

    public function guardarContacto(
        Request $request,
        Paciente $paciente
    ) {
        $idUsuario =
            session('id_usuario');

        if (! $idUsuario) {

            return redirect('/')
                ->with(
                    'error',
                    'Debe iniciar sesión.'
                );
        }

        $datos =
            $this->validarContacto(
                $request
            );

        $datos['nombre'] =
            $this->formatearNombre(
                $datos['nombre']
            );

        $datos['parentesco'] =
            $this->formatearNombre(
                $datos['parentesco']
                    ?? null
            );

        $datos['telefono'] =
            $this->formatearTelefono(
                $datos['telefono']
                    ?? null
            );

        $datos['id_paciente'] =
            $paciente
                ->id_paciente;

        ContactoPaciente::create(
            $datos
        );

        return back()
            ->with(
                'exito',
                'Contacto agregado correctamente.'
            );
    }

    public function actualizarContacto(
        Request $request,
        ContactoPaciente $contacto
    ) {
        $idUsuario =
            session('id_usuario');

        if (! $idUsuario) {

            return redirect('/')
                ->with(
                    'error',
                    'Debe iniciar sesión.'
                );
        }

        $datos =
            $this->validarContacto(
                $request
            );

        $datos['nombre'] =
            $this->formatearNombre(
                $datos['nombre']
            );

        $datos['parentesco'] =
            $this->formatearNombre(
                $datos['parentesco']
                    ?? null
            );

        $datos['telefono'] =
            $this->formatearTelefono(
                $datos['telefono']
                    ?? null
            );

        $contacto->update(
            $datos
        );

        return back()
            ->with(
                'exito',
                'Contacto actualizado correctamente.'
            );
    }

    public function eliminarContacto(
        ContactoPaciente $contacto
    ) {
        $idUsuario =
            session('id_usuario');

        if (! $idUsuario) {

            return redirect('/')
                ->with(
                    'error',
                    'Debe iniciar sesión.'
                );
        }

        $contacto->delete();

        return back()
            ->with(
                'exito',
                'Contacto eliminado correctamente.'
            );
    }

    private function validarPaciente(
        Request $request,
        ?Paciente $paciente = null
    ) {
        $reglaDpi =
            Rule::unique(
                'tbl_paciente',
                'dpi'
            );

        if ($paciente) {

            $reglaDpi->ignore(
                $paciente
                    ->id_paciente,
                'id_paciente'
            );
        }

        return $request->validate(
            [
                'estado_identificacion' => [
                    'required',
                    'in:Identificado,No identificado',
                ],

                'nombres' => [
                    'required_if:estado_identificacion,Identificado',
                    'nullable',
                    'string',
                    'max:100',
                    "regex:/^(?=.*\pL)[\pL\s'-]+$/u",
                ],

                'apellidos' => [
                    'required_if:estado_identificacion,Identificado',
                    'nullable',
                    'string',
                    'max:100',
                    "regex:/^(?=.*\pL)[\pL\s'-]+$/u",
                ],

                'dpi' => [
                    'nullable',
                    'digits:13',
                    $reglaDpi,
                ],

                'fecha_nacimiento' => [
                    'nullable',
                    'date',
                    'before_or_equal:today',
                ],

                'sexo' => [
                    'nullable',
                    'in:Masculino,Femenino',
                ],

                'telefono' => [
                    'required_if:estado_identificacion,Identificado',
                    'nullable',
                    'regex:/^(?:[0-9]{8}|[0-9]{4}-[0-9]{4})$/',
                ],

                'direccion' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'tipo_sangre' => [
                    'nullable',
                    'in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                ],

                'alergias' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'antecedentes_medicos' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'antecedentes_familiares' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'observaciones_generales' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ],
            [
                'estado_identificacion.required' => 'Debe indicar el estado de identificación.',

                'estado_identificacion.in' => 'El estado de identificación no es válido.',

                'nombres.required_if' => 'Los nombres son obligatorios para un paciente identificado.',

                'nombres.max' => 'Los nombres no pueden superar los 100 caracteres.',

                'nombres.regex' => 'Los nombres solo pueden contener letras, espacios, apóstrofes y guiones.',

                'apellidos.required_if' => 'Los apellidos son obligatorios para un paciente identificado.',

                'apellidos.max' => 'Los apellidos no pueden superar los 100 caracteres.',

                'apellidos.regex' => 'Los apellidos solo pueden contener letras, espacios, apóstrofes y guiones.',

                'dpi.digits' => 'El DPI debe contener exactamente 13 dígitos.',

                'dpi.unique' => 'El DPI ya se encuentra registrado.',

                'fecha_nacimiento.date' => 'La fecha de nacimiento no es válida.',

                'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',

                'sexo.in' => 'El sexo seleccionado no es válido.',

                'telefono.required_if' => 'El teléfono es obligatorio para un paciente identificado.',
                'telefono.regex' => 'El teléfono debe contener 8 dígitos y tener el formato 0000-0000.',

                'direccion.max' => 'La dirección no puede superar los 255 caracteres.',

                'tipo_sangre.in' => 'El tipo de sangre seleccionado no es válido.',

                'alergias.max' => 'Las alergias no pueden superar los 1000 caracteres.',

                'antecedentes_medicos.max' => 'Los antecedentes médicos no pueden superar los 2000 caracteres.',

                'antecedentes_familiares.max' => 'Los antecedentes familiares no pueden superar los 2000 caracteres.',

                'observaciones_generales.max' => 'Las observaciones generales no pueden superar los 2000 caracteres.',
            ]
        );
    }

    private function validarContacto(
        Request $request
    ) {
        return $request->validate(
            [
                'nombre' => [
                    'required',
                    'string',
                    'max:150',
                    "regex:/^(?=.*\pL)[\pL\s'-]+$/u",
                ],

                'parentesco' => [
                    'nullable',
                    'string',
                    'max:50',
                    "regex:/^(?=.*\pL)[\pL\s'-]+$/u",
                ],

                'telefono' => [
                    'nullable',
                    'regex:/^(?:[0-9]{8}|[0-9]{4}-[0-9]{4})$/',
                ],
            ],
            [
                'nombre.required' => 'El nombre del contacto es obligatorio.',

                'nombre.max' => 'El nombre del contacto no puede superar los 150 caracteres.',

                'nombre.regex' => 'El nombre solo puede contener letras, espacios, apóstrofes y guiones.',

                'parentesco.max' => 'El parentesco no puede superar los 50 caracteres.',

                'parentesco.regex' => 'El parentesco solo puede contener letras, espacios, apóstrofes y guiones.',

                'telefono.regex' => 'El teléfono debe contener 8 dígitos.',
            ]
        );
    }

    private function validarContactoOpcional(
        Request $request
    ) {
        $datos = $request->validate(
            [
                'contacto' => ['nullable', 'array:nombre,parentesco,telefono'],
                'contacto.nombre' => [
                    'nullable',
                    'required_with:contacto.parentesco,contacto.telefono',
                    'string',
                    'max:150',
                    "regex:/^(?=.*\pL)[\pL\s'-]+$/u",
                ],

                'contacto.parentesco' => [
                    'nullable',
                    'string',
                    'max:50',
                    "regex:/^(?=.*\pL)[\pL\s'-]+$/u",
                ],

                'contacto.telefono' => [
                    'nullable',
                    'regex:/^(?:[0-9]{8}|[0-9]{4}-[0-9]{4})$/',
                ],
            ],
            [
                'contacto.nombre.required_with' => 'Debe indicar el nombre del contacto.',

                'contacto.nombre.max' => 'El nombre del contacto no puede superar los 150 caracteres.',

                'contacto.nombre.regex' => 'El nombre del contacto solo puede contener letras, espacios, apóstrofes y guiones.',

                'contacto.parentesco.max' => 'El parentesco no puede superar los 50 caracteres.',

                'contacto.parentesco.regex' => 'El parentesco solo puede contener letras, espacios, apóstrofes y guiones.',

                'contacto.telefono.regex' => 'El teléfono del contacto debe contener 8 dígitos.',
            ]
        );

        return $datos['contacto']
            ?? [];
    }

    private function formatearTelefono(
        ?string $telefono
    ): ?string {
        if (! $telefono) {
            return null;
        }

        $telefono =
            str_replace(
                '-',
                '',
                trim($telefono)
            );

        return
            substr(
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

        $nombre =
            preg_replace(
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

    private function generarNumeroExpediente()
    {
        $prefijo =
            'EXP-'
            .now()->year
            .'-';

        $ultimo =
            Paciente::where(
                'numero_expediente',
                'like',
                $prefijo.'%'
            )
                ->lockForUpdate()
                ->orderByDesc(
                    'numero_expediente'
                )
                ->value(
                    'numero_expediente'
                );

        $numero =
            $ultimo
            ? ((int) substr(
                $ultimo,
                -4
            )) + 1
            : 1;

        return
            $prefijo
            .str_pad(
                $numero,
                4,
                '0',
                STR_PAD_LEFT
            );
    }

    private function generarCodigoTemporal()
    {
        $prefijo =
            'TMP-'
            .now()->year
            .'-';

        $ultimo =
            Paciente::where(
                'codigo_temporal',
                'like',
                $prefijo.'%'
            )
                ->lockForUpdate()
                ->orderByDesc(
                    'codigo_temporal'
                )
                ->value(
                    'codigo_temporal'
                );

        $numero =
            $ultimo
            ? ((int) substr(
                $ultimo,
                -4
            )) + 1
            : 1;

        return
            $prefijo
            .str_pad(
                $numero,
                4,
                '0',
                STR_PAD_LEFT
            );
    }
}
