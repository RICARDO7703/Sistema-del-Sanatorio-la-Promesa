<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Request;

class ExamenFisico
{
    public static function validar(Request $request): ?array
    {
        $datos = $request->validate([
            'examen_fisico' => ['nullable', 'array:frecuencia_cardiaca,presion_arterial,frecuencia_respiratoria,talla,peso,temperatura,saturacion_oxigeno,cabeza,tronco,extremidades'],
            'examen_fisico.frecuencia_cardiaca' => ['nullable', 'integer', 'between:1,300'],
            'examen_fisico.frecuencia_respiratoria' => ['nullable', 'integer', 'between:1,100'],
            'examen_fisico.presion_arterial' => ['bail', 'nullable', 'string', 'max:15', function (string $attribute, mixed $value, Closure $fail): void {
                if (! preg_match('/^\s*(\d{2,3})\s*\/\s*(\d{2,3})\s*$/', $value, $partes)) {
                    $fail('La presión arterial debe tener el formato 120/80.');

                    return;
                }
                if ((int) $partes[1] < 30 || (int) $partes[1] > 300 || (int) $partes[2] < 20 || (int) $partes[2] > 200) {
                    $fail('La presión arterial debe contener una sistólica entre 30 y 300 y una diastólica entre 20 y 200 mmHg.');
                }
            }],
            'examen_fisico.talla' => ['nullable', 'numeric', 'gt:0', 'max:999.99'],
            'examen_fisico.peso' => ['nullable', 'numeric', 'gt:0', 'max:999.99'],
            'examen_fisico.temperatura' => ['nullable', 'numeric', 'between:25,50'],
            'examen_fisico.saturacion_oxigeno' => ['nullable', 'integer', 'between:0,100'],
            'examen_fisico.cabeza' => ['nullable', 'string', 'max:2000'],
            'examen_fisico.tronco' => ['nullable', 'string', 'max:2000'],
            'examen_fisico.extremidades' => ['nullable', 'string', 'max:2000'],
        ], [
            'examen_fisico.array' => 'Los campos del examen físico no son válidos.',
            'examen_fisico.*.integer' => 'El campo :attribute debe ser un número entero.',
            'examen_fisico.*.numeric' => 'El campo :attribute debe ser numérico.',
            'examen_fisico.*.between' => 'El campo :attribute debe estar entre :min y :max.',
            'examen_fisico.*.gt' => 'El campo :attribute debe ser mayor que cero.',
            'examen_fisico.*.max' => 'El campo :attribute supera el máximo permitido (:max).',
            'examen_fisico.*.string' => 'El campo :attribute debe ser texto.',
        ], [
            'examen_fisico.frecuencia_cardiaca' => 'frecuencia cardíaca',
            'examen_fisico.frecuencia_respiratoria' => 'frecuencia respiratoria',
            'examen_fisico.presion_arterial' => 'presión arterial',
            'examen_fisico.talla' => 'talla', 'examen_fisico.peso' => 'peso',
            'examen_fisico.temperatura' => 'temperatura', 'examen_fisico.saturacion_oxigeno' => 'saturación de oxígeno',
            'examen_fisico.cabeza' => 'cabeza', 'examen_fisico.tronco' => 'tronco', 'examen_fisico.extremidades' => 'extremidades',
        ]);
        $examen = $datos['examen_fisico'] ?? null;
        if (! empty($examen['presion_arterial'])) {
            $examen['presion_arterial'] = preg_replace('/\s+/', '', $examen['presion_arterial']);
        }

        return $examen;
    }
}
