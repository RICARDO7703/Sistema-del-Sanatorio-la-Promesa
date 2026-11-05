<?php

namespace App\Http\Controllers;

use App\Models\ContactoPaciente;
use App\Models\Habitacion;
use App\Models\Ingreso;
use App\Models\Paciente;
use App\Services\DocumentoIngresoFirmado;
use App\Services\RegistroIngreso;
use App\Support\SituacionPaciente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
            // Para saber si una consulta externa ya se llenó o el paciente sigue esperando al médico.
            'ingresos' => fn ($consulta) => $consulta->withCount(['evoluciones' => fn ($evoluciones) => $evoluciones->where('estado', 'Activo')]),
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

        SituacionPaciente::asignar($pacientes);

        $habitacionesDisponibles = Habitacion::where('estado_operativo', 'Activa')
            ->whereDoesntHave('asignacionesHospitalizacion', fn ($consulta) => $consulta->whereNull('fecha_hora_fin'))
            ->orderBy('numero_habitacion')
            ->get();

        return view(
            'pacientes.index',
            compact(
                'pacientes',
                'buscar',
                'estado',
                'habitacionesDisponibles'
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

        $fechaRegistro = $request->validate([
            'fecha_registro' => ['nullable', 'date_format:Y-m-d\TH:i', 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')],
        ], ['fecha_registro.before_or_equal' => 'La fecha de registro no puede ser futura.']);
        $datos['fecha_registro'] = ! empty($fechaRegistro['fecha_registro'])
            ? Carbon::createFromFormat('Y-m-d\TH:i', $fechaRegistro['fecha_registro'], 'America/Guatemala')->second(0)->utc()
            : now('UTC');

        $contactos = $this->validarContactos($request);

        $hospitalizacion = $request->validate(
            [
                'hospitalizar' => ['nullable', 'boolean'],
                'fecha_ingreso' => ['exclude_unless:hospitalizar,1', 'required', 'date', 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')],
                'id_habitacion' => ['exclude_unless:hospitalizar,1', 'bail', 'nullable', 'integer', 'exists:tbl_habitacion,id_habitacion'],
                'motivo_ingreso' => ['exclude_unless:hospitalizar,1', 'nullable', 'string', 'max:255'],
                'documento_firmado' => ['exclude_unless:hospitalizar,1', 'nullable', 'boolean'],
            ] + DocumentoIngresoFirmado::reglas(['exclude_unless:hospitalizar,1']),
            DocumentoIngresoFirmado::mensajes() + [
                'fecha_ingreso.required' => 'Indique la fecha y hora de llegada.',
                'fecha_ingreso.before_or_equal' => 'La fecha y hora de llegada no pueden ser futuras.',
                'id_habitacion.required' => 'Seleccione la habitación donde se internará.',
                'id_habitacion.exists' => 'La habitación seleccionada no existe.',
                'motivo_ingreso.max' => 'El motivo de ingreso no puede superar los 255 caracteres.',
            ]
        );

        $hospitalizar = $request->boolean('hospitalizar');
        $firmado = $hospitalizar && $request->boolean('documento_firmado');
        $archivoFirmado = $request->file(DocumentoIngresoFirmado::CAMPO);
        if ($firmado) {
            DocumentoIngresoFirmado::comprobar($archivoFirmado);
        }

        $idPersonal = $hospitalizar
            ? DB::table('tbl_usuario')->where('id_usuario', $idUsuario)->value('id_personal')
            : null;

        if ($hospitalizar && ! $idPersonal) {
            return back()
                ->withInput()
                ->with('error', 'El usuario no tiene personal asociado para hospitalizar al paciente.');
        }

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

        $datos['estado'] =
            'Activo';

        $datos['id_usuario_creacion'] =
            $idUsuario;

        $ingreso = DB::transaction(
            function () use (
                &$datos,
                $contactos,
                $hospitalizar,
                $hospitalizacion,
                $firmado,
                $archivoFirmado,
                $idUsuario,
                $idPersonal
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

                foreach ($contactos as $contacto) {
                    $paciente->contactos()->create($contacto);
                }

                if (! $hospitalizar) {
                    return null;
                }

                try {
                    $ingreso = RegistroIngreso::registrar([
                        'id_paciente' => $paciente->id_paciente,
                        'tipo_atencion' => Ingreso::TIPO_HOSPITALIZACION,
                        'fecha_ingreso' => Carbon::parse($hospitalizacion['fecha_ingreso'])->format('Y-m-d H:i:s'),
                        'motivo_ingreso' => $hospitalizacion['motivo_ingreso'] ?? null,
                        'documento_firmado' => $firmado,
                        'id_habitacion' => isset($hospitalizacion['id_habitacion']) ? (int) $hospitalizacion['id_habitacion'] : null,
                    ], (int) $idUsuario, (int) $idPersonal);
                } catch (RuntimeException $error) {
                    throw ValidationException::withMessages(['id_habitacion' => $error->getMessage()]);
                }
                if ($firmado) {
                    DocumentoIngresoFirmado::guardar($archivoFirmado, $ingreso, (int) $idUsuario);
                }

                return $ingreso;
            }
        );

        if ($ingreso) {
            return redirect()
                ->route('atencion.show', $ingreso)
                ->with('exito', 'Paciente registrado y hospitalizado.');
        }

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

        $contactos = $this->validarContactos($request, $paciente);
        DB::transaction(function () use ($paciente, $datos, $contactos) {
            $paciente->update($datos);
            foreach ($contactos as $contacto) {
                $id = $contacto['id_contacto'] ?? null;
                unset($contacto['id_contacto']);
                if ($id) {
                    $paciente->contactos()->whereKey($id)->firstOrFail()->update($contacto);
                } else {
                    $paciente->contactos()->create($contacto);
                }
            }
        });

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

                'talla' => [
                    'nullable',
                    'regex:/^\d{1,3}(\.\d{1,2})?$/',
                    'numeric',
                    'between:30,250',
                ],

                'peso' => [
                    'nullable',
                    'regex:/^\d{1,3}(\.\d{1,2})?$/',
                    'numeric',
                    'between:1,700',
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

                'talla.regex' => 'La talla solo lleva números, con hasta 2 decimales (por ejemplo 165 o 165.5).',
                'talla.numeric' => 'La talla debe ser un número en centímetros.',
                'talla.between' => 'La talla debe estar entre 30 y 250 cm.',

                'peso.regex' => 'El peso solo lleva números, con hasta 2 decimales (por ejemplo 150 o 150.5).',
                'peso.numeric' => 'El peso debe ser un número en libras.',
                'peso.between' => 'El peso debe estar entre 1 y 700 lb.',

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

    private function validarContactos(Request $request, ?Paciente $paciente = null): array
    {
        // Mantener compatibilidad con formularios que envían un solo contacto.
        if (! $request->has('contactos')) {
            $contacto = $this->validarContactoOpcional($request);
            $request->merge(['contactos' => $contacto ? [$contacto] : []]);
        }
        $datos = $request->validate([
            'contactos' => ['nullable', 'array', 'max:20'],
            'contactos.*' => ['array:id_contacto,nombre,parentesco,telefono'],
            'contactos.*.id_contacto' => ['nullable', 'integer', 'distinct', Rule::exists('tbl_contacto_paciente', 'id_contacto')->where('id_paciente', $paciente?->id_paciente ?? 0)],
            'contactos.*.nombre' => ['nullable', 'required_with:contactos.*.id_contacto,contactos.*.parentesco,contactos.*.telefono', 'string', 'max:150', "regex:/^(?=.*\pL)[\pL\s'-]+$/u"],
            'contactos.*.parentesco' => ['nullable', 'string', 'max:50', "regex:/^(?=.*\pL)[\pL\s'-]+$/u"],
            'contactos.*.telefono' => ['nullable', 'regex:/^(?:[0-9]{8}|[0-9]{4}-[0-9]{4})$/'],
        ], [
            'contactos.*.nombre.required_with' => 'Indique el nombre del contacto.',
            'contactos.*.nombre.regex' => 'El nombre del contacto solo admite letras, espacios, apóstrofes y guiones.',
            'contactos.*.telefono.regex' => 'El teléfono del contacto debe contener 8 dígitos.',
            'contactos.*.id_contacto.exists' => 'El contacto no pertenece a este paciente.',
            'contactos.max' => 'Puede guardar hasta 20 contactos por formulario.',
        ]);

        return collect($datos['contactos'] ?? [])->filter(fn ($contacto) => ! empty($contacto['nombre']))
            ->map(function ($contacto) {
                $contacto['nombre'] = $this->formatearNombre($contacto['nombre']);
                $contacto['parentesco'] = $this->formatearNombre($contacto['parentesco'] ?? null);
                $contacto['telefono'] = $this->formatearTelefono($contacto['telefono'] ?? null);

                return $contacto;
            })->values()->all();
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
