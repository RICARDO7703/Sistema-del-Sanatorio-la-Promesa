{{--
    Campos de órdenes médicas (dieta, solución, medicamentos e indicaciones).
    Se usan en la evolución y, de forma opcional, en la valoración inicial de pacientes internados.
    Variables: $prefijo (para los id), $ordenes (valores), $idCampos (id opcional del fieldset), $deshabilitado (opcional).
--}}
@php
    $dietaGuardada = $ordenes['dieta'] ?? '';
    $dietaEsOtra = $dietaGuardada === \App\Services\OrdenesMedicas::DIETA_OTRA || ($dietaGuardada !== '' && ! in_array($dietaGuardada, \App\Services\OrdenesMedicas::DIETAS, true));
    $textoDietaOtra = array_key_exists('dieta_otra', $ordenes) ? ($ordenes['dieta_otra'] ?? '') : ($dietaEsOtra ? $dietaGuardada : '');
@endphp
@php
    $registroCancelado = $registroOrdenesCanceladas ?? null;
    $cancelacionEditor = $registroCancelado ? \App\Services\OrdenesMedicas::cancelacion($registroCancelado->ordenes_medicas) : null;
    $puedeReactivarEditor = $cancelacionEditor && \App\Support\EdicionClinica::puedeEditar($registroCancelado)
        && app(\App\Support\Permisos::class)->puede('Evolución clínica', 'Editar')
        && \App\Services\OrdenesMedicas::vigentes($registroCancelado->ingreso)?->getKey() === $registroCancelado->getKey();
    $reactivarEditor = $puedeReactivarEditor && old('_formulario') === ($formulario ?? null) && old('reactivar_ordenes');
@endphp
@if($cancelacionEditor)
<div data-editor-ordenes-canceladas>
    @if($puedeReactivarEditor)
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="reactivar_ordenes" value="1" id="{{ $prefijo }}-reactivar" data-habilitar-ordenes-canceladas @checked($reactivarEditor)>
        <label class="form-check-label fw-semibold" for="{{ $prefijo }}-reactivar"><i class="bi bi-pencil-square" aria-hidden="true"></i> Reactivar y editar estas órdenes</label>
        <small class="d-block text-muted">Se reactivarán únicamente al confirmar los cambios.</small>
    </div>
    @else
    <p class="text-muted">Estas órdenes no se pueden reactivar desde este registro.</p>
    @endif
    <div data-resumen-canceladas @if($reactivarEditor) hidden @endif>
        @include('atencion.partials.ordenes-resumen', ['resumenOrdenes' => $registroCancelado->ordenes_medicas])
    </div>
    <fieldset class="border-0 p-0 m-0" data-edicion-canceladas @disabled(! $reactivarEditor) @if(! $reactivarEditor) hidden @endif>
@endif
<fieldset data-ordenes @isset($idCampos) id="{{ $idCampos }}" @endisset @disabled($deshabilitado ?? false)>
    <label class="form-label" for="{{ $prefijo }}-dieta">Dieta <span class="text-danger">*</span></label>
    <select id="{{ $prefijo }}-dieta" data-orden-dieta class="form-select mb-2" name="ordenes[dieta]" data-mensaje-requerido="Seleccione la dieta." required>
        <option value="">Seleccione</option>
        @foreach(\App\Services\OrdenesMedicas::DIETAS as $dieta)<option @selected(! $dietaEsOtra && $dietaGuardada === $dieta)>{{ $dieta }}</option>@endforeach
        <option value="{{ \App\Services\OrdenesMedicas::DIETA_OTRA }}" @selected($dietaEsOtra)>Otra (escribirla)</option>
    </select>
    <div class="mb-3" data-dieta-otra @unless($dietaEsOtra) hidden @endunless>
        <input type="text" class="form-control" name="ordenes[dieta_otra]" value="{{ $textoDietaOtra }}" maxlength="100" placeholder="Ej. Hiposódica, diabética" aria-label="Otra dieta" data-mensaje-requerido="Escriba la dieta indicada." @disabled(! $dietaEsOtra) required>
    </div>
    <h6>Solución</h6><div data-filas="soluciones">
    @foreach($ordenes['soluciones'] ?? [] as $indice=>$fila)@include('atencion.partials.fila-solucion')@endforeach
    </div><button type="button" class="btn btn-outline-primary btn-sm mb-3" data-agregar="soluciones">Agregar solución</button>
    <h6>Medicamentos</h6><div data-filas="medicamentos">
    @foreach($ordenes['medicamentos'] ?? [] as $indice=>$fila)@include('atencion.partials.fila-medicamento')@endforeach
    </div><button type="button" class="btn btn-outline-primary btn-sm mb-3" data-agregar="medicamentos">Agregar medicamento</button>
    <div class="mb-3"><label class="form-label" for="{{ $prefijo }}-indicaciones">Otras indicaciones para enfermería</label><textarea id="{{ $prefijo }}-indicaciones" data-orden-indicaciones class="form-control" name="ordenes[indicaciones]" maxlength="2000" rows="4">{{ $ordenes['indicaciones'] ?? '' }}</textarea></div>
    <div><label class="form-label" for="{{ $prefijo }}-plan-educacional">Plan educacional <span class="text-muted fw-normal">(opcional)</span></label><textarea id="{{ $prefijo }}-plan-educacional" data-orden-plan class="form-control" name="ordenes[plan_educacional]" maxlength="2000" rows="3" placeholder="Orientación para el paciente y su familia. Ej. cuidados de la herida, signos de alarma">{{ $ordenes['plan_educacional'] ?? '' }}</textarea></div>
    <template data-plantilla="soluciones">@include('atencion.partials.fila-solucion', ['indice'=>'__INDEX__', 'fila'=>[]])</template>
    <template data-plantilla="medicamentos">@include('atencion.partials.fila-medicamento', ['indice'=>'__INDEX__', 'fila'=>[]])</template>
</fieldset>

@if($cancelacionEditor)
    </fieldset>
</div>
@endif
