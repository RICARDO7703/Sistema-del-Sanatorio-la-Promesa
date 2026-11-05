@php
    $tipoHojaArchivo = $tipoHojaArchivo ?? 'Otro documento';
    $referenciaArchivo = $referenciaArchivo ?? null;
    $tablaHojaArchivo = \App\Http\Controllers\ArchivoClinicoController::HOJAS[$tipoHojaArchivo] ?? null;
    $archivosHoja = $ingreso->documentosClinicos->where('estado','Activo')->where('tabla_origen',$tablaHojaArchivo)->where('id_registro_origen',$referenciaArchivo);
    // En las ventanas de lectura del expediente no se suben archivos: solo se muestran los que se adjuntaron al llenar la hoja.
    $puedeModificarAdjuntos = \App\Models\DocumentoClinico::puedeModificarHoja($ingreso,$tablaHojaArchivo,$referenciaArchivo,(int)session('id_usuario'));
    $permitirSubir = $puedeModificarAdjuntos && ($permitirSubir ?? true) && app(\App\Support\Permisos::class)->puede('Documentos clínicos', 'Ingresar');
    // El archivo toma la fecha de la hoja a la que se adjunta (nunca una hora futura), para quedar en su lugar del expediente.
    $ahoraArchivo = \Illuminate\Support\Carbon::parse(now('America/Guatemala')->format('Y-m-d H:i:s'));
    $fechaArchivo = isset($fechaHojaArchivo) && $fechaHojaArchivo && $fechaHojaArchivo->lte($ahoraArchivo) ? $fechaHojaArchivo : $ahoraArchivo;
    $claveAdjunto = 'adjunto-'.\Illuminate\Support\Str::slug($tipoHojaArchivo).'-'.$referenciaArchivo;
    $conErrorAdjunto = old('_formulario') === $claveAdjunto;
@endphp
@if($permitirSubir || $archivosHoja->isNotEmpty())
<div class="adjuntos-hoja border rounded p-3 my-3">
    <strong><i class="bi bi-paperclip text-primary" aria-hidden="true"></i> Hoja en papel / archivos</strong>
    @if(session('adjunto_subido') === $claveAdjunto)<p class="text-success small mb-0 mt-2" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Archivo agregado.</p>@endif
    @foreach($archivosHoja as $archivo)<a class="d-block mt-2" href="{{ route('archivos.contenido',$archivo) }}" data-abrir-documento data-tipo-archivo="{{ $archivo->tipo_archivo }}" data-titulo-archivo="{{ $archivo->titulo }}" data-nombre-archivo="{{ $archivo->nombre_archivo }}"><i class="bi bi-file-earmark-{{ $archivo->tipo_archivo === 'pdf' ? 'pdf' : 'image' }}" aria-hidden="true"></i> {{ $archivo->tipo_archivo === 'pdf' ? 'PDF' : 'Imagen' }} · {{ $archivo->fecha_documento?->format('d/m/Y H:i') }} · Ver archivo <i class="bi bi-eye small" aria-hidden="true"></i></a>
    @if($archivo->puedeEditar((int)session('id_usuario')) && app(\App\Support\Permisos::class)->puede('Documentos clínicos','Editar'))
    <form method="POST" action="{{ route('archivos.quitar',$archivo) }}" class="mt-2" onsubmit="return confirm('¿Quitar este archivo del expediente? La hoja clínica se conservará.');">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Quitar archivo</button></form>
    @endif
    @endforeach
    @if($permitirSubir)
    <form action="{{ route('archivos.guardar',$ingreso) }}" method="POST" enctype="multipart/form-data" class="mt-3" data-validar-en-linea data-validar-al-enviar>
        @csrf
        <input type="hidden" name="_formulario" value="{{ $claveAdjunto }}">
        <input type="hidden" name="en_linea" value="1">
        <input type="hidden" name="tipo" value="{{ $tipoHojaArchivo }}">
        @if($referenciaArchivo)<input type="hidden" name="referencia" value="{{ $referenciaArchivo }}">@endif
        <input type="hidden" name="fecha_documento" value="{{ $fechaArchivo->format('Y-m-d H:i:s') }}">
        <label class="form-label" for="{{ $claveAdjunto }}-archivo">{{ $archivosHoja->isEmpty() ? 'Subir una foto o PDF' : 'Agregar otra foto o PDF' }}</label>
        <div class="input-group has-validation">
            <input id="{{ $claveAdjunto }}-archivo" type="file" name="archivo" class="form-control @if($conErrorAdjunto && $errors->any()) is-invalid @endif" accept="image/jpeg,image/png,image/webp,application/pdf" data-mensaje-requerido="Seleccione una imagen o un PDF." required>
            <button class="btn btn-primary"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> Subir</button>
            @if($conErrorAdjunto && $errors->any())<div class="invalid-feedback">{{ $errors->first() }}</div>@endif
        </div>
        <small class="text-muted">JPG, PNG, WEBP o PDF, hasta 10 MB. Puede tomar la foto con el teléfono.</small>
    </form>
    @endif
</div>
@endif
