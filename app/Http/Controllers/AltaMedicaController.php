<?php

namespace App\Http\Controllers;

use App\Models\AltaMedica;
use App\Models\Ingreso;
use App\Services\ReglasAtencion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AltaMedicaController extends Controller
{
    public function guardar(Request $request, Ingreso $ingreso): RedirectResponse
    {
        $datos = $request->validate([
            'id_personal_medico' => ['required', 'integer', Rule::exists('tbl_personal', 'id_personal')->where('estado', 'Activo')],
            'fecha_hora' => ['required', 'date', 'before_or_equal:now'],
            'diagnostico_final' => ['required', 'string', 'max:2000'],
            'condicion_egreso' => ['required', 'string', 'max:150'],
            'indicaciones_alta' => ['nullable', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);
        $esMedico = DB::table('tbl_personal')->join('tbl_cargo', 'tbl_cargo.id_cargo', '=', 'tbl_personal.id_cargo')
            ->where('tbl_personal.id_personal', $datos['id_personal_medico'])
            ->whereIn('tbl_cargo.nombre_cargo', ['Médico general', 'Médico especialista', 'Cirujano', 'Cirujano externo', 'Anestesiólogo'])->exists();
        if (! $esMedico) {
            throw ValidationException::withMessages(['id_personal_medico' => 'Seleccione personal con cargo médico.']);
        }
        DB::transaction(function () use ($ingreso, $datos): void {
            $actual = Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            ReglasAtencion::exigirActivo($actual->estado);
            $fecha = Carbon::parse($datos['fecha_hora']);
            ReglasAtencion::validarFecha($fecha, $actual->fecha_ingreso, now());
            foreach (['evoluciones' => 'fecha_hora', 'signosVitales' => 'fecha_hora', 'tratamientos' => 'fecha_indicacion'] as $relacion => $campo) {
                if ($actual->$relacion()->where($campo, '>', $fecha)->exists()) {
                    throw ValidationException::withMessages(['fecha_hora' => 'El alta no puede ser anterior a registros clínicos del ingreso.']);
                }
            }
            $hospitalizacion = $actual->hospitalizacion;
            if ($hospitalizacion && $hospitalizacion->estado === 'Activa') {
                ReglasAtencion::validarCierre($fecha, $hospitalizacion->fecha_inicio);
                foreach ($hospitalizacion->historialHabitaciones()->whereNull('fecha_hora_fin')->get() as $asignacion) {
                    ReglasAtencion::validarCierre($fecha, $asignacion->fecha_hora_inicio);
                    $asignacion->update(['fecha_hora_fin' => $fecha]);
                }
                $hospitalizacion->update(['estado' => 'Finalizada', 'fecha_fin' => $fecha, 'id_usuario_modificacion' => session('id_usuario')]);
            }
            AltaMedica::create($datos + ['id_ingreso' => $actual->getKey(), 'id_usuario_creacion' => session('id_usuario')]);
            $actual->tratamientos()->where('estado', 'Activo')->update(['estado' => 'Finalizado', 'id_usuario_modificacion' => session('id_usuario')]);
            $actual->update(['estado' => 'Dado de alta', 'id_usuario_modificacion' => session('id_usuario')]);
        });

        return redirect()->route('atencion.show', $ingreso)->with('exito', 'Alta médica registrada; habitación liberada.');
    }
}
