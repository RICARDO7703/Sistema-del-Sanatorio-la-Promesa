@php
    $tipoHojaArchivo = $tipoHojaArchivo ?? 'Otro documento';
    $referenciaArchivo = $referenciaArchivo ?? null;
    $tablaHojaArchivo = \App\Http\Controllers\ArchivoClinicoController::HOJAS[$tipoHojaArchivo] ?? null;
    $archivosHoja = $ingreso->documentosClinicos->where('estado','Activo')->where('tabla_origen',$tablaHojaArchivo)->where('id_registro_origen',$referenciaArchivo);
    // En las ventanas de lectura del expediente no se suben archivos: solo se muestran los que se adjuntaron al llenar la hoja.
    $permitirSubir = $permitirSubir ?? true;
@endphp
@if($permitirSubir || $archivosHoja->isNotEmpty())
<div class="border rounded p-3 my-3">
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2"><strong><i class="bi bi-paperclip text-primary" aria-hidden="true"></i> Hoja en papel / archivos</strong>@if($permitirSubir)<a class="btn btn-outline-primary" href="{{ route('archivos.formulario',[$ingreso,'tipo'=>$tipoHojaArchivo,'referencia'=>$referenciaArchivo]) }}"><i class="bi bi-camera" aria-hidden="true"></i> Subir imagen o PDF</a>@endif</div>
@foreach($archivosHoja as $archivo)<a class="d-block mt-3" href="{{ route('archivos.show',$archivo) }}"><i class="bi bi-file-earmark-{{ $archivo->tipo_archivo === 'pdf' ? 'pdf' : 'image' }}" aria-hidden="true"></i> {{ $archivo->titulo }} · {{ $archivo->fecha_documento?->format('d/m/Y H:i') }} · Ver archivo</a>@endforeach
</div>
@endif
