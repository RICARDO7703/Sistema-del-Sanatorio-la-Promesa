<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AvisarSinCambios;
use App\Models\Cirugia;
use App\Models\EvolucionClinica;
use App\Models\Habitacion;
use App\Models\Hospitalizacion;
use App\Models\Ingreso;
use App\Models\NotaEnfermeria;
use App\Models\Personal;
use App\Models\SignoVital;
use App\Services\OrdenesMedicas;
use App\Support\Permisos;
use App\Support\RegistroBitacora;
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
    public function actualizar(Request $request, NotaEnfermeria $nota): RedirectResponse
    {
        return $this->guardar($request, $nota->ingreso, $nota);
    }

    /** @var list<string> */
    private const MEDIDAS = ['presion_sistolica', 'presion_diastolica', 'frecuencia_cardiaca', 'frecuencia_respiratoria', 'temperatura', 'saturacion_oxigeno'];

    public function guardar(Request $request, Ingreso $ingreso, ?NotaEnfermeria $notaEditar = null): RedirectResponse
    {
        if (! $notaEditar) {
            $ingreso->paciente->exigirFueraDeCirugia();
        }
        if ($notaEditar) {
            abort_unless($notaEditar->puedeEditar((int) session('id_usuario')), 403);
        }
        $trasladar = $request->boolean('cambiar_habitacion');
        if ($trasladar) {
            abort_unless(! $notaEditar && app(Permisos::class)->puede('Hospitalización', 'Editar'), 403);
        }
        $idPersonal = DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal');

        if (! $idPersonal) {
            return back()->withInput()->with('error', 'El usuario no tiene personal asociado.');
        }

        $datos = $request->validate([
            'cambiar_habitacion' => ['nullable', 'boolean'],
            'id_habitacion' => ['required_if:cambiar_habitacion,1', 'nullable', 'integer', 'exists:tbl_habitacion,id_habitacion'],
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
            'id_cirugia' => ['nullable', 'integer'],
            'ordenes_de' => ['nullable', 'integer'],
            'ordenes' => ['nullable', 'array', 'max:60'],
            'ordenes.*' => ['array:estado,motivo'],
            'ordenes.*.estado' => ['nullable', Rule::in(['Aplicada', 'No aplicada'])],
            'ordenes.*.motivo' => ['nullable', 'required_if:ordenes.*.estado,No aplicada', 'string', 'max:255'],
        ], [
            'ordenes.*.estado.in' => 'Indique si la orden se aplicó o no.',
            'ordenes.*.motivo.required_if' => 'Escriba por qué no se aplicó.',
            'ordenes.*.motivo.max' => 'El motivo no puede superar los 255 caracteres.',
            'id_cirugia.integer' => 'Seleccione una cirugía de la lista.',
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
        $ordenesMarcadas = array_filter($datos['ordenes'] ?? [], fn (array $orden): bool => filled($orden['estado'] ?? null));

        // Cada orden vigente es obligatoria: hay que indicar si se aplicó o no (y por qué no).
        // Una nota registrada después con una hora anterior a esas órdenes no las incluye.
        $vigentes = OrdenesMedicas::vigentes($ingreso);
        $items = $vigentes && Carbon::parse($datos['fecha_hora'])->gte($vigentes->fecha_hora) ? OrdenesMedicas::paraEnfermeria($vigentes->ordenes_medicas) : [];
        if ($notaEditar) {
            $items = $notaEditar->ordenes->map(fn ($o) => ['tipo' => $o->tipo, 'descripcion' => $o->descripcion])->all();
            $vigentes = $notaEditar->ordenes->first()?->id_evolucion ? EvolucionClinica::find($notaEditar->ordenes->first()->id_evolucion) : null;
            $datos['ordenes_de'] = $vigentes?->getKey();
        }
        if ($items !== [] && (int) ($datos['ordenes_de'] ?? 0) !== $vigentes->getKey()) {
            throw ValidationException::withMessages(['ordenes' => 'El médico actualizó las órdenes mientras llenaba la nota. Revíselas y vuelva a marcar lo que se aplicó.']);
        }
        if (array_diff_key($ordenesMarcadas, $items) !== []) {
            throw ValidationException::withMessages(['ordenes' => 'El médico actualizó las órdenes mientras llenaba la nota. Revíselas y vuelva a marcar lo que se aplicó.']);
        }
        $faltantes = array_diff_key($items, $ordenesMarcadas);
        if ($faltantes !== []) {
            throw ValidationException::withMessages(collect($faltantes)->mapWithKeys(fn (array $item, int $indice) => ['ordenes.'.$indice.'.estado' => 'Indique si «'.$item['descripcion'].'» se aplicó o no.'])->all());
        }

        if (! $conSignos && $actividades === [] && $nota === '' && $ordenesMarcadas === [] && ! $trasladar) {
            throw ValidationException::withMessages(['nota' => 'Registre signos vitales, marque una orden o actividad, o escriba la nota.']);
        }

        $idCirugia = isset($datos['id_cirugia']) ? (int) $datos['id_cirugia'] : null;
        if ($idCirugia && ! $conSignos) {
            throw ValidationException::withMessages(['id_cirugia' => 'Para un control de recuperación registre al menos un signo vital.']);
        }

        $avisoMedico = (bool) ($datos['aviso_medico'] ?? false);
        if ($avisoMedico && ! Personal::whereKey($datos['id_personal_avisado'])->whereHas('cargo', fn ($consulta) => $consulta->whereIn('nombre_cargo', Personal::CARGOS_MEDICOS))->exists()) {
            throw ValidationException::withMessages(['id_personal_avisado' => 'Seleccione un médico activo de la lista.']);
        }

        $fecha = Carbon::parse($datos['fecha_hora']);

        // Huella de la nota antes de corregirla: solo cuenta como edición si algo cambió de verdad.
        $antes = $notaEditar ? self::huella($notaEditar) : null;
        $huboCambios = false;
        DB::transaction(function () use ($ingreso, $datos, $actividades, $nota, $conSignos, $fecha, $idPersonal, $avisoMedico, $idCirugia, $ordenesMarcadas, $vigentes, $items, $notaEditar, $trasladar, $request, $antes, &$huboCambios): void {
            $actual = Ingreso::whereKey($ingreso->getKey())->lockForUpdate()->firstOrFail();
            if (! $notaEditar) {
                $actual->paciente->exigirFueraDeCirugia();
            }
            if ($notaEditar) {
                $notaEditar = NotaEnfermeria::whereKey($notaEditar->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($notaEditar->puedeEditar((int) session('id_usuario')), 403);
            }
            $internado = Hospitalizacion::where('id_ingreso', $actual->getKey())->where('estado', 'Activa')->exists();

            if ($actual->estado !== 'Activo' || ! $internado) {
                throw ValidationException::withMessages(['nota' => 'Solo se registran notas de enfermería de pacientes internados.']);
            }

            if ($fecha->lt($actual->fecha_ingreso)) {
                throw ValidationException::withMessages(['fecha_hora' => 'La nota no puede ser anterior al ingreso del paciente.']);
            }

            // Un control de recuperación solo puede ser de la cirugía terminada más reciente de este ingreso, y posterior a su final.
            if ($idCirugia) {
                $cirugia = Cirugia::recienteParaControl($actual->getKey());
                if (! $cirugia || $cirugia->getKey() !== $idCirugia) {
                    throw ValidationException::withMessages(['id_cirugia' => 'Los controles de recuperación se registran solo para la cirugía terminada más reciente de este paciente.']);
                }
                if ($fecha->lt($cirugia->fecha_fin_real)) {
                    throw ValidationException::withMessages(['fecha_hora' => 'El control de recuperación no puede ser anterior al final de la cirugía ('.$cirugia->fecha_fin_real->format('d/m/Y H:i').').']);
                }
            }

            if ($trasladar) {
                $origen = $actual->hospitalizacion->habitacionActual?->habitacion?->numero_habitacion ?? 'Sin asignar';
                $destino = Habitacion::findOrFail($datos['id_habitacion'])->numero_habitacion;
                app(HospitalizacionController::class)->cambiarHabitacion($request, $actual->hospitalizacion);
                $nota = trim($nota."\nTraslado de habitación: ".$origen.' → '.$destino.'.');
            }
            $datosSignos = [
                'id_ingreso' => $actual->getKey(),
                'id_cirugia' => $idCirugia,
                'id_personal' => $idPersonal,
                'tipo_control' => $idCirugia ? 'Recuperación' : 'Rutina',
                'fecha_hora' => $fecha,
                ...collect(self::MEDIDAS)->mapWithKeys(fn (string $medida): array => [$medida => $datos[$medida] ?? null])->all(),
                'id_usuario_creacion' => session('id_usuario'),
            ];
            $signos = $notaEditar?->signosVitales;
            if ($signos) {
                unset($datosSignos['id_usuario_creacion']);
                $signos->update($datosSignos);
            } elseif ($conSignos) {
                $signos = SignoVital::create($datosSignos);
            }

            $datosNota = [
                'id_ingreso' => $actual->getKey(),
                'id_personal' => $idPersonal,
                'fecha_hora' => $fecha,
                'estado_paciente' => $datos['estado_paciente'],
                'id_signos_vitales' => $signos?->getKey(),
                'observaciones' => $nota !== '' ? $nota : null,
                'aviso_medico' => $avisoMedico,
                'id_personal_avisado' => $avisoMedico ? (int) $datos['id_personal_avisado'] : null,
                'id_usuario_creacion' => session('id_usuario'),
            ];

            $registro = $notaEditar;
            if ($registro) {
                unset($datosNota['id_usuario_creacion']);
                $registro->update($datosNota);
                $registro->actividades()->delete();
                $registro->ordenes()->delete();
            } else {
                $registro = NotaEnfermeria::create($datosNota);
            }
            $registro->actividades()->createMany(array_map(fn (string $actividad): array => ['actividad' => $actividad], $actividades));

            if ($ordenesMarcadas !== []) {
                foreach ($ordenesMarcadas as $indice => $orden) {
                    $aplicada = $orden['estado'] === 'Aplicada';
                    $registro->ordenes()->create([
                        'id_evolucion' => $vigentes->getKey(),
                        'tipo' => $items[$indice]['tipo'],
                        'descripcion' => $items[$indice]['descripcion'],
                        'aplicada' => $aplicada,
                        'motivo' => $aplicada ? null : trim($orden['motivo']),
                    ]);
                }
            }
            if ($notaEditar) {
                $huboCambios = self::huella($registro) !== $antes;
                if ($huboCambios) {
                    app(RegistroBitacora::class)->registrar($request, 'Edición de nota de enfermería', 'Exitoso', 'Se corrigió la nota de enfermería.',
                        (int) session('id_usuario'), (int) $registro->getKey(), null, 'tbl_atencion_enfermeria');
                }
            }
        });

        if ($notaEditar) {
            return back()->with('exito', $huboCambios ? 'Nota corregida.' : AvisarSinCambios::MENSAJE)->with('nota_editada', $notaEditar->getKey());
        }

        return redirect()->route('atencion.enfermeria', $ingreso)->with('exito', $idCirugia ? 'Nota de enfermería guardada como control de recuperación.' : 'Nota de enfermería guardada correctamente.')
            ->with('borrador_clinico_guardado', 'nota-enfermeria');
    }

    /**
     * Contenido comparable de una nota (datos, signos, actividades y órdenes), para saber si una corrección cambió algo.
     */
    private static function huella(NotaEnfermeria $nota): string
    {
        $nota = NotaEnfermeria::with(['signosVitales', 'actividades', 'ordenes'])->findOrFail($nota->getKey());
        $numero = fn ($valor) => $valor === null || $valor === '' ? null : (float) $valor;

        return json_encode([
            $nota->fecha_hora?->format('Y-m-d H:i'), $nota->estado_paciente, trim((string) $nota->observaciones),
            (bool) $nota->aviso_medico, $nota->aviso_medico ? (int) $nota->id_personal_avisado : null,
            collect(self::MEDIDAS)->map(fn (string $medida) => $numero($nota->signosVitales?->{$medida}))->all(),
            $nota->actividades->pluck('actividad')->sort()->values()->all(),
            $nota->ordenes->map(fn ($orden) => [$orden->tipo, $orden->descripcion, (bool) $orden->aplicada, $orden->aplicada ? null : trim((string) $orden->motivo)])->all(),
        ]);
    }
}
