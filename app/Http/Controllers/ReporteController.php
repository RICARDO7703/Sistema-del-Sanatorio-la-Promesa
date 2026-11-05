<?php

namespace App\Http\Controllers;

use App\Models\DocumentoClinico;
use App\Models\Paciente;
use App\Services\DatosReportes;
use App\Services\PdfReportes;
use App\Services\ReportesAmpliados;
use App\Support\ArchivoZip;
use App\Support\Permisos;
use App\Support\RegistroBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReporteController extends Controller
{
    private const DOCUMENTOS = ['desde', 'hasta', 'id_paciente', 'personal', 'atencion', 'estado_atencion', 'identificacion', 'sexo'];

    private const HOJA_INICIAL = ['desde', 'hasta', 'id_paciente', 'personal', 'estado_atencion', 'identificacion', 'sexo'];

    /**
     * Reportes que se eligen en la pantalla.
     * Clave => [grupo, nombre, descripción, a qué fecha se refiere el filtro, datos que se pueden indicar].
     */
    public const REPORTES = [
        'pacientes.personal_atenciones' => ['Pacientes y atenciones', 'Atención recibida por el paciente', 'Consulte las valoraciones, evoluciones, notas de enfermería, signos vitales, altas y cirugías del paciente, con su responsable y lo registrado.', 'Fecha en que se registró cada atención o documento.', ['id_paciente', 'personal', 'actividad', 'desde', 'hasta']],
        'pacientes.pacientes' => ['Pacientes y atenciones', 'Lista de pacientes (datos generales)', 'Datos de cada paciente registrado en el sistema.', 'Fecha en que se registró al paciente. Si elige una situación, se usan las fechas de sus atenciones.', ['desde', 'hasta', 'id_paciente', 'situacion', 'personal', 'habitacion', 'identificacion', 'sexo', 'estado_paciente', 'atencion', 'estado_atencion']],
        'pacientes.internados' => ['Pacientes y atenciones', 'Pacientes hospitalizados ahora', 'Quiénes están internados en este momento y en qué habitación.', '', ['id_paciente', 'habitacion', 'identificacion', 'sexo']],
        'pacientes.hospitalizaciones' => ['Pacientes y atenciones', 'Hospitalizaciones', 'Estancias en el sanatorio dentro de un período.', 'Se incluyen las estancias que estuvieron activas en esas fechas.', ['desde', 'hasta', 'id_paciente', 'personal', 'habitacion', 'estado_atencion', 'identificacion', 'sexo']],
        'pacientes.atenciones' => ['Pacientes y atenciones', 'Ingresos y consultas', 'Cada llegada de un paciente: hospitalización o consulta externa.', 'Fecha de llegada del paciente.', ['desde', 'hasta', 'id_paciente', 'personal', 'habitacion', 'atencion', 'estado_atencion', 'identificacion', 'sexo']],
        'pacientes.valoraciones' => ['Hojas clínicas', 'Valoraciones iniciales', 'Primera hoja médica de cada hospitalización.', 'Fecha de la hoja.', self::HOJA_INICIAL],
        'pacientes.consultas' => ['Hojas clínicas', 'Consultas externas', 'Hojas de las consultas externas atendidas.', 'Fecha de la hoja.', self::HOJA_INICIAL],
        'pacientes.evoluciones' => ['Hojas clínicas', 'Evoluciones clínicas', 'Hojas de evolución registradas por los médicos.', 'Fecha de la hoja.', self::DOCUMENTOS],
        'pacientes.ordenes' => ['Hojas clínicas', 'Órdenes médicas', 'Dietas, soluciones, medicamentos e indicaciones ordenadas.', 'Fecha de la hoja.', self::DOCUMENTOS],
        'pacientes.enfermeria' => ['Hojas clínicas', 'Notas de enfermería', 'Notas registradas por enfermería en cada turno.', 'Fecha de la nota.', [...self::DOCUMENTOS, 'turno']],
        'pacientes.signos' => ['Pacientes y atenciones', 'Signos vitales por paciente y personal', 'Elija el paciente y quién tomó los signos. Incluye controles de enfermería y mediciones de valoraciones, consultas y evoluciones; las fechas son opcionales.', 'Fecha de la toma.', [...self::DOCUMENTOS, 'tipo_control']],
        'pacientes.cirugias' => ['Hojas clínicas', 'Cirugías', 'Cirugías con su personal y sus controles de recuperación.', 'Fecha de la cirugía (la real o, si no se ha hecho, la programada).', [...self::DOCUMENTOS, 'estado_cirugia']],
        'pacientes.altas' => ['Hojas clínicas', 'Altas médicas', 'Diagnóstico final, condición de egreso y receta de cada alta.', 'Fecha del alta.', self::DOCUMENTOS],
        'personal.personal_actividad' => ['Personal', 'Actividad de un empleado', 'Todas las hojas que registró un médico, enfermera u otro empleado.', 'Fecha de cada hoja.', ['personal', 'desde', 'hasta', 'id_paciente']],
        'personal.personal_pacientes' => ['Personal', 'Pacientes atendidos por un empleado', 'Un renglón por paciente, con cuántas hojas le registró ese empleado.', 'Fecha de cada hoja.', ['personal', 'desde', 'hasta', 'id_paciente']],
        'estadisticas.atenciones' => ['Estadísticas (cantidades)', 'Cantidad de ingresos y consultas', 'Total de atenciones y de pacientes distintos.', 'Fecha de llegada del paciente.', ['desde', 'hasta', 'atencion', 'estado_atencion', 'identificacion', 'sexo']],
        'estadisticas.hospitalizaciones' => ['Estadísticas (cantidades)', 'Cantidad de hospitalizaciones', 'Total de hospitalizaciones y de pacientes distintos.', 'Se cuentan las estancias activas en esas fechas.', ['desde', 'hasta', 'habitacion']],
        'estadisticas.internados' => ['Estadísticas (cantidades)', 'Cantidad de pacientes internados ahora', 'Cuántos pacientes están hospitalizados en este momento.', '', ['habitacion']],
        'estadisticas.cirugias' => ['Estadísticas (cantidades)', 'Cantidad de cirugías', 'Cirugías registradas, realizadas, programadas y canceladas.', 'Fecha de la cirugía.', ['desde', 'hasta']],
        'expediente' => ['Expediente', 'Expediente completo de un paciente', 'Todas sus hojas, imágenes y PDF subidos, del más antiguo al más reciente: en un solo PDF (cada documento en su propia página) o cada uno por separado en un .zip.', 'Sin fechas se incluye todo el expediente.', ['id_paciente', 'desde', 'hasta']],
    ];

    public function __construct(private DatosReportes $datos, private PdfReportes $pdf) {}

    /**
     * Reporte elegido según el formulario (o según enlaces antiguos con categoría y tipo).
     */
    private function clave(array $f): ?string
    {
        $categoria = $f['categoria'] ?? (isset($f['tipo']) ? (str_starts_with($f['tipo'], 'personal_') ? 'personal' : 'pacientes') : null);
        if ($categoria === 'expediente') {
            return 'expediente';
        }
        $clave = $categoria !== null && isset($f['tipo']) ? $categoria.'.'.$f['tipo'] : null;

        return isset(self::REPORTES[$clave]) ? $clave : null;
    }

    /**
     * Reportes que el usuario puede generar.
     */
    private function catalogo(): array
    {
        $tipos = $this->datos->disponibles();
        $permisos = app(Permisos::class);

        return array_filter(self::REPORTES, fn ($reporte, $clave) => $clave === 'expediente'
            ? $permisos->puede('Expedientes', 'Ver') && $permisos->puede('Cirugías', 'Ver') && $permisos->puede('Documentos clínicos', 'Ver')
            : isset($tipos[explode('.', $clave)[1]]), ARRAY_FILTER_USE_BOTH);
    }

    private function filtros(Request $request): array
    {
        // El formulario envía un solo valor («categoría.tipo»); los enlaces antiguos envían ambos por separado.
        if ($request->filled('reporte')) {
            $partes = explode('.', (string) $request->input('reporte'), 2);
            $request->merge(['categoria' => $partes[0], 'tipo' => $partes[1] ?? null]);
        }
        if ($request->filled('id_paciente')) {
            $request->merge(['identificacion' => null, 'sexo' => null, 'estado_paciente' => null]);
        }
        $f = $request->validate([
            'categoria' => ['nullable', Rule::in(['pacientes', 'personal', 'estadisticas', 'expediente'])],
            'tipo' => ['nullable', Rule::in(array_keys(DatosReportes::TIPOS))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('desde'), 'after_or_equal:desde')],
            'paciente' => ['nullable', 'string', 'max:100'],
            'situacion' => ['nullable', Rule::in(['internados', 'actuales', 'consultas'])],
            'id_paciente' => ['nullable', 'integer', 'exists:tbl_paciente,id_paciente'],
            'personal' => ['nullable', 'integer', 'exists:tbl_personal,id_personal'],
            'habitacion' => ['nullable', 'integer', 'exists:tbl_habitacion,id_habitacion'],
            'identificacion' => ['nullable', Rule::in(['Identificado', 'No identificado'])],
            'sexo' => ['nullable', Rule::in(['Masculino', 'Femenino', 'No determinado'])],
            'estado_paciente' => ['nullable', Rule::in(['Activo', 'Inactivo'])],
            'atencion' => ['nullable', Rule::in(['Hospitalización', 'Consulta externa'])],
            'estado_atencion' => ['nullable', Rule::in(['Activo', 'Dado de alta', 'Cancelado'])],
            'estado_cirugia' => ['nullable', Rule::in(['Programada', 'En cirugía', 'Realizada', 'Cancelada', 'Indicada', 'Aprobada', 'Evaluación preoperatoria'])],
            'tipo_control' => ['nullable', Rule::in(['Ingreso', 'Consulta', 'Preoperatorio', 'Recuperación', 'Rutina'])],
            'turno' => ['nullable', Rule::in(['Mañana', 'Tarde', 'Noche'])],
            'actividad' => ['nullable', Rule::in(['atenciones', 'valoraciones', 'consultas', 'evoluciones', 'ordenes', 'enfermeria', 'signos', 'altas', 'cirugias'])],
            'descargar' => ['nullable', 'boolean'],
        ]);
        $f = $this->fechas($f);
        $clave = $this->clave($f);
        $f['tipo'] ??= array_key_first($this->datos->disponibles()) ?? 'pacientes';
        abort_unless(isset($this->datos->disponibles()[$f['tipo']]), 403);
        if ($clave !== null) {
            // Solo se aplican los datos que corresponden al reporte elegido.
            $f = array_intersect_key($f, array_flip([...self::REPORTES[$clave][4], 'categoria', 'tipo', 'descargar']));
        }

        return array_filter($f, fn ($v) => $v !== null && $v !== '');
    }

    public function index(Request $request): View|RedirectResponse|Response
    {
        $filtros = $this->filtros($request);
        $tipo = $filtros['tipo'];
        $clave = $request->filled('reporte') || $request->filled('tipo') || $request->filled('categoria') ? $this->clave($filtros) : null;
        if ($clave === 'expediente' && ($request->boolean('generar') || $request->boolean('descargar') || $request->boolean('zip'))) {
            $request->validate(['id_paciente' => ['required', 'integer', 'exists:tbl_paciente,id_paciente']], ['id_paciente.required' => 'Elija el paciente cuyo expediente desea generar.']);
            $limite = 'reportes-expediente:'.session('id_usuario');
            abort_if(RateLimiter::tooManyAttempts($limite, 5), 429);
            RateLimiter::hit($limite);

            $paciente = Paciente::findOrFail($filtros['id_paciente']);

            return $request->boolean('zip') ? $this->expedienteZip($request, $paciente) : $this->expediente($request, $paciente);
        }
        $mostrar = $clave !== null && $clave !== 'expediente'
            && ($request->has('consultar') || ($request->has('tipo') && ! $request->has('categoria') && ! $request->has('reporte')));
        $estadisticas = [];
        $registros = null;
        $filas = collect();
        if ($mostrar && ($filtros['categoria'] ?? '') === 'estadisticas') {
            $estadisticas = $this->estadisticas($tipo, $filtros);
        } elseif ($mostrar && (str_starts_with($tipo, 'personal_') || $tipo === 'signos')) {
            $todos = $tipo === 'signos' ? app(ReportesAmpliados::class)->signos($filtros) : app(ReportesAmpliados::class)->actividad($filtros);
            $pagina = max(1, (int) $request->input('page', 1));
            $registros = new LengthAwarePaginator($todos->forPage($pagina, 20)->values(), $todos->count(), 20, $pagina, ['path' => route('reportes.index'), 'query' => $request->query()]);
        } elseif ($mostrar) {
            $registros = $this->datos->consulta($tipo, $filtros)->paginate(20)->withQueryString();
        }
        if ($registros) {
            $filas = $registros->getCollection()->map(fn ($r) => is_array($r) ? $r : $this->datos->fila($tipo, $r));
        }
        $catalogo = $this->catalogo();
        $personal = DB::table('tbl_personal as p')->leftJoin('tbl_cargo as c', 'c.id_cargo', '=', 'p.id_cargo')->orderBy('p.nombres')->get(['p.id_personal', 'p.nombres', 'p.apellidos', 'c.nombre_cargo']);
        $habitaciones = DB::table('tbl_habitacion')->orderBy('numero_habitacion')->get(['id_habitacion', 'numero_habitacion']);

        $pacientes = Paciente::orderByDesc('id_paciente')->get(['id_paciente', 'numero_expediente', 'nombres', 'apellidos', 'codigo_temporal']);

        return view('reportes.index', compact('estadisticas', 'pacientes', 'filtros', 'tipo', 'clave', 'catalogo', 'mostrar', 'registros', 'filas', 'personal', 'habitaciones'));
    }

    public function listado(Request $request): Response
    {
        $f = $this->filtros($request);
        $tipo = $f['tipo'];
        if (($f['categoria'] ?? '') === 'estadisticas') {
            $filas = collect($this->estadisticas($tipo, $f))->map(fn ($valor, $indicador) => ['indicador' => $indicador, 'cantidad' => $valor])->values()->all();

            return $this->respuesta($request, $this->pdf->generar('Estadísticas: '.DatosReportes::TIPOS[$tipo][0], $filas, $this->etiquetas($f)), 'estadisticas');
        }
        $registros = match (true) {
            $tipo === 'signos' => app(ReportesAmpliados::class)->signos($f),
            str_starts_with($tipo, 'personal_') => app(ReportesAmpliados::class)->actividad($f),
            default => $this->datos->consulta($tipo, $f)->limit(1001)->get(),
        };
        if ($registros->count() > 1000) {
            throw ValidationException::withMessages(['tipo' => 'El reporte supera 1,000 registros. Reduzca el intervalo de fechas o agregue filtros.']);
        }
        $filas = $registros->map(fn ($r) => is_array($r) ? $r : $this->datos->fila($tipo, $r))->all();
        $bytes = $this->pdf->generar(DatosReportes::TIPOS[$tipo][0], $filas, $this->etiquetas($f));

        return $this->respuesta($request, $bytes, $tipo);
    }

    public function documento(Request $request, string $tipo, int $id): Response
    {
        abort_unless(isset(DatosReportes::TIPOS[$tipo]) && ! str_starts_with($tipo, 'personal_'), 404);
        abort_unless(app(Permisos::class)->puede(DatosReportes::TIPOS[$tipo][3], 'Ver'), 403);
        $request->validate(['descargar' => ['nullable', 'boolean']]);
        $r = $this->datos->consulta($tipo)->findOrFail($id);
        $doc = $this->datos->documento($tipo, $r);
        $bytes = $this->pdf->generar($this->tituloDocumento($tipo), [], [], $doc);
        // Las fotos o PDF adjuntos a la hoja van al final del mismo PDF.
        if (app(Permisos::class)->puede('Documentos clínicos', 'Ver')) {
            $adjuntos = DocumentoClinico::where('tabla_origen', $r->getTable())->where('id_registro_origen', $r->getKey())->where('estado', 'Activo')
                ->orderBy('fecha_documento')->orderBy('id_documento')->get();
            $bytes = $this->conAdjuntos($request, $bytes, $this->tituloDocumento($tipo), $adjuntos);
        }

        return $this->respuesta($request, $bytes, $tipo.'-'.$id);
    }

    /**
     * Une al PDF de una hoja los archivos que se le adjuntaron (las imágenes se convierten a PDF).
     *
     * @param  iterable<DocumentoClinico>  $adjuntos
     */
    private function conAdjuntos(Request $request, string $bytes, string $nombre, iterable $adjuntos): string
    {
        $partes = [['nombre' => $nombre, 'bytes' => $bytes]];
        foreach ($adjuntos as $adjunto) {
            $partes[] = ['nombre' => $adjunto->titulo.' (#'.$adjunto->getKey().')', 'bytes' => $this->archivo($request, $adjunto)->getContent()];
        }

        return count($partes) === 1 ? $bytes : app(ReportesAmpliados::class)->unir($partes);
    }

    public function archivo(Request $request, DocumentoClinico $archivoClinico): Response
    {
        $request->validate(['descargar' => ['nullable', 'boolean']]);
        abort_unless($archivoClinico->estado === 'Activo' && ($archivoClinico->ingreso || $archivoClinico->esDelPaciente()), 404);
        $carpeta = $archivoClinico->esDelPaciente() ? 'pacientes/'.$archivoClinico->id_paciente : 'hojas/'.$archivoClinico->id_ingreso;
        $ruta = $archivoClinico->ruta_archivo;
        abort_unless(str_starts_with($ruta, $carpeta.'/') && ! str_contains($ruta, '..'), 404);
        $disk = Storage::disk('documentos_clinicos');
        abort_unless($disk->exists($ruta), 404);
        $mime = $disk->mimeType($ruta);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true), 415);
        $bytes = $disk->get($ruta);
        if ($mime !== 'application/pdf') {
            if ($mime === 'image/webp') {
                $imagen = imagecreatefromstring($bytes);
                abort_unless($imagen !== false, 415);
                ob_start();
                imagepng($imagen);
                $bytes = ob_get_clean();
                imagedestroy($imagen);
                $mime = 'image/png';
            }
            $p = $archivoClinico->paciente;
            $doc = ['id' => $archivoClinico->getKey(), 'paciente' => $p->estado_identificacion === 'No identificado' ? 'No identificado · '.$p->codigo_temporal : $this->datos->nombre($p), 'expediente' => $p->numero_expediente, 'fecha' => $archivoClinico->fecha_documento?->format('d/m/Y H:i'), 'responsable' => 'Subido por: '.$this->datos->nombre($archivoClinico->personalResponsable), 'secciones' => [], 'imagen' => 'data:'.$mime.';base64,'.base64_encode($bytes)];
            $bytes = $this->pdf->generar($archivoClinico->titulo, [], [], $doc);
        }

        return $this->respuesta($request, $bytes, 'hoja-'.$archivoClinico->getKey());
    }

    public function expediente(Request $request, Paciente $paciente): Response
    {
        $f = $request->validate(['desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('desde'), 'after_or_equal:desde')], 'descargar' => ['nullable', 'boolean']]);
        $f = $this->fechas($f);
        $servicio = app(ReportesAmpliados::class);
        $documentos = $servicio->documentos($paciente, $f);
        $bytes = $servicio->unir($this->partesExpediente($request, $documentos));
        $request->boolean('descargar')
            ? $this->registrarExpediente($request, $paciente, 'Descarga de expediente', 'Se descargó el expediente completo en un PDF', $documentos->count(), $f)
            : $this->registrarExpediente($request, $paciente, 'Impresión de expediente', 'Se abrió el expediente completo en PDF para imprimirlo o verlo', $documentos->count(), $f);

        return $this->respuesta($request, $bytes, 'expediente-'.$paciente->getKey());
    }

    /**
     * Expediente en un .zip: una carpeta «expediente - nombre» con la hoja de datos del paciente
     * y cada documento en su propio PDF, numerados en orden de fecha.
     */
    public function expedienteZip(Request $request, Paciente $paciente): Response
    {
        $f = $request->validate(['desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('desde'), 'after_or_equal:desde')]]);
        $documentos = app(ReportesAmpliados::class)->documentos($paciente, $this->fechas($f));
        $nombre = $paciente->estado_identificacion === 'No identificado' ? 'No identificado '.$paciente->codigo_temporal : $this->datos->nombre($paciente);
        $carpeta = $this->nombreArchivo($paciente->numero_expediente.' - '.$nombre);
        $zip = new ArchivoZip;
        $zip->agregar($carpeta.'/000 Datos del paciente.pdf', $this->pdf->generar('Datos del paciente', [], [], $this->datos->fichaPaciente($paciente)));
        $total = 0;
        foreach ($this->partesExpediente($request, $documentos) as $n => $parte) {
            $total += strlen($parte['bytes']);
            if ($total > 80000000) {
                throw ValidationException::withMessages(['desde' => 'Los documentos superan 80 MB. Reduzca el intervalo de fechas.']);
            }
            $fecha = $parte['fecha'] ? Carbon::parse($parte['fecha'])->format('Y-m-d H.i') : 'sin fecha';
            $zip->agregar(sprintf('%s/%03d %s %s.pdf', $carpeta, $n + 1, $fecha, $this->nombreArchivo($parte['titulo'])), $parte['bytes']);
        }
        $this->registrarExpediente($request, $paciente, 'Descarga de expediente', 'Se descargó el expediente en .zip, con los datos del paciente y cada documento por separado', $documentos->count(), $this->fechas($f));

        return response($zip->contenido(), 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="'.$carpeta.'.zip"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * En la bitácora solo queda la descarga o impresión de expedientes; los demás PDF no se registran.
     */
    private function registrarExpediente(Request $request, Paciente $paciente, string $accion, string $que, int $documentos, array $fechas): void
    {
        $periodo = ! empty($fechas['desde']) ? ' Fechas: '.Carbon::parse($fechas['desde'])->format('d/m/Y').($fechas['hasta'] !== $fechas['desde'] ? ' a '.Carbon::parse($fechas['hasta'])->format('d/m/Y') : '').'.' : '';
        app(RegistroBitacora::class)->registrar($request, $accion, 'Exitoso',
            $que.' ('.$paciente->numero_expediente.', '.$documentos.' '.($documentos === 1 ? 'documento' : 'documentos').').'.$periodo,
            usuario: (int) session('id_usuario'), registro: $paciente->getKey(), tabla: 'tbl_paciente');
    }

    /**
     * PDF de cada documento del expediente, en el orden recibido.
     *
     * @return \Generator<int, array{nombre: string, titulo: string, fecha: string, bytes: string}>
     */
    private function partesExpediente(Request $request, Collection $documentos): \Generator
    {
        foreach ($documentos as $item) {
            $r = $item['registro'];
            $tipo = $item['tipo'];
            if ($tipo === 'archivo') {
                $bytes = $this->archivo($request, $r)->getContent();
                $nombre = $r->titulo.' (#'.$r->getKey().')';
                $titulo = $r->titulo;
            } else {
                $nombre = DatosReportes::TIPOS[$tipo][0].' #'.$r->getKey();
                $titulo = $this->tituloDocumento($tipo);
                $bytes = $this->conAdjuntos($request, $this->pdf->generar($titulo, [], [], $this->datos->documento($tipo, $r)), $nombre, $item['adjuntos'] ?? []);
            }
            yield ['nombre' => $nombre, 'titulo' => $titulo, 'fecha' => $item['orden'], 'bytes' => $bytes];
        }
    }

    private function tituloDocumento(string $tipo): string
    {
        return match ($tipo) {
            'valoraciones', 'consultas' => 'Valoración inicial', 'evoluciones' => 'Evolución clínica', 'enfermeria' => 'Nota de enfermería', 'altas' => 'Alta médica', 'cirugias' => 'Cirugía y controles de recuperación', 'atenciones' => 'Hoja de ingreso / llegada', 'pacientes' => 'Datos del paciente', 'signos' => 'Signos vitales', default => DatosReportes::TIPOS[$tipo][0]
        };
    }

    /**
     * Nombre que Windows y otros sistemas aceptan: sin tildes ni caracteres especiales.
     */
    private function nombreArchivo(string $texto): string
    {
        $limpio = preg_replace('/[^A-Za-z0-9 ._-]+/', '', str_replace('/', '-', Str::ascii($texto)));

        return trim(Str::limit(preg_replace('/\s+/', ' ', $limpio), 90, ''), ' .') ?: 'documento';
    }

    private function fechas(array $f): array
    {
        if (! empty($f['desde']) && empty($f['hasta'])) {
            $f['hasta'] = $f['desde'];
        }
        if (! empty($f['hasta']) && empty($f['desde'])) {
            $f['desde'] = $f['hasta'];
        }

        return $f;
    }

    private function estadisticas(string $tipo, array $filtros): array
    {
        abort_unless(in_array($tipo, ['atenciones', 'cirugias', 'hospitalizaciones', 'internados'], true), 422);
        $q = $this->datos->consulta($tipo, $filtros);
        if ($tipo === 'cirugias') {
            return ['Cirugías registradas' => (clone $q)->count(), 'Realizadas' => (clone $q)->where('estado', 'Realizada')->count(), 'Programadas' => (clone $q)->where('estado', 'Programada')->count(), 'Canceladas' => (clone $q)->where('estado', 'Cancelada')->count()];
        }

        return ['Atenciones registradas' => (clone $q)->count(), 'Pacientes distintos' => (clone $q)->distinct()->count('id_paciente')];
    }

    private function respuesta(Request $request, string $bytes, string $nombre): Response
    {
        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($request->boolean('descargar') ? 'attachment' : 'inline').'; filename="'.$nombre.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function etiquetas(array $f): array
    {
        $nombres = ['situacion' => 'Situación seleccionada', 'desde' => 'Desde', 'hasta' => 'Hasta', 'paciente' => 'Paciente buscado', 'identificacion' => 'Identificación', 'sexo' => 'Sexo', 'estado_paciente' => 'Estado del paciente', 'atencion' => 'Tipo de atención', 'estado_atencion' => 'Estado de atención', 'estado_cirugia' => 'Estado de cirugía', 'tipo_control' => 'Tipo de control', 'turno' => 'Turno', 'actividad' => 'Atención consultada'];
        $salida = ['Fechas' => 'Hora de Guatemala; corresponden al documento. En pacientes sin situación seleccionada, fecha de registro. En hospitalizaciones, estancias que coinciden con el período. En consultas, fecha de llegada; en cirugías, inicio real o programado.'];
        foreach ($nombres as $k => $etiqueta) {
            if (isset($f[$k])) {
                $salida[$etiqueta] = $k === 'situacion' ? (['internados' => 'Hospitalizados durante el período', 'actuales' => 'Actualmente internados', 'consultas' => 'Con consultas externas'][$f[$k]] ?? $f[$k]) : $f[$k];
            }
        }
        if (isset($f['actividad'])) {
            $salida['Atención consultada'] = DatosReportes::TIPOS[$f['actividad']][0];
        }
        if (isset($f['id_paciente'])) {
            $paciente = Paciente::findOrFail($f['id_paciente']);
            $salida['Paciente'] = $paciente->numero_expediente.' · '.(trim($paciente->nombres.' '.$paciente->apellidos) ?: 'No identificado '.$paciente->codigo_temporal);
        }
        if (isset($f['personal'])) {
            $p = DB::table('tbl_personal')->where('id_personal', $f['personal'])->first();
            $salida['Personal'] = trim($p->nombres.' '.$p->apellidos);
        }
        if (isset($f['habitacion'])) {
            $salida['Habitación'] = DB::table('tbl_habitacion')->where('id_habitacion', $f['habitacion'])->value('numero_habitacion');
        }

        return $salida;
    }
}
