<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\Ingreso;
use App\Models\Personal;
use App\Models\SignoVital;
use App\Support\Permisos;
use App\Support\RegistroBitacora;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CirugiaController extends Controller
{
    public static function permisos(): array
    {
        $usuario = DB::table('tbl_usuario as u')->join('tbl_personal as p', 'p.id_personal', '=', 'u.id_personal')->join('tbl_cargo as c', 'c.id_cargo', '=', 'p.id_cargo')->join('tbl_rol as r', 'r.id_rol', '=', 'u.id_rol')->where('u.id_usuario', session('id_usuario'))->select('p.id_personal', 'c.nombre_cargo', 'r.nombre_rol')->first();
        $permisos = app(Permisos::class);

        return ['agendar' => $permisos->puede('Cirugías', Permisos::GUARDAR), 'control' => $permisos->puede('Cirugías', Permisos::EDITAR), 'personal' => $usuario->id_personal ?? null];
    }

    public function formulario(Request $request, Ingreso $ingreso): View
    {
        $modo = $request->query('modo', 'agendar');
        abort_unless(in_array($modo, ['agendar', 'realizada'], true) && self::permisos()['agendar'], 403);
        $this->activo($ingreso);
        $cirugia = null;
        if ($request->filled('cirugia')) {
            $cirugia = Cirugia::where('id_ingreso', $ingreso->getKey())->whereKey($request->query('cirugia'))->firstOrFail();
            abort_unless(in_array($cirugia->estado, ['Indicada', 'Programada', 'Aprobada', 'Evaluación preoperatoria'], true), 422);
        }
        $personal = Personal::with('cargo')->where('estado', 'Activo')->whereHas('cargo', fn ($q) => $q->where(function ($q) {
            $q->where('nombre_cargo', 'like', '%édic%')->orWhere('nombre_cargo', 'like', '%iruj%')->orWhere('nombre_cargo', 'like', '%nestes%')->orWhere('nombre_cargo', 'like', '%nferm%');
        }))->orderBy('nombres')->get();

        return view('cirugias.formulario', compact('ingreso', 'modo', 'cirugia', 'personal'));
    }

    public function guardar(Request $request, Ingreso $ingreso): RedirectResponse
    {
        $modo = $request->input('modo');
        abort_unless(in_array($modo, ['agendar', 'realizada'], true) && self::permisos()['agendar'], 403);
        $datos = $request->validate([
            'cirugia' => ['nullable', 'integer'], 'tipo_operacion' => ['required', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'fecha_inicio_real' => [$modo === 'realizada' ? 'required' : 'exclude', 'date', 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')],
            'fecha_fin_real' => [$modo === 'realizada' ? 'required' : 'exclude', 'date', 'after_or_equal:fecha_inicio_real', 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')],
            'motivo_registro_posterior' => [$modo === 'realizada' ? 'required' : 'exclude', 'string', 'max:500'],
            'fecha_programada' => [$modo === 'agendar' ? 'required' : 'nullable', 'date', 'after_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:00')],
            'personal' => ['nullable', 'array', 'max:30'], 'personal.*' => ['required', 'integer', 'distinct', Rule::exists('tbl_personal', 'id_personal')->where('estado', 'Activo')],
        ], ['fecha_programada.required' => 'Indique la fecha y hora de la cirugía.', 'fecha_programada.after_or_equal' => 'La fecha de la cirugía no puede estar en el pasado.', 'personal.*.distinct' => 'No agregue dos veces a la misma persona.']);
        foreach (['fecha_programada', 'fecha_inicio_real', 'fecha_fin_real'] as $campo) {
            if (! empty($datos[$campo])) {
                $datos[$campo] = Carbon::parse($datos[$campo])->format('Y-m-d H:i:s');
            }
        }
        $id = DB::transaction(function () use ($ingreso, $datos, $modo) {
            $actual = Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            $this->activo($actual);
            $cirugia = isset($datos['cirugia']) ? Cirugia::where('id_ingreso', $actual->getKey())->whereKey($datos['cirugia'])->lockForUpdate()->firstOrFail() : new Cirugia;
            if ($cirugia->exists && ($modo !== 'agendar' || ! in_array($cirugia->estado, ['Indicada', 'Programada', 'Aprobada', 'Evaluación preoperatoria'], true))) {
                throw ValidationException::withMessages(['cirugia' => 'Esta cirugía ya no puede reprogramarse.']);
            }
            if ($modo === 'realizada') {
                if ($cirugia->exists) {
                    throw ValidationException::withMessages(['cirugia' => 'Registre la cirugía realizada como un registro nuevo.']);
                }
                if ($datos['fecha_inicio_real'] < $actual->fecha_ingreso->format('Y-m-d H:i:s')) {
                    throw ValidationException::withMessages(['fecha_inicio_real' => 'El inicio real no puede ser anterior a la llegada del paciente.']);
                }
                if (Cirugia::where('id_ingreso', $actual->getKey())->where('fecha_inicio_real', $datos['fecha_inicio_real'])->where('tipo_operacion', trim($datos['tipo_operacion']))->exists()) {
                    throw ValidationException::withMessages(['tipo_operacion' => 'Ya existe esta cirugía con la misma fecha de inicio. Revise el historial.']);
                }
                if (Cirugia::where('id_ingreso', $actual->getKey())->where('estado', 'En cirugía')->exists()) {
                    throw ValidationException::withMessages(['cirugia' => 'Primero revise la cirugía que figura en curso.']);
                }
            }
            if (in_array($modo, ['agendar', 'realizada'], true)) {
                $seleccionados = Personal::with('cargo')->whereIn('id_personal', $datos['personal'] ?? [])->orderBy('id_personal')->lockForUpdate()->get();
                foreach ($seleccionados as $persona) {
                    if ($persona->estado !== 'Activo' || ! preg_match('/médic|medic|ciruj|anestes|enferm/u', mb_strtolower($persona->cargo?->nombre_cargo ?? ''))) {
                        throw ValidationException::withMessages(['personal' => 'Seleccione médicos o personal de enfermería activos.']);
                    }
                }
            }
            if ($modo === 'agendar') {
                if ($datos['fecha_programada'] < $actual->fecha_ingreso->format('Y-m-d H:i:s')) {
                    throw ValidationException::withMessages(['fecha_programada' => 'La cirugía no puede ser anterior al ingreso.']);
                }
                $personal = Personal::with('cargo')->whereIn('id_personal', $datos['personal'] ?? [])->orderBy('id_personal')->lockForUpdate()->get();
                foreach ($personal as $persona) {
                    $cargo = mb_strtolower($persona->cargo?->nombre_cargo ?? '');
                    if (! preg_match('/médic|medic|ciruj|anestes|enferm/u', $cargo)) {
                        throw ValidationException::withMessages(['personal' => 'Seleccione únicamente médicos o personal de enfermería.']);
                    }
                }
                $solapadas = Cirugia::whereIn('estado', ['Programada', 'Aprobada', 'Evaluación preoperatoria', 'En cirugía'])->where(function ($q) use ($datos) {
                    // Sin duración conocida: choca otra cirugía a la misma hora, o una anterior que tenía un fin estimado posterior.
                    $q->where('fecha_programada', $datos['fecha_programada'])
                        ->orWhere(fn ($q) => $q->where('fecha_programada', '<', $datos['fecha_programada'])->where('fecha_fin_programada', '>', $datos['fecha_programada']));
                })->when($cirugia->exists, fn ($q) => $q->where('id_cirugia', '<>', $cirugia->getKey()))->where(function ($q) use ($actual, $datos) {
                    $q->where('id_ingreso', $actual->getKey());
                    if (! empty($datos['personal'])) {
                        $q->orWhereHas('equipo', fn ($q) => $q->whereIn('tbl_personal.id_personal', $datos['personal'])->where('tbl_cirugia_equipo.estado', '<>', 'Cancelado'));
                    }
                })->exists();
                if ($solapadas) {
                    throw ValidationException::withMessages(['fecha_programada' => 'El paciente o el personal seleccionado ya tiene una cirugía en ese horario.']);
                }
            }
            $cirugia->fill(['id_ingreso' => $actual->getKey(), 'tipo_operacion' => trim($datos['tipo_operacion']), 'observaciones' => $datos['observaciones'] ?? null, 'estado' => $modo === 'realizada' ? 'Realizada' : 'Programada']);
            if (! $cirugia->exists) {
                $cirugia->id_usuario_creacion = session('id_usuario');
                $cirugia->id_personal_indica = null;
            } else {
                $cirugia->id_usuario_modificacion = session('id_usuario');
            }
            if ($modo === 'agendar') {
                $cirugia->fecha_programada = $datos['fecha_programada'];
                // La duración no se conoce de antemano: solo se agenda la hora de inicio.
                $cirugia->fecha_fin_programada = null;
            }
            if ($modo === 'realizada') {
                $cirugia->fecha_inicio_real = $datos['fecha_inicio_real'];
                $cirugia->fecha_fin_real = $datos['fecha_fin_real'];
                $cirugia->motivo_registro_posterior = trim($datos['motivo_registro_posterior']);
            }
            $cirugia->save();
            if (in_array($modo, ['agendar', 'realizada'], true)) {
                $cambiosEquipo = $cirugia->equipo()->syncWithPivotValues($datos['personal'] ?? [], ['rol_quirurgico' => 'Otro', 'estado' => $modo === 'realizada' ? 'Participó' : 'Asignado']);
                if (array_filter($cambiosEquipo)) {
                    app(RegistroBitacora::class)->registrar(request(), 'Asignación de equipo quirúrgico', 'Exitoso', 'Se actualizó el personal asignado a la cirugía.', (int) session('id_usuario'), (int) $cirugia->getKey(), null, 'tbl_cirugia');
                }
            }

            return $cirugia->getKey();
        });

        return redirect()->route('cirugias.show', $id)->with('exito', $modo === 'realizada' ? 'Cirugía realizada registrada con sus horarios reales.' : 'Cirugía agendada correctamente.');
    }

    public function show(Cirugia $cirugia): View
    {
        $cirugia->load(['ingreso.paciente', 'equipo.cargo', 'personalIndica', 'controles.personal']);

        return view('cirugias.show', compact('cirugia'));
    }

    public function estado(Request $request, Cirugia $cirugia): RedirectResponse
    {
        abort_unless(self::permisos()['agendar'], 403);
        $datos = $request->validate(['accion' => ['required', Rule::in(['iniciar', 'terminar', 'cancelar'])], 'fecha_hora' => ['required', 'date', 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')], 'motivo_cancelacion' => ['required_if:accion,cancelar', 'nullable', 'string', 'max:255']]);
        $datos['fecha_hora'] = Carbon::parse($datos['fecha_hora'])->format('Y-m-d H:i:s');
        DB::transaction(function () use ($cirugia, $datos) {
            $ingreso = Ingreso::whereKey($cirugia->id_ingreso)->lockForUpdate()->firstOrFail();
            $this->activo($ingreso);
            $actual = Cirugia::whereKey($cirugia->getKey())->lockForUpdate()->firstOrFail();
            if ($datos['fecha_hora'] < $ingreso->fecha_ingreso->format('Y-m-d H:i:s')) {
                throw ValidationException::withMessages(['fecha_hora' => 'La fecha no puede ser anterior al ingreso.']);
            }
            $programada = in_array($actual->estado, ['Programada', 'Aprobada', 'Evaluación preoperatoria'], true);
            if ($datos['accion'] === 'iniciar' && $programada) {
                if (Cirugia::where('id_ingreso', $actual->id_ingreso)->where('estado', 'En cirugía')->exists()) {
                    throw ValidationException::withMessages(['accion' => 'El paciente ya está en cirugía.']);
                }
                $actual->estado = 'En cirugía';
                $actual->fecha_inicio_real = $datos['fecha_hora'];
            } elseif ($datos['accion'] === 'terminar' && $actual->estado === 'En cirugía') {
                if ($datos['fecha_hora'] < $actual->fecha_inicio_real->format('Y-m-d H:i:s')) {
                    throw ValidationException::withMessages(['fecha_hora' => 'La finalización no puede ser anterior al inicio real.']);
                }
                $actual->estado = 'Realizada';
                $actual->fecha_fin_real = $datos['fecha_hora'];
                DB::table('tbl_cirugia_equipo')->where('id_cirugia', $actual->getKey())->update(['estado' => 'Participó']);
            } elseif ($datos['accion'] === 'cancelar' && ($programada || $actual->estado === 'Indicada')) {
                $actual->estado = 'Cancelada';
                $actual->motivo_cancelacion = $datos['motivo_cancelacion'];
                DB::table('tbl_cirugia_equipo')->where('id_cirugia', $actual->getKey())->update(['estado' => 'Cancelado']);
            } else {
                throw ValidationException::withMessages(['accion' => 'El estado de la cirugía cambió. Recargue la página.']);
            }
            $actual->id_usuario_modificacion = session('id_usuario');
            $actual->save();
        });

        return back()->with('exito', 'Estado de la cirugía actualizado.');
    }

    public function control(Request $request, Cirugia $cirugia): RedirectResponse
    {
        abort_unless(self::permisos()['control'], 403);
        $datos = $request->validate([
            'fecha_hora' => ['required', 'date', 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')],
            'presion_sistolica' => ['nullable', 'required_with:presion_diastolica', 'integer', 'between:30,300'],
            'presion_diastolica' => ['nullable', 'required_with:presion_sistolica', 'integer', 'between:20,200'],
            'frecuencia_cardiaca' => ['nullable', 'integer', 'between:1,300'], 'frecuencia_respiratoria' => ['nullable', 'integer', 'between:1,100'],
            'temperatura' => ['nullable', 'numeric', 'between:25,50'], 'saturacion_oxigeno' => ['nullable', 'integer', 'between:0,100'], 'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);
        if (collect(['presion_sistolica', 'frecuencia_cardiaca', 'frecuencia_respiratoria', 'temperatura', 'saturacion_oxigeno'])->every(fn ($c) => ! isset($datos[$c]))) {
            throw ValidationException::withMessages(['signos' => 'Registre al menos un signo vital.']);
        }
        $datos['fecha_hora'] = Carbon::parse($datos['fecha_hora'])->format('Y-m-d H:i:s');
        DB::transaction(function () use ($cirugia, $datos) {
            $ingreso = Ingreso::whereKey($cirugia->id_ingreso)->lockForUpdate()->firstOrFail();
            $this->activo($ingreso);
            $actual = Cirugia::whereKey($cirugia->getKey())->lockForUpdate()->firstOrFail();
            if ($actual->estado !== 'Realizada' || ! $actual->fecha_fin_real) {
                throw ValidationException::withMessages(['signos' => 'Primero registre que la cirugía terminó.']);
            }
            if ($datos['fecha_hora'] < $actual->fecha_fin_real->format('Y-m-d H:i:s')) {
                throw ValidationException::withMessages(['fecha_hora' => 'El control no puede ser anterior a la finalización de la cirugía.']);
            }
            SignoVital::create($datos + ['id_ingreso' => $actual->id_ingreso, 'id_cirugia' => $actual->getKey(), 'tipo_control' => 'Recuperación', 'id_personal' => self::permisos()['personal'], 'id_usuario_creacion' => session('id_usuario')]);
        });

        return back()->with('exito', 'Control de recuperación registrado.');
    }

    private function activo(Ingreso $ingreso): void
    {
        if ($ingreso->estado !== 'Activo' || $ingreso->paciente->estado !== 'Activo') {
            throw ValidationException::withMessages(['cirugia' => 'La atención y el paciente deben estar activos.']);
        }
    }
}
