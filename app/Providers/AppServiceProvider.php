<?php

namespace App\Providers;

use App\Http\Controllers\CirugiaController;
use App\Mail\ResendHttpTransport;
use App\Models\Cirugia;
use App\Models\Hospitalizacion;
use App\Models\NotaEnfermeria;
use App\Support\AuditoriaModelos;
use App\Support\CambiosEdicion;
use App\Support\Permisos;
use App\Support\RegistroBitacora;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CambiosEdicion::class);
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $cuenta = DB::table('tbl_usuario as u')->join('tbl_personal as p', 'p.id_personal', '=', 'u.id_personal')->join('tbl_cargo as c', 'c.id_cargo', '=', 'p.id_cargo')->where('u.id_usuario', session('id_usuario'))->select('p.id_personal', 'p.nombres', 'p.apellidos', 'p.foto_perfil', 'u.nombre_usuario', 'c.nombre_cargo')->first();
            $view->with('cuentaActual', $cuenta);

            $turno = NotaEnfermeria::turnoPorHora((int) now('America/Guatemala')->format('G'));
            $view->with('turnoActual', ['nombre' => $turno, 'horario' => NotaEnfermeria::horarioTurno($turno)]);
            $view->with('internadosActuales', Hospitalizacion::where('estado', 'Activa')->whereHas('ingreso', fn ($consulta) => $consulta->where('estado', 'Activo'))->count());
        });
        View::composer('cirugias.panel', function ($view): void {
            $ingreso = $view->getData()['ingreso'];
            $cirugias = Cirugia::where('id_ingreso', $ingreso->getKey())->with(['equipo.cargo', 'controles.personal', 'controles.nota'])->orderByDesc('fecha_programada')->orderByDesc('id_cirugia')->get();
            $permisos = CirugiaController::permisos();
            $view->with('cirugiasPanel', $cirugias);
            $view->with('idCirugiaControl', $cirugias->where('estado', 'Realizada')->whereNotNull('fecha_fin_real')
                ->sortBy([['fecha_fin_real', 'desc'], ['id_cirugia', 'desc']])->first()?->getKey());
            $view->with('permisosCirugia', $permisos);
            // La lista de personal solo hace falta si alguna cirugía todavía puede corregirse.
            $editable = $permisos['editar'] && $cirugias->contains(fn (Cirugia $cirugia) => $cirugia->setRelation('ingreso', $ingreso)->puedeCorregirDatos());
            $finalizable = $cirugias->contains(fn (Cirugia $cirugia) => $cirugia->estado === 'En cirugía' && $cirugia->permiteAccion('terminar'));
            $view->with('personalQuirurgico', ($editable || $finalizable) ? CirugiaController::personalQuirurgico() : collect());
        });
        // Formularios de edición: se cuenta lo que cambia y no se guarda un registro si solo cambiaría quién lo modificó.
        Event::listen('eloquent.updating: *', fn (string $nombre, array $datos) => app(CambiosEdicion::class)->antesDeActualizar($datos[0]));
        Event::listen(['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *'], fn () => app(CambiosEdicion::class)->registrar());
        foreach (['created', 'updated', 'deleted'] as $evento) {
            Event::listen('eloquent.'.$evento.': *', function (string $nombre, array $datos) use ($evento): void {
                app(AuditoriaModelos::class)->registrar($evento, $datos[0]);
            });
        }
        Mail::extend('resend_http', fn (array $config) => new ResendHttpTransport);
        Blade::if('puede', fn (string $modulo, string $accion) => app(Permisos::class)->puede($modulo, $accion));
        Blade::if('administrador', fn () => app(Permisos::class)->esAdministrador());
        $this->configurarLimitesDeIntentos();
    }

    /**
     * Cada límite lleva su propio contador. Así los avisos de cierre de página, los intentos
     * de otra persona o la recuperación de contraseña no bloquean el inicio de sesión de nadie.
     * Los intentos se cuentan por usuario escrito y por IP; el límite por IP es amplio porque
     * todo el personal del sanatorio puede salir a internet con la misma dirección.
     */
    private function configurarLimitesDeIntentos(): void
    {
        $usuarioEscrito = function (Request $request): string {
            $usuario = $request->input('usuario');

            return is_string($usuario) ? mb_strtolower(trim($usuario)) : '';
        };

        RateLimiter::for('inicio-sesion', fn (Request $request) => [
            Limit::perMinute(5)->by('usuario:'.$usuarioEscrito($request).'|'.RegistroBitacora::ipCliente($request)),
            Limit::perMinute(30)->by('red:'.RegistroBitacora::ipCliente($request)),
        ]);
        RateLimiter::for('solicitud-recuperacion', fn (Request $request) => [
            Limit::perMinute(5)->by('usuario:'.$usuarioEscrito($request).'|'.RegistroBitacora::ipCliente($request)),
            Limit::perMinute(20)->by('red:'.RegistroBitacora::ipCliente($request)),
        ]);
        RateLimiter::for('codigo-recuperacion', fn (Request $request) => [
            Limit::perMinute(5)->by('sesion:'.$request->session()->getId()),
            Limit::perMinute(30)->by('red:'.RegistroBitacora::ipCliente($request)),
        ]);
        RateLimiter::for('nueva-contrasena', fn (Request $request) => [
            Limit::perMinute(5)->by('sesion:'.$request->session()->getId()),
            Limit::perMinute(30)->by('red:'.RegistroBitacora::ipCliente($request)),
        ]);
        RateLimiter::for('cambio-contrasena', fn (Request $request) => Limit::perMinute(5)->by('usuario:'.$request->session()->get('id_usuario')));
        RateLimiter::for('salida-pagina', fn (Request $request) => Limit::perMinute(60)->by('sesion:'.$request->session()->getId()));
    }
}
