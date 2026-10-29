<?php

namespace App\Providers;

use App\Http\Controllers\CirugiaController;
use App\Mail\ResendHttpTransport;
use App\Models\Cirugia;
use App\Models\Hospitalizacion;
use App\Models\NotaEnfermeria;
use App\Support\AuditoriaModelos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
            $view->with('cirugiasPanel', Cirugia::where('id_ingreso', $ingreso->getKey())->with('equipo.cargo')->orderByDesc('fecha_programada')->orderByDesc('id_cirugia')->get());
            $view->with('permisosCirugia', CirugiaController::permisos());
        });
        foreach (['created', 'updated', 'deleted'] as $evento) {
            Event::listen('eloquent.'.$evento.': *', function (string $nombre, array $datos) use ($evento): void {
                app(AuditoriaModelos::class)->registrar($evento, $datos[0]);
            });
        }
        Mail::extend('resend_http', fn (array $config) => new ResendHttpTransport);
    }
}
