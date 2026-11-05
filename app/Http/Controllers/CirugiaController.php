<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\EvolucionClinica;
use App\Models\Habitacion;
use App\Models\Hospitalizacion;
use App\Models\HospitalizacionHabitacion;
use App\Models\Ingreso;
use App\Models\Personal;
use App\Services\OrdenesMedicas;
use App\Support\CambiosEdicion;
use App\Support\EdicionClinica;
use App\Support\Permisos;
use App\Support\RegistroBitacora;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
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

        return ['agendar' => $permisos->puede('Cirugías', Permisos::GUARDAR), 'editar' => $permisos->puede('Cirugías', Permisos::EDITAR), 'nota' => $permisos->puede('Enfermería', Permisos::GUARDAR), 'personal' => $usuario->id_personal ?? null];
    }

    public function formulario(Request $request, Ingreso $ingreso): View
    {
        $modo = $request->query('modo', 'agendar');
        abort_unless(in_array($modo, ['agendar', 'realizada'], true) && self::permisos()['agendar'], 403);
        $this->activo($ingreso);
        if ($modo === 'realizada') {
            abort_unless($ingreso->hospitalizacion()->where('estado', 'Activa')->exists(), 422, 'Primero interne al paciente para registrar una cirugía ya realizada.');
        }
        $cirugia = null;
        if ($request->filled('cirugia')) {
            $cirugia = Cirugia::where('id_ingreso', $ingreso->getKey())->whereKey($request->query('cirugia'))->firstOrFail();
            abort_unless(in_array($cirugia->estado, ['Indicada', 'Programada', 'Aprobada', 'Evaluación preoperatoria'], true), 422);
        }
        $personal = self::personalQuirurgico();

        return view('cirugias.formulario', compact('ingreso', 'modo', 'cirugia', 'personal'));
    }

    /**
     * Médicos, cirujanos, anestesiólogos y enfermería activos que pueden participar en una cirugía.
     *
     * @return Collection<int, Personal>
     */
    public static function personalQuirurgico(): Collection
    {
        return Personal::with('cargo')->where('estado', 'Activo')->whereHas('cargo', fn ($q) => $q->where(function ($q) {
            $q->where('nombre_cargo', 'like', '%édic%')->orWhere('nombre_cargo', 'like', '%iruj%')->orWhere('nombre_cargo', 'like', '%nestes%')->orWhere('nombre_cargo', 'like', '%nferm%');
        }))->orderBy('nombres')->get();
    }

    public function guardar(Request $request, Ingreso $ingreso): RedirectResponse
    {
        $modo = $request->input('modo');
        $id = $this->registrarSolicitud($request, $ingreso);

        return redirect()->route('pacientes.expediente', $ingreso->id_paciente)->with('abrir_cirugia', $id)
            ->with('exito', $modo === 'realizada' ? 'Cirugía realizada registrada con sus horarios reales.' : 'Cirugía agendada correctamente.');
    }

    public function registrarPendiente(Request $request, Ingreso $ingreso, array $programacion): void
    {
        $campos = array_intersect_key($programacion, array_flip(['tipo_operacion', 'fecha_programada', 'observaciones', 'personal']));
        try {
            $this->registrarSolicitud($request->duplicate($request->query->all(), ['modo' => 'agendar'] + $campos), $ingreso);
        } catch (ValidationException $error) {
            throw ValidationException::withMessages(['cirugia_pendiente' => implode(' ', array_merge(...array_values($error->errors())))]);
        }
    }

    private function registrarSolicitud(Request $request, Ingreso $ingreso): int
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

        return DB::transaction(function () use ($ingreso, $datos, $modo) {
            $actual = Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            $this->activo($actual);
            if ($modo === 'realizada' && ! $actual->hospitalizacion()->where('estado', 'Activa')->exists()) {
                throw ValidationException::withMessages(['cirugia' => 'Primero interne al paciente para registrar una cirugía ya realizada.']);
            }
            $cirugia = isset($datos['cirugia']) ? Cirugia::where('id_ingreso', $actual->getKey())->whereKey($datos['cirugia'])->lockForUpdate()->firstOrFail() : new Cirugia;
            if ($cirugia->exists) {
                abort_unless($cirugia->permiteAccion('reprogramar'), 403);
            }
            if ($cirugia->exists && ($modo !== 'agendar' || ! in_array($cirugia->estado, ['Indicada', 'Programada', 'Aprobada', 'Evaluación preoperatoria'], true))) {
                throw ValidationException::withMessages(['cirugia' => 'Esta cirugía ya no puede reprogramarse.']);
            }
            if ($modo === 'realizada') {
                if ($cirugia->exists) {
                    throw ValidationException::withMessages(['cirugia' => 'Registre la cirugía realizada como un registro nuevo.']);
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
                $this->validarCoincidenciaHorario($datos['fecha_programada'], $datos['personal'] ?? [], $actual->getKey(), $cirugia->exists ? $cirugia->getKey() : null);
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

    }

    /**
     * Los detalles se ven en una ventana flotante: los enlaces antiguos llevan a la atención (o al expediente) y la abren.
     */
    public function show(Cirugia $cirugia): RedirectResponse
    {
        $ingreso = $cirugia->ingreso;
        $destino = $ingreso->estado === 'Activo' ? route('atencion.show', $ingreso) : route('pacientes.expediente', $ingreso->id_paciente);

        return redirect()->to($destino)->with('abrir_cirugia', $cirugia->getKey());
    }

    /**
     * Corrección de una cirugía: solo quien la registró, durante las primeras 12 horas y con la atención activa.
     */
    public function actualizar(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $cirugia->loadMissing('ingreso.paciente');
        if ($cirugia->estado === 'En cirugía') {
            throw ValidationException::withMessages(['cirugia' => 'Los datos de la cirugía no se pueden editar mientras está en curso.']);
        }
        if (! $cirugia->puedeCorregirDatos()) {
            throw ValidationException::withMessages(['cirugia' => EdicionClinica::MENSAJE]);
        }
        $cambiarEstado = $request->exists('accion') || $request->exists('fecha_hora');
        if ($cambiarEstado) {
            $accion = $request->filled('accion') ? $request->input('accion') : 'reprogramar';
            abort_unless($cirugia->permiteAccion($accion), 403);
        }
        $ahora = now('America/Guatemala')->format('Y-m-d H:i:s');
        $datos = $request->validate([
            'tipo_operacion' => [$cirugia->estado === 'Realizada' ? 'exclude' : 'required', 'string', 'max:150'],
            'observaciones' => [$cirugia->estado === 'Realizada' ? 'exclude' : 'nullable', 'string', 'max:2000'],
            'fecha_inicio_real' => [$cirugia->estado !== 'Realizada' && $cirugia->fecha_inicio_real ? 'required' : 'exclude', 'date', 'before_or_equal:'.$ahora],
            'fecha_fin_real' => [$cirugia->estado !== 'Realizada' && $cirugia->fecha_fin_real ? 'required' : 'exclude', 'date', 'after_or_equal:fecha_inicio_real', 'before_or_equal:'.$ahora],
            'motivo_registro_posterior' => [$cirugia->estado !== 'Realizada' && $cirugia->motivo_registro_posterior !== null ? 'required' : 'exclude', 'string', 'max:500'],
            'personal' => ['nullable', 'array', 'max:30'], 'personal.*' => ['required', 'integer', 'distinct', Rule::exists('tbl_personal', 'id_personal')->where('estado', 'Activo')],
        ], [
            'tipo_operacion.required' => 'Indique el tipo de operación.',
            'fecha_inicio_real.required' => 'Indique la hora real de inicio.',
            'fecha_inicio_real.before_or_equal' => 'El inicio no puede ser una hora futura.',
            'fecha_fin_real.required' => 'Indique la hora real de finalización.',
            'fecha_fin_real.after_or_equal' => 'La finalización no puede ser anterior al inicio.',
            'fecha_fin_real.before_or_equal' => 'La finalización no puede ser una hora futura.',
            'motivo_registro_posterior.required' => 'Indique por qué se registró después.',
            'personal.*.distinct' => 'No agregue dos veces a la misma persona.',
        ]);
        foreach (['fecha_inicio_real', 'fecha_fin_real'] as $campo) {
            if (! empty($datos[$campo])) {
                $datos[$campo] = Carbon::parse($datos[$campo])->format('Y-m-d H:i:s');
            }
        }

        $respuestaEstado = DB::transaction(function () use ($cirugia, $datos, $request, $cambiarEstado): ?RedirectResponse {
            $ingreso = Ingreso::whereKey($cirugia->id_ingreso)->lockForUpdate()->firstOrFail();
            $actual = Cirugia::whereKey($cirugia->getKey())->lockForUpdate()->firstOrFail()->setRelation('ingreso', $ingreso);
            if (! $actual->atencionQuirurgicaDisponible()) {
                $this->activo($ingreso);
            }
            if ($actual->estado === 'En cirugía') {
                throw ValidationException::withMessages(['cirugia' => 'Los datos de la cirugía no se pueden editar mientras está en curso.']);
            }
            if ($actual->estado === 'Realizada') {
                $datos = array_intersect_key($datos, array_flip(['personal']));
            }
            if (! $actual->puedeCorregirDatos()) {
                throw ValidationException::withMessages(['cirugia' => EdicionClinica::MENSAJE]);
            }
            if (isset($datos['fecha_fin_real'])) {
                $primerControl = $actual->controles()->reorder()->orderBy('fecha_hora')->value('fecha_hora');
                if ($primerControl && Carbon::parse($primerControl)->format('Y-m-d H:i:s') < $datos['fecha_fin_real']) {
                    throw ValidationException::withMessages(['fecha_fin_real' => 'Ya hay controles de recuperación anteriores a esa hora de finalización.']);
                }
            }
            $seleccionados = Personal::with('cargo')->whereIn('id_personal', $datos['personal'] ?? [])->orderBy('id_personal')->lockForUpdate()->get();
            foreach ($seleccionados as $persona) {
                if ($persona->estado !== 'Activo' || ! preg_match('/médic|medic|ciruj|anestes|enferm/u', mb_strtolower($persona->cargo?->nombre_cargo ?? ''))) {
                    throw ValidationException::withMessages(['personal' => 'Seleccione médicos o personal de enfermería activos.']);
                }
            }

            if ($actual->estado !== 'Realizada') {
                $actual->fill([
                    'tipo_operacion' => trim($datos['tipo_operacion']),
                    'observaciones' => filled($datos['observaciones'] ?? null) ? trim($datos['observaciones']) : null,
                ]);
            }
            foreach (['fecha_inicio_real', 'fecha_fin_real'] as $campo) {
                if (isset($datos[$campo])) {
                    $actual->{$campo} = $datos[$campo];
                }
            }
            if (isset($datos['motivo_registro_posterior'])) {
                $actual->motivo_registro_posterior = trim($datos['motivo_registro_posterior']);
            }
            $actual->id_usuario_modificacion = session('id_usuario');
            if ($actual->estado !== 'Realizada' && $actual->fecha_programada) {
                $this->validarCoincidenciaHorario($actual->fecha_programada->format('Y-m-d H:i:s'), $datos['personal'] ?? [], $ingreso->getKey(), $actual->getKey());
            }
            $actual->save();

            // Se compara el equipo antes y después: así no se cuenta como cambio volver a guardar las mismas personas.
            $equipoDe = fn (): array => DB::table('tbl_cirugia_equipo')->where('id_cirugia', $actual->getKey())->orderBy('id_personal')->get(['id_personal', 'rol_quirurgico', 'estado'])->map(fn ($fila) => (array) $fila)->all();
            $equipoAntes = $equipoDe();
            if ($actual->estado === 'Realizada') {
                $existentes = $actual->equipo()->pluck('tbl_personal.id_personal')->all();
                $nuevos = array_values(array_diff($datos['personal'] ?? [], $existentes));
                if (count($existentes) + count($nuevos) > 30) {
                    throw ValidationException::withMessages(['personal' => 'Puede registrar hasta 30 participantes.']);
                }
                $actual->equipo()->syncWithPivotValues($nuevos, ['rol_quirurgico' => 'Otro', 'estado' => 'Participó'], false);
            } else {
                $actual->equipo()->syncWithPivotValues($datos['personal'] ?? [], ['rol_quirurgico' => 'Otro', 'estado' => 'Asignado']);
            }
            if ($equipoDe() != $equipoAntes) {
                app(RegistroBitacora::class)->registrar(request(), 'Asignación de equipo quirúrgico', 'Exitoso', 'Se corrigió el personal de la cirugía.', (int) session('id_usuario'), (int) $actual->getKey(), null, 'tbl_cirugia');
                app(CambiosEdicion::class)->registrar();
            }
            if ($cambiarEstado) {
                return $this->estado($request, $actual->fresh());
            }

            return null;
        });

        if ($respuestaEstado) {
            return $respuestaEstado;
        }

        return redirect()->route('pacientes.expediente', $cirugia->ingreso->id_paciente)->with('exito', 'Cirugía corregida.')->with('abrir_cirugia', $cirugia->getKey());
    }

    public function estado(Request $request, Cirugia $cirugia): RedirectResponse
    {
        if (! $request->filled('accion')) {
            $request->merge(['accion' => 'reprogramar']);
        }
        if (in_array($request->input('accion'), ['iniciar', 'terminar', 'cancelar'], true)) {
            $request->merge(['fecha_hora' => now('America/Guatemala')->format('Y-m-d H:i:s')]);
        }
        $datos = $request->validate(['accion' => ['required', Rule::in(['iniciar', 'terminar', 'cancelar', 'reprogramar'])], 'fecha_hora' => ['required', 'date', $request->input('accion') === 'reprogramar' ? 'after_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:00') : 'before_or_equal:'.now('America/Guatemala')->format('Y-m-d H:i:s')], 'motivo_cancelacion' => ['required_if:accion,cancelar', 'nullable', 'string', 'max:255']]);
        $datos += $request->validate([
            'internar_al_finalizar' => ['exclude_unless:accion,terminar', 'nullable', 'boolean'],
            'habitacion_recuperacion' => ['exclude_unless:internar_al_finalizar,1', 'required', 'integer', 'exists:tbl_habitacion,id_habitacion'],
        ], ['habitacion_recuperacion.required' => 'Seleccione la habitación para el internamiento después de la cirugía.']);
        $datos += $request->validate(['personal_final' => ['exclude_unless:accion,terminar', 'nullable', 'array', 'max:30'], 'personal_final.*' => ['integer', 'distinct', Rule::exists('tbl_personal', 'id_personal')->where('estado', 'Activo')]]);
        $datos += $request->validate([
            'agregar_orden_recuperacion' => ['exclude_unless:accion,terminar', 'nullable', 'boolean'],
            'cancelar_orden_vigente' => ['exclude_unless:agregar_orden_recuperacion,1', 'nullable', 'boolean'],
            'id_orden_vigente' => ['exclude_unless:cancelar_orden_vigente,1', 'required', 'integer'],
        ]);
        // La orden de recuperación se puede dejar al finalizar; se valida igual que cualquier orden médica.
        $ordenRecuperacion = ! empty($datos['agregar_orden_recuperacion']) ? OrdenesMedicas::validarOrdenes($request) : null;
        $datos['fecha_hora'] = Carbon::parse($datos['fecha_hora'])->format('Y-m-d H:i:s');
        DB::transaction(function () use ($cirugia, $datos, $ordenRecuperacion) {
            $ingreso = Ingreso::whereKey($cirugia->id_ingreso)->lockForUpdate()->firstOrFail();
            $actual = Cirugia::whereKey($cirugia->getKey())->lockForUpdate()->firstOrFail()->setRelation('ingreso', $ingreso);
            if (! $actual->atencionQuirurgicaDisponible()) {
                $this->activo($ingreso);
            }
            abort_unless($actual->permiteAccion($datos['accion']), 403, $datos['accion'] === 'terminar' ? 'Solo un médico participante puede finalizar la cirugía.' : 'Solo el personal asignado puede cancelar la cirugía.');
            if ($datos['fecha_hora'] < $ingreso->fecha_ingreso->format('Y-m-d H:i:s')) {
                throw ValidationException::withMessages(['fecha_hora' => 'La fecha no puede ser anterior al ingreso.']);
            }
            $programada = in_array($actual->estado, ['Programada', 'Aprobada', 'Evaluación preoperatoria'], true);
            if ($datos['accion'] === 'reprogramar' && $programada) {
                $equipo = $actual->equipo()->wherePivot('estado', '!=', 'Cancelado')->orderBy('tbl_personal.id_personal')->lockForUpdate()->get();
                $this->validarCoincidenciaHorario($datos['fecha_hora'], $equipo->modelKeys(), $ingreso->getKey(), $actual->getKey());
                $actual->fecha_programada = $datos['fecha_hora'];
                $actual->fecha_fin_programada = null;
            } elseif ($datos['accion'] === 'iniciar' && $programada) {
                if (Cirugia::where('id_ingreso', $actual->id_ingreso)->where('estado', 'En cirugía')->exists()) {
                    throw ValidationException::withMessages(['accion' => 'El paciente ya está en cirugía.']);
                }
                $actual->estado = 'En cirugía';
                $actual->fecha_inicio_real = $datos['fecha_hora'];
            } elseif ($datos['accion'] === 'terminar' && $actual->estado === 'En cirugía') {
                if ($datos['fecha_hora'] < $actual->fecha_inicio_real->format('Y-m-d H:i:s')) {
                    throw ValidationException::withMessages(['fecha_hora' => 'La finalización no puede ser anterior al inicio real.']);
                }
                if (! empty($datos['personal_final'])) {
                    abort_unless(self::permisos()['editar'], 403);
                    $seleccionados = self::personalQuirurgico()->modelKeys();
                    if (array_diff($datos['personal_final'], $seleccionados)) {
                        throw ValidationException::withMessages(['personal_final' => 'Seleccione médicos o personal de enfermería activos.']);
                    }
                    $existentes = $actual->equipo()->pluck('tbl_personal.id_personal')->all();
                    $nuevos = array_values(array_diff($datos['personal_final'], $existentes));
                    if (count($existentes) + count($nuevos) > 30) {
                        throw ValidationException::withMessages(['personal_final' => 'Puede registrar hasta 30 participantes.']);
                    }
                    $actual->equipo()->syncWithPivotValues($nuevos, ['rol_quirurgico' => 'Otro', 'estado' => 'Participó'], false);
                }
                if (! empty($datos['internar_al_finalizar'])) {
                    $this->internarAlFinalizar($actual, $ingreso, (int) $datos['habitacion_recuperacion']);
                }
                $actual->estado = 'Realizada';
                $actual->fecha_fin_real = $datos['fecha_hora'];
                if ($ordenRecuperacion !== null) {
                    $this->ordenAlFinalizar($actual, $ingreso, $ordenRecuperacion, $datos);
                }
                DB::table('tbl_cirugia_equipo')->where('id_cirugia', $actual->getKey())->where('estado', '!=', 'Cancelado')->update(['estado' => 'Participó']);
            } elseif ($datos['accion'] === 'cancelar' && ($programada || $actual->estado === 'Indicada')) {
                $actual->estado = 'Cancelada';
                $actual->motivo_cancelacion = $datos['motivo_cancelacion'];
                $actual->fecha_actualizacion = now('UTC');
                DB::table('tbl_cirugia_equipo')->where('id_cirugia', $actual->getKey())->update(['estado' => 'Cancelado']);
            } else {
                throw ValidationException::withMessages(['accion' => 'El estado de la cirugía cambió. Recargue la página.']);
            }
            $actual->id_usuario_modificacion = session('id_usuario');
            $actual->save();
        });

        $respuesta = redirect()->route('pacientes.expediente', $cirugia->ingreso->id_paciente)->with('exito', $ordenRecuperacion !== null ? 'Cirugía finalizada. Enfermería ya ve la orden de recuperación.' : 'Estado de la cirugía actualizado.');
        if (! in_array($datos['accion'], ['iniciar', 'terminar'], true)) {
            $respuesta->with('abrir_cirugia', $cirugia->getKey());
        }

        return $respuesta;
    }

    public function guardarRecuperacion(Request $request, Cirugia $cirugia): RedirectResponse
    {
        DB::transaction(function () use ($request, $cirugia): void {
            $ingreso = Ingreso::whereKey($cirugia->id_ingreso)->lockForUpdate()->firstOrFail();
            $actual = Cirugia::whereKey($cirugia->getKey())->lockForUpdate()->firstOrFail()->setRelation('ingreso', $ingreso);
            abort_unless($actual->estado === 'Realizada' && $actual->atencionQuirurgicaDisponible() && $actual->permiteAccion('terminar') && $ingreso->estado === 'Activo' && $ingreso->hospitalizacion()->where('estado', 'Activa')->exists(), 403);
            $existente = $actual->ordenRecuperacion();
            $editar = $request->boolean('editar_recuperacion');
            abort_unless(app(Permisos::class)->puede('Evolución clínica', $editar ? Permisos::EDITAR : Permisos::GUARDAR), 403);
            if ($existente && ! $editar) {
                throw ValidationException::withMessages(['ordenes' => 'Esta cirugía ya tiene una orden de recuperación. Edite o cancele la existente.']);
            }
            if ($editar) {
                abort_unless($existente && (int) $existente->id_usuario_creacion === (int) session('id_usuario') && (EdicionClinica::editableHasta($existente)?->isFuture() ?? false), 403);
            }
            $ordenes = OrdenesMedicas::validarOrdenes($request);
            if ($editar) {
                if ($cancelacion = OrdenesMedicas::cancelacion($existente->ordenes_medicas)) {
                    $ordenes['cancelacion'] = $cancelacion;
                }
                $existente->update(['ordenes_medicas' => $ordenes, 'id_usuario_modificacion' => session('id_usuario')]);
                $registro = $existente;
            } else {
                $registro = EvolucionClinica::create([
                    'id_ingreso' => $ingreso->getKey(), 'id_personal' => self::permisos()['personal'],
                    'fecha_hora' => now('America/Guatemala'), 'motivo_atencion' => 'Orden de recuperación posoperatoria',
                    'diagnostico' => $ingreso->evoluciones()->orderByDesc('fecha_hora')->value('diagnostico') ?: 'Sin diagnóstico registrado',
                    'ordenes_medicas' => $ordenes, 'estado' => 'Activo', 'id_usuario_creacion' => session('id_usuario'),
                ]);
            }
            $actual->update(['id_evolucion' => $registro->getKey(), 'id_usuario_modificacion' => session('id_usuario')]);
        });

        return redirect()->route('pacientes.expediente', $cirugia->ingreso->id_paciente)->with('abrir_cirugia', $cirugia->getKey())->with('exito', 'Orden de recuperación guardada.');
    }

    /**
     * Guarda la orden de recuperación al finalizar la cirugía y, si el médico lo pidió, cancela la orden que estaba vigente.
     *
     * @param  array<string, mixed>  $ordenes
     * @param  array<string, mixed>  $datos
     */
    private function ordenAlFinalizar(Cirugia $cirugia, Ingreso $ingresoOriginal, array $ordenes, array $datos): void
    {
        abort_unless(app(Permisos::class)->puede('Evolución clínica', Permisos::GUARDAR), 403);
        // Si se internó al finalizar, la cirugía ya apunta a la atención de hospitalización.
        $ingreso = $cirugia->ingreso;
        if (! $ingreso->hospitalizacion()->where('estado', 'Activa')->exists()) {
            throw ValidationException::withMessages(['agregar_orden_recuperacion' => 'Para dejar una orden de recuperación, el paciente debe quedar internado.']);
        }
        if (! empty($datos['cancelar_orden_vigente'])) {
            $vigente = OrdenesMedicas::vigentes($ingreso);
            if (! $vigente || $vigente->getKey() !== (int) $datos['id_orden_vigente'] || OrdenesMedicas::cancelacion($vigente->ordenes_medicas)) {
                throw ValidationException::withMessages(['cancelar_orden_vigente' => 'La orden vigente cambió mientras finalizaba. Revise la orden actual e intente de nuevo.']);
            }
            $idPersonal = self::permisos()['personal'];
            $medico = Personal::find($idPersonal);
            $anteriores = $vigente->ordenes_medicas;
            $anteriores['cancelacion'] = [
                'fecha' => $datos['fecha_hora'],
                'id_personal' => $idPersonal,
                'medico' => trim(($medico?->nombres ?? '').' '.($medico?->apellidos ?? '')),
                'motivo' => 'Reemplazada por la orden de recuperación de la cirugía #'.$cirugia->getKey().' · '.$cirugia->tipo_operacion,
            ];
            $vigente->update(['ordenes_medicas' => $anteriores, 'id_usuario_modificacion' => session('id_usuario')]);
        }
        $diagnostico = $ingreso->evoluciones()->where('estado', 'Activo')->orderByDesc('fecha_hora')->value('diagnostico')
            ?: $ingresoOriginal->evoluciones()->where('estado', 'Activo')->orderByDesc('fecha_hora')->value('diagnostico');
        $registro = EvolucionClinica::create([
            'id_ingreso' => $ingreso->getKey(), 'id_personal' => self::permisos()['personal'],
            'fecha_hora' => $datos['fecha_hora'], 'motivo_atencion' => 'Orden de recuperación posoperatoria',
            'diagnostico' => $diagnostico ?: 'Sin diagnóstico registrado',
            'ordenes_medicas' => $ordenes, 'estado' => 'Activo', 'id_usuario_creacion' => session('id_usuario'),
        ]);
        $cirugia->id_evolucion = $registro->getKey();
    }

    private function internarAlFinalizar(Cirugia $cirugia, Ingreso $ingreso, int $idHabitacion): void
    {
        abort_unless(app(Permisos::class)->puede('Hospitalización', Permisos::GUARDAR), 403);
        $ingreso->paciente()->lockForUpdate()->firstOrFail();
        if ($ingreso->hospitalizacion()->where('estado', 'Activa')->exists()) {
            throw ValidationException::withMessages(['habitacion_recuperacion' => 'El paciente ya está internado en esta atención.']);
        }
        $habitacion = Habitacion::whereKey($idHabitacion)->lockForUpdate()->firstOrFail();
        if ($habitacion->estado_operativo !== 'Activa' || $habitacion->asignacionesHospitalizacion()->whereNull('fecha_hora_fin')->exists()) {
            throw ValidationException::withMessages(['habitacion_recuperacion' => 'La habitación ya no está disponible. Seleccione otra.']);
        }
        $ahora = now('America/Guatemala');
        if ($ingreso->estado !== 'Activo') {
            if (Ingreso::where('id_paciente', $ingreso->id_paciente)->where('estado', 'Activo')->exists()) {
                throw ValidationException::withMessages(['habitacion_recuperacion' => 'El paciente ya tiene otra atención activa.']);
            }
            $ingreso = Ingreso::create([
                'id_paciente' => $ingreso->id_paciente, 'id_personal_registra' => self::permisos()['personal'],
                'tipo_atencion' => Ingreso::TIPO_HOSPITALIZACION, 'estado' => 'Activo', 'fecha_ingreso' => $ahora,
                'motivo_ingreso' => 'Recuperación después de cirugía: '.$cirugia->tipo_operacion,
                'id_usuario_creacion' => session('id_usuario'),
            ]);
            $cirugia->id_ingreso = $ingreso->getKey();
            $cirugia->id_evolucion = null;
            $cirugia->setRelation('ingreso', $ingreso);
        } else {
            $ingreso->update(['tipo_atencion' => Ingreso::TIPO_HOSPITALIZACION, 'id_usuario_modificacion' => session('id_usuario')]);
        }
        $hospitalizacion = Hospitalizacion::create([
            'id_ingreso' => $ingreso->getKey(), 'fecha_inicio' => $ahora, 'estado' => 'Activa',
            'motivo' => 'Recuperación posoperatoria', 'id_usuario_creacion' => session('id_usuario'),
        ]);
        HospitalizacionHabitacion::create([
            'id_hospitalizacion' => $hospitalizacion->getKey(), 'id_habitacion' => $habitacion->getKey(),
            'fecha_hora_inicio' => $ahora, 'id_usuario_creacion' => session('id_usuario'),
        ]);
    }

    private function activo(Ingreso $ingreso): void
    {
        if ($ingreso->estado !== 'Activo' || $ingreso->paciente->estado !== 'Activo') {
            throw ValidationException::withMessages(['cirugia' => 'La atención y el paciente deben estar activos.']);
        }
    }

    private function validarCoincidenciaHorario(string $fecha, array $personal, int $ingreso, ?int $excluir = null): void
    {
        $inicio = Carbon::parse($fecha)->startOfMinute();
        $cirugias = Cirugia::with('equipo')->whereIn('estado', ['Programada', 'Aprobada', 'Evaluación preoperatoria', 'En cirugía'])
            ->whereBetween('fecha_programada', [$inicio->format('Y-m-d H:i:s'), $inicio->copy()->endOfMinute()->format('Y-m-d H:i:s')])
            ->when($excluir, fn ($q) => $q->whereKeyNot($excluir))->get();
        $mensajes = [];
        foreach ($cirugias as $otra) {
            if ((int) $otra->id_ingreso === $ingreso) {
                $mensajes[] = 'El paciente ya tiene la cirugía «'.$otra->tipo_operacion.'» en esa fecha y hora.';
            }
            foreach ($otra->equipo as $persona) {
                if (in_array((int) $persona->getKey(), array_map('intval', $personal), true) && $persona->pivot->estado !== 'Cancelado') {
                    $mensajes[] = trim($persona->nombres.' '.$persona->apellidos).' está ocupado en «'.$otra->tipo_operacion.'» el '.$otra->fecha_programada->format('d/m/Y H:i').'.';
                }
            }
        }
        if ($mensajes !== []) {
            throw ValidationException::withMessages(['fecha_programada' => implode(' ', array_unique($mensajes)), 'personal' => implode(' ', array_unique($mensajes))]);
        }
    }
}
