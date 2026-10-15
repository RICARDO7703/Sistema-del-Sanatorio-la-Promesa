<?php

use App\Http\Controllers\AltaMedicaController;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\EvolucionController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\HabitacionController;
use App\Http\Controllers\HospitalizacionController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\RecuperarContrasenaController;
use App\Http\Controllers\SignoVitalController;
use App\Http\Controllers\TratamientoController;
use App\Http\Middleware\SesionClinica;
use Illuminate\Support\Facades\Route;

Route::get('/', [LoginController::class, 'index']);
Route::get('/recuperar-contrasena', [RecuperarContrasenaController::class, 'solicitar'])->name('password.request');
Route::post('/recuperar-contrasena', [RecuperarContrasenaController::class, 'enviar'])->middleware('throttle:5,1')->name('password.email');
Route::get('/verificar-codigo', [RecuperarContrasenaController::class, 'codigo'])->name('password.code');
Route::post('/verificar-codigo', [RecuperarContrasenaController::class, 'verificar'])->middleware('throttle:5,1')->name('password.verify');
Route::get('/restablecer-contrasena', [RecuperarContrasenaController::class, 'formulario'])->name('password.reset');
Route::post('/restablecer-contrasena', [RecuperarContrasenaController::class, 'actualizar'])->middleware('throttle:5,1')->name('password.update');

Route::post('/login', [LoginController::class, 'validar'])
    ->middleware('throttle:5,1')->name('login.validar');

Route::post('/logout', [LoginController::class, 'cerrarSesion'])
    ->name('logout');

Route::middleware(SesionClinica::class)->group(function () {

    Route::get('/inicio', [InicioController::class, 'index']);

    Route::get('/pacientes', [PacienteController::class, 'index'])
        ->name('pacientes.index');

    Route::post('/pacientes', [PacienteController::class, 'guardar'])
        ->name('pacientes.guardar');

    Route::put('/pacientes/{paciente}', [PacienteController::class, 'actualizar'])
        ->name('pacientes.actualizar');

    Route::patch('/pacientes/{paciente}/desactivar', [PacienteController::class, 'eliminar'])
        ->name('pacientes.desactivar');

    Route::patch('/pacientes/{paciente}/activar', [PacienteController::class, 'activar'])
        ->name('pacientes.activar');

    Route::post('/pacientes/{paciente}/contactos', [PacienteController::class, 'guardarContacto'])
        ->name('pacientes.contactos.guardar');

    Route::put('/pacientes/contactos/{contacto}', [PacienteController::class, 'actualizarContacto'])
        ->name('pacientes.contactos.actualizar');

    Route::delete('/pacientes/contactos/{contacto}', [PacienteController::class, 'eliminarContacto'])
        ->name('pacientes.contactos.eliminar');

    Route::get('/pacientes/{paciente}/expediente', [ExpedienteController::class, 'show'])->name('pacientes.expediente');

    Route::get('/evolucion', [EvolucionController::class, 'index'])
        ->name('evolucion.index');

    Route::post('/evolucion', [EvolucionController::class, 'guardar'])
        ->name('evolucion.guardar');

    Route::put('/evolucion/{evolucion}', [EvolucionController::class, 'actualizar'])
        ->name('evolucion.actualizar');

    Route::delete('/evolucion/{evolucion}', [EvolucionController::class, 'eliminar'])
        ->name('evolucion.eliminar');

    Route::get('/bitacora', [BitacoraController::class, 'index']);

    Route::get('/personal', [PersonalController::class, 'index'])
        ->name('personal.index');

    Route::post('/personal', [PersonalController::class, 'guardar'])
        ->name('personal.guardar');

    Route::put('/personal/{personal}', [PersonalController::class, 'actualizar'])
        ->name('personal.actualizar');

    Route::patch('/personal/{personal}/desactivar', [PersonalController::class, 'eliminar'])
        ->name('personal.desactivar');

    Route::patch('/personal/{personal}/activar', [PersonalController::class, 'activar'])
        ->name('personal.activar');

    Route::get('/habitaciones', [HabitacionController::class, 'index'])
        ->name('habitaciones.index');

    Route::post('/habitaciones', [HabitacionController::class, 'guardar'])
        ->name('habitaciones.guardar');

    Route::put('/habitaciones/{habitacion}', [HabitacionController::class, 'actualizar'])
        ->name('habitaciones.actualizar');

    Route::get('/ingresos', [IngresoController::class, 'index'])
        ->name('ingresos.index');

    Route::post('/ingresos', [IngresoController::class, 'guardar'])
        ->name('ingresos.guardar');

    Route::put('/ingresos/{ingreso}', [IngresoController::class, 'actualizar'])
        ->name('ingresos.actualizar');

    Route::patch('/ingresos/{ingreso}/cancelar', [IngresoController::class, 'cancelar'])
        ->name('ingresos.cancelar');

    Route::post(
        '/atencion/{ingreso}/hospitalizacion',
        [HospitalizacionController::class, 'guardar']
    )->name('hospitalizacion.guardar');

    Route::patch(
        '/hospitalizacion/{hospitalizacion}/cambiar-habitacion',
        [HospitalizacionController::class, 'cambiarHabitacion']
    )->name('hospitalizacion.cambiar-habitacion');

    Route::get('/signos-vitales', [SignoVitalController::class, 'index'])
        ->name('signos.index');

    Route::post('/signos-vitales', [SignoVitalController::class, 'guardar'])
        ->name('signos.guardar');

    Route::put('/signos-vitales/{signo}', [SignoVitalController::class, 'actualizar'])
        ->name('signos.actualizar');

    Route::get('/tratamientos', [TratamientoController::class, 'index'])
        ->name('tratamientos.index');

    Route::post('/tratamientos', [TratamientoController::class, 'guardar'])
        ->name('tratamientos.guardar');

    Route::put('/tratamientos/{tratamiento}', [TratamientoController::class, 'actualizar'])
        ->name('tratamientos.actualizar');

    Route::delete('/tratamientos/{tratamiento}', [TratamientoController::class, 'eliminar'])
        ->name('tratamientos.eliminar');

    Route::get('/atencion', [AtencionController::class, 'index'])
        ->name('atencion.index');

    Route::get('/atencion/{ingreso}', [AtencionController::class, 'show'])
        ->name('atencion.show');

    Route::post('/atencion/{ingreso}/alta', [AltaMedicaController::class, 'guardar'])->name('alta.guardar');
});
