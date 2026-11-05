@extends('layouts.app')
@section('title', 'Reportes - Sanatorio La Promesa')
@section('menu-reportes', 'activo')
@section('content')
@php
    $actual = $clave !== null ? ($catalogo[$clave] ?? null) : null;
    $camposActuales = $actual[4] ?? [];
    $ver = fn (string $campo): bool => in_array($campo, $camposActuales, true) && (empty($filtros['id_paciente']) || ! in_array($campo, ['identificacion', 'sexo', 'estado_paciente'], true));
    $grupos = collect($catalogo)->groupBy(fn (array $reporte) => $reporte[0], preserveKeys: true);
    $avanzados = ['identificacion', 'sexo', 'estado_paciente', 'atencion', 'estado_atencion'];
    $hayAvanzados = collect($avanzados)->contains(fn (string $campo) => $ver($campo));
    $abrirAvanzados = collect($avanzados)->contains(fn (string $campo) => $ver($campo) && filled($filtros[$campo] ?? null));
    $esExpediente = $clave === 'expediente';
    $nombrePaciente = fn ($p) => trim($p->nombres.' '.$p->apellidos) ?: 'No identificado '.$p->codigo_temporal;
    $tiposActividades = array_intersect_key([
        'atenciones' => 'Ingresos y consultas', 'valoraciones' => 'Valoraciones iniciales',
        'consultas' => 'Consultas externas', 'evoluciones' => 'Evoluciones clínicas',
        'ordenes' => 'Órdenes médicas', 'enfermeria' => 'Notas de enfermería y actividades realizadas',
        'signos' => 'Signos vitales', 'cirugias' => 'Cirugías', 'altas' => 'Altas médicas',
    ], app(\App\Services\DatosReportes::class)->disponibles());
    $selectores = [
        'situacion' => ['col-md-6', 'Situación del paciente', ['' => 'Todos', 'internados' => 'Estuvieron internados en esas fechas', 'actuales' => 'Están internados ahora', 'consultas' => 'Tuvieron consultas en esas fechas']],
        'turno' => ['col-md-6', 'Turno', ['' => 'Todos los turnos', 'Mañana' => 'Mañana (7:00 a 13:00)', 'Tarde' => 'Tarde (13:00 a 19:00)', 'Noche' => 'Noche (19:00 a 7:00)']],
        'tipo_control' => ['col-md-6', 'Tipo de control', ['' => 'Todos', 'Ingreso' => 'Ingreso', 'Consulta' => 'Consulta', 'Preoperatorio' => 'Preoperatorio', 'Recuperación' => 'Recuperación', 'Rutina' => 'Rutina']],
        'estado_cirugia' => ['col-md-6', 'Estado de la cirugía', ['' => 'Todos', 'Programada' => 'Programada', 'En cirugía' => 'En cirugía', 'Realizada' => 'Realizada', 'Cancelada' => 'Cancelada']],
    ];
    $selectoresAvanzados = [
        'identificacion' => ['Identificación', ['' => 'Todos', 'Identificado' => 'Identificado', 'No identificado' => 'No identificado']],
        'sexo' => ['Sexo', ['' => 'Todos', 'Masculino' => 'Masculino', 'Femenino' => 'Femenino', 'No determinado' => 'No determinado']],
        'estado_paciente' => ['Estado del paciente', ['' => 'Todos', 'Activo' => 'Activo', 'Inactivo' => 'Inactivo']],
        'atencion' => ['Tipo de atención', ['' => 'Todas', 'Hospitalización' => 'Hospitalización', 'Consulta externa' => 'Consulta externa']],
        'estado_atencion' => ['Estado de la atención', ['' => 'Todos', 'Activo' => 'Activa', 'Dado de alta' => 'Dado de alta', 'Cancelado' => 'Cancelada']],
    ];
@endphp
<div class="inicio-encabezado"><div class="inicio-icono"><i class="bi bi-file-earmark-pdf"></i></div><div><h1>Reportes</h1><p>Elija el reporte, indique los datos y véalo en pantalla o descárguelo en PDF.</p></div></div>

<section class="card rounded-4 border-0 shadow-sm mb-4">
<div class="card-body p-4">
<form method="GET" action="{{ route('reportes.index') }}#resultados" id="form-reporte" data-reporte="{{ $clave }}" data-borrador-reportes="{{ session('id_usuario') }}-{{ hash('sha256', session()->getId()) }}" data-restaurar-borrador="{{ $clave === null && ! $errors->any() ? '1' : '0' }}">
    @if($errors->any())
    <div class="alert alert-danger" role="alert"><strong>No se pudo generar el reporte.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="reporte-paso">
        <span class="reporte-paso-numero" aria-hidden="true">1</span>
        <label for="reporte" class="reporte-paso-titulo">¿Qué reporte necesita?</label>
    </div>
    <select id="reporte" name="reporte" class="form-select form-select-lg" required aria-describedby="reporte-descripcion">
        <option value="">Elija un reporte…</option>
        @foreach($grupos as $grupo => $reportes)
        <optgroup label="{{ $grupo }}">
            @foreach($reportes as $valor => $reporte)
            <option value="{{ $valor }}" data-descripcion="{{ $reporte[2] }}" data-fecha="{{ $reporte[3] }}" data-campos="{{ implode(' ', $reporte[4]) }}" @selected($clave === $valor)>{{ $reporte[1] }}</option>
            @endforeach
        </optgroup>
        @endforeach
    </select>
    <p class="reporte-descripcion" id="reporte-descripcion" data-inicial="Al elegir un reporte aparecerán solo los datos que puede indicar." aria-live="polite">{{ $actual[2] ?? 'Al elegir un reporte aparecerán solo los datos que puede indicar.' }}</p>

    <div class="reporte-datos" id="reporte-datos" @unless($actual) hidden @endunless>
        <div class="reporte-paso">
            <span class="reporte-paso-numero" aria-hidden="true">2</span>
            <span class="reporte-paso-titulo">¿Con qué datos?</span>
            <span class="reporte-paso-nota" data-nota-opcional @if($esExpediente) hidden @endif>Todo es opcional: si deja un dato vacío, se incluye todo.</span>
        </div>
        <div class="row g-3">
            <div class="col-12" data-campo="actividad" @unless($ver('actividad')) hidden @endunless>
                <label class="form-label" for="actividad"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i> ¿Qué atención desea consultar?</label>
                <select class="form-select" name="actividad" id="actividad" @disabled(! $ver('actividad'))>
                    <option value="">Toda la atención y sus responsables</option>
                    @foreach($tiposActividades as $valor => $texto)<option value="{{ $valor }}" @selected(($filtros['actividad'] ?? '') === $valor)>{{ $texto }}</option>@endforeach
                </select>
            </div>
            <div class="col-12" data-campo="desde" @unless($ver('desde')) hidden @endunless>
                <fieldset class="reporte-fechas">
                    <legend class="form-label mb-2">Fechas</legend>
                    <div class="reporte-atajos" role="group" aria-label="Elegir fechas rápidamente">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-rango="hoy" @disabled(! $ver('desde'))>Hoy</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-rango="7" @disabled(! $ver('desde'))>Últimos 7 días</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-rango="mes" @disabled(! $ver('desde'))>Este mes</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-rango="mes-anterior" @disabled(! $ver('desde'))>Mes pasado</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-rango="todo" @disabled(! $ver('desde'))>Todas las fechas</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6"><label class="form-label" for="desde">Desde</label><input class="form-control" type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}" @disabled(! $ver('desde'))></div>
                        <div class="col-sm-6"><label class="form-label" for="hasta">Hasta</label><input class="form-control" type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" @disabled(! $ver('desde'))></div>
                    </div>
                    <p class="form-text mb-0"><span id="reporte-ayuda-fecha">{{ $actual[3] ?? '' }}</span> Con una sola fecha se consulta solo ese día. Hora de Guatemala.</p>
                </fieldset>
            </div>

            <div class="col-md-6" data-campo="personal" @unless($ver('personal')) hidden @endunless>
                <label for="personal" class="form-label"><span data-etiqueta-personal>{{ $tipo === 'signos' ? 'Quién tomó los signos' : (str_contains((string) $clave, 'personal_') ? 'Personal que atendió' : 'Personal que registró la hoja') }}</span></label>
                <select id="personal" name="personal" class="form-select" @disabled(! $ver('personal'))>
                    <option value="">Todo el personal</option>
                    @foreach($personal as $p)<option value="{{ $p->id_personal }}" @selected(($filtros['personal'] ?? '') == $p->id_personal)>{{ $p->nombres }} {{ $p->apellidos }}{{ $p->nombre_cargo ? ' · '.$p->nombre_cargo : '' }}</option>@endforeach
                </select>
            </div>

            <div class="col-md-6" data-campo="id_paciente" @unless($ver('id_paciente')) hidden @endunless>
                <label for="id_paciente" class="form-label">Paciente</label>
                <select id="id_paciente" name="id_paciente" class="form-select" @required($esExpediente) @disabled(! $ver('id_paciente'))>
                    <option value="">{{ $esExpediente ? 'Elija un paciente' : 'Todos los pacientes' }}</option>
                    @foreach($pacientes as $p)<option value="{{ $p->id_paciente }}" @selected(($filtros['id_paciente'] ?? '') == $p->id_paciente)>{{ $p->numero_expediente }} · {{ $nombrePaciente($p) }}</option>@endforeach
                </select>
            </div>

            <div class="col-md-6" data-campo="habitacion" @unless($ver('habitacion')) hidden @endunless>
                <label for="habitacion" class="form-label">Habitación</label>
                <select id="habitacion" name="habitacion" class="form-select" @disabled(! $ver('habitacion'))>
                    <option value="">Todas las habitaciones</option>
                    @foreach($habitaciones as $h)<option value="{{ $h->id_habitacion }}" @selected(($filtros['habitacion'] ?? '') == $h->id_habitacion)>{{ $h->numero_habitacion }}</option>@endforeach
                </select>
            </div>

            @foreach($selectores as $campo => [$columna, $texto, $valores])
            <div class="{{ $columna }}" data-campo="{{ $campo }}" @unless($ver($campo)) hidden @endunless>
                <label for="{{ $campo }}" class="form-label">{{ $texto }}</label>
                <select id="{{ $campo }}" name="{{ $campo }}" class="form-select" @disabled(! $ver($campo))>
                    @foreach($valores as $valor => $etiqueta)<option value="{{ $valor }}" @selected(($filtros[$campo] ?? '') === $valor)>{{ $etiqueta }}</option>@endforeach
                </select>
            </div>
            @endforeach

            <div class="col-12" data-mas-filtros @unless($hayAvanzados) hidden @endunless>
                <details class="reporte-mas" @if($abrirAvanzados) open @endif>
                    <summary>Más filtros <span class="reporte-paso-nota">(según el reporte seleccionado)</span></summary>
                    <div class="row g-3 pt-2">
                        @foreach($selectoresAvanzados as $campo => [$texto, $valores])
                        <div class="col-md-4 col-sm-6" data-campo="{{ $campo }}" @unless($ver($campo)) hidden @endunless>
                            <label for="{{ $campo }}" class="form-label">{{ $texto }}</label>
                            <select id="{{ $campo }}" name="{{ $campo }}" class="form-select" @disabled(! $ver($campo))>
                                @foreach($valores as $valor => $etiqueta)<option value="{{ $valor }}" @selected(($filtros[$campo] ?? '') === $valor)>{{ $etiqueta }}</option>@endforeach
                            </select>
                        </div>
                        @endforeach
                    </div>
                </details>
            </div>
        </div>

        <div class="reporte-paso mt-4">
            <span class="reporte-paso-numero" aria-hidden="true">3</span>
            <span class="reporte-paso-titulo">Genere el reporte</span>
        </div>
        <div class="reporte-acciones">
            <div class="reporte-acciones-grupo" data-acciones="lista" @if($esExpediente) hidden @endif>
                <button class="btn btn-primary btn-lg" name="consultar" value="1" @disabled($esExpediente)><i class="bi bi-search" aria-hidden="true"></i> Ver resultados</button>
                <button class="btn btn-outline-primary btn-lg" formaction="{{ route('reportes.pdf') }}" name="descargar" value="1" @disabled($esExpediente)><i class="bi bi-download" aria-hidden="true"></i> Descargar PDF</button>
            </div>
            <div class="reporte-acciones-grupo" data-acciones="expediente" @unless($esExpediente) hidden @endunless>
                <button class="btn btn-primary btn-lg" name="descargar" value="1" @disabled(! $esExpediente)><i class="bi bi-download" aria-hidden="true"></i> Descargar expediente</button>
                <button class="btn btn-outline-primary btn-lg" name="zip" value="1" @disabled(! $esExpediente)><i class="bi bi-file-earmark-zip" aria-hidden="true"></i> Archivos por separado (.zip)</button>
                <button class="btn btn-outline-primary btn-lg" name="generar" value="1" formtarget="_blank" @disabled(! $esExpediente)><i class="bi bi-eye" aria-hidden="true"></i> Vista previa</button>
            </div>
            <a href="{{ route('reportes.index') }}" class="btn btn-link" data-reiniciar-reportes>Empezar de nuevo</a>
        </div>
    </div>
</form>
</div>
</section>

@if($mostrar)
<section id="resultados" class="reporte-resultados" data-clave="{{ $clave }}">
@if(($filtros['categoria'] ?? '') === 'estadisticas')
<div class="card rounded-4 border-0 shadow-sm"><div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
        <div><h2 class="h4 mb-1">{{ $actual[1] }}</h2>@if($tipo !== 'cirugias')<p class="text-muted mb-0">Pacientes distintos cuenta a cada persona una sola vez; atenciones cuenta cada visita o ingreso.</p>@endif</div>
        <div class="d-flex flex-wrap align-items-start gap-2"><a class="btn btn-primary" href="{{ route('reportes.pdf', $filtros + ['descargar' => 1]) }}"><i class="bi bi-download" aria-hidden="true"></i> Descargar PDF</a><a target="_blank" rel="noopener" class="btn btn-outline-primary" href="{{ route('reportes.pdf', $filtros) }}"><i class="bi bi-printer" aria-hidden="true"></i> Abrir para imprimir</a></div>
    </div>
    <div class="row g-3">@foreach($estadisticas as $etiqueta => $cantidad)<div class="col-sm-6 col-xl-3"><div class="reporte-cifra"><span>{{ $etiqueta }}</span><strong>{{ $cantidad }}</strong></div></div>@endforeach</div>
</div></div>
@else
<div class="card rounded-4 border-0 shadow-sm"><div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
        <div><h2 class="h4 mb-1">{{ $actual[1] }}</h2><p class="text-muted mb-0">{{ $registros->total() }} {{ $registros->total() === 1 ? 'registro encontrado' : 'registros encontrados' }}</p></div>
        <div class="d-flex flex-wrap align-items-start gap-2"><a class="btn btn-primary" href="{{ route('reportes.pdf', $filtros + ['descargar' => 1]) }}"><i class="bi bi-download" aria-hidden="true"></i> Descargar PDF</a><a target="_blank" rel="noopener" class="btn btn-outline-primary" href="{{ route('reportes.pdf', $filtros) }}"><i class="bi bi-printer" aria-hidden="true"></i> Abrir para imprimir</a></div>
    </div>
    <p class="text-muted">El PDF incluye todos los resultados de estos datos, hasta 1,000 registros. Para ver la hoja completa use el botón de cada fila.</p>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Fecha y hora</th><th>Paciente</th><th>Responsable</th><th>Resumen</th><th>Documento</th></tr></thead><tbody>
    @forelse($filas as $fila)<tr><td>{{ $fila['fecha'] }}</td><td>{{ $fila['paciente'] }}<br><small>{{ $fila['expediente'] }}</small></td><td>{{ $fila['responsable'] }}</td><td><div class="text-break">{{ $fila['detalle'] }}</div></td><td><a target="_blank" rel="noopener" href="{{ route('reportes.documento', [$fila['tipo_pdf'] ?? $tipo, $fila['id']]) }}" class="btn btn-outline-primary text-nowrap"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Ver PDF</a></td></tr>@empty<tr><td colspan="5">{{ $tipo === 'signos' ? 'No se encontraron signos vitales con esta combinación de paciente, personal y fechas. Pruebe con Todo el personal o Todas las fechas.' : 'No hay resultados. Pruebe con otras fechas o datos.' }}</td></tr>@endforelse
    </tbody></table></div>
    {{ $registros->links('pagination::bootstrap-5') }}
</div></div>
@endif
<details class="card rounded-4 border-0 shadow-sm mt-4 reporte-vista-previa">
    <summary class="card-body"><i class="bi bi-eye" aria-hidden="true"></i> Vista previa del reporte <span class="reporte-paso-nota">(así se verá el PDF)</span></summary>
    <div class="card-body pt-0">
        <iframe title="Vista previa del PDF" loading="lazy" src="{{ route('reportes.pdf', $filtros) }}"></iframe>
        <a class="d-inline-block mt-2" target="_blank" rel="noopener" href="{{ route('reportes.pdf', $filtros) }}">Abrir vista previa en otra pestaña</a>
    </div>
</details>
</section>
@endif

<script>
(() => {
    const form = document.getElementById('form-reporte');
    if (!form) return;
    const selector = form.querySelector('#reporte');
    const datos = document.getElementById('reporte-datos');
    const descripcion = document.getElementById('reporte-descripcion');
    const ayudaFecha = document.getElementById('reporte-ayuda-fecha');
    const masFiltros = form.querySelector('[data-mas-filtros]');
    const paciente = form.querySelector('#id_paciente');
    const resultados = document.getElementById('resultados');
    const actualizar = () => {
        const valor = selector.value;
        const opcion = selector.selectedOptions[0];
        const campos = valor ? (opcion.dataset.campos || '').split(' ') : [];
        const expediente = valor === 'expediente';
        form.dataset.reporte = valor;
        datos.hidden = !valor;
        if (resultados) resultados.hidden = resultados.dataset.clave !== valor;
        descripcion.textContent = valor ? opcion.dataset.descripcion : descripcion.dataset.inicial;
        ayudaFecha.textContent = valor ? opcion.dataset.fecha : '';
        form.querySelectorAll('[data-campo]').forEach(caja => {
            const activo = campos.includes(caja.dataset.campo) && (!paciente.value || !['identificacion', 'sexo', 'estado_paciente'].includes(caja.dataset.campo));
            caja.hidden = !activo;
            caja.querySelectorAll('input, select, button').forEach(control => { control.disabled = !activo; if (!activo && ['SELECT', 'INPUT'].includes(control.tagName)) control.value = ''; });
        });
        masFiltros.hidden = !masFiltros.querySelector('[data-campo]:not([hidden])');
        form.querySelectorAll('[data-acciones]').forEach(grupo => {
            const activo = grupo.dataset.acciones === (expediente ? 'expediente' : 'lista');
            grupo.hidden = !activo;
            grupo.querySelectorAll('button').forEach(boton => { boton.disabled = !activo; });
        });
        paciente.required = expediente;
        paciente.options[0].textContent = expediente ? 'Elija un paciente' : 'Todos los pacientes';
        form.querySelector('[data-nota-opcional]').hidden = expediente;
        form.querySelector('[data-etiqueta-personal]').textContent = (valor === 'pacientes.signos' || (valor === 'pacientes.personal_atenciones' && form.querySelector('#actividad').value === 'signos')) ? 'Quién tomó los signos' : (valor.includes('personal_') ? 'Personal que atendió' : 'Personal que registró la hoja');
    };
    const claveBorrador = 'sanatorio:reportes:' + form.dataset.borradorReportes;
    const controles = [...form.querySelectorAll('input[name], select[name]')];
    const desplegables = [...form.querySelectorAll('details')];
    let reiniciando = false;
    const guardarBorrador = () => {
        if (reiniciando) return;
        const valores = Object.fromEntries(controles.filter(control => !control.disabled).map(control => [control.name, control.value]));
        try {
            sessionStorage.setItem(claveBorrador, JSON.stringify({valores, desplegables: desplegables.map(detalle => detalle.open)}));
        } catch (_) {}
    };
    if (form.dataset.restaurarBorrador === '1') {
        try {
            const borrador = JSON.parse(sessionStorage.getItem(claveBorrador) || 'null');
            if (borrador?.valores && [...selector.options].some(opcion => opcion.value === borrador.valores.reporte)) {
                controles.forEach(control => {
                    const valor = borrador.valores[control.name];
                    if (typeof valor !== 'string') return;
                    if (control.tagName === 'SELECT' && ![...control.options].some(opcion => opcion.value === valor)) return;
                    control.value = valor;
                });
                desplegables.forEach((detalle, indice) => { detalle.open = borrador.desplegables?.[indice] === true; });
            }
        } catch (_) {}
    }
    form.addEventListener('input', guardarBorrador);
    form.addEventListener('change', () => { actualizar(); guardarBorrador(); });
    form.addEventListener('submit', guardarBorrador);
    desplegables.forEach(detalle => detalle.addEventListener('toggle', guardarBorrador));
    window.addEventListener('pagehide', guardarBorrador);
    form.querySelector('[data-reiniciar-reportes]').addEventListener('click', () => {
        reiniciando = true;
        try { sessionStorage.removeItem(claveBorrador); } catch (_) {}
    });
    selector.addEventListener('change', actualizar);
    paciente.addEventListener('change', actualizar);
    actualizar();

    const formato = fecha => `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;
    form.querySelectorAll('[data-rango]').forEach(boton => boton.addEventListener('click', () => {
        const hoy = new Date();
        let desde = '';
        let hasta = '';
        if (boton.dataset.rango === 'hoy') { desde = hasta = formato(hoy); }
        if (boton.dataset.rango === '7') { const inicio = new Date(hoy); inicio.setDate(hoy.getDate() - 6); desde = formato(inicio); hasta = formato(hoy); }
        if (boton.dataset.rango === 'mes') { desde = formato(new Date(hoy.getFullYear(), hoy.getMonth(), 1)); hasta = formato(hoy); }
        if (boton.dataset.rango === 'mes-anterior') { desde = formato(new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1)); hasta = formato(new Date(hoy.getFullYear(), hoy.getMonth(), 0)); }
        form.querySelector('#desde').value = desde;
        form.querySelector('#hasta').value = hasta;
        guardarBorrador();
    }));
})();
</script>
@endsection
