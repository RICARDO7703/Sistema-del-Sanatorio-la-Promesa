<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use App\Support\RegistroBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class PerfilController extends Controller
{
    private function cuenta(): object
    {
        return DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->firstOrFail();
    }

    private function comprobarClave(string $clave, object $cuenta, string $campo = 'contrasena_actual'): void
    {
        try {
            $valida = Hash::check($clave, $cuenta->contrasena);
        } catch (\RuntimeException) {
            $valida = false;
        }
        if (! $valida) {
            throw ValidationException::withMessages([$campo => 'La contraseña actual no es correcta.']);
        }
    }

    /**
     * Mi perfil: datos de la cuenta y datos de contacto.
     */
    public function show(): View
    {
        return $this->pantalla('perfil');
    }

    /**
     * Pantalla que solo muestra la foto de perfil.
     */
    public function editarFoto(): View
    {
        return $this->pantalla('foto');
    }

    /**
     * Pantalla que solo muestra el cambio de contraseña.
     */
    public function editarContrasena(): View
    {
        return $this->pantalla('contrasena');
    }

    /**
     * @param  'perfil'|'foto'|'contrasena'  $seccion
     */
    private function pantalla(string $seccion): View
    {
        $cuenta = $this->cuenta();
        $persona = Personal::with('cargo')->findOrFail($cuenta->id_personal);
        $rol = DB::table('tbl_rol')->where('id_rol', $cuenta->id_rol)->value('nombre_rol');

        return view('perfil.show', ['seccion' => $seccion, 'persona' => $persona, 'nombreUsuario' => $cuenta->nombre_usuario, 'rol' => $rol, 'estadoCuenta' => $cuenta->estado]);
    }

    public function contacto(Request $request): RedirectResponse
    {
        $cuenta = $this->cuenta();
        $persona = Personal::findOrFail($cuenta->id_personal);
        $request->validate(['correo' => ['nullable', 'string']]);
        $request->merge(['correo' => $request->filled('correo') ? mb_strtolower(trim((string) $request->input('correo'))) : null]);
        $datos = $request->validate([
            'telefono' => ['nullable', 'regex:/^(?:[0-9]{8}|[0-9]{4}-[0-9]{4})$/'],
            'correo' => ['nullable', 'email', 'max:150', Rule::unique('tbl_personal', 'correo')->ignore($persona->getKey(), 'id_personal')],
            'clave_contacto' => ['nullable', 'string', 'max:100'],
        ], ['telefono.regex' => 'El teléfono debe contener 8 dígitos.', 'correo.unique' => 'Este correo ya pertenece a otra persona.']);
        DB::transaction(function () use ($datos, $cuenta) {
            $usuario = DB::table('tbl_usuario')->where('id_usuario', $cuenta->id_usuario)->lockForUpdate()->firstOrFail();
            $actual = Personal::whereKey($usuario->id_personal)->lockForUpdate()->firstOrFail();
            $cambiaCorreo = ($datos['correo'] ?? null) !== $actual->correo;
            if ($cambiaCorreo) {
                $this->comprobarClave($datos['clave_contacto'] ?? '', $usuario, 'clave_contacto');
            }
            $telefono = isset($datos['telefono']) ? str_replace('-', '', $datos['telefono']) : null;
            $actual->update(['telefono' => $telefono ? substr($telefono, 0, 4).'-'.substr($telefono, 4) : null, 'correo' => $datos['correo'] ?? null]);
            if ($cambiaCorreo && Schema::hasTable('recuperacion_contrasenas')) {
                DB::table('recuperacion_contrasenas')->where('id_usuario', $usuario->id_usuario)->delete();
            }
        });

        return redirect()->route('perfil.show')->with('exito', 'Datos de contacto actualizados.');
    }

    public function foto(Request $request): RedirectResponse
    {
        $request->validate(['foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096']], ['foto.max' => 'La foto no puede superar los 2 MB.', 'foto.dimensions' => 'La foto debe medir entre 64 y 4096 píxeles por lado.']);
        $archivo = $request->file('foto');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($archivo->getRealPath());
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['foto' => 'Seleccione una imagen JPG, PNG o WEBP válida.']);
        }
        $cuenta = $this->cuenta();
        $ruta = $archivo->store('fotos-personal/'.$cuenta->id_personal, 'documentos_clinicos');
        if (! $ruta) {
            throw ValidationException::withMessages(['foto' => 'No se pudo guardar la foto.']);
        }
        try {
            $anterior = DB::transaction(function () use ($cuenta, $ruta) {
                $persona = Personal::whereKey($cuenta->id_personal)->lockForUpdate()->firstOrFail();
                $anterior = $persona->foto_perfil;
                $persona->update(['foto_perfil' => $ruta]);

                return $anterior;
            });
        } catch (Throwable $error) {
            Storage::disk('documentos_clinicos')->delete($ruta);
            throw $error;
        }
        $this->borrarFoto($anterior, (int) $cuenta->id_personal);

        return redirect()->route('perfil.foto.editar')->with('exito', 'Foto de perfil actualizada.');
    }

    public function quitarFoto(): RedirectResponse
    {
        $cuenta = $this->cuenta();
        $anterior = DB::transaction(function () use ($cuenta) {
            $p = Personal::whereKey($cuenta->id_personal)->lockForUpdate()->firstOrFail();
            $foto = $p->foto_perfil;
            $p->update(['foto_perfil' => null]);

            return $foto;
        });
        $this->borrarFoto($anterior, (int) $cuenta->id_personal);

        return redirect()->route('perfil.foto.editar')->with('exito', 'Foto eliminada. Se mostrará el icono de usuario.');
    }

    private function borrarFoto(?string $ruta, int $personal): void
    {
        if ($ruta && str_starts_with($ruta, 'fotos-personal/'.$personal.'/') && ! str_contains($ruta, '..')) {
            Storage::disk('documentos_clinicos')->delete($ruta);
        }
    }

    public function imagen(Personal $personaFoto): BinaryFileResponse
    {
        $ruta = $personaFoto->foto_perfil;
        abort_unless($ruta && str_starts_with($ruta, 'fotos-personal/'.$personaFoto->getKey().'/') && ! str_contains($ruta, '..'), 404);
        $disk = Storage::disk('documentos_clinicos');
        abort_unless($disk->exists($ruta), 404);
        $mime = $disk->mimeType($ruta);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 415);

        return response()->file($disk->path($ruta), ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function contrasena(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'contrasena_actual' => ['required', 'string', 'max:100'],
            'nueva_contrasena' => ['required', 'string', 'confirmed', 'different:contrasena_actual', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols(), function (string $attribute, mixed $value, \Closure $fail): void {
                if (strlen($value) > 72) {
                    $fail('La nueva contraseña supera el máximo permitido de 72 bytes.');
                }
            }],
        ]);
        DB::transaction(function () use ($datos) {
            $cuenta = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->lockForUpdate()->firstOrFail();
            $this->comprobarClave($datos['contrasena_actual'], $cuenta);
            DB::table('tbl_usuario')->where('id_usuario', $cuenta->id_usuario)->update(['contrasena' => Hash::make($datos['nueva_contrasena'])]);
            app(RegistroBitacora::class)->registrar(request(), 'Cambio de contraseña', 'Exitoso', 'El usuario cambió su contraseña desde Mi perfil.', (int) $cuenta->id_usuario, (int) $cuenta->id_usuario);
            if (Schema::hasTable('recuperacion_contrasenas')) {
                DB::table('recuperacion_contrasenas')->where('id_usuario', $cuenta->id_usuario)->delete();
            }
        });
        $request->session()->forget('recuperacion_verificada');
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('perfil.contrasena.editar')->with('exito', 'Contraseña actualizada correctamente.');
    }
}
