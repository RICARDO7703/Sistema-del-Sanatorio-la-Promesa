<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
        $request->session()->regenerate();

        session([
            'id_usuario' => $usuario->id_usuario,
        ]);

        return redirect('/inicio');
    }

    public function cerrarSesion(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
