<?php

namespace App\Services;

use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Models\Personal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrdenesMedicas
{
    public static function validar(Request $request, Ingreso $ingreso, ?EvolucionClinica $registro = null): array
    {
        if (! $request->exists('modo_ordenes')) {
            return [];
        }
        $datos = $request->validate([
            'modo_ordenes' => ['required', Rule::in(['actualizar', 'igual'])],
            'estado_mejoria' => ['required', Rule::in(['Mejoró', 'Sin cambios', 'Empeoró'])],
            'id_personal_medico' => ['required', 'integer'],
            'origen_ordenes' => ['nullable', 'integer'],
        ]);
        $medico = Personal::whereKey($datos['id_personal_medico'])->where('estado', 'Activo')
            ->whereHas('cargo', fn ($q) => $q->whereIn('nombre_cargo', ['Médico general', 'Médico especialista', 'Cirujano', 'Cirujano externo', 'Anestesiólogo']))->first();
        if (! $medico) {
            throw ValidationException::withMessages(['id_personal_medico' => 'Seleccione un médico activo.']);
        }
        if ($datos['modo_ordenes'] === 'igual') {
            $previo = $ingreso->evoluciones()->where('estado', 'Activo')->whereNotNull('ordenes_medicas')
                ->when($registro, fn ($q) => $q->where('id_evolucion', '<>', $registro->getKey()))
                ->where('fecha_hora', '<=', Carbon::parse($request->input('fecha_hora')))
                ->orderByDesc('fecha_hora')->orderByDesc('id_evolucion')->first();
            if (! $previo || (int) ($datos['origen_ordenes'] ?? 0) !== $previo->getKey()) {
                throw ValidationException::withMessages(['origen_ordenes' => 'No hay órdenes anteriores válidas para esta fecha o cambiaron. Recargue la vista y revise las órdenes.']);
            }
            $ordenes = $previo->ordenes_medicas;
            $ordenes['origen_evolucion'] = $previo->getKey();
            $ordenes['modo'] = 'igual';
        } else {
            $validado = $request->validate([
                'ordenes' => ['required', 'array:dieta,soluciones,medicamentos,indicaciones'],
                'ordenes.dieta' => ['required', Rule::in(['Libre', 'Blanda', 'Líquida'])],
                'ordenes.indicaciones' => ['nullable', 'string', 'max:2000'],
                'ordenes.soluciones' => ['nullable', 'array', 'max:20'],
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
                'in' => 'Seleccione una opción válida en :attribute.',
                'ordenes.medicamentos.*.nombre.required' => 'Escriba el nombre del medicamento.',
                'ordenes.medicamentos.*.nombre.max' => 'El nombre del medicamento no puede superar los 255 caracteres.',
                'gt' => 'El volumen debe ser mayor que cero.',
                'max' => 'El campo :attribute supera el límite permitido.',
            ]);
            $ordenes = $validado['ordenes'];
            $ordenes['modo'] = 'actualizar';
            $ordenes['origen_evolucion'] = null;
        }

        return ['ordenes_medicas' => $ordenes, 'estado_mejoria' => $datos['estado_mejoria'], 'id_personal' => $medico->getKey()];
    }
}
