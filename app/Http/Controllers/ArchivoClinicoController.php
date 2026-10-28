<?php

namespace App\Http\Controllers;

use App\Models\DocumentoClinico;
use App\Models\Ingreso;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function formulario(Request $request, Ingreso $atencionArchivo): View
    {
        $tipo = $request->query('tipo', 'Evolución clínica');
        abort_unless(is_string($tipo) && array_key_exists($tipo, self::HOJAS), 422);
        $referencia = $request->query('referencia');
        if ($referencia !== null) {
            $this->referencia($atencionArchivo, $tipo, $referencia);
        }
        $ingreso = $atencionArchivo;

        return view('archivos.formulario', compact('ingreso', 'tipo', 'referencia'));
    }

    public function guardar(Request $request, Ingreso $atencionArchivo): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(self::HOJAS))],
            'referencia' => ['nullable', 'integer', 'min:1'],
            'fecha_documento' => ['required', 'date', 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'archivo.required' => 'Seleccione una imagen o un PDF de la hoja.',
            'archivo.mimes' => 'Solo se aceptan imágenes JPG, PNG, WEBP o archivos PDF.',
            'archivo.extensions' => 'La extensión del archivo debe ser JPG, PNG, WEBP o PDF.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
            'fecha_documento.before_or_equal' => 'La fecha de la hoja no puede ser futura.',
        ]);
        $fecha = Carbon::parse($datos['fecha_documento']);
        if ($fecha->lt($atencionArchivo->fecha_ingreso)) {
            throw ValidationException::withMessages(['fecha_documento' => 'La hoja no puede tener una fecha anterior a la llegada del paciente.']);
        }
        $tabla = null;
        $referencia = $datos['referencia'] ?? null;
        if ($referencia) {
            $tabla = $this->referencia($atencionArchivo, $datos['tipo'], $referencia);
        }
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
                $personal = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');

                return DocumentoClinico::create([
                    'id_paciente' => $actual->id_paciente, 'id_ingreso' => $actual->getKey(),
                    'id_personal_responsable' => $personal, 'categoria' => 'Informe', 'titulo' => $datos['tipo'],
                    'tipo_documento' => $datos['tipo'], 'tabla_origen' => $tabla, 'id_registro_origen' => $referencia,
                    'descripcion' => $datos['descripcion'] ?? null, 'ruta_archivo' => $ruta,
                    'nombre_archivo' => mb_substr(basename($archivo->getClientOriginalName()), 0, 255),
                    'tipo_archivo' => $archivo->extension(), 'fecha_documento' => $fecha,
                    'estado' => 'Activo', 'id_usuario_creacion' => session('id_usuario'),
                ]);
            });
        } catch (Throwable $error) {
            Storage::disk('documentos_clinicos')->delete($ruta);
            throw $error;
        }

        return redirect()->route('archivos.show', $documento)->with('exito', 'Hoja guardada en el expediente.');
    }

    public function show(DocumentoClinico $archivoClinico): View
    {
        abort_unless($archivoClinico->estado === 'Activo' && $archivoClinico->ingreso, 404);
        $archivoClinico->load(['paciente', 'personalResponsable']);

        return view('archivos.show', ['documento' => $archivoClinico]);
    }

    public function contenido(DocumentoClinico $archivoClinico): BinaryFileResponse
    {
        abort_unless($archivoClinico->estado === 'Activo' && $archivoClinico->ingreso, 404);
        $ruta = $archivoClinico->ruta_archivo;
        abort_unless(str_starts_with($ruta, 'hojas/'.$archivoClinico->id_ingreso.'/') && ! str_contains($ruta, '..'), 404);
        $disk = Storage::disk('documentos_clinicos');
        abort_unless($disk->exists($ruta), 404);
        $mime = $disk->mimeType($ruta);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true), 415);

        return response()->file($disk->path($ruta), [
            'Content-Type' => $mime, 'Content-Disposition' => 'inline; filename="hoja-'.$archivoClinico->getKey().'.'.$archivoClinico->tipo_archivo.'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
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
