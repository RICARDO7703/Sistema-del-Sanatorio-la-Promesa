<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AvisarSinCambios;
use App\Models\Ingreso;
use App\Models\Paciente;
use App\Services\DocumentoIngresoFirmado;
use App\Services\RegistroIngreso;
use App\Services\ReglasAtencion;
use App\Support\EdicionClinica;
use App\Support\RegistroBitacora;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
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
     * La llegada se guarda sin segundos, igual que la hora de la consulta, para poder cerrarla en el mismo minuto.
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
                'fecha_ingreso' => ReglasAtencion::ahora()->format('Y-m-d H:i:00'),
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
                    ReglasAtencion::reglaNoFutura(),
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
            ] + DocumentoIngresoFirmado::reglas(['exclude_if:tipo_atencion,'.Ingreso::TIPO_CONSULTA_EXTERNA]),
            DocumentoIngresoFirmado::mensajes() + [
                'id_paciente.required' => 'El paciente es obligatorio.',

                'tipo_atencion.required' => 'Seleccione el tipo de atención.',

                'tipo_atencion.in' => 'El tipo de atención no es válido.',

                'id_habitacion.required' => 'Debe seleccionar una habitación.',

                'id_habitacion.exists' => 'La habitación seleccionada no existe.',

                'fecha_ingreso.required' => 'La fecha de llegada es obligatoria.',

                'fecha_ingreso.before_or_equal' => 'La fecha de llegada no puede ser futura.',
            ]
        );

        $archivoFirmado = $request->file(DocumentoIngresoFirmado::CAMPO);
        $firmado = $request->boolean('documento_firmado') && $datos['tipo_atencion'] !== Ingreso::TIPO_CONSULTA_EXTERNA;
        if ($firmado) {
            DocumentoIngresoFirmado::comprobar($archivoFirmado);
        }

        try {
            $ingreso = DB::transaction(function () use ($datos, $firmado, $archivoFirmado, $idUsuario, $idPersonal): Ingreso {
                $ingreso = RegistroIngreso::registrar([
                    'id_paciente' => (int) $datos['id_paciente'],
                    'tipo_atencion' => $datos['tipo_atencion'],
                    'fecha_ingreso' => Carbon::parse($datos['fecha_ingreso'])->format('Y-m-d H:i:s'),
                    'motivo_ingreso' => $datos['motivo_ingreso'] ?? null,
                    'documento_firmado' => $firmado,
                    'id_habitacion' => isset($datos['id_habitacion']) ? (int) $datos['id_habitacion'] : null,
                ], (int) $idUsuario, (int) $idPersonal);
                if ($firmado) {
                    DocumentoIngresoFirmado::guardar($archivoFirmado, $ingreso, (int) $idUsuario);
                }

                return $ingreso;
            });
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

    /**
     * Sube después el documento de ingreso firmado de una hospitalización que quedó pendiente de firma.
     * No edita la hoja de ingreso: solo agrega el archivo y marca el documento como firmado.
     */
    public function subirDocumentoFirmado(Request $request, Ingreso $ingreso): RedirectResponse|JsonResponse
    {
        $volver = redirect()->to(route('pacientes.expediente', $ingreso->id_paciente).'#documento-ingreso-'.$ingreso->getKey());

        if ($ingreso->esConsultaExterna() || $ingreso->estado !== 'Activo') {
            return $volver->with('error', 'Solo se puede subir el documento firmado de una hospitalización activa.');
        }
        if ($ingreso->documentoFirmado) {
            return $volver->with('error', 'Este ingreso ya tiene su documento firmado.');
        }

        abort_unless(EdicionClinica::puedeEditar($ingreso), 403, EdicionClinica::MENSAJE);
        $request->merge(['documento_firmado' => 1]);
        $validador = validator($request->all(), DocumentoIngresoFirmado::reglas(), DocumentoIngresoFirmado::mensajes());
        if ($validador->fails()) {
            if ($request->expectsJson()) {
                throw new ValidationException($validador);
            }

            return $volver->withErrors($validador)->withInput(['_formulario' => 'documento-firmado-'.$ingreso->getKey()]);
        }

        $archivo = $request->file(DocumentoIngresoFirmado::CAMPO);
        try {
            DocumentoIngresoFirmado::comprobar($archivo);
        } catch (ValidationException $error) {
            if ($request->expectsJson()) {
                throw $error;
            }

            return $volver->withErrors($error->errors())->withInput(['_formulario' => 'documento-firmado-'.$ingreso->getKey()]);
        }
        $idUsuario = (int) session('id_usuario');
        DB::transaction(function () use ($ingreso, $archivo, $idUsuario): void {
            $actual = Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            if ($actual->estado !== 'Activo' || $actual->documentoFirmado()->exists()) {
                throw ValidationException::withMessages([DocumentoIngresoFirmado::CAMPO => 'El ingreso cambió mientras subía el archivo. Revise el expediente.']);
            }
            abort_unless(EdicionClinica::puedeEditar($actual, $idUsuario), 403, EdicionClinica::MENSAJE);
            DocumentoIngresoFirmado::guardar($archivo, $actual, $idUsuario);
            $actual->update(['documento_firmado' => true, 'id_usuario_modificacion' => $idUsuario]);
        });

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Documento guardado. La hoja de ingreso está firmada.', 'url' => route('archivos.show', $ingreso->fresh()->documentoFirmado), 'contenido' => route('archivos.contenido', $ingreso->fresh()->documentoFirmado), 'tipo' => $ingreso->fresh()->documentoFirmado->tipo_archivo]);
        }

        return $volver->with('exito', 'Documento de ingreso firmado agregado al expediente.');
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

        if (! EdicionClinica::puedeEditar($ingreso, (int) $idUsuario)) {
            return back()->with('error', EdicionClinica::MENSAJE);
        }

        // Si el documento firmado ya está en el expediente no se vuelve a pedir ni se puede desmarcar.
        $documentoExistente = $ingreso->documentoFirmado;
        $pedirArchivo = ! $ingreso->esConsultaExterna() && ! $documentoExistente;

        $datos = $request->validate(
            [
                'fecha_ingreso' => [
                    'required',
                    'date',
                    ReglasAtencion::reglaNoFutura(),
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
            ] + ($pedirArchivo ? DocumentoIngresoFirmado::reglas() : []),
            DocumentoIngresoFirmado::mensajes() + [
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
        $subirArchivo = $pedirArchivo && $request->boolean('documento_firmado');
        $archivoFirmado = $request->file(DocumentoIngresoFirmado::CAMPO);
        if ($subirArchivo) {
            DocumentoIngresoFirmado::comprobar($archivoFirmado);
        }
        $firmado = $documentoExistente !== null || $subirArchivo || (! $request->has('documento_firmado') && $ingreso->documento_firmado);

        $request->attributes->set('edicion_documento', true);
        $huboCambios = false;
        DB::transaction(function () use (
            $ingreso,
            $datos,
            $firmado,
            $subirArchivo,
            $archivoFirmado,
            $idUsuario,
            &$huboCambios
        ) {
            $ingreso->update([
                'fecha_ingreso' => $datos['fecha_ingreso'],

                'motivo_ingreso' => $datos['motivo_ingreso'] ?? null,

                'documento_firmado' => $firmado,

                'id_usuario_modificacion' => $idUsuario,
            ]);

            $huboCambios = $ingreso->wasChanged() || $subirArchivo;

            if ($subirArchivo) {
                DocumentoIngresoFirmado::guardar($archivoFirmado, $ingreso, (int) $idUsuario);
            }

            if ($ingreso->hospitalizacion) {
                $ingreso->hospitalizacion->update([
                    'id_usuario_modificacion' => $idUsuario,
                ]);
            }
        });

        if ($request->expectsJson()) {
            // «editado» permite marcar la hoja como editada en la lista de documentos sin recargar la página.
            $ahora = now('America/Guatemala');

            return response()->json([
                'mensaje' => $huboCambios ? 'Cambios confirmados.' : AvisarSinCambios::MENSAJE,
                'fecha' => $ingreso->fecha_ingreso->format('d/m/Y H:i'), 'motivo' => $ingreso->motivo_ingreso,
                'editado' => $huboCambios ? 'Se editó el '.$ahora->format('d/m/Y').' a las '.$ahora->format('H:i') : null,
            ]);
        }

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

    /**
     * Anula una atención abierta por error. Los registros clínicos no se borran
     * y el motivo queda en la bitácora junto con quién la anuló.
     */
    public function cancelar(Request $request, Ingreso $ingreso): RedirectResponse
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

        $datos = $request->validate([
            'motivo_anulacion' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'motivo_anulacion.required' => 'Escriba por qué se anula la atención.',
            'motivo_anulacion.min' => 'Explique el motivo de la anulación con un poco más de detalle.',
            'motivo_anulacion.max' => 'El motivo de la anulación no puede pasar de 255 caracteres.',
        ]);
        $esConsultaExterna = $ingreso->esConsultaExterna();

        DB::transaction(function () use (
            $request,
            $datos,
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

                $ahora = ReglasAtencion::ahora();
                ReglasAtencion::validarCierre($ahora, $hospitalizacion->fecha_inicio);
                if ($asignacionActual) {
                    ReglasAtencion::validarCierre($ahora, $asignacionActual->fecha_hora_inicio);
                    $asignacionActual->update([
                        'fecha_hora_fin' => $ahora,
                    ]);
                }

                $hospitalizacion->update([
                    'fecha_fin' => $ahora,
                    'estado' => 'Cancelada',
                    'id_usuario_modificacion' => $idUsuario,
                ]);
            }

            $ingreso->update([
                'estado' => 'Cancelado',
                'id_usuario_modificacion' => $idUsuario,
            ]);

            app(RegistroBitacora::class)->registrar(
                $request, 'Anulación de atención', 'Exitoso', 'Motivo: '.trim($datos['motivo_anulacion']),
                (int) $idUsuario, (int) $ingreso->getKey(), null, 'tbl_ingreso'
            );
        });

        return redirect()
            ->route($esConsultaExterna ? 'pacientes.index' : 'atencion.index')
            ->with(
                'exito',
                'Atención anulada. Los registros se conservan y el motivo quedó en la bitácora.'
            );
    }
}
