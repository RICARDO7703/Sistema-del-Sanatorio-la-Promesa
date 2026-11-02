<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class AuditoriaModelos
{
    public function registrar(string $evento, Model $modelo): void
    {
        $request = request();
        if (! $request->attributes->get('auditar_operaciones') || ! $request->hasSession() || ! $request->session()->get('id_usuario')) {
            return;
        }
        $nombres = [
            'tbl_paciente' => 'paciente', 'tbl_contacto_paciente' => 'contacto del paciente',
            'tbl_personal' => 'personal', 'tbl_habitacion' => 'habitación',
            'tbl_ingreso' => 'ingreso', 'tbl_hospitalizacion' => 'hospitalización',
            'tbl_hospitalizacion_habitacion' => 'asignación de habitación',
            'tbl_evolucion_clinica' => 'evolución clínica', 'tbl_signos_vitales' => 'signos vitales',
            'tbl_tratamiento' => 'tratamiento', 'tbl_atencion_enfermeria' => 'nota de enfermería',
            'tbl_atencion_enfermeria_actividad' => 'actividad de enfermería',
            'tbl_alta_medica' => 'alta médica', 'tbl_alta_medicamento' => 'medicamento de alta',
            'tbl_cirugia' => 'cirugía', 'tbl_documento_clinico' => 'archivo clínico',
        ];
        $tabla = $modelo->getTable();
        if (! isset($nombres[$tabla]) || ($evento === 'updated' && ! $modelo->wasChanged())) {
            return;
        }
        $verbo = match ($evento) {
            'created' => 'Registro de ', 'deleted' => 'Eliminación de ', default => 'Actualización de '
        };
        if ($evento === 'updated' && $request->attributes->get('edicion_documento')) {
            $verbo = 'Edición de ';
        }
        if ($evento === 'updated' && $modelo->wasChanged('estado')) {
            $verbo = match ($modelo->estado) {
                'Inactivo' => 'Desactivación de ', 'Activo' => 'Activación de ', 'Cancelado', 'Cancelada' => 'Cancelación de ', default => 'Cambio de estado de '
            };
        }
        $detalle = 'Operación guardada en el sistema.';
        if ($evento === 'updated') {
            $campos = array_diff(array_keys($modelo->getChanges()), ['contrasena', 'password', 'token', 'id_usuario_modificacion', 'fecha_actualizacion', 'updated_at']);
            $detalle .= ' Campos modificados: '.implode(', ', $campos).'.';
        }
        app(RegistroBitacora::class)->registrar($request, $verbo.$nombres[$tabla], 'Exitoso', $detalle,
            (int) $request->session()->get('id_usuario'), (int) $modelo->getKey(), null, $tabla);
    }
}
