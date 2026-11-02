<?php

namespace App\Http\Controllers;

use App\Models\AltaMedica;
use App\Models\EvolucionClinica;
use App\Models\Ingreso;
use App\Services\ExamenFisico;
use App\Services\ReglasAtencion;
use App\Support\Permisos;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Consulta externa en un solo formulario: se guarda la consulta y,
 * según el botón elegido, se da de alta al paciente o se prepara su internamiento.
 */
class EmergenciaController extends Controller
{
    public function guardar(Request $request, Ingreso $ingreso): RedirectResponse
    {
        if (! $ingreso->esConsultaExterna()) {
            return redirect()->route('atencion.show', $ingreso)->with('error', 'Esta atención no es una consulta externa.');
        }

        if ($request->input('accion') === 'alta' && ! app(Permisos::class)->puede('Alta médica', Permisos::GUARDAR)) {
            return back()->withInput()->with('permiso_denegado', 'No tiene permiso para '.Permisos::descripcion('Alta médica', Permisos::GUARDAR).'. Puede guardar la consulta con «Internar paciente» o pedir a un médico que dé el alta.');
        }

        $idPersonal = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');

        if (! $idPersonal) {
            return back()->withInput()->with('error', 'El usuario no tiene personal asociado.');
        }

        $request->merge($request->input('accion') === 'internar'
            ? ['medicamentos_casa' => null, 'indicaciones_casa' => null]
            : ['medicamentos_casa' => $this->filasConDatos($request->input('medicamentos_casa'))]);

        $datos = $request->validate([
            'accion' => ['required', Rule::in(['alta', 'internar'])],
            'fecha_hora' => ['required', 'date', ReglasAtencion::reglaNoFutura()],
            'motivo_atencion' => ['required', 'string', 'max:255'],
            'sintomas' => ['nullable', 'string', 'max:1000'],
            'evaluacion_medica' => ['nullable', 'string', 'max:2000'],
            'diagnostico' => ['required', 'string', 'max:1000'],
            'indicaciones' => ['nullable', 'string', 'max:2000'],
            'medicamentos_casa' => ['nullable', 'array', 'max:20'],
            'medicamentos_casa.*' => ['array:medicamento,dosis,frecuencia,duracion'],
            'medicamentos_casa.*.medicamento' => ['required', 'string', 'max:255'],
            'medicamentos_casa.*.dosis' => ['required', 'string', 'max:100'],
            'medicamentos_casa.*.frecuencia' => ['required', 'string', 'max:100'],
            'medicamentos_casa.*.duracion' => ['nullable', 'string', 'max:100'],
            'indicaciones_casa' => ['nullable', 'string', 'max:2000'],
        ], [
            'fecha_hora.required' => 'La fecha y hora son obligatorias.',
            'fecha_hora.before_or_equal' => 'La fecha y hora no pueden ser futuras.',
            'motivo_atencion.required' => 'El motivo de consulta es obligatorio.',
            'diagnostico.required' => 'El diagnóstico es obligatorio.',
            'medicamentos_casa.max' => 'Puede indicar como máximo 20 medicamentos ambulatorios.',
            'medicamentos_casa.*.medicamento.required' => 'Escriba el nombre de cada medicamento ambulatorio.',
            'medicamentos_casa.*.dosis.required' => 'Indique la dosis de cada medicamento ambulatorio.',
            'medicamentos_casa.*.frecuencia.required' => 'Indique la frecuencia de cada medicamento ambulatorio.',
        ]);

        $examenFisico = ExamenFisico::validar($request);
        $fecha = Carbon::parse($datos['fecha_hora']);
        $darDeAlta = $datos['accion'] === 'alta';

        DB::transaction(function () use ($ingreso, $datos, $examenFisico, $fecha, $idPersonal, $darDeAlta): void {
            $actual = Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            ReglasAtencion::exigirActivo($actual->estado);

            // La consulta se registra al minuto; la llegada puede tener segundos en registros anteriores.
            if ($fecha->lt($actual->fecha_ingreso->copy()->startOfMinute())) {
                throw ValidationException::withMessages(['fecha_hora' => 'La consulta no puede ser anterior a la llegada del paciente.']);
            }

            $consulta = [
                'fecha_hora' => $fecha,
                'motivo_atencion' => trim($datos['motivo_atencion']),
                'sintomas' => $this->texto($datos['sintomas'] ?? null),
                'evaluacion_medica' => $this->texto($datos['evaluacion_medica'] ?? null),
                'examen_fisico' => $examenFisico,
                'diagnostico' => trim($datos['diagnostico']),
                'indicaciones' => $this->texto($datos['indicaciones'] ?? null),
            ];

            // Una consulta iniciada con un solo botón aún no tiene motivo: se toma el de la consulta.
            if (blank($actual->motivo_ingreso)) {
                $actual->update(['motivo_ingreso' => $consulta['motivo_atencion'], 'id_usuario_modificacion' => session('id_usuario')]);
            }

            $registro = $actual->evoluciones()->where('estado', 'Activo')->orderBy('fecha_hora')->orderBy('id_evolucion')->first();

            if ($registro) {
                request()->attributes->set('edicion_documento', true);
                $registro->update($consulta + ['id_usuario_modificacion' => session('id_usuario')]);
                request()->attributes->remove('edicion_documento');
            } else {
                EvolucionClinica::create($consulta + [
                    'id_ingreso' => $actual->getKey(),
                    'id_personal' => $idPersonal,
                    'estado' => 'Activo',
                    'id_usuario_creacion' => session('id_usuario'),
                ]);
            }

            if (! $darDeAlta) {
                return;
            }

            $ahoraLocal = ReglasAtencion::ahora()->startOfMinute();
            $fechaAlta = $ahoraLocal->max($fecha);
            $alta = AltaMedica::create([
                'id_ingreso' => $actual->getKey(),
                'id_personal_medico' => $idPersonal,
                'fecha_hora' => $fechaAlta->format('Y-m-d H:i:s'),
                'diagnostico_final' => trim($datos['diagnostico']),
                'condicion_egreso' => 'Alta a casa',
                'indicaciones_alta' => $this->texto($datos['indicaciones_casa'] ?? null),
                'id_usuario_creacion' => session('id_usuario'),
            ]);

            foreach ($datos['medicamentos_casa'] ?? [] as $fila) {
                $alta->medicamentos()->create(array_map(fn ($valor) => $this->texto($valor), $fila));
            }

            $actual->tratamientos()->where('estado', 'Activo')->update(['estado' => 'Finalizado', 'id_usuario_modificacion' => session('id_usuario')]);
            $actual->update(['estado' => 'Dado de alta', 'id_usuario_modificacion' => session('id_usuario')]);
        });

        if ($darDeAlta) {
            return redirect()->route('atencion.show', $ingreso)->with('exito', 'Paciente dado de alta. La consulta externa quedó cerrada.');
        }

        return redirect()->route('atencion.show', $ingreso)
            ->with('exito', 'Consulta guardada. Seleccione la habitación para internar al paciente.')
            ->with('abrir_internamiento', true);
    }

    private function texto(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $limpio = trim($valor);

        return $limpio === '' ? null : $limpio;
    }

    /**
     * Descarta las filas de medicamentos para casa que llegaron completamente vacías.
     */
    private function filasConDatos(mixed $filas): mixed
    {
        if (! is_array($filas)) {
            return $filas;
        }

        return array_values(array_filter(
            $filas,
            fn ($fila) => ! is_array($fila) || collect($fila)->contains(fn ($valor) => $valor !== null && (! is_string($valor) || trim($valor) !== ''))
        ));
    }
}
