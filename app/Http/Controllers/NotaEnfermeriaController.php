<?php

namespace App\Http\Controllers;

use App\Models\Hospitalizacion;
use App\Models\Ingreso;
use App\Models\NotaEnfermeria;
use App\Models\Personal;
use App\Models\SignoVital;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Notas de la hoja de enfermería. Una nota guardada no se modifica: si hay que corregir algo,
 * se registra una nota nueva, igual que en una hoja de papel.
 */
class NotaEnfermeriaController extends Controller
{
    /** @var list<string> */
    private const MEDIDAS = ['presion_sistolica', 'presion_diastolica', 'frecuencia_cardiaca', 'frecuencia_respiratoria', 'temperatura', 'saturacion_oxigeno'];

    public function guardar(Request $request, Ingreso $ingreso): RedirectResponse
    {
        $idPersonal = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');

        if (! $idPersonal) {
            return back()->withInput()->with('error', 'El usuario no tiene personal asociado.');
        }

        $datos = $request->validate([
            'fecha_hora' => ['required', 'date', 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')],
            'estado_paciente' => ['required', Rule::in(NotaEnfermeria::ESTADOS)],
            'presion_sistolica' => ['nullable', 'required_with:presion_diastolica', 'integer', 'between:30,300'],
            'presion_diastolica' => ['nullable', 'required_with:presion_sistolica', 'integer', 'between:20,200'],
            'frecuencia_cardiaca' => ['nullable', 'integer', 'between:1,300'],
            'frecuencia_respiratoria' => ['nullable', 'integer', 'between:1,100'],
            'temperatura' => ['nullable', 'numeric', 'between:25,50'],
            'saturacion_oxigeno' => ['nullable', 'integer', 'between:0,100'],
            'actividades' => ['nullable', 'array'],
            'actividades.*' => ['distinct', Rule::in(NotaEnfermeria::ACTIVIDADES)],
            'actividad_otra' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'aviso_medico' => ['nullable', 'boolean'],
            'id_personal_avisado' => ['exclude_unless:aviso_medico,1', 'required', 'integer', Rule::exists('tbl_personal', 'id_personal')->where('estado', 'Activo')],
        ], [
            'id_personal_avisado.required' => 'Seleccione a qué médico se avisó.',
            'id_personal_avisado.exists' => 'Seleccione un médico activo de la lista.',
            'fecha_hora.required' => 'Indique la fecha y hora de la nota.',
            'fecha_hora.before_or_equal' => 'La fecha y hora no pueden ser futuras.',
            'estado_paciente.required' => 'Indique cómo está el paciente.',
            'estado_paciente.in' => 'El estado del paciente no es válido.',
            'presion_sistolica.required_with' => 'Escriba también la presión sistólica.',
            'presion_sistolica.between' => 'La presión sistólica debe estar entre 30 y 300.',
            'presion_diastolica.required_with' => 'Escriba también la presión diastólica.',
            'presion_diastolica.between' => 'La presión diastólica debe estar entre 20 y 200.',
            'frecuencia_cardiaca.between' => 'La frecuencia cardíaca debe estar entre 1 y 300.',
            'frecuencia_respiratoria.between' => 'La frecuencia respiratoria debe estar entre 1 y 100.',
            'temperatura.between' => 'La temperatura debe estar entre 25 y 50.',
            'saturacion_oxigeno.between' => 'La saturación debe estar entre 0 y 100.',
            'actividades.*.in' => 'Una de las actividades no es válida.',
            'actividad_otra.max' => 'La otra actividad no puede superar los 100 caracteres.',
            'observaciones.max' => 'La nota no puede superar los 2000 caracteres.',
        ]);

        $actividades = $datos['actividades'] ?? [];
        $otra = trim((string) ($datos['actividad_otra'] ?? ''));
        if ($otra !== '') {
            $actividades[] = $otra;
        }
        $nota = trim((string) ($datos['observaciones'] ?? ''));
        $conSignos = collect(self::MEDIDAS)->contains(fn (string $medida): bool => isset($datos[$medida]));

        if (! $conSignos && $actividades === [] && $nota === '') {
            throw ValidationException::withMessages(['nota' => 'Registre signos vitales, marque una actividad o escriba la nota.']);
        }

        $avisoMedico = (bool) ($datos['aviso_medico'] ?? false);
        if ($avisoMedico && ! Personal::whereKey($datos['id_personal_avisado'])->whereHas('cargo', fn ($consulta) => $consulta->whereIn('nombre_cargo', Personal::CARGOS_MEDICOS))->exists()) {
            throw ValidationException::withMessages(['id_personal_avisado' => 'Seleccione un médico activo de la lista.']);
        }

        $fecha = Carbon::parse($datos['fecha_hora']);

        DB::transaction(function () use ($ingreso, $datos, $actividades, $nota, $conSignos, $fecha, $idPersonal, $avisoMedico): void {
            $actual = Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            $internado = Hospitalizacion::where('id_ingreso', $actual->getKey())->where('estado', 'Activa')->exists();

            if ($actual->estado !== 'Activo' || ! $internado) {
                throw ValidationException::withMessages(['nota' => 'Solo se registran notas de enfermería de pacientes internados.']);
            }

            if ($fecha->lt($actual->fecha_ingreso)) {
                throw ValidationException::withMessages(['fecha_hora' => 'La nota no puede ser anterior al ingreso del paciente.']);
            }

            $signos = $conSignos ? SignoVital::create([
                'id_ingreso' => $actual->getKey(),
                'id_personal' => $idPersonal,
                'tipo_control' => 'Rutina',
                'fecha_hora' => $fecha,
                ...collect(self::MEDIDAS)->mapWithKeys(fn (string $medida): array => [$medida => $datos[$medida] ?? null])->all(),
                'id_usuario_creacion' => session('id_usuario'),
            ]) : null;

            $registro = NotaEnfermeria::create([
                'id_ingreso' => $actual->getKey(),
                'id_personal' => $idPersonal,
                'fecha_hora' => $fecha,
                'estado_paciente' => $datos['estado_paciente'],
                'id_signos_vitales' => $signos?->getKey(),
                'observaciones' => $nota !== '' ? $nota : null,
                'aviso_medico' => $avisoMedico,
                'id_personal_avisado' => $avisoMedico ? (int) $datos['id_personal_avisado'] : null,
                'id_usuario_creacion' => session('id_usuario'),
            ]);

            $registro->actividades()->createMany(array_map(fn (string $actividad): array => ['actividad' => $actividad], $actividades));
        });

        return redirect()->route('atencion.enfermeria', $ingreso)->with('exito', 'Nota de enfermería guardada correctamente.')
            ->with('borrador_clinico_guardado', 'nota-enfermeria');
    }
}
