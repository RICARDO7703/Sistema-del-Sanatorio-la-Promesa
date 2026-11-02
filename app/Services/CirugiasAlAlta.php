<?php

namespace App\Services;

use App\Models\Cirugia;
use App\Models\Ingreso;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CirugiasAlAlta
{
    public const PENDIENTES = ['Indicada', 'Programada', 'Aprobada', 'Evaluación preoperatoria'];

    /** Se ejecuta dentro de la transacción del alta, con el ingreso bloqueado. */
    public function cancelar(Ingreso $ingreso, bool $confirmado): void
    {
        $cirugias = Cirugia::where('id_ingreso', $ingreso->getKey())->orderBy('id_cirugia')->lockForUpdate()->get();
        if ($cirugias->contains('estado', 'En cirugía')) {
            throw ValidationException::withMessages(['cancelar_cirugias' => 'El paciente tiene una cirugía en curso. Registre su finalización antes de dar el alta.']);
        }
        $pendientes = $cirugias->whereIn('estado', self::PENDIENTES);
        if ($pendientes->isNotEmpty() && ! $confirmado) {
            throw ValidationException::withMessages(['cancelar_cirugias' => 'El paciente tiene cirugías pendientes. Confirme su cancelación para registrar el alta.']);
        }
        foreach ($pendientes as $cirugia) {
            $cirugia->update(['estado' => 'Cancelada', 'motivo_cancelacion' => 'Cancelada al registrar el alta médica del paciente.', 'id_usuario_modificacion' => session('id_usuario')]);
            DB::table('tbl_cirugia_equipo')->where('id_cirugia', $cirugia->getKey())->where('estado', 'Asignado')->update(['estado' => 'Cancelado']);
        }
    }
}
