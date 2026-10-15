<?php

namespace App\Http\Controllers;

use App\Mail\RecuperarContrasenaMail;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecuperarContrasenaController extends Controller
{
    private const MENSAJE = 'Si los datos corresponden a una cuenta activa, recibirás un código en el correo registrado. Revisa también la carpeta de spam.';

    private function usuarios(): Builder
    {
        return DB::table('tbl_usuario')->where('estado', 'Activo')
            ->whereIn('id_personal', DB::table('tbl_personal')->select('id_personal')->where('estado', 'Activo'))
            ->whereIn('id_rol', DB::table('tbl_rol')->select('id_rol')->where('estado', 'Activo'));
    }

    private function huella(string $valor): string
    {
        return hash_hmac('sha256', $valor, config('app.key'));
    }

    private function vigente(?object $usuario, ?object $registro): bool
    {
        return $usuario && $registro
            && Carbon::parse($registro->created_at)->addMinutes(10)->isFuture()
            && DB::table('tbl_personal')->where('id_personal', $usuario->id_personal)->value('correo') === $registro->correo;
    }

    public function solicitar(): View
    {
        return view('auth.recuperar', ['paso' => 'solicitar']);
    }

    public function enviar(Request $request): RedirectResponse
    {
        $datos = $request->validate(['usuario' => ['required', 'string', 'max:50'], 'correo' => ['required', 'email', 'max:150']]);
        if (app()->isProduction() && ! in_array(config('mail.default'), ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'resend_http', 'mailgun', 'sendmail'], true)) {
            return back()->with('error', 'La recuperación por correo todavía no está disponible. Contacta al administrador.');
        }
        if (config('mail.default') === 'resend_http' && ! config('services.resend.key')) {
            return back()->with('error', 'La recuperación por correo todavía no está disponible. Contacta al administrador.');
        }
        $request->session()->forget(['recuperacion_usuario', 'recuperacion_permiso']);
        $usuario = $this->usuarios()->where('nombre_usuario', $datos['usuario'])->first();
        $personal = $usuario ? DB::table('tbl_personal')->where('id_personal', $usuario->id_personal)->first() : null;
        $coincide = $personal && $personal->correo && Str::lower(trim($personal->correo)) === Str::lower(trim($datos['correo']));
        // La siguiente pantalla es idéntica aunque la cuenta no exista.
        $request->session()->put('recuperacion_usuario', $coincide ? $usuario->id_usuario : 0);
        $limite = 'recuperacion:'.hash('sha256', Str::lower($datos['usuario']));
        if (RateLimiter::tooManyAttempts($limite, 1)) {
            return redirect()->route('password.code')->with('status', self::MENSAJE);
        }
        RateLimiter::hit($limite, 60);
        if (! $coincide) {
            return redirect()->route('password.code')->with('status', self::MENSAJE);
        }
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = $this->huella($codigo);
        // Serializa los reenvíos de una cuenta, incluso entre réplicas.
        $creado = DB::transaction(function () use ($usuario, $personal, $hash): bool {
            DB::table('tbl_usuario')->where('id_usuario', $usuario->id_usuario)->lockForUpdate()->first();
            $anterior = DB::table('recuperacion_contrasenas')->where('id_usuario', $usuario->id_usuario)->first();
            if ($anterior && Carbon::parse($anterior->created_at)->addMinute()->isFuture()) {
                return false;
            }
            DB::table('recuperacion_contrasenas')->updateOrInsert(['id_usuario' => $usuario->id_usuario], [
                'correo' => $personal->correo, 'token' => $hash, 'created_at' => now(), 'intentos' => 0, 'verificado' => false,
            ]);

            return true;
        });
        if ($creado) {
            try {
                Mail::to($personal->correo)->send(new RecuperarContrasenaMail($codigo));
            } catch (\Throwable $error) {
                DB::table('recuperacion_contrasenas')->where('id_usuario', $usuario->id_usuario)->where('token', $hash)->delete();
                logger()->warning('No se pudo enviar el correo de recuperación.', ['tipo' => get_class($error)]);
            }
        }

        return redirect()->route('password.code')->with('status', self::MENSAJE);
    }

    public function codigo(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('recuperacion_usuario')) {
            return redirect()->route('password.request');
        }

        return view('auth.recuperar', ['paso' => 'codigo']);
    }

    public function verificar(Request $request): RedirectResponse
    {
        $datos = $request->validate(['codigo' => ['required', 'string', 'regex:/^[0-9]{6}$/']], [
            'codigo.regex' => 'Escribe los 6 dígitos del código recibido por correo.',
        ]);
        $id = $request->session()->get('recuperacion_usuario', 0);
        $permiso = Str::random(64);
        $valido = DB::transaction(function () use ($datos, $id, $permiso): bool {
            $usuario = $this->usuarios()->where('id_usuario', $id)->lockForUpdate()->first();
            $registro = DB::table('recuperacion_contrasenas')->where('id_usuario', $id)->lockForUpdate()->first();
            if (! $this->vigente($usuario, $registro) || $registro->verificado || $registro->intentos >= 5) {
                return false;
            }
            if (! hash_equals($registro->token, $this->huella($datos['codigo']))) {
                DB::table('recuperacion_contrasenas')->where('id_usuario', $id)->increment('intentos');

                return false;
            }
            // Consume el código y crea una autorización distinta, solo en esta sesión.
            DB::table('recuperacion_contrasenas')->where('id_usuario', $id)->update([
                'token' => $this->huella($permiso), 'verificado' => true, 'created_at' => now(),
            ]);

            return true;
        });
        if (! $valido) {
            throw ValidationException::withMessages(['codigo' => 'El código no es válido, caducó o alcanzó el límite de 5 intentos. Puedes solicitar otro.']);
        }
        $request->session()->regenerate();
        $request->session()->forget('recuperacion_usuario');
        $request->session()->put('recuperacion_permiso', ['usuario' => $id, 'token' => $permiso]);

        return redirect()->route('password.reset');
    }

    public function formulario(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('recuperacion_permiso')) {
            return redirect()->route('password.request');
        }

        return view('auth.recuperar', ['paso' => 'nueva']);
    }

    public function actualizar(Request $request): RedirectResponse
    {
        $permiso = $request->session()->get('recuperacion_permiso');
        if (! $permiso) {
            return redirect()->route('password.request');
        }
        $datos = $request->validate([
            'password' => ['required', 'string', 'confirmed', 'max:72', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        DB::transaction(function () use ($datos, $permiso): void {
            $usuario = $this->usuarios()->where('id_usuario', $permiso['usuario'])->lockForUpdate()->first();
            $registro = DB::table('recuperacion_contrasenas')->where('id_usuario', $permiso['usuario'])->lockForUpdate()->first();
            if (! $this->vigente($usuario, $registro) || ! $registro->verificado
                || ! hash_equals($registro->token, $this->huella($permiso['token']))) {
                throw ValidationException::withMessages(['codigo' => 'La verificación caducó o dejó de ser válida. Solicita otro código.']);
            }
            DB::table('tbl_usuario')->where('id_usuario', $usuario->id_usuario)->update([
                'contrasena' => Hash::make($datos['password']), 'intentos_fallidos' => 0, 'fecha_actualizacion' => now(),
            ]);
            DB::table('recuperacion_contrasenas')->where('id_usuario', $usuario->id_usuario)->delete();
        });
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }
}
