<?php

namespace App\Http\Controllers;

use App\Support\RegistroBitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    public function index()
    {
        return view('login');
    }

    public function validar(Request $request)
    {
        $request->validate([
            'usuario' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
            ],
            'contrasena' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $usuario = DB::table('tbl_usuario')
            ->where('nombre_usuario', $request->usuario)
            ->where('estado', 'Activo')
            ->whereIn('id_personal', DB::table('tbl_personal')->select('id_personal')->where('estado', 'Activo'))
            ->whereIn('id_rol', DB::table('tbl_rol')->select('id_rol')->where('estado', 'Activo'))
            ->first();

        if (! $usuario) {
            return back()->with('error', 'Usuario o contraseña incorrectos.');
        }

        try {
            $valida = Hash::check($request->contrasena, $usuario->contrasena);
        } catch (\RuntimeException $error) {
            $valida = false;
        }
        if (! $valida) {
            return back()->with('error', 'Usuario o contraseña incorrectos.');
        }

        $request->attributes->set('bitacora_usuario_autenticado', $usuario->id_usuario);
        DB::table('tbl_usuario')->where('id_usuario', $usuario->id_usuario)->update(['ultimo_acceso' => now('UTC'), 'intentos_fallidos' => 0]);
        $request->session()->regenerate();

        session([
            'id_usuario' => $usuario->id_usuario,
        ]);

        return redirect('/inicio');
    }

    public function salidaPagina(Request $request): Response
    {
        $datos = $request->validate(['pagina' => ['required', 'uuid']]);
        $id = (int) $request->session()->get('id_usuario');
        $clave = 'salida-pagina:'.$id.':'.$datos['pagina'];
        if (Cache::add($clave, true, now()->addHours(2))) {
            app(RegistroBitacora::class)->registrar($request, 'Posible cierre de página', 'Exitoso', 'Aviso recibido del navegador al abandonar la página. Puede ser cierre, recarga o navegación. No confirma cierre de sesión ni invalida otras pestañas.', $id, $id);
        }

        return response()->noContent();
    }

    public function cerrarSesion(Request $request)
    {
        if ($id = $request->session()->get('id_usuario')) {
            app(RegistroBitacora::class)->registrar($request, 'Cierre de sesión', 'Exitoso', 'El usuario cerró su sesión mediante el botón de salida.', (int) $id, (int) $id);
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
