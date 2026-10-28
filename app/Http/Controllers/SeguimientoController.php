<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Services\RegistroIngreso;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class SeguimientoController extends Controller
{
    public function crear(Ingreso $origen): View|RedirectResponse
    {
        $this->exigirFinalizada($origen);
        $origen->load(['paciente', 'altaMedica.medicamentos']);
        $activo = $origen->paciente->ingresos()->where('estado', 'Activo')->first();
        if ($activo) {
            return redirect()->route('atencion.show', $activo)->with('error', 'El paciente ya tiene una atención activa. Finalícela antes de abrir otro seguimiento.');
        }
        $ingreso = new Ingreso([
            'id_paciente' => $origen->id_paciente,
            'id_ingreso_anterior' => $origen->getKey(),
            'tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA,
            'estado' => 'Activo',
            'fecha_ingreso' => now('America/Guatemala')->format('Y-m-d H:i:s'),
        ]);
        $ingreso->setRelation('paciente', $origen->paciente);
        $ingreso->setRelation('atencionAnterior', $origen);
        $ingreso->setRelation('evoluciones', collect());
        $consultasAnteriores = $ingreso->historialPrevio();

        return view('seguimientos.create', compact('origen', 'ingreso', 'consultasAnteriores'));
    }

    public function guardar(Request $request, Ingreso $origen): RedirectResponse
    {
        $this->exigirFinalizada($origen);
        $datos = $request->validate([
            'fecha_hora' => ['required', 'date', 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')],
            'motivo_atencion' => ['required', 'string', 'max:255'],
        ], ['fecha_hora.before_or_equal' => 'La fecha y hora no pueden ser futuras.']);
        $idUsuario = (int) $request->session()->get('id_usuario');
        $idPersonal = (int) DB::table('tbl_usuario')->where('id_usuario', $idUsuario)->value('id_personal');
        try {
            return DB::transaction(function () use ($datos, $origen, $idUsuario, $idPersonal, $request): RedirectResponse {
                $nuevo = RegistroIngreso::registrar([
                    'fecha_ingreso' => $datos['fecha_hora'],
                    'motivo_ingreso' => $datos['motivo_atencion'],
                    'id_paciente' => $origen->id_paciente,
                    'id_ingreso_anterior' => $origen->getKey(),
                    'tipo_atencion' => Ingreso::TIPO_CONSULTA_EXTERNA,
                ], $idUsuario, $idPersonal);

                return app(EmergenciaController::class)->guardar($request, $nuevo);
            });
        } catch (RuntimeException $error) {
            if ($error instanceof QueryException) {
                throw $error;
            }
            throw ValidationException::withMessages(['fecha_hora' => $error->getMessage()]);
        }
    }

    private function exigirFinalizada(Ingreso $origen): void
    {
        abort_unless($origen->estado === 'Dado de alta' && $origen->altaMedica()->exists() && $origen->paciente->estado === 'Activo', 422, 'El seguimiento necesita una atención finalizada y un paciente activo.');
    }
}
