{{--
    Documento clínico del expediente (hoja de ingreso, valoración, evolución, nota de enfermería, control de recuperación o alta).
    Variables: $tipo, $ingreso, $paciente y, según el tipo, $registro, $nota o $control.
--}}
@php
    $emergenciaDocumento = $ingreso->esConsultaExterna();
    $nombrePersona = fn ($persona) => trim(($persona?->nombres ?? '').' '.($persona?->apellidos ?? '')) ?: 'Sin registro';
    $nombrePaciente = $paciente->estado_identificacion === 'Identificado' ? trim($paciente->nombres.' '.$paciente->apellidos) : 'Paciente no identificado';
    $datosPaciente = array_filter([
        $paciente->numero_expediente,
        $paciente->fecha_nacimiento ? $paciente->fecha_nacimiento->age.' años' : null,
        $paciente->sexo,
        $emergenciaDocumento ? ($tipo === 'valoracion' ? 'Atención del ' : 'Consulta externa del ').$ingreso->fecha_ingreso->format('d/m/Y') : 'Ingreso #'.$ingreso->getKey(),
    ]);
    [$iconoDocumento, $tonoDocumento] = match ($tipo) {
        'ingreso' => ['bi-file-earmark-text', 'primario'],
        'valoracion' => ['bi-clipboard2-pulse', 'primario'],
        'evolucion' => ['bi-graph-up-arrow', 'exito'],
        'nota' => ['bi-journal-medical', 'info'],
        'control' => ['bi-heart-pulse', 'info'],
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
    } elseif (($tipo === 'nota' && $nota->signosVitales) || $tipo === 'control') {
        $medida = $tipo === 'control' ? $control : $nota->signosVitales;
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
@php
    [$tipoPdf, $idPdf] = match ($tipo) {
        'ingreso' => ['atenciones', $ingreso->getKey()],
        'valoracion' => [$emergenciaDocumento ? 'consultas' : 'valoraciones', $registro->getKey()],
        'evolucion' => ['evoluciones', $registro->getKey()],
        'nota' => ['enfermeria', $nota->getKey()],
        'control' => ['signos', $control->getKey()],
        'alta' => ['altas', $ingreso->altaMedica?->getKey()],
        default => [null, null],
    };
@endphp
@if($idPdf)
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-primary" href="{{ route('reportes.documento', [$tipoPdf, $idPdf, 'descargar'=>1]) }}"><i class="bi bi-download"></i> Descargar PDF</a>
    <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('reportes.documento', [$tipoPdf, $idPdf]) }}"><i class="bi bi-printer"></i> Imprimir</a>
    @if(in_array($tipo, ['valoracion','evolucion']) && !empty($registro->ordenes_medicas))<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('reportes.documento', ['ordenes', $registro->getKey()]) }}"><i class="bi bi-file-earmark-medical"></i> PDF de órdenes médicas</a>@endif
</div>
@endif

<article class="doc-clinico">
    <header class="doc-cabecera doc-tono-{{ $tonoDocumento }}">
        <span class="doc-icono"><i class="bi {{ $iconoDocumento }}" aria-hidden="true"></i></span>
        <div class="doc-paciente">
            <p class="doc-paciente-nombre">{{ $nombrePaciente }}</p>
            <p class="doc-paciente-datos">{{ implode(' · ', $datosPaciente) }}</p>
            @if(filled($paciente->alergias) && in_array($tipo, ['valoracion', 'evolucion', 'nota', 'control', 'alta'], true))
            <p class="doc-alergia"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Alergias: {{ $paciente->alergias }}</p>
            @endif
        </div>
        <span class="doc-pastilla {{ $claseEstadoIngreso }}">{{ $estadoIngreso }}</span>
    </header>

    @if($tipo === 'ingreso')
        @if(\App\Support\EdicionClinica::puedeEditar($ingreso) && app(\App\Support\Permisos::class)->puede('Ingresos','Editar'))
        <form hidden id="editor-ingreso-{{ $ingreso->getKey() }}" data-edicion-ingreso-enlinea class="border rounded-3 p-3 mb-3" method="POST" action="{{ route('ingresos.actualizar',$ingreso) }}">@csrf @method('PUT')
            <h6><i class="bi bi-pencil"></i> Editar fecha o motivo</h6>
            <label class="form-label d-block">Fecha y hora de ingreso<input class="form-control" type="datetime-local" name="fecha_ingreso" value="{{ $ingreso->fecha_ingreso->format('Y-m-d\TH:i') }}" max="{{ now('America/Guatemala')->format('Y-m-d\TH:i') }}" required></label>
            <label class="form-label d-block">Motivo de ingreso<textarea class="form-control" name="motivo_ingreso" maxlength="255">{{ $ingreso->motivo_ingreso }}</textarea></label>
            <button hidden type="submit" data-submit-ingreso></button><button class="btn btn-outline-secondary ms-2" type="button" data-editar-ingreso-enlinea="editor-ingreso-{{ $ingreso->getKey() }}">Cancelar</button>
        </form>@endif
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
        <div class="d-flex flex-wrap align-items-center gap-3 border-top border-bottom py-3 mb-3"><span class="text-primary"><i class="bi bi-door-open"></i></span> @include('expedientes.partials.habitaciones', ['ingreso' => $ingreso,'claseHabitaciones' => 'mb-0'])</div>
        <div>
            <section class="doc-seccion">
                <h6 class="doc-seccion-titulo"><i class="bi bi-pen" aria-hidden="true"></i> Documento de ingreso</h6>
                @if($ingreso->documento_firmado)
                <p class="mb-0"><span class="doc-pastilla doc-pastilla-exito"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Firmado</span>
                    @if($documentoFirmado)<a class="ms-2" href="{{ route('archivos.contenido',$documentoFirmado) }}" data-abrir-documento data-tipo-archivo="{{ $documentoFirmado->tipo_archivo }}" data-titulo-archivo="{{ $documentoFirmado->titulo }}" data-nombre-archivo="{{ $documentoFirmado->nombre_archivo }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Ver documento</a>@endif</p>
                @else
                <p class="mb-0"><span class="doc-pastilla doc-pastilla-alerta"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Pendiente de firma</span></p>
                @if(\App\Support\EdicionClinica::puedeEditar($ingreso) && app(\App\Support\Permisos::class)->puede('Documentos clínicos', 'Ingresar'))
                @php
                    $formularioFirma = 'documento-firmado-'.$ingreso->getKey();
                    $errorFirma = old('_formulario') === $formularioFirma ? $errors->first('documento_archivo') : null;
                @endphp
                {{-- Cuando el paciente o su familiar firma después, aquí se sube solo ese documento. --}}
                <form data-firma-en-linea class="mt-3" method="POST" action="{{ route('ingresos.documento-firmado', $ingreso) }}" enctype="multipart/form-data" novalidate data-validar-en-linea data-validar-al-enviar>
                    @csrf
                    <input type="hidden" name="_formulario" value="{{ $formularioFirma }}">
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="{{ $formularioFirma }}-casilla" name="documento_firmado" value="1" data-archivo-firmado="{{ $formularioFirma }}-carga" @checked(old('_formulario')===$formularioFirma)><label class="form-check-label" for="{{ $formularioFirma }}-casilla">Documento de ingreso firmado</label></div>
                    <div id="{{ $formularioFirma }}-carga" @unless(old('_formulario')===$formularioFirma) hidden @endunless>
                    <label class="form-label" for="{{ $formularioFirma }}">¿Ya lo firmó? Suba el documento (PDF o imagen)</label>
                    <input id="{{ $formularioFirma }}" type="file" name="documento_archivo" class="form-control @if($errorFirma) is-invalid @endif" accept="application/pdf,image/jpeg,image/png,image/webp" @if(old('_formulario')===$formularioFirma) required @else disabled @endif data-mensaje-requerido="Seleccione el documento firmado.">
                    @if($errorFirma)<div class="invalid-feedback d-block">{{ $errorFirma }}</div>@endif
                    <small class="text-muted d-block mb-2">Máximo 10 MB. Quedará en «Archivos médicos» y la hoja se marcará como firmada.</small>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-upload" aria-hidden="true"></i> Confirmar cambios</button>
                    </div>
                </form>
                @endif
                @endif
            </section>

        </div>
        @endunless

    @elseif($tipo === 'nota')
        @include('atencion.partials.editar-nota',['nota'=>$nota])
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

        @if($nota->signosVitales?->cirugia)
        <div class="doc-aviso doc-aviso-info"><i class="bi bi-hospital-fill" aria-hidden="true"></i><div><strong>Control de recuperación</strong><span class="d-block">{{ $nota->signosVitales->cirugia->descripcionCorta() }}</span></div></div>
        @endif

        @if($nota->aviso_medico)
        <div class="doc-aviso"><i class="bi bi-telephone-forward-fill" aria-hidden="true"></i><div><strong>Se avisó al médico</strong>@if($nota->medicoAvisado)<span class="d-block">{{ $nombrePersona($nota->medicoAvisado) }}</span>@endif</div></div>
        @endif

        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Signos vitales</h6>
            @include('expedientes.partials.doc-signos', ['signos' => $signosDocumento])
        </section>

        @if($nota->ordenes->isNotEmpty())
        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-journal-medical" aria-hidden="true"></i> Órdenes médicas</h6>
            <ul class="doc-ordenes-aplicadas">
                @foreach($nota->ordenes as $ordenNota)
                <li><i class="bi {{ $ordenNota->aplicada ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}" aria-hidden="true"></i><div><strong>{{ $ordenNota->resultado() }}:</strong> {{ $ordenNota->descripcion }}@if(! $ordenNota->aplicada && filled($ordenNota->motivo))<span class="d-block text-muted">Motivo: {{ $ordenNota->motivo }}</span>@endif</div></li>
                @endforeach
            </ul>
        </section>
        @endif

        <section class="doc-seccion">
            @php
                $textoNota = (string) $nota->observaciones;
                preg_match_all('/^Traslado de habitación:[^\r\n]+/mu', $textoNota, $trasladosNota);
                $textoNota = trim(preg_replace('/^Traslado de habitación:[^\r\n]+[\r\n]*/mu', '', $textoNota));
            @endphp
            @if(!empty($trasladosNota[0]))
            <h6 class="doc-seccion-titulo"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Traslado de habitación</h6>
            @foreach($trasladosNota[0] as $trasladoNota)<p class="doc-texto mb-3">{{ trim(str_replace('Traslado de habitación:', '', $trasladoNota)) }}</p>@endforeach
            @endif
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
            @if(filled($textoNota))<p class="doc-texto">{{ $textoNota }}</p>@else<p class="doc-vacio">Sin nota escrita.</p>@endif
        </section>

    @elseif($tipo === 'control')
        <dl class="doc-meta">
            <div><dt><i class="bi bi-calendar-event" aria-hidden="true"></i> Fecha y hora</dt><dd>{{ $control->fecha_hora->format('d/m/Y') }} <span>{{ $control->fecha_hora->format('H:i') }}</span></dd></div>
            <div><dt><i class="bi bi-person-heart" aria-hidden="true"></i> Registró</dt><dd>{{ $nombrePersona($control->personal) }}</dd></div>
        </dl>
        @if($control->cirugia)
        <div class="doc-aviso doc-aviso-info"><i class="bi bi-hospital-fill" aria-hidden="true"></i><div><strong>Control de recuperación</strong><span class="d-block">{{ $control->cirugia->descripcionCorta() }}</span></div></div>
        @endif
        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Signos vitales</h6>
            @include('expedientes.partials.doc-signos', ['signos' => $signosDocumento])
        </section>
        <section class="doc-seccion">
            <h6 class="doc-seccion-titulo"><i class="bi bi-chat-left-text" aria-hidden="true"></i> Observaciones</h6>
            @if(filled($control->observaciones))<p class="doc-texto">{{ $control->observaciones }}</p>@else<p class="doc-vacio">Sin observaciones.</p>@endif
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
                    @php
                        $idOrdenes = $registro->getKey();
                        $ordenesCanceladas = \App\Services\OrdenesMedicas::cancelacion($registro->ordenes_medicas) !== null;
                        // Solo el médico que indicó las órdenes, con la atención abierta.
                        $autorOrdenes = $ingreso->estado === 'Activo' && $registro->estado === 'Activo'
                            && (int) $registro->id_personal === (int) \Illuminate\Support\Facades\DB::table('tbl_usuario')->where('id_usuario', session('id_usuario'))->value('id_personal')
                            && app(\App\Support\Permisos::class)->puede('Evolución clínica', 'Editar');
                        // Reactivar tiene sentido solo si no hay órdenes más recientes que las reemplacen.
                        $sonLasUltimas = $ordenesCanceladas && \App\Services\OrdenesMedicas::vigentes($ingreso)?->getKey() === $idOrdenes;
                        $puedeEditarHoja = \App\Support\EdicionClinica::puedeEditar($registro->setRelation('ingreso', $ingreso));
                    @endphp
                    @if($autorOrdenes && ! $ordenesCanceladas)
                    {{-- Los cambios se confirman con el botón del pie de la ventana, igual que las demás ediciones. --}}
                    <form class="mt-3 border rounded p-3" id="ordenes-doc-{{ $idOrdenes }}" method="POST" action="{{ route('evolucion.cancelar-ordenes', $registro) }}" data-ordenes-documento data-validar-en-linea data-validar-al-enviar>
                        @csrf
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="cancelar-ordenes-{{ $idOrdenes }}" data-cambio-ordenes><label class="form-check-label text-danger fw-semibold" for="cancelar-ordenes-{{ $idOrdenes }}"><i class="bi bi-x-octagon" aria-hidden="true"></i> Cancelar estas órdenes</label></div>
                        <fieldset class="border-0 p-0 m-0 mt-2" data-detalle-cambio-ordenes hidden disabled>
                            <label class="form-label" for="motivo-cancelar-{{ $idOrdenes }}">¿Por qué se cancelan?</label>
                            <textarea class="form-control" id="motivo-cancelar-{{ $idOrdenes }}" name="motivo_cancelacion" rows="2" maxlength="255" required data-mensaje-requerido="Escriba por qué se cancelan las órdenes." placeholder="Ej. Se suspende el tratamiento por reacción al medicamento"></textarea>
                            <small class="text-muted d-block mt-1">Enfermería dejará de verlas como vigentes. La hoja conserva lo que se ordenó.</small>
                        </fieldset>
                    </form>
                    @elseif($autorOrdenes && $ordenesCanceladas && ! $sonLasUltimas)
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Hay órdenes más recientes; estas ya no se pueden reactivar.</p>
                    @endif
                @else
                    <p class="doc-texto mb-0">{{ $registro->indicaciones ?: 'Sin órdenes registradas.' }}</p>
                @endif
            </aside>
            @endif
        </div>
    @endif
</article>

<?php
    $tipoHojaArchivo = match($tipo) { 'ingreso'=>'Hoja de ingreso', 'valoracion'=>($ingreso->esConsultaExterna()?'Consulta externa':'Valoración inicial'), 'evolucion'=>'Evolución clínica', 'nota'=>'Nota de enfermería', 'control'=>'Cirugía', 'alta'=>'Alta médica', default=>'Otro documento' };
    $referenciaArchivo = match($tipo) { 'ingreso'=>$ingreso->getKey(), 'valoracion','evolucion'=>$registro->getKey(), 'nota'=>$nota->getKey(), 'control'=>$control->id_cirugia, 'alta'=>$ingreso->altaMedica->getKey(), default=>null };
?>
@include('archivos.adjuntos',['tipoHojaArchivo'=>$tipoHojaArchivo,'referenciaArchivo'=>$referenciaArchivo,'permitirSubir'=>false])
@push('modales')
@include('atencion.partials.ordenes-scripts')
@endpush
