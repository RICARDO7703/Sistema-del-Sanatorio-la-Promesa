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
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\SignoVitalController;
use App\Http\Controllers\TratamientoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Middleware\AvisarSinCambios;
use App\Http\Middleware\PermisoModulo;
use App\Http\Middleware\RegistrarOperaciones;
use App\Http\Middleware\SesionClinica;
use Illuminate\Support\Facades\Route;

Route::get('/', [LoginController::class, 'index']);
Route::get('/recuperar-contrasena', [RecuperarContrasenaController::class, 'solicitar'])->name('password.request');
Route::post('/recuperar-contrasena', [RecuperarContrasenaController::class, 'enviar'])->middleware('throttle:solicitud-recuperacion')->name('password.email');
Route::get('/verificar-codigo', [RecuperarContrasenaController::class, 'codigo'])->name('password.code');
Route::post('/verificar-codigo', [RecuperarContrasenaController::class, 'verificar'])->middleware('throttle:codigo-recuperacion')->name('password.verify');
Route::get('/restablecer-contrasena', [RecuperarContrasenaController::class, 'formulario'])->name('password.reset');
Route::post('/restablecer-contrasena', [RecuperarContrasenaController::class, 'actualizar'])->middleware('throttle:nueva-contrasena')->name('password.update');

Route::post('/login', [LoginController::class, 'validar'])
    ->middleware('throttle:inicio-sesion')->name('login.validar');

Route::post('/logout', [LoginController::class, 'cerrarSesion'])
    ->name('logout');

Route::middleware([SesionClinica::class, PermisoModulo::class, RegistrarOperaciones::class])->group(function () {

    Route::post('/sesion/salida-pagina', [LoginController::class, 'salidaPagina'])->middleware('throttle:salida-pagina')->name('sesion.salida-pagina');

    Route::get('/mi-perfil', [PerfilController::class, 'show'])->name('perfil.show');
    Route::get('/mi-perfil/foto', [PerfilController::class, 'editarFoto'])->name('perfil.foto.editar');
    Route::get('/mi-perfil/contrasena', [PerfilController::class, 'editarContrasena'])->name('perfil.contrasena.editar');
    Route::get('/mi-perfil/apariencia', [PerfilController::class, 'editarApariencia'])->name('perfil.apariencia.editar');
    Route::patch('/mi-perfil/contacto', [PerfilController::class, 'contacto'])->middleware(AvisarSinCambios::class)->name('perfil.contacto');
    Route::post('/mi-perfil/foto', [PerfilController::class, 'foto'])->name('perfil.foto');
    Route::delete('/mi-perfil/foto', [PerfilController::class, 'quitarFoto'])->name('perfil.quitar-foto');
    Route::post('/mi-perfil/contrasena', [PerfilController::class, 'contrasena'])->middleware('throttle:cambio-contrasena')->name('perfil.contrasena');
    Route::get('/personal/{personaFoto}/foto', [PerfilController::class, 'imagen'])->name('personal.foto');

    Route::get('/inicio', [InicioController::class, 'index'])->name('inicio');

    Route::get('/pacientes', [PacienteController::class, 'index'])
        ->name('pacientes.index');

    Route::post('/pacientes', [PacienteController::class, 'guardar'])
        ->name('pacientes.guardar');

    Route::put('/pacientes/{paciente}', [PacienteController::class, 'actualizar'])
        ->middleware(AvisarSinCambios::class)->name('pacientes.actualizar');

    Route::patch('/pacientes/{paciente}/desactivar', [PacienteController::class, 'eliminar'])
        ->name('pacientes.desactivar');

    Route::patch('/pacientes/{paciente}/activar', [PacienteController::class, 'activar'])
        ->name('pacientes.activar');

    Route::post('/pacientes/{paciente}/contactos', [PacienteController::class, 'guardarContacto'])
        ->name('pacientes.contactos.guardar');

    Route::put('/pacientes/contactos/{contacto}', [PacienteController::class, 'actualizarContacto'])
        ->middleware(AvisarSinCambios::class)->name('pacientes.contactos.actualizar');

    Route::delete('/pacientes/contactos/{contacto}', [PacienteController::class, 'eliminarContacto'])
        ->name('pacientes.contactos.eliminar');

    Route::get('/expedientes', [ExpedienteController::class, 'index'])->name('expedientes.index');
    Route::get('/pacientes/{paciente}/expediente', [ExpedienteController::class, 'show'])->name('pacientes.expediente');

    Route::get('/evolucion', [EvolucionController::class, 'index'])
        ->name('evolucion.index');

    Route::post('/evolucion', [EvolucionController::class, 'guardar'])
        ->name('evolucion.guardar');

    Route::put('/evolucion/{evolucion}', [EvolucionController::class, 'actualizar'])
        ->middleware(AvisarSinCambios::class)->name('evolucion.actualizar');

    Route::post('/evolucion/{evolucion}/cancelar-ordenes', [EvolucionController::class, 'cancelarOrdenes'])
        ->name('evolucion.cancelar-ordenes');
    Route::post('/evolucion/{evolucion}/reactivar-ordenes', [EvolucionController::class, 'reactivarOrdenes'])
        ->name('evolucion.reactivar-ordenes');

    Route::delete('/evolucion/{evolucion}', [EvolucionController::class, 'eliminar'])
        ->name('evolucion.eliminar');

    Route::get('/bitacora/pdf', [BitacoraController::class, 'pdf'])->middleware('throttle:20,1')->name('bitacora.pdf');
    Route::get('/bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');

    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/nuevo', [UsuarioController::class, 'crear'])->name('usuarios.crear');
    Route::post('/usuarios', [UsuarioController::class, 'guardar'])->name('usuarios.guardar');
    Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'editar'])->whereNumber('usuario')->name('usuarios.editar');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'actualizar'])->whereNumber('usuario')->middleware(AvisarSinCambios::class)->name('usuarios.actualizar');
    Route::patch('/usuarios/{usuario}/contrasena', [UsuarioController::class, 'contrasena'])->whereNumber('usuario')->name('usuarios.contrasena');
    Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])->whereNumber('usuario')->name('usuarios.estado');

    Route::get('/personal', [PersonalController::class, 'index'])
        ->name('personal.index');

    Route::post('/personal', [PersonalController::class, 'guardar'])
        ->name('personal.guardar');

    Route::put('/personal/{personal}', [PersonalController::class, 'actualizar'])
        ->middleware(AvisarSinCambios::class)->name('personal.actualizar');

    Route::patch('/personal/{personal}/desactivar', [PersonalController::class, 'eliminar'])
        ->name('personal.desactivar');

    Route::patch('/personal/{personal}/activar', [PersonalController::class, 'activar'])
        ->name('personal.activar');

    Route::get('/habitaciones', [HabitacionController::class, 'index'])
        ->name('habitaciones.index');

    Route::post('/habitaciones', [HabitacionController::class, 'guardar'])
        ->name('habitaciones.guardar');

    Route::put('/habitaciones/{habitacion}', [HabitacionController::class, 'actualizar'])
        ->middleware(AvisarSinCambios::class)->name('habitaciones.actualizar');

    Route::get('/ingresos', [IngresoController::class, 'index'])
        ->name('ingresos.index');

    Route::post('/ingresos', [IngresoController::class, 'guardar'])
        ->name('ingresos.guardar');

    Route::put('/ingresos/{ingreso}', [IngresoController::class, 'actualizar'])
        ->middleware(AvisarSinCambios::class)->name('ingresos.actualizar');

    Route::patch('/ingresos/{ingreso}/cancelar', [IngresoController::class, 'cancelar'])
        ->name('ingresos.cancelar');

    Route::post('/ingresos/{ingreso}/documento-firmado', [IngresoController::class, 'subirDocumentoFirmado'])
        ->name('ingresos.documento-firmado');

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
        ->middleware(AvisarSinCambios::class)->name('signos.actualizar');

    Route::get('/tratamientos', [TratamientoController::class, 'index'])
        ->name('tratamientos.index');

    Route::post('/tratamientos', [TratamientoController::class, 'guardar'])
        ->name('tratamientos.guardar');

    Route::put('/tratamientos/{tratamiento}', [TratamientoController::class, 'actualizar'])
        ->middleware(AvisarSinCambios::class)->name('tratamientos.actualizar');

    Route::delete('/tratamientos/{tratamiento}', [TratamientoController::class, 'eliminar'])
        ->name('tratamientos.eliminar');

    Route::get('/atencion/{ingreso}/cirugias/nueva', [CirugiaController::class, 'formulario'])->name('cirugias.formulario');
    Route::post('/atencion/{ingreso}/cirugias', [CirugiaController::class, 'guardar'])->name('cirugias.guardar');
    Route::get('/cirugias/{cirugia}', [CirugiaController::class, 'show'])->name('cirugias.show');
    Route::post('/cirugias/{cirugia}/recuperacion', [CirugiaController::class, 'guardarRecuperacion'])->name('cirugias.recuperacion');
    Route::post('/cirugias/{cirugia}/estado', [CirugiaController::class, 'estado'])->name('cirugias.estado');
    Route::put('/cirugias/{cirugia}', [CirugiaController::class, 'actualizar'])->middleware(AvisarSinCambios::class)->name('cirugias.actualizar');
    // Archivar una hoja en papel también está permitido tras el alta; no modifica la atención.
    Route::post('/hojas/{atencionArchivo}/subir', [ArchivoClinicoController::class, 'guardar'])->name('archivos.guardar');
    Route::post('/pacientes/{paciente}/archivos', [ArchivoClinicoController::class, 'guardarPaciente'])->name('archivos.paciente.guardar');
    Route::get('/archivos-clinicos/{archivoClinico}/editar', [ArchivoClinicoController::class, 'editar'])->name('archivos.editar');
    Route::delete('/archivos-clinicos/{archivoClinico}', [ArchivoClinicoController::class, 'quitar'])->name('archivos.quitar');
    Route::put('/archivos-clinicos/{archivoClinico}', [ArchivoClinicoController::class, 'actualizar'])->middleware(AvisarSinCambios::class)->name('archivos.actualizar');
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

    Route::put('/notas-enfermeria/{nota}', [NotaEnfermeriaController::class, 'actualizar'])->name('notas-enfermeria.actualizar');
    Route::post('/atencion/{ingreso}/notas-enfermeria', [NotaEnfermeriaController::class, 'guardar'])
        ->name('notas-enfermeria.guardar');

    Route::get('/atencion/{ingreso}', [AtencionController::class, 'show'])
        ->name('atencion.show');

    Route::post('/atencion/{ingreso}/alta', [AltaMedicaController::class, 'guardar'])->name('alta.guardar');
    Route::get('/altas/{alta}/receta', [AltaMedicaController::class, 'receta'])->name('alta.receta');
    Route::post('/atencion/{ingreso}/emergencia', [EmergenciaController::class, 'guardar'])->middleware(AvisarSinCambios::class)->name('emergencia.guardar');
    Route::get('/reportes/archivos/{archivoClinico}', [ReporteController::class, 'archivo'])->middleware('throttle:30,1')->name('reportes.archivo');
    Route::get('/reportes/expediente/{paciente}', [ReporteController::class, 'expediente'])->middleware('throttle:5,1')->name('reportes.expediente');
    Route::get('/reportes/expediente/{paciente}/zip', [ReporteController::class, 'expedienteZip'])->middleware('throttle:5,1')->name('reportes.expediente.zip');
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/pdf', [ReporteController::class, 'listado'])->middleware('throttle:20,1')->name('reportes.pdf');
    Route::get('/reportes/documentos/{tipo}/{id}', [ReporteController::class, 'documento'])->whereNumber('id')->middleware('throttle:30,1')->name('reportes.documento');
});
