<?php

namespace App\Services;

use App\Models\DocumentoClinico;
use App\Models\Ingreso;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Archivo del documento de ingreso firmado. Se pide al marcar la casilla «Documento de ingreso firmado»
 * y queda en «Archivos médicos» del expediente, ligado al ingreso por tabla_origen / id_registro_origen.
 */
class DocumentoIngresoFirmado
{
    public const CAMPO = 'documento_archivo';

    /**
     * El archivo es obligatorio solo cuando la casilla está marcada; si no, se ignora.
     *
     * @param  list<string>  $condiciones  reglas previas, como exclude_unless:hospitalizar,1
     * @return array<string, list<string>>
     */
    public static function reglas(array $condiciones = []): array
    {
        return [self::CAMPO => [...$condiciones, 'exclude_unless:documento_firmado,1', 'required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:10240']];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            self::CAMPO.'.required' => 'Suba el documento de ingreso firmado o desmarque la casilla.',
            self::CAMPO.'.uploaded' => 'No se pudo subir el documento. Revise que no pase de 10 MB e inténtelo de nuevo.',
            self::CAMPO.'.file' => 'Suba el documento de ingreso firmado o desmarque la casilla.',
            self::CAMPO.'.mimes' => 'El documento firmado debe ser PDF o imagen JPG, PNG o WEBP.',
            self::CAMPO.'.extensions' => 'El documento firmado debe ser PDF o imagen JPG, PNG o WEBP.',
            self::CAMPO.'.max' => 'El documento firmado no puede superar los 10 MB.',
        ];
    }

    /**
     * Revisa el contenido real del archivo antes de registrar nada.
     */
    public static function comprobar(UploadedFile $archivo): void
    {
        $mimeReal = (new \finfo(FILEINFO_MIME_TYPE))->file($archivo->getRealPath());
        if (! in_array($mimeReal, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages([self::CAMPO => 'El documento firmado no es un PDF o una imagen válida.']);
        }
    }

    /**
     * Guarda el archivo en la carpeta del paciente y lo registra en sus archivos médicos.
     */
    public static function guardar(UploadedFile $archivo, Ingreso $ingreso, int $idUsuario): DocumentoClinico
    {
        self::comprobar($archivo);
        $ruta = $archivo->store('pacientes/'.$ingreso->id_paciente, 'documentos_clinicos');
        if (! $ruta) {
            throw ValidationException::withMessages([self::CAMPO => 'No se pudo guardar el documento firmado. Inténtelo nuevamente.']);
        }

        try {
            return DocumentoClinico::create([
                'id_paciente' => $ingreso->id_paciente,
                'id_ingreso' => null,
                'id_personal_responsable' => DB::table('tbl_usuario')->where('id_usuario', $idUsuario)->value('id_personal'),
                'categoria' => 'Otro',
                'titulo' => 'Documento de ingreso firmado · ingreso del '.$ingreso->fecha_ingreso->format('d/m/Y H:i'),
                'tipo_documento' => DocumentoClinico::TIPO_INGRESO_FIRMADO,
                'tabla_origen' => 'tbl_ingreso',
                'id_registro_origen' => $ingreso->getKey(),
                'descripcion' => null,
                'ruta_archivo' => $ruta,
                'nombre_archivo' => mb_substr(basename($archivo->getClientOriginalName()), 0, 255),
                'tipo_archivo' => $archivo->extension(),
                'fecha_documento' => ReglasAtencion::ahora(),
                'estado' => 'Activo',
                'id_usuario_creacion' => $idUsuario,
                'fecha_registro' => now('UTC'),
            ]);
        } catch (Throwable $error) {
            Storage::disk('documentos_clinicos')->delete($ruta);
            throw $error;
        }
    }
}
