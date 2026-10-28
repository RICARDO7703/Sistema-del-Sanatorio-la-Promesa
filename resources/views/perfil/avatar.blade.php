<span class="perfil-avatar {{ $claseAvatar ?? '' }}">
@if($personaAvatar?->foto_perfil)<img src="{{ route('personal.foto',['personaFoto'=>$personaAvatar->id_personal,'v'=>substr(sha1($personaAvatar->foto_perfil),0,10)]) }}" alt="Foto de {{ $personaAvatar->nombres }}" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><i class="bi bi-person-circle perfil-icono" aria-hidden="true" hidden></i>
@else<i class="bi bi-person-circle perfil-icono" aria-hidden="true"></i>@endif
</span>