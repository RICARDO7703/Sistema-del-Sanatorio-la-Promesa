<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HabitacionController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['buscar' => ['nullable', 'string', 'max:100']]);
        $buscar = trim((string) $request->input('buscar', ''));
        $habitaciones = Habitacion::withExists(['asignacionesHospitalizacion as ocupada' => fn ($q) => $q->whereNull('fecha_hora_fin')])
            ->when($buscar !== '', fn ($q) => $q->where('numero_habitacion', 'like', '%'.$buscar.'%'))
            ->orderBy('numero_habitacion')->get();

        $tiposHabitacion = collect(['General', 'Privada', 'Semiprivada'])->merge(Habitacion::whereNotNull('tipo')->distinct()->pluck('tipo'))->filter()->unique()->sort()->values();

        return view('habitaciones.index', compact('habitaciones', 'buscar', 'tiposHabitacion'));
    }

    public function guardar(Request $request): RedirectResponse
    {
        Habitacion::create($this->validar($request));

        return redirect()->route('habitaciones.index')->with('exito', 'Habitación registrada correctamente.');
    }

    public function actualizar(Request $request, Habitacion $habitacion): RedirectResponse
    {
        $datos = $this->validar($request, $habitacion);
        DB::transaction(function () use ($habitacion, $datos): void {
            $actual = Habitacion::whereKey($habitacion->getKey())->lockForUpdate()->firstOrFail();
            if ($datos['estado_operativo'] !== 'Activa' && $actual->asignacionesHospitalizacion()->whereNull('fecha_hora_fin')->exists()) {
                throw ValidationException::withMessages(['estado_operativo' => 'La habitación está ocupada. Traslade al paciente o registre el alta antes de cambiar su estado operativo.']);
            }
            $actual->update($datos);
        });

        return redirect()->route('habitaciones.index')->with('exito', 'Habitación actualizada correctamente.');
    }

    private function validar(Request $request, ?Habitacion $habitacion = null): array
    {
        return $request->validate([
            'numero_habitacion' => ['required', 'string', 'max:20', Rule::unique('tbl_habitacion', 'numero_habitacion')->ignore($habitacion?->getKey(), 'id_habitacion')],
            'tipo' => ['required', 'string', 'max:50'],
            'estado_operativo' => ['required', Rule::in(['Activa', 'Mantenimiento', 'Inactiva'])],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute supera el máximo de :max caracteres.',
            'numero_habitacion.unique' => 'La habitación ya se encuentra registrada.',
            'estado_operativo.in' => 'Seleccione Activa, Mantenimiento o Inactiva.',
        ]);
    }
}
