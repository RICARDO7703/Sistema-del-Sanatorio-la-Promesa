<?php

namespace App\Http\Controllers;

use App\Models\DocumentoClinico;
use App\Models\Ingreso;
use App\Models\Paciente;
use App\Services\ReglasAtencion;
use App\Support\EdicionClinica;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ArchivoClinicoController extends Controller
{
    public const HOJAS = [
        'Hoja de ingreso' => 'tbl_ingreso', 'Consulta externa' => 'tbl_evolucion_clinica',
        'Valoración inicial' => 'tbl_evolucion_clinica', 'Evolución clínica' => 'tbl_evolucion_clinica',
        'Órdenes médicas' => 'tbl_evolucion_clinica', 'Nota de enfermería' => 'tbl_atencion_enfermeria',
        'Control de signos vitales' => 'tbl_signos_vitales', 'Alta médica' => 'tbl_alta_medica',
        'Cirugía' => 'tbl_cirugia', 'Otro documento' => '',
    ];

    public function guardar(Request $request, Ingreso $atencionArchivo): RedirectResponse
    {
        // Desde el recuadro de una hoja (por ejemplo, la ventana de una cirugía) el archivo se sube sin cambiar de pantalla.
        $enLinea = $request->boolean('en_linea');
        $cirugia = $request->input('tipo') === 'Cirugía' ? (int) $request->input('referencia') : null;
        try {
            $documento = $this->guardarHoja($request, $atencionArchivo);
        } catch (ValidationException $error) {
            if ($enLinea && $cirugia) {
                session()->flash('abrir_cirugia', $cirugia);
            }
            throw $error;
        }
        if ($enLinea) {
            return back()->with('exito', 'Archivo agregado a «'.$documento->titulo.'».')
                ->with('adjunto_subido', $request->input('_formulario'))
                ->with($cirugia ? ['abrir_cirugia' => $cirugia] : []);
        }

        return redirect()->route('archivos.show', $documento)->with('exito', 'Hoja guardada en el expediente.');
    }

    private function guardarHoja(Request $request, Ingreso $atencionArchivo): DocumentoClinico
    {
        $datos = $request->validate([
            // Los archivos complementan una hoja ya registrada (una foto, un PDF): no reemplazan la hoja.
            'tipo' => ['required', Rule::in(array_keys(array_filter(self::HOJAS)))],
            'referencia' => ['required', 'integer', 'min:1'],
            'fecha_documento' => ['required', 'date', 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'archivo.required' => 'Seleccione una imagen o un PDF de la hoja.',
            'tipo.in' => 'Los archivos se agregan a una hoja ya registrada.',
            'referencia.required' => 'Los archivos se agregan a una hoja ya registrada: abra la hoja y use «Subir una foto o PDF».',
            'archivo.mimes' => 'Solo se aceptan imágenes JPG, PNG, WEBP o archivos PDF.',
            'archivo.extensions' => 'La extensión del archivo debe ser JPG, PNG, WEBP o PDF.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
            'fecha_documento.before_or_equal' => 'La fecha de la hoja no puede ser futura.',
        ]);
        $fecha = Carbon::parse($datos['fecha_documento']);
        if ($fecha->lt($atencionArchivo->fecha_ingreso)) {
            throw ValidationException::withMessages(['fecha_documento' => 'La hoja no puede tener una fecha anterior a la llegada del paciente.']);
        }
        $referencia = $datos['referencia'];
        $tabla = $this->referencia($atencionArchivo, $datos['tipo'], $referencia);
        abort_unless(DocumentoClinico::puedeModificarHoja($atencionArchivo, $tabla, $referencia, (int) session('id_usuario')), 403, EdicionClinica::MENSAJE);
        $archivo = $request->file('archivo');
        $mimeReal = (new \finfo(FILEINFO_MIME_TYPE))->file($archivo->getRealPath());
        if (! in_array($mimeReal, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['archivo' => 'El contenido no corresponde a una imagen o PDF válido.']);
        }
        $ruta = $archivo->store('hojas/'.$atencionArchivo->getKey(), 'documentos_clinicos');
        if (! $ruta) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo guardar el archivo. Inténtelo nuevamente.']);
        }
        try {
            $documento = DB::transaction(function () use ($atencionArchivo, $datos, $fecha, $archivo, $ruta, $tabla, $referencia) {
                $actual = Ingreso::whereKey($atencionArchivo->getKey())->lockForUpdate()->firstOrFail();
                if ($fecha->lt($actual->fecha_ingreso)) {
                    throw ValidationException::withMessages(['fecha_documento' => 'La fecha de llegada cambió. Revise la fecha de la hoja.']);
                }
                abort_unless(DocumentoClinico::puedeModificarHoja($actual, $tabla, $referencia, (int) session('id_usuario')), 403, EdicionClinica::MENSAJE);
                $personal = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');

                return DocumentoClinico::create([
                    'id_paciente' => $actual->id_paciente, 'id_ingreso' => $actual->getKey(),
                    'id_personal_responsable' => $personal, 'categoria' => 'Informe', 'titulo' => $datos['tipo'],
                    'tipo_documento' => $datos['tipo'], 'tabla_origen' => $tabla, 'id_registro_origen' => $referencia,
                    'descripcion' => $datos['descripcion'] ?? null, 'ruta_archivo' => $ruta,
                    'nombre_archivo' => mb_substr(basename($archivo->getClientOriginalName()), 0, 255),
                    'tipo_archivo' => $archivo->extension(), 'fecha_documento' => $fecha,
                    'estado' => 'Activo', 'id_usuario_creacion' => session('id_usuario'), 'fecha_registro' => now('UTC'),
                ]);
            });
        } catch (Throwable $error) {
            Storage::disk('documentos_clinicos')->delete($ruta);
            throw $error;
        }

        return $documento;
    }

    public function show(DocumentoClinico $archivoClinico): RedirectResponse
    {
        abort_unless($archivoClinico->estado === 'Activo' && ($archivoClinico->ingreso || $archivoClinico->esDelPaciente()), 404);
        $archivoClinico->load(['paciente', 'personalResponsable']);

        return redirect()->route('pacientes.expediente', $archivoClinico->id_paciente ?? $archivoClinico->ingreso->id_paciente)
            ->with('abrir_archivo_subido', [
                'url' => route('archivos.contenido', $archivoClinico),
                'tipo' => $archivoClinico->tipo_archivo,
                'titulo' => $archivoClinico->titulo,
                'nombre' => $archivoClinico->nombre_archivo,
            ]);
    }

    public function contenido(DocumentoClinico $archivoClinico): BinaryFileResponse
    {
        abort_unless($archivoClinico->estado === 'Activo' && ($archivoClinico->ingreso || $archivoClinico->esDelPaciente()), 404);
        $ruta = $archivoClinico->ruta_archivo;
        abort_unless(str_starts_with($ruta, $this->carpeta($archivoClinico).'/') && ! str_contains($ruta, '..'), 404);
        $disk = Storage::disk('documentos_clinicos');
        abort_unless($disk->exists($ruta), 404);
        $mime = $disk->mimeType($ruta);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true), 415);

        return response()->file($disk->path($ruta), [
            'Content-Type' => $mime, 'Content-Disposition' => 'inline; filename="hoja-'.$archivoClinico->getKey().'.'.$archivoClinico->tipo_archivo.'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Archivo médico que se agrega directamente al expediente del paciente (laboratorio, radiografía, informe…).
     * La fecha es la hora en que se sube.
     */
    public function guardarPaciente(Request $request, Paciente $paciente): RedirectResponse
    {
        $datos = $request->validate($this->reglasArchivoPaciente(true), $this->mensajesArchivo());
        $archivo = $this->archivoValido($request->file('archivo'));
        $ruta = $archivo->store('pacientes/'.$paciente->getKey(), 'documentos_clinicos');
        if (! $ruta) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo guardar el archivo. Inténtelo nuevamente.']);
        }
        try {
            DocumentoClinico::create([
                'id_paciente' => $paciente->getKey(), 'id_ingreso' => null,
                'id_personal_responsable' => DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal'),
                'categoria' => in_array($datos['categoria'], DocumentoClinico::EXAMENES_LABORATORIO, true) ? 'Laboratorio' : $datos['categoria'], 'titulo' => trim($datos['titulo']), 'tipo_documento' => in_array($datos['categoria'], DocumentoClinico::EXAMENES_LABORATORIO, true) ? $datos['categoria'] : null,
                'descripcion' => $this->texto($datos['descripcion'] ?? null), 'ruta_archivo' => $ruta,
                'nombre_archivo' => mb_substr(basename($archivo->getClientOriginalName()), 0, 255),
                'tipo_archivo' => $archivo->extension(), 'fecha_documento' => ReglasAtencion::ahora(),
                'estado' => 'Activo', 'id_usuario_creacion' => session('id_usuario'), 'fecha_registro' => now('UTC'),
            ]);
        } catch (Throwable $error) {
            Storage::disk('documentos_clinicos')->delete($ruta);
            throw $error;
        }

        return redirect()->to(route('pacientes.expediente', $paciente).'#archivos-paciente')
            ->with('exito', 'Archivo agregado al expediente. Puede corregirlo durante las próximas '.DocumentoClinico::HORAS_PARA_EDITAR.' horas.');
    }

    public function editar(DocumentoClinico $archivoClinico): View
    {
        $this->exigirEdicion($archivoClinico);
        $archivoClinico->load(['paciente', 'ingreso']);

        return view('archivos.editar', ['documento' => $archivoClinico]);
    }

    /**
     * Corrige los datos o reemplaza el archivo. Solo quien lo subió y dentro de las primeras 12 horas.
     */
    public function actualizar(Request $request, DocumentoClinico $archivoClinico): RedirectResponse
    {
        $this->exigirEdicion($archivoClinico);
        $delPaciente = $archivoClinico->esDelPaciente();
        $reglas = $delPaciente ? $this->reglasArchivoPaciente(false) : [
            'fecha_documento' => ['required', 'date', ReglasAtencion::reglaNoFutura()],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
        if ($archivoClinico->esDocumentoIngresoFirmado()) {
            unset($reglas['categoria']);
        }
        $datos = $request->validate($reglas, $this->mensajesArchivo());
        $cambios = ['descripcion' => $this->texto($datos['descripcion'] ?? null), 'id_usuario_modificacion' => session('id_usuario')];
        if ($delPaciente) {
            $cambios += ['titulo' => trim($datos['titulo'])];
            if (isset($datos['categoria'])) {
                $esExamen = in_array($datos['categoria'], DocumentoClinico::EXAMENES_LABORATORIO, true);
                $cambios += ['categoria' => $esExamen ? 'Laboratorio' : $datos['categoria'], 'tipo_documento' => $esExamen ? $datos['categoria'] : null];
            }
        } else {
            $fecha = Carbon::parse($datos['fecha_documento']);
            if ($fecha->lt($archivoClinico->ingreso->fecha_ingreso)) {
                throw ValidationException::withMessages(['fecha_documento' => 'La hoja no puede tener una fecha anterior a la llegada del paciente.']);
            }
            $cambios['fecha_documento'] = $fecha;
        }
        $rutaNueva = null;
        if ($request->hasFile('archivo')) {
            $archivo = $this->archivoValido($request->file('archivo'));
            $rutaNueva = $archivo->store($this->carpeta($archivoClinico), 'documentos_clinicos');
            if (! $rutaNueva) {
                throw ValidationException::withMessages(['archivo' => 'No se pudo guardar el archivo. Inténtelo nuevamente.']);
            }
            $cambios += ['ruta_archivo' => $rutaNueva, 'nombre_archivo' => mb_substr(basename($archivo->getClientOriginalName()), 0, 255), 'tipo_archivo' => $archivo->extension()];
        }
        $rutaAnterior = $archivoClinico->ruta_archivo;
        try {
            $request->attributes->set('edicion_documento', true);
            $archivoClinico->update($cambios);
        } catch (Throwable $error) {
            if ($rutaNueva) {
                Storage::disk('documentos_clinicos')->delete($rutaNueva);
            }
            throw $error;
        }
        if ($rutaNueva) {
            DB::afterCommit(fn () => Storage::disk('documentos_clinicos')->delete($rutaAnterior));
        }

        return redirect()->route('archivos.show', $archivoClinico)->with('exito', 'Archivo corregido.');
    }

    public function quitar(Request $request, DocumentoClinico $archivoClinico): RedirectResponse
    {
        DB::transaction(function () use ($archivoClinico) {
            if ($archivoClinico->id_ingreso) {
                Ingreso::whereKey($archivoClinico->id_ingreso)->lockForUpdate()->firstOrFail();
            }
            $actual = DocumentoClinico::whereKey($archivoClinico->getKey())->lockForUpdate()->firstOrFail();
            $this->exigirEdicion($actual);
            $actual->update(['estado' => 'Inactivo', 'id_usuario_modificacion' => session('id_usuario')]);
        });

        return redirect()->to(route('pacientes.expediente', $archivoClinico->id_paciente))->with('exito', 'Archivo quitado del expediente.');
    }

    private function exigirEdicion(DocumentoClinico $documento): void
    {
        abort_unless($documento->estado === 'Activo' && ($documento->ingreso || $documento->esDelPaciente()), 404);
        abort_unless($documento->puedeEditar((int) session('id_usuario')), 403, 'Solo quien subió el archivo puede editarlo, y únicamente durante las primeras '.DocumentoClinico::HORAS_PARA_EDITAR.' horas.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasArchivoPaciente(bool $archivoObligatorio): array
    {
        return [
            'categoria' => ['required', Rule::in(DocumentoClinico::CATEGORIAS)],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'archivo' => [$archivoObligatorio ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensajesArchivo(): array
    {
        return [
            'categoria.required' => 'Seleccione el tipo de archivo.',
            'categoria.in' => 'Seleccione un tipo de la lista.',
            'titulo.required' => 'Escriba el nombre del archivo.',
            'titulo.max' => 'El nombre no puede pasar de 150 caracteres.',
            'fecha_documento.required' => 'Indique la fecha.',
            'fecha_documento.before_or_equal' => 'La fecha no puede ser futura.',
            'archivo.required' => 'Seleccione el PDF o la imagen.',
            'archivo.mimes' => 'Solo se aceptan archivos PDF o imágenes JPG, PNG o WEBP.',
            'archivo.extensions' => 'La extensión del archivo debe ser PDF, JPG, PNG o WEBP.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
        ];
    }

    private function archivoValido(UploadedFile $archivo): UploadedFile
    {
        $mimeReal = (new \finfo(FILEINFO_MIME_TYPE))->file($archivo->getRealPath());
        if (! in_array($mimeReal, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['archivo' => 'El contenido no corresponde a una imagen o PDF válido.']);
        }

        return $archivo;
    }

    private function carpeta(DocumentoClinico $documento): string
    {
        return $documento->esDelPaciente() ? 'pacientes/'.$documento->id_paciente : 'hojas/'.$documento->id_ingreso;
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }

    private function referencia(Ingreso $ingreso, string $tipo, mixed $id): string
    {
        $tabla = self::HOJAS[$tipo];
        $claves = ['tbl_ingreso' => 'id_ingreso', 'tbl_evolucion_clinica' => 'id_evolucion', 'tbl_atencion_enfermeria' => 'id_atencion_enfermeria', 'tbl_signos_vitales' => 'id_signos_vitales', 'tbl_alta_medica' => 'id_alta', 'tbl_cirugia' => 'id_cirugia'];
        if (! filter_var($id, FILTER_VALIDATE_INT) || ! isset($claves[$tabla]) || ! DB::table($tabla)->where($claves[$tabla], $id)->where('id_ingreso', $ingreso->getKey())->exists()) {
            throw ValidationException::withMessages(['referencia' => 'La hoja seleccionada no pertenece a esta atención.']);
        }

        return $tabla;
    }
}
