<?php

namespace App\Http\Controllers;

use App\Models\AltaMedica;
use App\Models\Ingreso;
use App\Models\Personal;
use App\Services\CirugiasAlAlta;
use App\Services\ReglasAtencion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AltaMedicaController extends Controller
{
    public function guardar(Request $request, Ingreso $ingreso): RedirectResponse
    {
        $request->merge(['medicamentos_casa' => $this->filasConDatos($request->input('medicamentos_casa'))]);
        $datos = $request->validate([
            'medicamentos_casa' => ['nullable', 'array', 'max:20'],
            'medicamentos_casa.*' => ['array:medicamento,dosis,via_administracion,frecuencia,duracion'],
            'medicamentos_casa.*.medicamento' => ['required', 'string', 'max:255'],
            'medicamentos_casa.*.dosis' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'medicamentos_casa.*.via_administracion' => ['nullable', 'string', 'max:80'],
            'medicamentos_casa.*.frecuencia' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'medicamentos_casa.*.duracion' => ['nullable', 'integer', 'min:1'],
            'fecha_hora' => ['required', 'date', ReglasAtencion::reglaNoFutura()],
            'diagnostico_final' => ['required', 'string', 'max:2000'],
            'condicion_egreso' => ['required', 'string', 'max:150'],
            'indicaciones_alta' => ['nullable', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ], [
            'medicamentos_casa.max' => 'Puede indicar como máximo 20 medicamentos para casa.',
            'medicamentos_casa.*.medicamento.required' => 'Escriba el nombre de cada medicamento para casa.',
            'medicamentos_casa.*.dosis.required' => 'Indique la dosis de cada medicamento para casa.',
            'medicamentos_casa.*.frecuencia.required' => 'Indique la frecuencia de cada medicamento para casa.',
        ]);
        $medicamentosCasa = $datos['medicamentos_casa'] ?? [];
        unset($datos['medicamentos_casa']);
        $cancelarCirugias = $request->input('cancelar_cirugias') === '1';
        $idPersonal = DB::table('tbl_usuario as u')->join('tbl_personal as p', 'p.id_personal', '=', 'u.id_personal')
            ->where('u.id_usuario', $request->session()->get('id_usuario'))->where('u.estado', 'Activo')->where('p.estado', 'Activo')->value('p.id_personal');
        if (! $idPersonal) {
            throw ValidationException::withMessages(['id_personal_medico' => 'Su usuario no tiene personal activo vinculado.']);
        }
        $datos['id_personal_medico'] = $idPersonal;
        DB::transaction(function () use ($ingreso, $datos, $medicamentosCasa, $cancelarCirugias): void {
            $actual = Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            ReglasAtencion::exigirActivo($actual->estado);
            app(CirugiasAlAlta::class)->cancelar($actual, $cancelarCirugias);
            $fecha = Carbon::parse($datos['fecha_hora']);
            ReglasAtencion::validarFecha($fecha, $actual->fecha_ingreso, ReglasAtencion::ahora());
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
            $alta = AltaMedica::create($datos + ['id_ingreso' => $actual->getKey(), 'id_usuario_creacion' => session('id_usuario')]);
            foreach ($medicamentosCasa as $fila) {
                $alta->medicamentos()->create(array_map(fn ($valor) => is_string($valor) ? trim($valor) : $valor, $fila));
            }
            $actual->tratamientos()->where('estado', 'Activo')->update(['estado' => 'Finalizado', 'id_usuario_modificacion' => session('id_usuario')]);
            $actual->update(['estado' => 'Dado de alta', 'id_usuario_modificacion' => session('id_usuario')]);
        });

        return redirect()->route('atencion.show', $ingreso)->with('exito', $ingreso->esConsultaExterna() ? 'Consulta externa finalizada.' : 'Alta médica registrada; habitación liberada.');
    }

    /**
     * Descarta las filas de medicamentos para casa que llegaron completamente vacías.
     *
     * @return array<int, mixed>|mixed
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

    /**
     * Receta del alta (o del final de la consulta externa) lista para imprimir o guardar como PDF desde el navegador.
     * El médico es quien registró el alta; si no quedó vinculado, se usa el personal del usuario que la imprime.
     */
    public function receta(Request $request, AltaMedica $alta): View
    {
        $alta->load(['ingreso.paciente', 'medicamentos', 'medico.cargo', 'medico.especialidad']);
        $medico = $alta->medico ?? Personal::with(['cargo', 'especialidad'])
            ->find(DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal'));

        return view('recetas.alta', [
            'alta' => $alta,
            'paciente' => $alta->ingreso->paciente,
            'medico' => $medico,
            'imprimirAlAbrir' => $request->boolean('imprimir'),
        ]);
    }
}
