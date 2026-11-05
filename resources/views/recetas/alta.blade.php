{{--
    Receta del alta médica o del final de la consulta externa, con el formato de la receta en papel del sanatorio.
    El médico va arriba (tomado del usuario que registró el alta) y abajo queda el espacio para firmar.
    Se imprime con el navegador; desde la misma ventana de impresión se puede «Guardar como PDF».
    Variables: $alta (con medicamentos), $paciente, $medico (Personal o null), $imprimirAlAbrir.
--}}
@php
    $nombrePaciente = $paciente->estado_identificacion === 'Identificado'
        ? trim($paciente->nombres.' '.$paciente->apellidos)
        : 'Paciente no identificado'.($paciente->codigo_temporal ? ' ('.$paciente->codigo_temporal.')' : '');
    $nombreMedico = trim(($medico?->nombres ?? '').' '.($medico?->apellidos ?? ''));
    $especialidadMedico = $medico?->especialidad?->nombre_especialidad;
    $descripcionMedico = $especialidadMedico ? 'Especialidad en '.$especialidadMedico : $medico?->cargo?->nombre_cargo;
    // «5» se lee como «5 días»; si ya trae la unidad se deja tal cual.
    $frecuenciaTexto = fn (?string $valor) => blank($valor) ? null : (is_numeric(trim($valor)) ? 'Frecuencia: cada '.trim($valor).((float)$valor === 1.0 ? ' hora' : ' horas') : trim($valor));
    $dosisTexto = fn (?string $valor) => blank($valor) ? null : (is_numeric(trim($valor)) ? 'Dosis: '.trim($valor) : trim($valor));
    $duracionTexto = fn (?string $duracion) => blank($duracion) ? null
        : (ctype_digit(trim($duracion)) ? 'Duración: '.trim($duracion).(trim($duracion) === '1' ? ' día' : ' días') : 'Por '.trim($duracion));
    $indicaciones = trim((string) $alta->indicaciones_alta);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receta · {{ $nombrePaciente }} · {{ $alta->fecha_hora->format('d/m/Y') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,600;0,700;0,800;0,900;1,700&family=Dancing+Script:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --vino: #8e1b1f;
            --vino-oscuro: #5e1114;
            --tinta: #1f2430;
            --tenue: #5b6474;
            --linea: #d9dde4;
            --fondo: #eef1f5;
        }
        * { box-sizing: border-box; }
        html { color-scheme: light; }
        body { margin: 0; background: var(--fondo); color: var(--tinta); font-family: 'Nunito', 'Segoe UI', Arial, sans-serif; }

        /* Barra de acciones: solo en pantalla. */
        .acciones { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px 16px; padding: 12px 16px; background: #fff; border-bottom: 1px solid var(--linea); }
        .acciones p { margin: 0; color: var(--tenue); font-size: 0.92rem; max-width: 34rem; }
        .boton { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 18px; border-radius: 10px; border: 1px solid var(--vino); background: var(--vino); color: #fff; font: 700 1rem 'Nunito', sans-serif; cursor: pointer; text-decoration: none; }
        .boton.secundario { background: #fff; color: var(--vino); }

        /* Hoja tamaño carta. */
        .hoja { width: 8.5in; max-width: calc(100% - 32px); min-height: 11in; margin: 24px auto; padding: 0.55in 0.6in 0.45in; background: #fff; box-shadow: 0 10px 30px rgba(20, 30, 50, 0.12); display: flex; flex-direction: column; position: relative; overflow: hidden; }
        .hoja::before { content: ""; position: absolute; inset: 0; background: url('{{ asset('imagenes/emblema-sanatorio.png') }}') center 58% / 46% no-repeat; opacity: 0.05; pointer-events: none; }
        .hoja > * { position: relative; }

        .membrete { display: flex; align-items: center; gap: 18px; }
        .membrete img { width: 92px; height: auto; flex-shrink: 0; }
        .membrete-texto { flex: 1; min-width: 0; text-align: center; }
        .membrete-sanatorio { margin: 0; color: var(--vino-oscuro); font-weight: 700; font-size: 1.35rem; letter-spacing: 0.08em; line-height: 1; }
        .membrete-nombre { margin: 2px 0 0; color: var(--vino); font-weight: 900; font-size: 2.6rem; letter-spacing: 0.04em; line-height: 1; }
        .membrete-telefonos { margin: 8px 0 0; font-size: 0.92rem; color: var(--tinta); }
        .membrete-telefonos strong { font-weight: 800; }
        .servicios { margin: 12px 0 0; text-align: center; color: var(--vino-oscuro); font-style: italic; font-weight: 700; font-size: 0.98rem; line-height: 1.35; }
        .franja { height: 6px; margin: 12px 0 0; border-radius: 3px; background: linear-gradient(90deg, var(--vino-oscuro), var(--vino) 55%, #b83a3e); }

        .medico { margin: 16px 0 0; text-align: center; }
        .medico-nombre { margin: 0; font-family: 'Dancing Script', 'Brush Script MT', cursive; font-size: 2rem; font-weight: 700; color: var(--vino-oscuro); line-height: 1.1; }
        .medico-detalle { margin: 2px 0 0; font-weight: 800; font-size: 0.95rem; }
        .medico-colegiado { margin: 0; color: var(--tenue); font-size: 0.86rem; }

        .datos { display: grid; grid-template-columns: 1fr auto; gap: 10px 28px; margin: 18px 0 0; }
        .dato { display: flex; align-items: baseline; gap: 8px; min-width: 0; }
        .dato dt { font-weight: 800; white-space: nowrap; }
        .dato dd { flex: 1; margin: 0; padding: 0 4px 2px; border-bottom: 1.5px solid var(--tinta); min-width: 0; overflow-wrap: anywhere; }
        .dato-ancho { grid-column: 1 / -1; }

        .rp { margin: 20px 0 6px; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-weight: 700; font-size: 2.1rem; color: var(--vino-oscuro); line-height: 1; }
        .medicamentos { list-style: none; counter-reset: medicamento; margin: 0; padding: 0; }
        .medicamentos li { counter-increment: medicamento; display: grid; grid-template-columns: 2rem 1fr; gap: 2px 6px; padding: 10px 0; border-bottom: 1px dashed var(--linea); break-inside: avoid; }
        .medicamentos li::before { content: counter(medicamento) "."; grid-row: span 2; font-weight: 900; font-size: 1.1rem; color: var(--vino); }
        .medicamento-nombre { font-weight: 800; font-size: 1.12rem; overflow-wrap: anywhere; }
        .medicamento-uso { color: var(--tinta); overflow-wrap: anywhere; }
        .vacio { margin: 8px 0 0; color: var(--tenue); font-style: italic; }

        .indicaciones { margin: 20px 0 0; break-inside: avoid; }
        .indicaciones h2 { margin: 0 0 6px; font-size: 1rem; font-weight: 900; letter-spacing: 0.06em; text-transform: uppercase; color: var(--vino-oscuro); }
        .indicaciones p { margin: 0; white-space: pre-line; line-height: 1.55; overflow-wrap: anywhere; }

        .firma { margin-top: auto; padding-top: 56px; display: flex; justify-content: flex-end; break-inside: avoid; }
        .firma div { width: 3.3in; max-width: 100%; text-align: center; }
        .firma hr { margin: 0 0 6px; border: 0; border-top: 1.5px solid var(--tinta); }
        .firma span { display: block; font-weight: 800; }

        .pie { margin: 22px -0.6in -0.45in; padding: 10px 16px; background: var(--vino-oscuro); color: #fff; text-align: center; font-weight: 900; letter-spacing: 0.08em; font-size: 1.05rem; }

        @media (max-width: 640px) {
            .hoja { min-height: auto; margin: 16px auto; padding: 22px 18px 0; }
            .membrete { gap: 10px; }
            .membrete img { width: 58px; }
            .membrete-sanatorio { font-size: 1rem; }
            .membrete-nombre { font-size: 1.75rem; }
            .membrete-telefonos, .servicios { font-size: 0.82rem; }
            .datos { grid-template-columns: 1fr; }
            .firma { justify-content: center; }
            .pie { margin: 22px -18px 0; font-size: 0.9rem; }
        }

        @page { size: letter portrait; margin: 0; }
        @media print {
            body { background: #fff; }
            .acciones { display: none; }
            .hoja { width: 8.5in; max-width: none; height: 11in; min-height: 0; margin: 0; box-shadow: none; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="acciones">
        <button type="button" class="boton" onclick="window.print()">Imprimir receta</button>
        <a class="boton secundario" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('atencion.show', $alta->id_ingreso) }}">Volver</a>
        <p>Para descargarla, en la ventana de impresión elija <strong>Destino: Guardar como PDF</strong>.</p>
    </div>

    <main class="hoja" aria-label="Receta médica">
        <header class="membrete">
            <img src="{{ asset('imagenes/emblema-sanatorio.png') }}" alt="Emblema del Sanatorio La Promesa">
            <div class="membrete-texto">
                <p class="membrete-sanatorio">SANATORIO</p>
                <p class="membrete-nombre">LA PROMESA</p>
                <p class="membrete-telefonos">Teléfono: <strong>7830-5174</strong> &nbsp;·&nbsp; Tel. de Farmacia: <strong>5718-6325</strong></p>
            </div>
        </header>
        <p class="servicios">Medicina General, Pediatría, Hospitalización,<br>Operaciones de: Vesícula, Apéndice, Próstata, Cesáreas,<br>Atención del Parto, Ultrasonido.</p>
        <div class="franja" aria-hidden="true"></div>

        @if($nombreMedico !== '')
        <section class="medico" aria-label="Médico">
            <p class="medico-nombre">{{ $nombreMedico }}</p>
            @if($descripcionMedico)<p class="medico-detalle">{{ $descripcionMedico }}</p>@endif
            @if(filled($medico?->numero_colegiado))<p class="medico-colegiado">Colegiado No. {{ $medico->numero_colegiado }}</p>@endif
        </section>
        @endif

        <dl class="datos">
            <div class="dato"><dt>Paciente:</dt><dd>{{ $nombrePaciente }}</dd></div>
            <div class="dato"><dt>Edad:</dt><dd>{{ $paciente->fecha_nacimiento ? $paciente->fecha_nacimiento->age.' años' : '—' }}</dd></div>
            <div class="dato"><dt>Fecha:</dt><dd>{{ $alta->fecha_hora->format('d/m/Y') }}</dd></div>
            <div class="dato"><dt>Expediente:</dt><dd>{{ $paciente->numero_expediente }}</dd></div>
        </dl>

        <p class="rp" aria-label="Receta">Rp.</p>
        @if($alta->medicamentos->isNotEmpty())
        <ol class="medicamentos">
            @foreach($alta->medicamentos as $medicamento)
            <li>
                <span class="medicamento-nombre">{{ $medicamento->medicamento }}</span>
                <span class="medicamento-uso">{{ collect([$dosisTexto($medicamento->dosis), $medicamento->via_administracion ? 'vía '.mb_strtolower($medicamento->via_administracion) : null, $frecuenciaTexto($medicamento->frecuencia), $duracionTexto($medicamento->duracion)])->filter(fn ($parte) => filled($parte))->implode(' · ') }}</span>
            </li>
            @endforeach
        </ol>
        @else
        <p class="vacio">Sin medicamentos indicados.</p>
        @endif

        @if($indicaciones !== '')
        <section class="indicaciones">
            <h2>Indicaciones médicas</h2>
            <p>{{ $indicaciones }}</p>
        </section>
        @endif

        <section class="firma" aria-label="Firma del médico">
            <div>
                <hr>
                <span>Firma y sello</span>
            </div>
        </section>

        <footer class="pie">FAVOR NO CAMBIAR ESTA RECETA</footer>
    </main>

    @if($imprimirAlAbrir)
    <script>
        window.addEventListener('load', () => {
            const imprimir = () => window.print();
            (document.fonts ? document.fonts.ready : Promise.resolve()).then(() => setTimeout(imprimir, 150));
        });
    </script>
    @endif
</body>
</html>
