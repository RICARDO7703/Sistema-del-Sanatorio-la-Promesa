{{-- Signos vitales de un documento como fichas: sigla, valor y unidad. Variable: $signos = [[sigla, nombre, valor, unidad], ...] --}}
@if($signos)
<ul class="doc-signos" aria-label="Signos vitales">
    @foreach($signos as [$sigla, $nombre, $valor, $unidad])
    <li title="{{ $nombre }}">
        <span class="doc-signo-sigla">{{ $sigla }}</span>
        <span class="doc-signo-valor">{{ is_float($valor) ? rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.') : $valor }}<small>{{ $unidad }}</small></span>
        @if(in_array($sigla, ['PA', 'FC', 'FR', 'SpO₂'], true))<span class="doc-signo-nombre">{{ $nombre }}</span>@endif
    </li>
    @endforeach
</ul>
@else
<p class="doc-vacio">No se tomaron signos vitales.</p>
@endif
