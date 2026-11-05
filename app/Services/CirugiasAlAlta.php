<?php

namespace App\Services;

use App\Models\Cirugia;
use App\Models\Ingreso;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CirugiasAlAlta
{
    public const PENDIENTES = ['Indicada', 'Programada', 'Aprobada', 'Evaluación preoperatoria'];

    public function resolverProgramadasPaciente(Ingreso $ingreso, mixed $decision, string $campo, bool $reemplazo): void
    {
        $pendientes = Cirugia::whereHas('ingreso', fn ($q) => $q->where('id_paciente', $ingreso->id_paciente))
            ->whereIn('estado', self::PENDIENTES)->orderBy('id_cirugia')->lockForUpdate()->get();
        if ($pendientes->isEmpty()) {
            return;
        }
        if (! in_array($decision, ['mantener', 'cancelar'], true)) {
            throw ValidationException::withMessages([$campo => 'Ya tiene una cirugía programada. Indique si desea conservar o cancelar la programación anterior.']);
        }
        if ($decision === 'mantener') {
            return;
        }
        foreach ($pendientes as $cirugia) {
            abort_unless($cirugia->programacionGestionable() && $cirugia->permiteAccion('cancelar'), 403, 'Solo el personal participante puede cancelar la cirugía anterior.');
            $cirugia->forceFill(['estado' => 'Cancelada', 'motivo_cancelacion' => $reemplazo ? 'Cancelada al registrar una nueva programación de cirugía.' : 'Cancelada al registrar el alta médica del paciente.', 'fecha_actualizacion' => now('UTC'), 'id_usuario_modificacion' => session('id_usuario')])->save();
            DB::table('tbl_cirugia_equipo')->where('id_cirugia', $cirugia->getKey())->where('estado', 'Asignado')->update(['estado' => 'Cancelado']);
        }
    }

    /** Se ejecuta dentro de la transacción del alta, con el ingreso bloqueado. */
    public function cancelar(Ingreso $ingreso, bool $confirmado, bool $mantener = false): void
    {
        $cirugias = Cirugia::where('id_ingreso', $ingreso->getKey())->orderBy('id_cirugia')->lockForUpdate()->get();
        if ($cirugias->contains('estado', 'En cirugía')) {
            throw ValidationException::withMessages(['cancelar_cirugias' => 'El paciente tiene una cirugía en curso. Registre su finalización antes de dar el alta.']);
        }
        if ($mantener && $ingreso->esConsultaExterna()) {
            return;
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
