{{--
    Documento clínico del expediente (hoja de ingreso, valoración, evolución, nota de enfermería o alta).
    Variables: $tipo, $ingreso, $paciente y, según el tipo, $registro o $nota.
--}}
@php
    $emergenciaDocumento = $ingreso->esConsultaExterna();
    $nombrePersona = fn ($persona) => trim(($persona?->nombres ?? '').' '.($persona?->apellidos ?? '')) ?: 'Sin registro';
    $nombrePaciente = $paciente->estado_identificacion === 'Identificado' ? trim($paciente->nombres.' '.$paciente->apellidos) : 'Paciente no identificado';
    $datosPaciente = array_filter([
        $paciente->numero_expediente,
        $paciente->fecha_nacimiento ? $paciente->fecha_nacimiento->age.' años' : null,
        $paciente->sexo,
        $emergenciaDocumento ? 'Consulta externa del '.$ingreso->fecha_ingreso->format('d/m/Y') : 'Ingreso #'.$ingreso->getKey(),
    ]);
    [$iconoDocumento, $tonoDocumento] = match ($tipo) {
        'ingreso' => ['bi-file-earmark-text', 'primario'],
        'valoracion' => ['bi-clipboard2-pulse', 'primario'],
        'evolucion' => ['bi-graph-up-arrow', 'exito'],
        'nota' => ['bi-journal-medical', 'info'],
        'alta' => ['bi-box-arrow-right', 'exito'],
        default => ['bi-file-earmark', 'primario'],
    };
    $estadoIngreso = $emergenciaDocumento && $ingreso->estado === 'Dado de alta' ? 'Atención finalizada' : $ingreso->estado;
    $claseEstadoIngreso = match ($ingreso->estado) { 'Activo' => 'doc-pastilla-exito', 'Cancelado' => 'doc-pastilla-peligro', default => 'doc-pastilla-neutra' };

    // Signos vitales en el mismo orden que en los formularios: PA, FC, FR, temperatura, saturación, talla y peso.
    $signosDocumento = [];
    if (in_array($tipo, ['valoracion', 'evolucion'], true)) {
        $examen = $registro->examen_fisico ?? [];
        $signosDocumento = [
            ['PA', 'Presión arterial', $examen['presion_arterial'] ?? null, 'mmHg'],
            ['FC', 'Frecuencia cardíaca', $examen['frecuencia_cardiaca'] ?? null, 'lpm'],
            ['FR', 'Frecuencia respiratoria', $examen['frecuencia_respiratoria'] ?? null, 'rpm'],
            ['Temp.', 'Temperatura', $examen['temperatura'] ?? null, '°C'],
            ['SpO₂', 'Saturación de oxígeno', $examen['saturacion_oxigeno'] ?? null, '%'],
            ['Talla', 'Talla', $examen['talla'] ?? null, 'cm'],
            ['Peso', 'Peso', \App\Services\ExamenFisico::pesoEnLibras($examen), 'lb'],
        ];
        $regionesExamen = array_filter(['Cabeza' => $examen['cabeza'] ?? null, 'Tronco' => $examen['tronco'] ?? null, 'Extremidades' => $examen['extremidades'] ?? null], fn ($valor) => filled($valor));
    } elseif ($tipo === 'nota' && $nota->signosVitales) {
        $medida = $nota->signosVitales;
        $signosDocumento = [
            ['PA', 'Presión arterial', $medida->presion_sistolica ? $medida->presion_sistolica.'/'.$medida->presion_diastolica : null, 'mmHg'],
            ['FC', 'Frecuencia cardíaca', $medida->frecuencia_cardiaca, 'lpm'],
            ['FR', 'Frecuencia respiratoria', $medida->frecuencia_respiratoria, 'rpm'],
            ['Temp.', 'Temperatura', $medida->temperatura, '°C'],
            ['SpO₂', 'Saturación de oxígeno', $medida->saturacion_oxigeno, '%'],
        ];
    }
    $signosDocumento = array_values(array_filter($signosDocumento, fn ($signo) => filled($signo[2])));
@endphp

<article class="doc-clinico">
    <header class="doc-cabecera doc-tono-{{ $tonoDocumento }}">
        <span class="doc-icono"><i class="bi {{ $iconoDocumento }}" aria-hidden="true"></i></span>
        <div class="doc-paciente">
            <p class="doc-paciente-nombre">{{ $nombrePaciente }}</p>
            <p class="doc-paciente-datos">{{ implode(' · ', $datosPaciente) }}</p>
            @if(filled($paciente->alergias) && in_array($tipo, ['valoracion', 'evolucion', 'nota', 'alta'], true))
            <p class="doc-alergia"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Alergias: {{ $paciente->alergias }}</p>
            @endif
        </div>
        <span class="doc-pastilla {{ $claseEstadoIngreso }}">{{ $estadoIngreso }}</span>
    </header>

    @if($tipo === 'ingreso')
        @php
            $documentoFirmado = isset($documentosFirmados) ? $documentosFirmados->get($ingreso->getKey()) : null;
        @endphp
        <dl class="doc-meta">
            <div><dt><i class="bi bi-calendar-event" aria-hidden="true"></i> {{ $emergenciaDocumento ? 'Llegada' : 'Fecha de ingreso' }}</dt><dd>{{ $ingreso->fecha_ingreso->format('d/m/Y') }} <span>{{ $ingreso->fecha_ingreso->format('H:i') }}</span></dd></div>
            <div><dt><i class="bi bi-person-badge" aria-hidden="true"></i> Registrado por</dt><dd>{{ $nombrePersona($ingreso->personalRegistra) }}</dd></div>
            <div><dt><i class="bi bi-hospital" aria-hidden="true"></i> Tipo de atención</dt><dd>{{ $ingreso->nombreTipoAtencion() }}</dd></div>
        </dl>

        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-chat-left-text" aria-hidden="true"></i> {{ $emergenciaDocumento ? 'Motivo de consulta' : 'Motivo de ingreso' }}</h6>
            <p class="doc-texto">{{ $ingreso->motivo_ingreso ?: 'Sin registro' }}</p>
        </section>

        @unless($emergenciaDocumento)
        <div class="doc-columnas">
            <section class="doc-seccion">
                <h6 class="doc-seccion-titulo"><i class="bi bi-pen" aria-hidden="true"></i> Documento de ingreso</h6>
                @if($ingreso->documento_firmado)
                <p class="mb-0"><span class="doc-pastilla doc-pastilla-exito"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Firmado</span>
                    @if($documentoFirmado)<a class="ms-2" href="{{ route('archivos.show', $documentoFirmado) }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Ver documento</a>@endif</p>
                @else
                <p class="mb-0"><span class="doc-pastilla doc-pastilla-alerta"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Pendiente de firma</span></p>
                @endif
            </section>
            <section class="doc-seccion">
                <h6 class="doc-seccion-titulo"><i class="bi bi-door-open" aria-hidden="true"></i> Habitación</h6>
                @include('expedientes.partials.habitaciones', ['ingreso' => $ingreso])
            </section>
        </div>
        @endunless

    @elseif($tipo === 'nota')
        <dl class="doc-meta">
            <div><dt><i class="bi bi-calendar-event" aria-hidden="true"></i> Fecha y hora</dt><dd>{{ $nota->fecha_hora->format('d/m/Y') }} <span>{{ $nota->fecha_hora->format('H:i') }}</span></dd></div>
            <div><dt><i class="bi bi-clock" aria-hidden="true"></i> Turno</dt><dd>{{ $nota->turno() }}</dd></div>
            <div><dt><i class="bi bi-person-heart" aria-hidden="true"></i> Enfermería</dt><dd>{{ $nombrePersona($nota->personal) }}</dd></div>
            <div><dt><i class="bi bi-activity" aria-hidden="true"></i> Estado del paciente</dt><dd>
                @if($nota->estado_paciente)
                <span class="doc-pastilla {{ ['Mejoró' => 'doc-pastilla-exito', 'Empeoró' => 'doc-pastilla-peligro'][$nota->estado_paciente] ?? 'doc-pastilla-neutra' }}">{{ $nota->estado_paciente }}</span>
                @else Sin registro @endif
            </dd></div>
        </dl>

        @if($nota->aviso_medico)
        <div class="doc-aviso"><i class="bi bi-telephone-forward-fill" aria-hidden="true"></i><div><strong>Se avisó al médico</strong>@if($nota->medicoAvisado)<span class="d-block">{{ $nombrePersona($nota->medicoAvisado) }}</span>@endif</div></div>
        @endif

        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Signos vitales</h6>
            @include('expedientes.partials.doc-signos', ['signos' => $signosDocumento])
        </section>

        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-list-check" aria-hidden="true"></i> Actividades realizadas</h6>
            @if($nota->listaActividades())
            <ul class="doc-chips">
                @foreach($nota->listaActividades() as $actividad)<li><i class="bi bi-check2" aria-hidden="true"></i> {{ $actividad }}</li>@endforeach
            </ul>
            @else
            <p class="doc-vacio">No se marcaron actividades.</p>
            @endif
        </section>

        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-journal-text" aria-hidden="true"></i> Nota de enfermería</h6>
            @if(filled($nota->observaciones))<p class="doc-texto">{{ $nota->observaciones }}</p>@else<p class="doc-vacio">Sin nota escrita.</p>@endif
        </section>

    @elseif($tipo === 'alta')
        @php
            $alta = $ingreso->altaMedica;
            $diasEstancia = max(1, (int) ceil($ingreso->fecha_ingreso->diffInHours($alta->fecha_hora) / 24));
        @endphp
        <dl class="doc-meta">
            <div><dt><i class="bi bi-calendar-check" aria-hidden="true"></i> {{ $emergenciaDocumento ? 'Salida' : 'Fecha de egreso' }}</dt><dd>{{ $alta->fecha_hora->format('d/m/Y') }} <span>{{ $alta->fecha_hora->format('H:i') }}</span></dd></div>
            <div><dt><i class="bi bi-person-badge" aria-hidden="true"></i> Médico responsable</dt><dd>{{ $nombrePersona($alta->medico) }}</dd></div>
            @unless($emergenciaDocumento)<div><dt><i class="bi bi-hourglass" aria-hidden="true"></i> Estancia</dt><dd>{{ $diasEstancia }} {{ $diasEstancia === 1 ? 'día' : 'días' }}</dd></div>@endunless
        </dl>

        <div class="doc-diagnostico">
            <span class="doc-diagnostico-etiqueta">{{ $emergenciaDocumento ? 'Diagnóstico' : 'Diagnóstico final' }}</span>
            <p class="doc-texto mb-0">{{ $alta->diagnostico_final }}</p>
        </div>

        @if($alta->observaciones)
        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-chat-square-dots" aria-hidden="true"></i> Observaciones</h6>
            <p class="doc-texto">{{ $alta->observaciones }}</p>
        </section>
        @endif

        @unless($emergenciaDocumento)
        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-person-check" aria-hidden="true"></i> Condición de egreso</h6>
            <p class="doc-texto">{{ $alta->condicion_egreso ?: 'Sin registro' }}</p>
        </section>
        @endunless

        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-capsule" aria-hidden="true"></i> Medicamentos para casa</h6>
            @if($alta->medicamentos->isNotEmpty())
            <ul class="doc-lista-medicamentos">
                @foreach($alta->medicamentos as $medicamento)
                <li>
                    <strong>{{ $medicamento->medicamento }}</strong>
                    <span class="doc-chips doc-chips-compactos">
                        <span>{{ $medicamento->dosis }}</span>
                        @if($medicamento->via_administracion)<span>{{ $medicamento->via_administracion }}</span>@endif
                        <span>{{ $medicamento->frecuencia }}</span>
                        @if($medicamento->duracion)<span>{{ $medicamento->duracion }}</span>@endif
                    </span>
                </li>
                @endforeach
            </ul>
            @else
            <p class="doc-vacio">No se indicaron medicamentos para casa.</p>
            @endif
        </section>

        @if($alta->indicaciones_alta)
        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-house-heart" aria-hidden="true"></i> {{ $emergenciaDocumento ? 'Otras indicaciones' : 'Indicaciones de alta' }}</h6>
            <p class="doc-texto">{{ $alta->indicaciones_alta }}</p>
        </section>
        @endif


    @else
        @php
            $conOrdenes = $tipo === 'evolucion' || filled($registro->ordenes_medicas);
        @endphp
        <div class="doc-cuerpo {{ $conOrdenes ? 'doc-cuerpo-con-lateral' : '' }}">
            <div class="doc-principal">
                <dl class="doc-meta">
                    <div><dt><i class="bi bi-calendar-event" aria-hidden="true"></i> Fecha y hora</dt><dd>{{ $registro->fecha_hora->format('d/m/Y') }} <span>{{ $registro->fecha_hora->format('H:i') }}</span></dd></div>
                    <div><dt><i class="bi bi-person-badge" aria-hidden="true"></i> Médico responsable</dt><dd>{{ $nombrePersona($registro->personal) }}</dd></div>
                    @if($tipo === 'evolucion')
                    <div><dt><i class="bi bi-activity" aria-hidden="true"></i> Evolución</dt><dd>
                        @if($registro->estado_mejoria)<span class="doc-pastilla {{ ['Mejoró' => 'doc-pastilla-exito', 'Empeoró' => 'doc-pastilla-peligro'][$registro->estado_mejoria] ?? 'doc-pastilla-neutra' }}">{{ $registro->estado_mejoria }}</span>@else Sin registro @endif
                    </dd></div>
                    @endif
                </dl>

                @if($tipo === 'valoracion')
                <section class="doc-seccion">
                    <h6 class="doc-seccion-titulo"><i class="bi bi-chat-left-text" aria-hidden="true"></i> Motivo de consulta</h6>
                    <p class="doc-texto">{{ $registro->motivo_atencion ?: 'Sin registro' }}</p>
                </section>
                @endif

                <section class="doc-seccion">
                    <h6 class="doc-seccion-titulo"><i class="bi bi-thermometer-half" aria-hidden="true"></i> Síntomas</h6>
                    @if(filled($registro->sintomas))<p class="doc-texto">{{ $registro->sintomas }}</p>@else<p class="doc-vacio">Sin síntomas registrados.</p>@endif
                </section>

                <section class="doc-seccion">
                    <h6 class="doc-seccion-titulo"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Signos vitales</h6>
                    @include('expedientes.partials.doc-signos', ['signos' => $signosDocumento])
                </section>

                <section class="doc-seccion">
                    <h6 class="doc-seccion-titulo"><i class="bi bi-person-bounding-box" aria-hidden="true"></i> Examen físico</h6>
                    @if($regionesExamen)
                    <div class="doc-regiones">
                        @foreach($regionesExamen as $region => $hallazgo)
                        <div><span>{{ $region }}</span><p class="doc-texto mb-0">{{ $hallazgo }}</p></div>
                        @endforeach
                    </div>
                    @endif
                    @if(filled($registro->evaluacion_medica))<p class="doc-texto {{ $regionesExamen ? 'mt-2' : '' }}">{{ $registro->evaluacion_medica }}</p>@endif
                    @if(! $regionesExamen && blank($registro->evaluacion_medica))<p class="doc-vacio">No se registró examen por regiones.</p>@endif
                </section>

                <div class="doc-diagnostico">
                    <span class="doc-diagnostico-etiqueta">{{ $tipo === 'valoracion' ? 'Diagnóstico' : 'Diagnóstico actual' }}</span>
                    <p class="doc-texto mb-0">{{ $registro->diagnostico ?: 'Sin registro' }}</p>
                </div>

                @if($tipo === 'valoracion' && filled($registro->indicaciones))
                <section class="doc-seccion">
                    <h6 class="doc-seccion-titulo"><i class="bi bi-bandaid" aria-hidden="true"></i> {{ $emergenciaDocumento ? 'Tratamiento aplicado en el sanatorio' : 'Tratamiento' }}</h6>
                    <p class="doc-texto">{{ $registro->indicaciones }}</p>
                </section>
                @endif
                @if(filled($registro->observaciones))
                <section class="doc-seccion">
                    <h6 class="doc-seccion-titulo"><i class="bi bi-chat-square-dots" aria-hidden="true"></i> Observaciones</h6>
                    <p class="doc-texto">{{ $registro->observaciones }}</p>
                </section>
                @endif
            </div>

            @if($conOrdenes)
            <aside class="doc-lateral">
                <h6 class="doc-seccion-titulo"><i class="bi bi-clipboard2-check" aria-hidden="true"></i> Órdenes médicas</h6>
                @if($registro->ordenes_medicas)
                    @include('expedientes.partials.doc-ordenes', ['ordenes' => $registro->ordenes_medicas])
                @else
                    <p class="doc-texto mb-0">{{ $registro->indicaciones ?: 'Sin órdenes registradas.' }}</p>
                @endif
            </aside>
            @endif
        </div>
    @endif
</article>

<?php
    $tipoHojaArchivo = match($tipo) { 'ingreso'=>'Hoja de ingreso', 'valoracion'=>($ingreso->esConsultaExterna()?'Consulta externa':'Valoración inicial'), 'evolucion'=>'Evolución clínica', 'nota'=>'Nota de enfermería', 'alta'=>'Alta médica', default=>'Otro documento' };
    $referenciaArchivo = match($tipo) { 'ingreso'=>$ingreso->getKey(), 'valoracion','evolucion'=>$registro->getKey(), 'nota'=>$nota->getKey(), 'alta'=>$ingreso->altaMedica->getKey(), default=>null };
?>
@include('archivos.adjuntos',['tipoHojaArchivo'=>$tipoHojaArchivo,'referenciaArchivo'=>$referenciaArchivo,'permitirSubir'=>false])
