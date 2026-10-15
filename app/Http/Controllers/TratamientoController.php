<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\Tratamiento;
use App\Services\ReglasAtencion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TratamientoController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('atencion.index');
    }

    public function guardar(Request $request): RedirectResponse
    {
        $request->validate(['id_ingreso' => ['required', 'integer', 'exists:tbl_ingreso,id_ingreso']]);
        $ingreso = Ingreso::findOrFail($request->input('id_ingreso'));
        $datos = $this->validar($request, $ingreso);

        DB::transaction(function () use ($ingreso, $datos): void {
            $actual = Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            ReglasAtencion::exigirActivo($actual->estado);
            Tratamiento::create($datos + [
                'id_ingreso' => $actual->getKey(),
                'id_personal_indica' => DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal'),
                'id_usuario_creacion' => session('id_usuario'),
                'estado' => 'Activo',
            ]);
        });

        return redirect()->route('atencion.show', $ingreso)->with('exito', 'Tratamiento registrado correctamente.');
    }

    public function actualizar(Request $request, Tratamiento $tratamiento): RedirectResponse
    {
        $datos = $this->validar($request, $tratamiento->ingreso);
        DB::transaction(function () use ($tratamiento, $datos): void {
            $ingreso = Ingreso::whereKey($tratamiento->id_ingreso)->lockForUpdate()->firstOrFail();
            ReglasAtencion::exigirActivo($ingreso->estado);
            $actual = Tratamiento::whereKey($tratamiento->getKey())->lockForUpdate()->firstOrFail();
            ReglasAtencion::exigirActivo($actual->estado);
            $actual->update($datos + ['id_usuario_modificacion' => session('id_usuario')]);
        });

        return redirect()->route('atencion.show', $tratamiento->id_ingreso)->with('exito', 'Tratamiento actualizado correctamente.');
    }

    public function eliminar(Tratamiento $tratamiento): RedirectResponse
    {
        DB::transaction(function () use ($tratamiento): void {
            $ingreso = Ingreso::whereKey($tratamiento->id_ingreso)->lockForUpdate()->firstOrFail();
            ReglasAtencion::exigirActivo($ingreso->estado);
            $actual = Tratamiento::whereKey($tratamiento->getKey())->lockForUpdate()->firstOrFail();
            ReglasAtencion::exigirActivo($actual->estado);
            $actual->update(['estado' => 'Cancelado', 'id_usuario_modificacion' => session('id_usuario')]);
        });

        return redirect()->route('atencion.show', $tratamiento->id_ingreso)->with('exito', 'Tratamiento cancelado correctamente.');
    }

    private function validar(Request $request, Ingreso $ingreso): array
    {
        ReglasAtencion::exigirActivo($ingreso->estado);
        $datos = $request->validate([
            'id_evolucion' => ['nullable', 'integer', Rule::exists('tbl_evolucion_clinica', 'id_evolucion')->where('id_ingreso', $ingreso->getKey())->where('estado', 'Activo')],
            'id_medicamento' => ['nullable', 'integer', Rule::exists('tbl_medicamento', 'id_medicamento')->where('estado', 'Activo')],
            'tipo_tratamiento' => ['required', Rule::in(['Medicamento', 'No farmacológico'])],
            'descripcion_tratamiento' => ['nullable', 'string', 'max:255'],
            'dosis' => ['nullable', 'string', 'max:100'],
            'via_administracion' => ['nullable', 'string', 'max:80'],
            'frecuencia' => ['nullable', 'string', 'max:100'],
            'duracion' => ['nullable', 'string', 'max:100'],
            'indicaciones' => ['nullable', 'string', 'max:2000'],
            'fecha_indicacion' => ['required', 'date', 'before_or_equal:now'],
            'fecha_inicio' => ['nullable', 'date', 'after_or_equal:fecha_indicacion'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);
        ReglasAtencion::validarFecha(Carbon::parse($datos['fecha_indicacion']), $ingreso->fecha_ingreso, now(), 'fecha_indicacion');
        ReglasAtencion::validarTratamiento($datos['tipo_tratamiento'], isset($datos['id_medicamento']) ? (int) $datos['id_medicamento'] : null, $datos['descripcion_tratamiento'] ?? null);
        if (! empty($datos['fecha_fin']) && empty($datos['fecha_inicio'])) {
            throw ValidationException::withMessages(['fecha_inicio' => 'Indique la fecha de inicio.']);
        }
        if ($datos['tipo_tratamiento'] === 'No farmacológico') {
            $datos['id_medicamento'] = null;
        }

        return $datos;
    }
}
