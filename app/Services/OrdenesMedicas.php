<?php

namespace App\Services;

use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrdenesMedicas
{
    public static function validar(Request $request, Ingreso $ingreso, ?EvolucionClinica $registro = null): array
    {
        $formulario = $request->input('_formulario');
        $primero = $ingreso->evoluciones()->where('estado', 'Activo')->orderBy('fecha_hora')->orderBy('id_evolucion')->first();
        $esValoracion = $registro
            ? $registro->ordenes_medicas === null && $primero?->getKey() === $registro->getKey()
            : $primero === null && (! is_string($formulario) || ! str_starts_with($formulario, 'evolucion-'));
        if (! $request->hasAny(['ordenes', 'estado_mejoria', 'id_personal_medico']) && $esValoracion) {
            return [];
        }
        $datos = $request->validate([
            'estado_mejoria' => ['required', Rule::in(['Mejoró', 'Sin cambios', 'Empeoró'])],
            'id_personal_medico' => ['required', 'integer'],
            'ordenes' => ['required', 'array:dieta,soluciones,medicamentos,indicaciones'],
        ], [
            'ordenes.required' => 'Debe completar las órdenes médicas antes de guardar la evolución.',
        ]);
        $medico = Personal::whereKey($datos['id_personal_medico'])->where('estado', 'Activo')
            ->whereHas('cargo', fn ($q) => $q->whereIn('nombre_cargo', ['Médico general', 'Médico especialista', 'Cirujano', 'Cirujano externo', 'Anestesiólogo']))->first();
        if (! $medico) {
            throw ValidationException::withMessages(['id_personal_medico' => 'Seleccione un médico activo.']);
        }
        $validado = $request->validate([
            'ordenes' => ['required', 'array:dieta,soluciones,medicamentos,indicaciones'],
            'ordenes.dieta' => ['required', Rule::in(['Libre', 'Blanda', 'Líquida'])],
            'ordenes.indicaciones' => ['nullable', 'string', 'max:2000'],
            'ordenes.soluciones' => ['nullable', 'array', 'max:1'],
            'ordenes.soluciones.*' => ['array:tipo,volumen,velocidad'],
            'ordenes.soluciones.*.tipo' => ['required', Rule::in(['Mixta', 'Salina', 'Glucosada', 'Hartmann', 'Otra'])],
            'ordenes.soluciones.*.volumen' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'ordenes.soluciones.*.velocidad' => ['required', 'string', 'max:100'],
            'ordenes.medicamentos' => ['nullable', 'array', 'max:50'],
            'ordenes.medicamentos.*' => ['array:nombre,dosis,via,frecuencia'],
            'ordenes.medicamentos.*.nombre' => ['required', 'string', 'max:255'],
            'ordenes.medicamentos.*.dosis' => ['required', 'string', 'max:100'],
            'ordenes.medicamentos.*.via' => ['required', 'string', 'max:80'],
            'ordenes.medicamentos.*.frecuencia' => ['required', 'string', 'max:100'],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'ordenes.soluciones.max' => 'Solo puede registrar una solución por evolución.',
            'ordenes.required' => 'Debe completar las órdenes médicas antes de guardar la evolución.',
            'ordenes.dieta.required' => 'Seleccione la dieta en las órdenes médicas antes de guardar la evolución.',
            'in' => 'Seleccione una opción válida en :attribute.',
            'ordenes.medicamentos.*.nombre.required' => 'Escriba el nombre del medicamento.',
            'ordenes.medicamentos.*.nombre.max' => 'El nombre del medicamento no puede superar los 255 caracteres.',
            'gt' => 'El volumen debe ser mayor que cero.',
            'max' => 'El campo :attribute supera el límite permitido.',
        ]);
        $ordenes = $validado['ordenes'];

        return ['ordenes_medicas' => $ordenes, 'estado_mejoria' => $datos['estado_mejoria'], 'id_personal' => $medico->getKey()];
    }
}
