<?php

namespace App\Support;

use App\Models\Personal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Foto de perfil del personal, que el administrador puede subir al crear o editar un usuario.
 * Usa la misma carpeta y las mismas reglas que «Mi perfil».
 */
class FotoPersonal
{
    /**
     * @return list<string>
     */
    public static function reglas(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096'];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'foto.image' => 'La foto debe ser una imagen JPG, PNG o WEBP.',
            'foto.mimes' => 'La foto debe ser una imagen JPG, PNG o WEBP.',
            'foto.extensions' => 'La foto debe ser una imagen JPG, PNG o WEBP.',
            'foto.max' => 'La foto no puede superar los 2 MB.',
            'foto.dimensions' => 'La foto debe medir entre 64 y 4096 píxeles por lado.',
            'foto.uploaded' => 'No se pudo subir la foto. Revise que no pase de 2 MB.',
        ];
    }

    /**
     * Revisa el contenido real de la imagen antes de guardar nada.
     */
    public static function comprobar(UploadedFile $archivo): void
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($archivo->getRealPath());
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['foto' => 'Seleccione una imagen JPG, PNG o WEBP válida.']);
        }
    }

    public static function guardar(UploadedFile $archivo, int $idPersonal): void
    {
        self::comprobar($archivo);
        $ruta = $archivo->store('fotos-personal/'.$idPersonal, 'documentos_clinicos');
        if (! $ruta) {
            throw ValidationException::withMessages(['foto' => 'No se pudo guardar la foto.']);
        }
        try {
            $anterior = DB::transaction(function () use ($idPersonal, $ruta): ?string {
                $persona = Personal::whereKey($idPersonal)->lockForUpdate()->firstOrFail();
                $anterior = $persona->foto_perfil;
                $persona->update(['foto_perfil' => $ruta]);

                return $anterior;
            });
        } catch (Throwable $error) {
            Storage::disk('documentos_clinicos')->delete($ruta);
            throw $error;
        }
        self::borrar($anterior, $idPersonal);
    }

    public static function quitar(int $idPersonal): void
    {
        $anterior = DB::transaction(function () use ($idPersonal): ?string {
            $persona = Personal::whereKey($idPersonal)->lockForUpdate()->firstOrFail();
            $anterior = $persona->foto_perfil;
            $persona->update(['foto_perfil' => null]);

            return $anterior;
        });
        self::borrar($anterior, $idPersonal);
    }

    private static function borrar(?string $ruta, int $idPersonal): void
    {
        if ($ruta && str_starts_with($ruta, 'fotos-personal/'.$idPersonal.'/') && ! str_contains($ruta, '..')) {
            Storage::disk('documentos_clinicos')->delete($ruta);
        }
    }
}
