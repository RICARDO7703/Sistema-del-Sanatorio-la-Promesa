<?php

namespace App\Services;

use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrdenesMedicas
{
    /** @var list<string> */
    public const DIETAS = ['Libre', 'Blanda', 'Líquida', 'NPO (nada por vía oral)', 'HNO (hasta nueva orden)'];

    public const DIETA_OTRA = 'Otra';

    /** @var list<string> */
    public const VIAS = ['Oral', 'Intravenosa', 'Intramuscular', 'Rectal'];

    /** @var list<string> */
    public const UNIDADES_GOTEO = ['gotas/min', 'microgotas/min'];

    public static function validar(Request $request, Ingreso $ingreso, ?EvolucionClinica $registro = null): array
    {
        $formulario = $request->input('_formulario');
        $primero = $ingreso->evoluciones()->where('estado', 'Activo')->orderBy('fecha_hora')->orderBy('id_evolucion')->first();
        $desdeEvolucion = (is_string($formulario) && str_starts_with($formulario, 'evolucion-')) || $request->has('estado_mejoria');
        // La valoración inicial es el primer registro y no lleva «estado de mejoría»; sus órdenes son opcionales.
        $esValoracion = $registro
            ? $registro->estado_mejoria === null && $primero?->getKey() === $registro->getKey()
            : $primero === null && ! (is_string($formulario) && str_starts_with($formulario, 'evolucion-'));
        if ($esValoracion && ! $desdeEvolucion) {
            if ($ingreso->esConsultaExterna() || ! $request->boolean('con_ordenes')) {
                // Si el formulario ofrecía las órdenes y se desmarcaron, se quitan las guardadas.
                return $request->boolean('ordenes_opcionales') && ! $ingreso->esConsultaExterna() ? ['ordenes_medicas' => null] : [];
            }

            return ['ordenes_medicas' => self::validarOrdenes($request)];
        }
        // El responsable de la evolución es el personal del usuario que inició sesión (lo asigna el controlador).
        $datos = $request->validate([
            'estado_mejoria' => ['required', Rule::in(['Mejoró', 'Sin cambios', 'Empeoró'])],
            'ordenes' => ['required', 'array:dieta,dieta_otra,soluciones,medicamentos,indicaciones,plan_educacional'],
        ], [
            'ordenes.required' => 'Debe completar las órdenes médicas antes de guardar la evolución.',
        ]);

        return ['ordenes_medicas' => self::validarOrdenes($request), 'estado_mejoria' => $datos['estado_mejoria']];
    }

    /**
     * Valida y ordena dieta, solución, medicamentos e indicaciones.
     *
     * @return array<string, mixed>
     */
    private static function validarOrdenes(Request $request): array
    {
        $validado = $request->validate([
            'ordenes' => ['required', 'array:dieta,dieta_otra,soluciones,medicamentos,indicaciones,plan_educacional'],
            'ordenes.dieta' => ['required', 'string', Rule::in([...self::DIETAS, self::DIETA_OTRA])],
            'ordenes.dieta_otra' => ['nullable', 'required_if:ordenes.dieta,'.self::DIETA_OTRA, 'string', 'max:100'],
            'ordenes.indicaciones' => ['nullable', 'string', 'max:2000'],
            'ordenes.plan_educacional' => ['nullable', 'string', 'max:2000'],
            'ordenes.soluciones' => ['nullable', 'array', 'max:1'],
            'ordenes.soluciones.*' => ['array:tipo,volumen,velocidad,unidad'],
            'ordenes.soluciones.*.tipo' => ['required', Rule::in(['Mixta', 'Salina', 'Glucosada', 'Hartmann', 'Otra'])],
            'ordenes.soluciones.*.volumen' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'ordenes.soluciones.*.velocidad' => ['bail', 'required', 'integer', 'between:1,999'],
            'ordenes.soluciones.*.unidad' => ['required', Rule::in(self::UNIDADES_GOTEO)],
            'ordenes.medicamentos' => ['nullable', 'array', 'max:50'],
            'ordenes.medicamentos.*' => ['array:nombre,dosis,via,frecuencia'],
            'ordenes.medicamentos.*.nombre' => ['required', 'string', 'max:255'],
            'ordenes.medicamentos.*.dosis' => ['bail', 'required', 'numeric', 'gt:0', 'max:100000'],
            'ordenes.medicamentos.*.via' => ['required', Rule::in(self::VIAS)],
            'ordenes.medicamentos.*.frecuencia' => ['bail', 'required', 'integer', 'between:1,168'],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'ordenes.soluciones.max' => 'Solo puede registrar una solución por evolución.',
            'ordenes.required' => 'Debe completar las órdenes médicas antes de guardar la evolución.',
            'ordenes.dieta.required' => 'Seleccione la dieta en las órdenes médicas antes de guardar la evolución.',
            'ordenes.dieta_otra.required_if' => 'Escriba la dieta indicada.',
            'in' => 'Seleccione una opción válida en :attribute.',
            'ordenes.medicamentos.*.nombre.required' => 'Escriba el nombre del medicamento.',
            'ordenes.medicamentos.*.nombre.max' => 'El nombre del medicamento no puede superar los 255 caracteres.',
            'ordenes.medicamentos.*.dosis.numeric' => 'La dosis debe ser un número en miligramos.',
            'ordenes.medicamentos.*.dosis.gt' => 'La dosis debe ser mayor que cero.',
            'ordenes.medicamentos.*.frecuencia.integer' => 'La frecuencia debe indicarse en horas enteras.',
            'ordenes.medicamentos.*.frecuencia.between' => 'La frecuencia debe estar entre 1 y 168 horas.',
            'ordenes.soluciones.*.velocidad.integer' => 'El goteo debe ser un número entero.',
            'ordenes.soluciones.*.velocidad.between' => 'El goteo debe estar entre 1 y 999.',
            'gt' => 'El volumen debe ser mayor que cero.',
            'max' => 'El campo :attribute supera el límite permitido.',
        ], [
            'ordenes.dieta' => 'dieta',
            'ordenes.medicamentos.*.dosis' => 'dosis',
            'ordenes.medicamentos.*.via' => 'vía',
            'ordenes.medicamentos.*.frecuencia' => 'frecuencia',
            'ordenes.soluciones.*.tipo' => 'tipo de suero',
            'ordenes.soluciones.*.volumen' => 'volumen',
            'ordenes.soluciones.*.velocidad' => 'goteo',
            'ordenes.soluciones.*.unidad' => 'unidad de goteo',
        ]);
        $ordenes = $validado['ordenes'];
        if ($ordenes['dieta'] === self::DIETA_OTRA) {
            $ordenes['dieta'] = trim($ordenes['dieta_otra']);
        }
        unset($ordenes['dieta_otra']);
        foreach ($ordenes['soluciones'] ?? [] as $indice => $solucion) {
            $ordenes['soluciones'][$indice]['velocidad'] = (int) $solucion['velocidad'];
        }
        foreach ($ordenes['medicamentos'] ?? [] as $indice => $medicamento) {
            $ordenes['medicamentos'][$indice]['dosis'] = $medicamento['dosis'] + 0;
            $ordenes['medicamentos'][$indice]['frecuencia'] = (int) $medicamento['frecuencia'];
        }
        if (blank($ordenes['plan_educacional'] ?? null)) {
            unset($ordenes['plan_educacional']);
        }

        return $ordenes;
    }
}
