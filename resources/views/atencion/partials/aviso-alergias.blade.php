{{-- Aviso de alergias para quien indica medicamentos. Con $mostrarSinRegistro también avisa cuando no hay alergias anotadas. --}}
@if(filled($paciente->alergias))
<div class="alert alert-danger d-flex gap-2 aviso-alergias {{ $clase ?? '' }}" role="alert">
    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
    <div><strong>Alergias:</strong> <span class="text-break">{{ $paciente->alergias }}</span></div>
</div>
@elseif($mostrarSinRegistro ?? false)
<p class="text-muted d-flex gap-2 mb-0 aviso-alergias-vacio {{ $clase ?? '' }}"><i class="bi bi-info-circle" aria-hidden="true"></i> <span>Sin alergias registradas. Si el paciente tiene alguna, anótela en su ficha de paciente.</span></p>
@endif
