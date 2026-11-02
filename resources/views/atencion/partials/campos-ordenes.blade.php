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
