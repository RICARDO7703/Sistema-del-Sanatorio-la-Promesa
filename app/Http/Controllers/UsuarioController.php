<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use App\Support\FotoPersonal;
use App\Support\Permisos;
use App\Support\RegistroBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Cuentas de acceso del personal y sus permisos por módulo. Solo el administrador entra aquí.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::in(['Activo', 'Inactivo', 'Bloqueado', 'Todos'])], 'buscar' => ['nullable', 'string', 'max:100']]);
        $estado = $filtros['estado'] ?? 'Activo';
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $usuarios = DB::table('tbl_usuario as u')
            ->join('tbl_personal as p', 'p.id_personal', '=', 'u.id_personal')
            ->join('tbl_rol as r', 'r.id_rol', '=', 'u.id_rol')
            ->leftJoin('tbl_cargo as c', 'c.id_cargo', '=', 'p.id_cargo')
            ->when($estado !== 'Todos', fn ($consulta) => $consulta->where('u.estado', $estado))
            ->when($buscar !== '', fn ($consulta) => $consulta->where(fn ($q) => $q->where('u.nombre_usuario', 'like', '%'.$buscar.'%')
                ->orWhere('p.nombres', 'like', '%'.$buscar.'%')->orWhere('p.apellidos', 'like', '%'.$buscar.'%')))
            ->orderBy('p.nombres')->orderBy('p.apellidos')
            ->select('u.id_usuario', 'u.nombre_usuario', 'u.estado', 'u.ultimo_acceso', 'p.nombres', 'p.apellidos', 'c.nombre_cargo', 'r.nombre_rol')
            ->paginate(20)->withQueryString();
        $personasSinCuenta = $this->personasSinCuenta()->count();

        return view('usuarios.index', compact('usuarios', 'estado', 'buscar', 'personasSinCuenta'));
    }

    public function crear(): View
    {
        return view('usuarios.formulario', [
            'usuario' => null, 'personas' => $this->personasSinCuenta(), 'roles' => $this->roles(), 'marcados' => [],
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $this->normalizarNombre($request);
        $datos = $request->validate([
            'id_personal' => ['required', 'integer', Rule::exists('tbl_personal', 'id_personal')->where('estado', 'Activo'), Rule::unique('tbl_usuario', 'id_personal')],
            'nombre_usuario' => $this->reglaNombreUsuario(),
            'id_rol' => ['required', 'integer', Rule::exists('tbl_rol', 'id_rol')->where('estado', 'Activo')],
            'contrasena' => $this->reglaContrasena(),
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['string', Rule::in($this->clavesPermitidas())],
            'foto' => FotoPersonal::reglas(),
        ], $this->mensajes() + FotoPersonal::mensajes());
        if ($request->hasFile('foto')) {
            FotoPersonal::comprobar($request->file('foto'));
        }

        $idUsuario = DB::transaction(function () use ($request, $datos): int {
            $idUsuario = (int) DB::table('tbl_usuario')->insertGetId([
                'id_personal' => $datos['id_personal'], 'id_rol' => $datos['id_rol'],
                'nombre_usuario' => mb_strtolower(trim($datos['nombre_usuario'])), 'contrasena' => Hash::make($datos['contrasena']),
                'estado' => 'Activo', 'intentos_fallidos' => 0,
            ], 'id_usuario');
            $this->guardarPermisos($idUsuario, (int) $datos['id_rol'], $datos['permisos'] ?? []);
            app(RegistroBitacora::class)->registrar($request, 'Creación de usuario', 'Exitoso', 'Se creó la cuenta «'.mb_strtolower(trim($datos['nombre_usuario'])).'».', (int) session('id_usuario'), $idUsuario);

            return $idUsuario;
        });
        if ($request->hasFile('foto')) {
            FotoPersonal::guardar($request->file('foto'), (int) $datos['id_personal']);
        }

        return redirect()->route('usuarios.editar', $idUsuario)->with('exito', 'Usuario creado. Entregue la contraseña a la persona y pídale que la cambie en «Mi perfil».');
    }

    public function editar(int $usuario): View
    {
        $cuenta = $this->cuenta($usuario);

        return view('usuarios.formulario', [
            'usuario' => $cuenta, 'personas' => collect(), 'roles' => $this->roles(), 'marcados' => app(Permisos::class)->deUsuario($cuenta->id_usuario),
        ]);
    }

    public function actualizar(Request $request, int $usuario): RedirectResponse
    {
        $cuenta = $this->cuenta($usuario);
        $this->normalizarNombre($request);
        $datos = $request->validate([
            'nombre_usuario' => $this->reglaNombreUsuario($cuenta->id_usuario),
            'id_rol' => ['required', 'integer', Rule::exists('tbl_rol', 'id_rol')->where('estado', 'Activo')],
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['string', Rule::in($this->clavesPermitidas())],
            'foto' => FotoPersonal::reglas(),
            'quitar_foto' => ['nullable', 'boolean'],
        ], $this->mensajes() + FotoPersonal::mensajes());
        if ($request->hasFile('foto')) {
            FotoPersonal::comprobar($request->file('foto'));
        }
        $quedaAdministrador = Permisos::rolEsAdministrador(DB::table('tbl_rol')->where('id_rol', $datos['id_rol'])->value('nombre_rol'));
        if (Permisos::rolEsAdministrador($cuenta->nombre_rol) && ! $quedaAdministrador) {
            if ((int) $cuenta->id_usuario === (int) session('id_usuario')) {
                throw ValidationException::withMessages(['id_rol' => 'No puede quitarse a sí mismo el rol de administrador.']);
            }
            $this->exigirOtroAdministrador($cuenta->id_usuario);
        }

        DB::transaction(function () use ($request, $cuenta, $datos): void {
            DB::table('tbl_usuario')->where('id_usuario', $cuenta->id_usuario)->update([
                'nombre_usuario' => mb_strtolower(trim($datos['nombre_usuario'])), 'id_rol' => $datos['id_rol'],
            ]);
            $this->guardarPermisos($cuenta->id_usuario, (int) $datos['id_rol'], $datos['permisos'] ?? []);
            app(RegistroBitacora::class)->registrar($request, 'Edición de usuario', 'Exitoso', 'Se actualizaron el rol y los permisos de «'.$cuenta->nombre_usuario.'».', (int) session('id_usuario'), $cuenta->id_usuario);
        });
        Permisos::olvidar($cuenta->id_usuario);
        if ($request->hasFile('foto')) {
            FotoPersonal::guardar($request->file('foto'), (int) $cuenta->id_personal);
        } elseif ($request->boolean('quitar_foto')) {
            FotoPersonal::quitar((int) $cuenta->id_personal);
        }

        return redirect()->route('usuarios.editar', $cuenta->id_usuario)->with('exito', 'Cambios guardados. Se aplican desde la siguiente pantalla que abra el usuario.');
    }

    /**
     * El administrador asigna una contraseña nueva, por ejemplo cuando alguien la olvidó y no tiene correo.
     */
    public function contrasena(Request $request, int $usuario): RedirectResponse
    {
        $cuenta = $this->cuenta($usuario);
        $datos = $request->validate(['contrasena' => $this->reglaContrasena()], $this->mensajes());
        DB::transaction(function () use ($request, $cuenta, $datos): void {
            DB::table('tbl_usuario')->where('id_usuario', $cuenta->id_usuario)->update([
                'contrasena' => Hash::make($datos['contrasena']), 'intentos_fallidos' => 0,
                'estado' => $cuenta->estado === 'Bloqueado' ? 'Activo' : $cuenta->estado,
            ]);
            app(RegistroBitacora::class)->registrar($request, 'Restablecimiento de contraseña', 'Exitoso', 'El administrador asignó una contraseña nueva a «'.$cuenta->nombre_usuario.'».', (int) session('id_usuario'), $cuenta->id_usuario);
        });

        return redirect()->route('usuarios.editar', $cuenta->id_usuario)->with('exito', 'Contraseña restablecida. Entréguela a la persona y pídale que la cambie en «Mi perfil».');
    }

    public function estado(Request $request, int $usuario): RedirectResponse
    {
        $cuenta = $this->cuenta($usuario);
        $nuevo = $cuenta->estado === 'Activo' ? 'Inactivo' : 'Activo';
        if ($nuevo === 'Inactivo') {
            if ((int) $cuenta->id_usuario === (int) session('id_usuario')) {
                return back()->with('error', 'No puede desactivar su propia cuenta.');
            }
            if (Permisos::rolEsAdministrador($cuenta->nombre_rol)) {
                $this->exigirOtroAdministrador($cuenta->id_usuario);
            }
        }
        DB::transaction(function () use ($request, $cuenta, $nuevo): void {
            DB::table('tbl_usuario')->where('id_usuario', $cuenta->id_usuario)->update(['estado' => $nuevo, 'intentos_fallidos' => 0]);
            app(RegistroBitacora::class)->registrar($request, $nuevo === 'Activo' ? 'Activación de usuario' : 'Desactivación de usuario', 'Exitoso', 'Cuenta «'.$cuenta->nombre_usuario.'» '.($nuevo === 'Activo' ? 'activada' : 'desactivada').'.', (int) session('id_usuario'), $cuenta->id_usuario);
        });

        return back()->with('exito', $nuevo === 'Activo' ? 'Usuario activado. Ya puede iniciar sesión.' : 'Usuario desactivado. Ya no podrá iniciar sesión.');
    }

    /**
     * Guarda los permisos marcados. Si se marca Guardar, Editar o Eliminar en un módulo que tiene «Ver», se agrega «Ver».
     * El administrador no necesita permisos guardados porque siempre tiene acceso a todo.
     *
     * @param  list<string>  $claves
     */
    private function guardarPermisos(int $idUsuario, int $idRol, array $claves): void
    {
        DB::table('tbl_usuario_permiso')->where('id_usuario', $idUsuario)->delete();
        if (Permisos::rolEsAdministrador(DB::table('tbl_rol')->where('id_rol', $idRol)->value('nombre_rol'))) {
            return;
        }
        $claves = collect($claves)->unique();
        foreach ($claves->all() as $clave) {
            [$modulo] = explode('|', $clave, 2);
            if (in_array(Permisos::VER, Permisos::MODULOS[$modulo][2], true)) {
                $claves->push($modulo.'|'.Permisos::VER);
            }
        }
        $ids = DB::table('tbl_permiso as pe')->join('tbl_modulo as m', 'm.id_modulo', '=', 'pe.id_modulo')
            ->get(['pe.id_permiso', 'm.nombre_modulo', 'pe.accion'])
            ->mapWithKeys(fn ($fila) => [$fila->nombre_modulo.'|'.$fila->accion => $fila->id_permiso]);
        foreach ($claves->unique()->all() as $clave) {
            if (isset($ids[$clave])) {
                DB::table('tbl_usuario_permiso')->insert(['id_usuario' => $idUsuario, 'id_permiso' => $ids[$clave]]);
            }
        }
    }

    /**
     * El nombre de usuario se guarda en minúsculas y sin espacios alrededor, para que «Admin» y «admin» sean el mismo.
     */
    private function normalizarNombre(Request $request): void
    {
        if (is_string($request->input('nombre_usuario'))) {
            $request->merge(['nombre_usuario' => mb_strtolower(trim($request->input('nombre_usuario')))]);
        }
    }

    private function exigirOtroAdministrador(int $idUsuario): void
    {
        $otros = DB::table('tbl_usuario as u')->join('tbl_rol as r', 'r.id_rol', '=', 'u.id_rol')
            ->where('u.estado', 'Activo')->where('u.id_usuario', '<>', $idUsuario)->pluck('r.nombre_rol')
            ->filter(fn ($rol) => Permisos::rolEsAdministrador($rol))->count();
        if ($otros === 0) {
            throw ValidationException::withMessages(['id_rol' => 'Debe quedar al menos otro administrador activo.']);
        }
    }

    private function cuenta(int $idUsuario): object
    {
        $cuenta = DB::table('tbl_usuario as u')
            ->join('tbl_personal as p', 'p.id_personal', '=', 'u.id_personal')
            ->join('tbl_rol as r', 'r.id_rol', '=', 'u.id_rol')
            ->leftJoin('tbl_cargo as c', 'c.id_cargo', '=', 'p.id_cargo')
            ->where('u.id_usuario', $idUsuario)
            ->select('u.id_usuario', 'u.nombre_usuario', 'u.estado', 'u.id_rol', 'u.ultimo_acceso', 'u.id_personal', 'p.nombres', 'p.apellidos', 'p.foto_perfil', 'c.nombre_cargo', 'r.nombre_rol')
            ->first();
        abort_unless($cuenta, 404);
        $cuenta->id_usuario = (int) $cuenta->id_usuario;

        return $cuenta;
    }

    /**
     * @return Collection<int, Personal>
     */
    private function personasSinCuenta(): Collection
    {
        return Personal::with('cargo')->where('estado', 'Activo')
            ->whereNotIn('id_personal', DB::table('tbl_usuario')->select('id_personal'))
            ->orderBy('nombres')->orderBy('apellidos')->get();
    }

    private function roles(): Collection
    {
        return DB::table('tbl_rol')->where('estado', 'Activo')->orderBy('id_rol')->get(['id_rol', 'nombre_rol']);
    }

    /**
     * @return list<string>
     */
    private function clavesPermitidas(): array
    {
        return collect(Permisos::MODULOS)->flatMap(fn ($modulo, $nombre) => array_map(fn ($accion) => $nombre.'|'.$accion, $modulo[2]))->values()->all();
    }

    /**
     * @return list<mixed>
     */
    private function reglaNombreUsuario(?int $ignorar = null): array
    {
        return ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('tbl_usuario', 'nombre_usuario')->ignore($ignorar, 'id_usuario')];
    }

    /**
     * @return list<mixed>
     */
    private function reglaContrasena(): array
    {
        return ['required', 'string', 'confirmed', 'max:72', Password::min(12)->letters()->mixedCase()->numbers()->symbols()];
    }

    /**
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        return [
            'id_personal.required' => 'Elija a la persona del personal que usará la cuenta.',
            'id_personal.exists' => 'La persona elegida no existe o está inactiva.',
            'id_personal.unique' => 'Esa persona ya tiene una cuenta.',
            'nombre_usuario.required' => 'Escriba el nombre de usuario.',
            'nombre_usuario.min' => 'El nombre de usuario debe tener al menos 3 caracteres.',
            'nombre_usuario.max' => 'El nombre de usuario no puede pasar de 50 caracteres.',
            'nombre_usuario.regex' => 'Use solo letras sin tilde, números, punto, guion o guion bajo. Por ejemplo: dra.lopez',
            'nombre_usuario.unique' => 'Ese nombre de usuario ya existe. Elija otro.',
            'id_rol.required' => 'Elija el rol.',
            'id_rol.exists' => 'El rol elegido no existe o está inactivo.',
            'contrasena.required' => 'Escriba la contraseña.',
            'contrasena.confirmed' => 'Las dos contraseñas no coinciden.',
            'contrasena.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'contrasena.mixed' => 'La contraseña debe tener mayúsculas y minúsculas.',
            'contrasena.numbers' => 'La contraseña debe tener al menos un número.',
            'contrasena.symbols' => 'La contraseña debe tener al menos un símbolo, por ejemplo # o !.',
            'contrasena.letters' => 'La contraseña debe tener letras.',
            'permisos.*.in' => 'Uno de los permisos marcados no es válido.',
        ];
    }
}
