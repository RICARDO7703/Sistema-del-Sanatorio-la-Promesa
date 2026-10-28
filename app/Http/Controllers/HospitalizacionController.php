<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use App\Models\Hospitalizacion;
use App\Models\HospitalizacionHabitacion;
use App\Models\Ingreso;
use App\Services\ReglasAtencion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HospitalizacionController extends Controller
{
    public function guardar(Request $request, Ingreso $ingreso): RedirectResponse
    {
        $datos = $this->validar($request);
        $eraEmergencia = $ingreso->esConsultaExterna();
        DB::transaction(function () use ($ingreso, $datos): void {
            $actual = Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            ReglasAtencion::exigirActivo($actual->estado);
            if ($actual->hospitalizacion()->exists()) {
                throw ValidationException::withMessages(['id_habitacion' => 'El ingreso ya tiene una hospitalización.']);
            }
            $habitacion = $this->habitacionDisponible((int) $datos['id_habitacion']);
            $hospitalizacion = Hospitalizacion::create([
                'id_ingreso' => $actual->getKey(), 'fecha_inicio' => now(), 'estado' => 'Activa',
                'motivo' => $datos['motivo'] ?? null, 'observaciones' => $datos['observaciones'] ?? null,
                'id_usuario_creacion' => session('id_usuario'),
            ]);
            HospitalizacionHabitacion::create([
                'id_hospitalizacion' => $hospitalizacion->getKey(), 'id_habitacion' => $habitacion->getKey(),
                'fecha_hora_inicio' => now(), 'id_usuario_creacion' => session('id_usuario'),
            ]);
            $actual->update(['tipo_atencion' => Ingreso::TIPO_HOSPITALIZACION, 'id_usuario_modificacion' => session('id_usuario')]);
        });

        return redirect()->route('atencion.show', $ingreso)->with('exito', $eraEmergencia ? 'Paciente internado: la consulta externa ahora es una hospitalización.' : 'Habitación asignada correctamente.');
    }

    public function cambiarHabitacion(Request $request, Hospitalizacion $hospitalizacion): RedirectResponse
    {
        $datos = $this->validar($request);
        DB::transaction(function () use ($hospitalizacion, $datos): void {
            $ingreso = Ingreso::whereKey($hospitalizacion->id_ingreso)->lockForUpdate()->firstOrFail();
            ReglasAtencion::exigirActivo($ingreso->estado);
            $actual = Hospitalizacion::whereKey($hospitalizacion->getKey())->lockForUpdate()->firstOrFail();
            if ($actual->estado !== 'Activa') {
                throw ValidationException::withMessages(['id_habitacion' => 'La hospitalización ya no se encuentra activa.']);
            }
            $habitacion = $this->habitacionDisponible((int) $datos['id_habitacion']);
            $asignacion = $actual->habitacionActual;
            $fecha = $asignacion ? now() : Carbon::parse(now('America/Guatemala')->format('Y-m-d H:i:s'));
            if ($asignacion) {
                ReglasAtencion::validarCierre($fecha, $asignacion->fecha_hora_inicio);
                $asignacion->update(['fecha_hora_fin' => $fecha]);
            }
            HospitalizacionHabitacion::create([
                'id_hospitalizacion' => $actual->getKey(), 'id_habitacion' => $habitacion->getKey(),
                'fecha_hora_inicio' => $fecha, 'observaciones' => $datos['observaciones'] ?? null,
                'id_usuario_creacion' => session('id_usuario'),
            ]);
            $actual->update(['id_usuario_modificacion' => session('id_usuario')]);
        });

        return redirect()->route('atencion.show', $hospitalizacion->id_ingreso)->with('exito', 'Habitación cambiada correctamente.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'id_habitacion' => ['required', 'integer', 'exists:tbl_habitacion,id_habitacion'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function habitacionDisponible(int $id): Habitacion
    {
        $habitacion = Habitacion::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($habitacion->estado_operativo !== 'Activa' || $habitacion->asignacionesHospitalizacion()->whereNull('fecha_hora_fin')->exists()) {
            throw ValidationException::withMessages(['id_habitacion' => 'La habitación seleccionada no está disponible.']);
        }

        return $habitacion;
    }
}
