<?php

use App\Http\Controllers\AltaMedicaController;
use App\Http\Controllers\ArchivoClinicoController;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CirugiaController;
use App\Http\Controllers\EmergenciaController;
use App\Http\Controllers\EvolucionController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\HabitacionController;
use App\Http\Controllers\HospitalizacionController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NotaEnfermeriaController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\RecuperarContrasenaController;
use App\Http\Controllers\SeguimientoController;
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

    Route::get('/mi-perfil', [PerfilController::class, 'show'])->name('perfil.show');
    Route::patch('/mi-perfil/contacto', [PerfilController::class, 'contacto'])->name('perfil.contacto');
    Route::post('/mi-perfil/foto', [PerfilController::class, 'foto'])->name('perfil.foto');
    Route::delete('/mi-perfil/foto', [PerfilController::class, 'quitarFoto'])->name('perfil.quitar-foto');
    Route::post('/mi-perfil/contrasena', [PerfilController::class, 'contrasena'])->middleware('throttle:5,1')->name('perfil.contrasena');
    Route::get('/personal/{personaFoto}/foto', [PerfilController::class, 'imagen'])->name('personal.foto');

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

    Route::get('/expedientes', [ExpedienteController::class, 'index'])->name('expedientes.index');
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

    Route::get('/atencion/{ingreso}/cirugias/nueva', [CirugiaController::class, 'formulario'])->name('cirugias.formulario');
    Route::post('/atencion/{ingreso}/cirugias', [CirugiaController::class, 'guardar'])->name('cirugias.guardar');
    Route::get('/cirugias/{cirugia}', [CirugiaController::class, 'show'])->name('cirugias.show');
    Route::post('/cirugias/{cirugia}/estado', [CirugiaController::class, 'estado'])->name('cirugias.estado');
    Route::post('/cirugias/{cirugia}/controles', [CirugiaController::class, 'control'])->name('cirugias.control');
    // Archivar una hoja en papel también está permitido tras el alta; no modifica la atención.
    Route::get('/hojas/{atencionArchivo}/subir', [ArchivoClinicoController::class, 'formulario'])->name('archivos.formulario');
    Route::post('/hojas/{atencionArchivo}/subir', [ArchivoClinicoController::class, 'guardar'])->name('archivos.guardar');
    Route::get('/archivos-clinicos/{archivoClinico}', [ArchivoClinicoController::class, 'show'])->name('archivos.show');
    Route::get('/archivos-clinicos/{archivoClinico}/contenido', [ArchivoClinicoController::class, 'contenido'])->name('archivos.contenido');
    // El origen está cerrado; RegistroIngreso protege la creación nueva con sus propios bloqueos.
    Route::get('/atencion/{origen}/seguimiento', [SeguimientoController::class, 'crear'])->name('seguimientos.crear');
    Route::post('/atencion/{origen}/seguimiento', [SeguimientoController::class, 'guardar'])->name('seguimientos.guardar');

    Route::get('/atencion', [AtencionController::class, 'index'])
        ->name('atencion.index');

    Route::get('/atencion/{ingreso}/enfermeria', [AtencionController::class, 'enfermeria'])
        ->name('atencion.enfermeria');

    Route::post('/pacientes/{paciente}/consulta', [IngresoController::class, 'iniciarConsulta'])
        ->name('consultas.iniciar');

    Route::post('/atencion/{ingreso}/notas-enfermeria', [NotaEnfermeriaController::class, 'guardar'])
        ->name('notas-enfermeria.guardar');

    Route::get('/atencion/{ingreso}', [AtencionController::class, 'show'])
        ->name('atencion.show');

    Route::post('/atencion/{ingreso}/alta', [AltaMedicaController::class, 'guardar'])->name('alta.guardar');
    Route::post('/atencion/{ingreso}/emergencia', [EmergenciaController::class, 'guardar'])->name('emergencia.guardar');
});
