<?php

namespace App\Support;

use App\Models\DocumentoClinico;
use App\Models\Ingreso;
use App\Services\ReglasAtencion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Documentos del expediente de un paciente: los de todas sus atenciones, agrupados por día.
 * Cada documento conserva la atención a la que pertenece (ingreso o consulta externa).
 */
class DocumentosExpediente
{
    /**
     * Filtros que se muestran en cada día, en este orden.
     *
     * @var array<string, string>
     */
    public const FILTROS = [
        'valoracion' => 'Valoración inicial',
        'consulta' => 'Consulta externa',
        'evolucion' => 'Evoluciones',
        'nota' => 'Notas de enfermería',
        'cirugia' => 'Cirugías',
        'alta' => 'Alta médica',
    ];

    /**
     * Días con sus documentos, del más reciente al más antiguo. Dentro de cada día, lo último que se registró va primero.
     *
     * @param  Collection<int, Ingreso>  $ingresos
     * @param  Collection<string, Carbon>  $ediciones  «tabla:id» => fecha de la última edición
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public static function porDia(Collection $ingresos, Collection $ediciones): Collection
    {
        return $ingresos->flatMap(fn (Ingreso $ingreso) => self::deIngreso($ingreso, $ediciones))
            ->sortBy([fn ($a, $b) => $b['fecha'] <=> $a['fecha'], fn ($a, $b) => $b['orden'] <=> $a['orden']])
            ->groupBy(fn (array $documento) => $documento['fecha']->format('Y-m-d'));
    }

    /**
     * Documentos de una atención. Una consulta externa usa nombres de consulta, no de hospitalización.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function deIngreso(Ingreso $ingreso, Collection $ediciones): Collection
    {
        $consulta = $ingreso->esConsultaExterna();
        $atencion = $consulta ? 'Consulta del '.$ingreso->fecha_ingreso->format('d/m/Y') : 'Ingreso #'.$ingreso->getKey();
        $persona = fn ($personal) => trim(($personal?->nombres ?? '').' '.($personal?->apellidos ?? ''));
        $documentos = collect([[
            'tipo' => 'general', 'fecha' => $ingreso->fecha_ingreso, 'orden' => 0, 'destino' => 'documento-ingreso-'.$ingreso->getKey(), 'origen' => 'tbl_ingreso:'.$ingreso->getKey(),
            'titulo' => $consulta ? 'Hoja de llegada' : 'Hoja de ingreso', 'icono' => 'bi-file-earmark-text text-primary', 'detalle' => '',
            'editado' => $ediciones->get('tbl_ingreso:'.$ingreso->getKey()),
            'editable' => EdicionClinica::puedeEditar($ingreso) ? EdicionClinica::limiteLocal($ingreso) : null,
        ]]);
        foreach ($ingreso->evoluciones as $registro) {
            $esPrimera = $ingreso->evoluciones->first()->is($registro) && $registro->estado_mejoria === null;
            $documentos->push([
                'tipo' => $esPrimera ? 'valoracion' : 'evolucion', 'fecha' => $registro->fecha_hora, 'orden' => 1,
                'destino' => 'documento-evolucion-'.$registro->getKey(), 'origen' => 'tbl_evolucion_clinica:'.$registro->getKey(),
                'titulo' => $esPrimera ? (filled($registro->ordenes_medicas) ? 'Valoración inicial con orden médica' : 'Valoración inicial') : 'Evolución clínica #'.$registro->getKey(),
                'icono' => 'bi-file-earmark-medical text-primary', 'detalle' => $persona($registro->personal),
                'editado' => $ediciones->get('tbl_evolucion_clinica:'.$registro->getKey()),
                'editable' => EdicionClinica::puedeEditar($registro->setRelation('ingreso', $ingreso)) ? EdicionClinica::limiteLocal($registro) : null,
            ]);
        }
        foreach ($ingreso->notasEnfermeria->values() as $numero => $nota) {
            $documentos->push([
                'tipo' => 'nota', 'fecha' => $nota->fecha_hora, 'orden' => 2, 'destino' => 'documento-nota-'.$nota->getKey(), 'origen' => 'tbl_atencion_enfermeria:'.$nota->getKey(),
                'titulo' => 'Nota de enfermería #'.($numero + 1), 'icono' => 'bi-journal-text text-info', 'detalle' => $persona($nota->personal), 'nota' => $nota,
                // Igual que las demás hojas: solo su autor, durante las primeras 12 horas.
                'editado' => $ediciones->get('tbl_atencion_enfermeria:'.$nota->getKey()),
                'editable' => $nota->setRelation('ingreso', $ingreso)->puedeEditar((int) session('id_usuario')) ? EdicionClinica::limiteLocal($nota) : null,
            ]);
        }
        // Controles de recuperación antiguos, tomados desde la cirugía sin nota de enfermería: se muestran con las notas.
        foreach ($ingreso->controlesSinNota as $control) {
            $documentos->push([
                'tipo' => 'nota', 'fecha' => $control->fecha_hora, 'orden' => 2, 'destino' => 'documento-control-'.$control->getKey(), 'origen' => 'tbl_signos_vitales:'.$control->getKey(),
                'titulo' => 'Control de recuperación', 'icono' => 'bi-heart-pulse text-info', 'detalle' => $persona($control->personal), 'cirugia' => $control->cirugia,
            ]);
        }
        // Cada cirugía va en la fecha en que se operó (o, si aún no se opera, en la programada); abre su ventana de detalles y controles.
        foreach ($ingreso->cirugias as $cirugia) {
            $documentos->push([
                'tipo' => 'cirugia', 'fecha' => $cirugia->fecha_inicio_real ?? $cirugia->fecha_programada ?? $cirugia->fecha_registro, 'orden' => 2,
                'destino' => 'cirugia-detalle-'.$cirugia->getKey(), 'origen' => 'tbl_cirugia:'.$cirugia->getKey(), 'titulo' => 'Cirugía: '.$cirugia->tipo_operacion, 'icono' => 'bi-hospital text-danger',
                'detalle' => '', 'estadoCirugia' => $cirugia->estado,
            ]);
        }
        if ($ingreso->altaMedica) {
            $documentos->push([
                'tipo' => 'alta', 'fecha' => $ingreso->altaMedica->fecha_hora, 'orden' => 3, 'destino' => 'documento-alta-'.$ingreso->getKey(), 'origen' => 'tbl_alta_medica:'.$ingreso->altaMedica->getKey(),
                'titulo' => $consulta ? 'Alta e indicaciones para casa' : 'Alta médica', 'icono' => 'bi-file-earmark-check text-success',
                'detalle' => $persona($ingreso->altaMedica->medico),
            ]);
        }
        // Un archivo adjunto a una hoja (por ejemplo, la foto de una cirugía) es parte de esa hoja: no se lista aparte,
        // la hoja indica cuántos archivos tiene y se ven desde su ventana.
        $hojas = $documentos->pluck('origen')->filter()->flip();
        foreach ($ingreso->documentosClinicos->where('estado', 'Activo') as $archivo) {
            $origen = $archivo->tabla_origen && $archivo->id_registro_origen ? $archivo->tabla_origen.':'.$archivo->id_registro_origen : null;
            if ($origen !== null && $hojas->has($origen)) {
                $documentos = $documentos->map(fn (array $documento) => ($documento['origen'] ?? null) === $origen ? ['adjuntos' => ($documento['adjuntos'] ?? 0) + 1] + $documento : $documento);

                continue;
            }
            $tipo = match ($archivo->tipo_documento) {
                'Valoración inicial' => 'valoracion', 'Consulta externa' => 'consulta', 'Evolución clínica', 'Órdenes médicas' => 'evolucion',
                'Nota de enfermería' => 'nota', 'Cirugía' => 'cirugia', 'Alta médica' => 'alta', default => 'general',
            };
            $documentos->push([
                'tipo' => $tipo, 'fecha' => $archivo->fecha_documento ?? $archivo->fecha_registro, 'orden' => 4, 'destino' => '', 'titulo' => $archivo->titulo,
                'icono' => 'bi-file-earmark-'.($archivo->tipo_archivo === 'pdf' ? 'pdf' : 'image').' text-primary', 'detalle' => 'Hoja en papel', 'archivo' => $archivo,
                'editado' => $ediciones->get('tbl_documento_clinico:'.$archivo->getKey()),
            ]);
        }

        return $documentos->map(fn (array $documento) => $documento + ['atencion' => $atencion, 'atencionConsulta' => $consulta, 'idAtencion' => $ingreso->getKey()]);
    }

    /**
     * Fecha de la última edición hecha por una persona a la hoja de llegada o de ingreso
     * y a cada consulta, valoración o evolución, según la bitácora. Se devuelve en hora de Guatemala.
     *
     * @param  Collection<int, Ingreso>  $ingresos
     * @param  Collection<int, DocumentoClinico>  $archivosPaciente
     * @return Collection<string, Carbon>
     */
    public static function ediciones(Collection $ingresos, Collection $archivosPaciente): Collection
    {
        $idsIngresos = $ingresos->map(fn (Ingreso $ingreso) => $ingreso->getKey())->all();
        $idsEvoluciones = $ingresos->flatMap(fn (Ingreso $ingreso) => $ingreso->evoluciones->modelKeys())->all();
        $idsNotas = $ingresos->flatMap(fn (Ingreso $ingreso) => $ingreso->notasEnfermeria->modelKeys())->all();
        $idsArchivos = $ingresos->flatMap(fn (Ingreso $ingreso) => $ingreso->documentosClinicos->modelKeys())->merge($archivosPaciente->map(fn (DocumentoClinico $archivo) => $archivo->getKey()))->all();
        if ($idsIngresos === [] && $idsEvoluciones === [] && $idsNotas === [] && $idsArchivos === []) {
            return collect();
        }

        return DB::table('tbl_bitacora')
            ->where(function ($consulta) use ($idsIngresos, $idsEvoluciones, $idsNotas, $idsArchivos): void {
                $consulta->where(fn ($ingreso) => $ingreso->where('tabla', 'tbl_ingreso')->whereIn('id_registro', $idsIngresos)->where('accion', 'Edición de ingreso'))
                    ->orWhere(fn ($evolucion) => $evolucion->where('tabla', 'tbl_evolucion_clinica')->whereIn('id_registro', $idsEvoluciones)
                        ->whereIn('accion', ['Edición de evolución clínica', 'Actualización de evolución clínica']))
                    ->orWhere(fn ($nota) => $nota->where('tabla', 'tbl_atencion_enfermeria')->whereIn('id_registro', $idsNotas)->where('accion', 'Edición de nota de enfermería'))
                    ->orWhere(fn ($archivo) => $archivo->where('tabla', 'tbl_documento_clinico')->whereIn('id_registro', $idsArchivos)->where('accion', 'Edición de archivo clínico'));
            })
            ->where('resultado', 'Exitoso')
            ->groupBy('tabla', 'id_registro')
            ->selectRaw('tabla, id_registro, MAX(fecha_hora) AS ultima')
            ->get()
            ->mapWithKeys(fn ($fila) => [$fila->tabla.':'.$fila->id_registro => Carbon::parse($fila->ultima, 'UTC')->setTimezone(ReglasAtencion::ZONA_HORARIA)]);
    }
}
