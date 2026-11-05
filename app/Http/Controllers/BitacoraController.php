<?php

namespace App\Http\Controllers;

use App\Services\PdfReportes;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BitacoraController extends Controller
{
    private function filtros(Request $request): array
    {
        $f = $request->validate(['accion' => ['nullable', 'string', 'max:100'], 'usuario' => ['nullable', 'integer', 'exists:tbl_usuario,id_usuario'], 'fecha' => ['nullable', 'date_format:Y-m-d'], 'desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('desde'), 'after_or_equal:desde')], 'resultado' => ['nullable', 'in:Exitoso,Fallido'], 'descargar' => ['nullable', 'boolean']]);
        $f['desde'] = $f['desde'] ?? $f['fecha'] ?? $f['hasta'] ?? null;
        $f['hasta'] = $f['hasta'] ?? $f['fecha'] ?? $f['desde'];

        return array_filter($f, fn ($v) => $v !== null && $v !== '');
    }

    private function consulta(array $f): Builder
    {
        $q = DB::table('tbl_bitacora as b')->leftJoin('tbl_usuario as u', 'u.id_usuario', '=', 'b.id_usuario')->select('b.*', 'u.nombre_usuario');
        if (! empty($f['desde'])) {
            $q->where('b.fecha_hora', '>=', Carbon::parse($f['desde'], 'America/Guatemala')->startOfDay()->utc());
        }
        if (! empty($f['hasta'])) {
            $q->where('b.fecha_hora', '<', Carbon::parse($f['hasta'], 'America/Guatemala')->startOfDay()->addDay()->utc());
        }
        foreach (['accion', 'resultado'] as $campo) {
            if (! empty($f[$campo])) {
                $q->where('b.'.$campo, $f[$campo]);
            }
        }
        if (! empty($f['usuario'])) {
            $q->where('b.id_usuario', $f['usuario']);
        }

        return $q->orderByDesc('b.fecha_hora')->orderByDesc('b.id_bitacora');
    }

    private function nombres(Collection $registros): void
    {
        $mapa = ['tbl_usuario' => ['id_usuario', 'Usuario', 'nombre_usuario'], 'tbl_paciente' => ['id_paciente', 'Paciente', 'nombres', 'apellidos'], 'tbl_personal' => ['id_personal', 'Personal', 'nombres', 'apellidos'], 'tbl_habitacion' => ['id_habitacion', 'Habitación', 'numero_habitacion'], 'tbl_ingreso' => ['id_ingreso', 'Atención'], 'tbl_evolucion_clinica' => ['id_evolucion', 'Evolución clínica'], 'tbl_atencion_enfermeria' => ['id_atencion_enfermeria', 'Nota de enfermería'], 'tbl_signos_vitales' => ['id_signos_vitales', 'Signos vitales'], 'tbl_alta_medica' => ['id_alta', 'Alta médica'], 'tbl_cirugia' => ['id_cirugia', 'Cirugía'], 'tbl_documento_clinico' => ['id_documento', 'Archivo', 'titulo']];
        foreach ($registros->groupBy('tabla') as $tabla => $grupo) {
            $config = $mapa[$tabla] ?? null;
            $datos = $config && Schema::hasTable($tabla) ? DB::table($tabla)->whereIn($config[0], $grupo->pluck('id_registro')->filter())->get()->keyBy($config[0]) : collect();
            $pacientes = collect();
            if ($config && ! isset($config[2]) && $datos->isNotEmpty() && Schema::hasColumn($tabla, 'id_ingreso')) {
                $pacientes = DB::table('tbl_ingreso as i')->join('tbl_paciente as p', 'p.id_paciente', '=', 'i.id_paciente')->whereIn('i.id_ingreso', $datos->pluck('id_ingreso'))->select('i.id_ingreso', 'p.nombres', 'p.apellidos', 'p.numero_expediente')->get()->keyBy('id_ingreso');
            }
            foreach ($grupo as $registro) {
                $dato = $datos->get($registro->id_registro);
                $nombre = $config[1] ?? 'Registro';
                if ($dato && isset($config[2])) {
                    $nombre .= ' · '.trim(($dato->{$config[2]} ?? '').' '.(isset($config[3]) ? ($dato->{$config[3]} ?? '') : ''));
                } elseif ($dato && ($p = $pacientes->get($dato->id_ingreso ?? null))) {
                    $nombre .= ' · '.(trim($p->nombres.' '.$p->apellidos) ?: 'No identificado').' · '.$p->numero_expediente;
                } elseif ($registro->id_registro) {
                    $nombre .= ' · referencia #'.$registro->id_registro.($dato ? '' : ' (sin datos disponibles)');
                } else {
                    $nombre = 'Acción general';
                }
                $registro->registro_nombre = $nombre;
                $registro->fecha_local = Carbon::parse($registro->fecha_hora, 'UTC')->setTimezone('America/Guatemala')->format('d/m/Y H:i:s');
            }
        }
    }

    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);
        $registros = $this->consulta($filtros)->paginate(5)->withQueryString();
        $this->nombres($registros->getCollection());
        $acciones = DB::table('tbl_bitacora')->distinct()->orderBy('accion')->pluck('accion');
        $usuarios = DB::table('tbl_usuario')->orderBy('nombre_usuario')->get(['id_usuario', 'nombre_usuario']);

        return view('bitacora.index', compact('registros', 'filtros', 'acciones', 'usuarios'));
    }

    public function pdf(Request $request): Response
    {
        $f = $this->filtros($request);
        $registros = $this->consulta($f)->limit(1001)->get();
        if ($registros->count() > 1000) {
            throw ValidationException::withMessages(['desde' => 'El reporte supera 1,000 registros. Reduzca las fechas o agregue filtros.']);
        }
        $this->nombres($registros);
        $filas = $registros->map(fn ($r) => ['bitacora' => true, 'fecha' => $r->fecha_local, 'usuario' => $r->nombre_usuario ?? 'Sin autenticar', 'intentado' => $r->usuario_intentado ?: 'Sin nombre', 'accion' => $r->accion, 'resultado' => $r->resultado, 'registro' => $r->registro_nombre, 'detalle' => $r->descripcion, 'ip' => $r->ip, 'navegador' => $r->navegador, 'sistema' => $r->sistema_operativo])->all();
        $etiquetas = ['Período' => isset($f['desde']) ? $f['desde'].' al '.$f['hasta'] : 'Todas las fechas', 'Acción' => $f['accion'] ?? 'Todas', 'Resultado' => $f['resultado'] ?? 'Todos', 'Usuario' => isset($f['usuario']) ? DB::table('tbl_usuario')->where('id_usuario', $f['usuario'])->value('nombre_usuario') : 'Todos'];
        $bytes = app(PdfReportes::class)->generar('Bitácora del sistema', $filas, $etiquetas);

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($request->boolean('descargar') ? 'attachment' : 'inline').'; filename="bitacora.pdf"', 'Cache-Control' => 'private, no-store']);
    }
}
