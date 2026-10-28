<section class="col-12 mt-3" data-contactos-multiples>
    <h5>Contactos del paciente</h5>
    <p class="text-muted">Opcional. Puede agregar varios contactos. Para eliminar uno registrado, use el botón Contactos del listado.</p>
    @if($erroresContactos)
        @foreach($errors->getMessages() as $campo => $mensajes)
            @if(str_starts_with($campo, 'contactos'))
                @foreach($mensajes as $mensaje)<div class="text-danger" role="alert">{{ $mensaje }}</div>@endforeach
            @endif
        @endforeach
    @endif
    <div data-contactos-lista>
        @foreach($filasContactos ?: [[]] as $indice => $contacto)
            <div class="row g-2 border rounded p-2 mb-3" data-contacto-fila>
                <input type="hidden" name="contactos[{{ $indice }}][id_contacto]" value="{{ $contacto['id_contacto'] ?? '' }}">
                <div class="col-12 col-md-5"><label class="form-label w-100">Nombre del contacto<input class="form-control" name="contactos[{{ $indice }}][nombre]" value="{{ $contacto['nombre'] ?? '' }}" maxlength="150"></label></div>
                <div class="col-12 col-md-3"><label class="form-label w-100">Parentesco<input class="form-control" name="contactos[{{ $indice }}][parentesco]" value="{{ $contacto['parentesco'] ?? '' }}" maxlength="50"></label></div>
                <div class="col-12 col-md-4"><label class="form-label w-100">Teléfono<input class="form-control" name="contactos[{{ $indice }}][telefono]" value="{{ $contacto['telefono'] ?? '' }}" inputmode="tel" maxlength="9" pattern="[0-9]{4}-?[0-9]{4}"></label></div>
                @if(empty($contacto['id_contacto']))<div class="col-12"><button type="button" class="btn btn-outline-secondary btn-sm" data-quitar-contacto>Quitar este contacto</button></div>@endif
            </div>
        @endforeach
    </div>
    <button type="button" class="btn btn-outline-primary" data-agregar-contacto>Agregar otro contacto</button>
</section>
