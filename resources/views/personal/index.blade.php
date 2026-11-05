@extends('layouts.app')

@section('title', 'Personal - Sanatorio La Promesa')

@section('menu-personal', 'activo')

@section('content')

<!-- Encabezado -->
<div class="inicio-encabezado">

    <div class="inicio-icono">
        <i class="bi bi-person-badge"></i>
    </div>

    <div>
        <h1>Personal</h1>
        <p>Gestión del personal del sanatorio</p>
    </div>

</div>


<!-- Mensajes -->
@if(session('exito'))

    <div class="alert alert-success">
        {{ session('exito') }}
    </div>

@endif

@if(session('error'))

    <div class="alert alert-danger">
        {{ session('error') }}
    </div>

@endif


<!-- Buscador -->
<div class="pacientes-buscador mb-4">

    <form
        action="{{ route('personal.index') }}"
        method="GET"
    >

        <div class="row g-3">

            <div class="col-md-7">

                <input
                    type="text"
                    name="buscar"
                    class="form-control @error('buscar') is-invalid @enderror"
                    placeholder="Buscar por nombre, apellido, DPI o correo"
                    value="{{ $buscar ?? '' }}"
                    maxlength="100"
                >

                @error('buscar')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror

            </div>


            <div class="col-md-3">

                <select
                    name="estado"
                    class="form-select @error('estado') is-invalid @enderror"
                >

                    <option
                        value="Activo"
                        {{ ($estado ?? 'Activo') === 'Activo' ? 'selected' : '' }}
                    >
                        Activos
                    </option>

                    <option
                        value="Inactivo"
                        {{ ($estado ?? '') === 'Inactivo' ? 'selected' : '' }}
                    >
                        Inactivos
                    </option>

                </select>

                @error('estado')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror

            </div>


            <div class="col-md-2">

                <button
                    type="submit"
                    class="btn btn-primary w-100"
                >
                    <i class="bi bi-search"></i>
                    Buscar
                </button>

            </div>

        </div>

    </form>

</div>


<!-- Tabla -->
<div class="pacientes-tabla">

    <div class="tabla-titulo">

        <div>

            <h3>Personal registrado</h3>

            <p>
                {{ ($estado ?? 'Activo') === 'Activo'
                    ? 'Personal activo del sanatorio'
                    : 'Personal inactivo del sanatorio' }}
            </p>

        </div>


        @if(($estado ?? 'Activo') === 'Activo')

            @puede('Personal', 'Ingresar')
            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalPersonal"
            >
                <i class="bi bi-person-plus"></i>
                Nuevo personal
            </button>
            @endpuede

        @endif

    </div>


    <div class="table-responsive">

        <table class="table align-middle">

            <thead>

                <tr>
                    <th>Nombre</th>
                    <th>Cargo</th>
                    <th>Especialidad</th>
                    <th>Teléfono</th>
                    
                    <th>Acciones</th>
                </tr>

            </thead>

            <tbody>

                @forelse($personal as $persona)

                    @php

                        $editarConError =
                            old('form_context')
                            === 'editar-' . $persona->id_personal;

                    @endphp

                    <tr>

                        <td>

                            @include('perfil.avatar',['personaAvatar'=>$persona])
                            <strong>
                                {{ $persona->nombres }}
                                {{ $persona->apellidos }}
                            </strong>

                            @if($persona->correo)

                                <small class="d-block text-muted">
                                    {{ $persona->correo }}
                                </small>

                            @endif

                        </td>


                        <td>
                            {{ $persona->cargo->nombre_cargo ?? 'Sin cargo' }}
                        </td>


                        <td>
                            {{
                                $persona->especialidad
                                    ->nombre_especialidad
                                ?? 'No aplica'
                            }}
                        </td>


                        <td>
                            {{ $persona->telefono ?: 'Sin teléfono' }}
                        </td>


                        


                        <td>

                            <div class="d-flex flex-wrap gap-1">

                                @if($persona->estado === 'Activo')

                                    @puede('Personal', 'Editar')
                                    <button
                                        type="button"
                                        class="btn btn-outline-warning btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditar{{ $persona->id_personal }}"
                                    >
                                        <i class="bi bi-pencil"></i>
                                        Editar
                                    </button>
                                    @endpuede


                                    @puede('Personal', 'Desactivar')
                                    <form
                                        action="{{ route(
                                            'personal.desactivar',
                                            $persona->id_personal
                                        ) }}"
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('¿Desea desactivar este personal?');"
                                    >

                                        @csrf
                                        @method('PATCH')

                                        <button
                                            type="submit"
                                            class="btn btn-outline-danger btn-sm"
                                        >
                                            <i class="bi bi-person-x"></i>
                                            Desactivar
                                        </button>

                                    </form>
                                    @endpuede

                                @else

                                    @puede('Personal', 'Desactivar')
                                    <form
                                        action="{{ route(
                                            'personal.activar',
                                            $persona->id_personal
                                        ) }}"
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('¿Desea activar nuevamente este personal?');"
                                    >

                                        @csrf
                                        @method('PATCH')

                                        <button
                                            type="submit"
                                            class="btn btn-outline-success btn-sm"
                                        >
                                            <i class="bi bi-person-check"></i>
                                            Activar
                                        </button>

                                    </form>
                                    @endpuede

                                @endif

                            </div>

                        </td>

                    </tr>


                    <!-- Modal editar -->
                    @if($persona->estado === 'Activo')

                        <div
                            class="modal fade"
                            id="modalEditar{{ $persona->id_personal }}"
                            tabindex="-1"
                            aria-hidden="true"
                        >

                            <div class="modal-dialog modal-lg">

                                <div class="modal-content">

                                    <form
                                        action="{{ route(
                                            'personal.actualizar',
                                            $persona->id_personal
                                        ) }}"
                                        method="POST"
                                    >

                                        @csrf
                                        @method('PUT')

                                        <input
                                            type="hidden"
                                            name="form_context"
                                            value="editar-{{ $persona->id_personal }}"
                                        >


                                        <div class="modal-header">

                                            <h5 class="modal-title">
                                                Editar personal
                                            </h5>

                                            <button
                                                type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"
                                            ></button>

                                        </div>


                                        <div class="modal-body">

                                            <div class="row g-3">


                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Nombres
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="nombres"
                                                        class="form-control solo-letras
                                                            @if($editarConError)
                                                                @error('nombres')
                                                                    is-invalid
                                                                @enderror
                                                            @endif
                                                        "
                                                        value="{{
                                                            $editarConError
                                                                ? old('nombres')
                                                                : $persona->nombres
                                                        }}"
                                                        maxlength="100"
                                                        autocomplete="off"
                                                        required
                                                    >

                                                    @if($editarConError)

                                                        @error('nombres')
                                                            <div class="invalid-feedback">
                                                                {{ $message }}
                                                            </div>
                                                        @enderror

                                                    @endif

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Apellidos
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="apellidos"
                                                        class="form-control solo-letras
                                                            @if($editarConError)
                                                                @error('apellidos')
                                                                    is-invalid
                                                                @enderror
                                                            @endif
                                                        "
                                                        value="{{
                                                            $editarConError
                                                                ? old('apellidos')
                                                                : $persona->apellidos
                                                        }}"
                                                        maxlength="100"
                                                        autocomplete="off"
                                                        required
                                                    >

                                                    @if($editarConError)

                                                        @error('apellidos')
                                                            <div class="invalid-feedback">
                                                                {{ $message }}
                                                            </div>
                                                        @enderror

                                                    @endif

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        DPI
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="dpi"
                                                        class="form-control solo-dpi
                                                            @if($editarConError)
                                                                @error('dpi')
                                                                    is-invalid
                                                                @enderror
                                                            @endif
                                                        "
                                                        value="{{
                                                            $editarConError
                                                                ? old('dpi')
                                                                : $persona->dpi
                                                        }}"
                                                        maxlength="13"
                                                        minlength="13"
                                                        inputmode="numeric"
                                                        pattern="[0-9]{13}"
                                                        autocomplete="off"
                                                        required
                                                    >

                                                    @if($editarConError)

                                                        @error('dpi')
                                                            <div class="invalid-feedback">
                                                                {{ $message }}
                                                            </div>
                                                        @enderror

                                                    @endif

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Teléfono
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="telefono"
                                                        class="form-control telefono
                                                            @if($editarConError)
                                                                @error('telefono')
                                                                    is-invalid
                                                                @enderror
                                                            @endif
                                                        "
                                                        value="{{
                                                            $editarConError
                                                                ? old('telefono')
                                                                : $persona->telefono
                                                        }}"
                                                        maxlength="9"
                                                        inputmode="numeric"
                                                        placeholder="0000-0000"
                                                        autocomplete="off"
                                                    >

                                                    @if($editarConError)

                                                        @error('telefono')
                                                            <div class="invalid-feedback">
                                                                {{ $message }}
                                                            </div>
                                                        @enderror

                                                    @endif

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Correo
                                                    </label>

                                                    <input
                                                        type="email"
                                                        name="correo"
                                                        class="form-control correo-com
                                                            @if($editarConError)
                                                                @error('correo')
                                                                    is-invalid
                                                                @enderror
                                                            @endif
                                                        "
                                                        value="{{
                                                            $editarConError
                                                                ? old('correo')
                                                                : $persona->correo
                                                        }}"
                                                        maxlength="150"
                                                        pattern=".+\.com"
                                                        title="El correo debe terminar en .com"
                                                        autocomplete="off"
                                                    >

                                                    @if($editarConError)

                                                        @error('correo')
                                                            <div class="invalid-feedback">
                                                                {{ $message }}
                                                            </div>
                                                        @enderror

                                                    @endif

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Cargo
                                                    </label>

                                                    <select
                                                        name="id_cargo"
                                                        class="form-select cargo-select
                                                            @if($editarConError)
                                                                @error('id_cargo')
                                                                    is-invalid
                                                                @enderror
                                                            @endif
                                                        "
                                                        data-especialidad-target="especialidadEditar{{ $persona->id_personal }}"
                                                        required
                                                    >

                                                        <option value="">
                                                            Seleccione
                                                        </option>

                                                        @foreach($cargos as $cargo)

                                                            <option
                                                                value="{{ $cargo->id_cargo }}"
                                                                {{
                                                                    (
                                                                        $editarConError
                                                                            ? old('id_cargo')
                                                                            : $persona->id_cargo
                                                                    )
                                                                    == $cargo->id_cargo
                                                                        ? 'selected'
                                                                        : ''
                                                                }}
                                                            >
                                                                {{ $cargo->nombre_cargo }}
                                                            </option>

                                                        @endforeach

                                                    </select>

                                                    @if($editarConError)

                                                        @error('id_cargo')
                                                            <div class="invalid-feedback">
                                                                {{ $message }}
                                                            </div>
                                                        @enderror

                                                    @endif

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Especialidad
                                                    </label>

                                                    <select
                                                        name="id_especialidad"
                                                        id="especialidadEditar{{ $persona->id_personal }}"
                                                        class="form-select especialidad-select
                                                            @if($editarConError)
                                                                @error('id_especialidad')
                                                                    is-invalid
                                                                @enderror
                                                            @endif
                                                        "
                                                        data-seleccion="{{
                                                            $editarConError
                                                                ? old('id_especialidad')
                                                                : $persona->id_especialidad
                                                        }}"
                                                    >

                                                        <option value="">
                                                            No aplica
                                                        </option>

                                                    </select>

                                                    @if($editarConError)

                                                        @error('id_especialidad')
                                                            <div class="invalid-feedback">
                                                                {{ $message }}
                                                            </div>
                                                        @enderror

                                                    @endif

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Número de colegiado
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="numero_colegiado"
                                                        class="form-control colegiado-mayuscula
                                                            @if($editarConError)
                                                                @error('numero_colegiado')
                                                                    is-invalid
                                                                @enderror
                                                            @endif
                                                        "
                                                        value="{{
                                                            $editarConError
                                                                ? old('numero_colegiado')
                                                                : $persona->numero_colegiado
                                                        }}"
                                                        maxlength="5" inputmode="numeric" pattern="[1-9][0-9]{0,4}" data-mensaje-patron="Ingrese de 1 a 5 dígitos, sin cero inicial."
                                                        autocomplete="off"
                                                    >

                                                    @if($editarConError)

                                                        @error('numero_colegiado')
                                                            <div class="invalid-feedback">
                                                                {{ $message }}
                                                            </div>
                                                        @enderror

                                                    @endif

                                                </div>


                                                

                                            </div>

                                        </div>


                                        <div class="modal-footer">

                                            <button
                                                type="button"
                                                class="btn btn-secondary"
                                                data-bs-dismiss="modal"
                                            >
                                                Cancelar
                                            </button>

                                            <button
                                                type="submit"
                                                class="btn btn-primary"
                                            >
                                                Confirmar cambios
                                            </button>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        </div>

                    @endif

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="text-center py-4"
                        >
                            No hay personal registrado.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>


<!-- Modal nuevo personal -->
<div
    class="modal fade"
    id="modalPersonal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form
                action="{{ route('personal.guardar') }}"
                method="POST"
            >

                @csrf

                <input
                    type="hidden"
                    name="form_context"
                    value="nuevo"
                >


                <div class="modal-header">

                    <h5 class="modal-title">
                        Registrar personal
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">

                    <div class="row g-3">


                        <div class="col-md-6">

                            <label class="form-label">
                                Nombres
                            </label>

                            <input
                                type="text"
                                name="nombres"
                                class="form-control solo-letras
                                    @error('nombres')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('nombres') }}"
                                maxlength="100"
                                autocomplete="off"
                                required
                            >

                            @error('nombres')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Apellidos
                            </label>

                            <input
                                type="text"
                                name="apellidos"
                                class="form-control solo-letras
                                    @error('apellidos')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('apellidos') }}"
                                maxlength="100"
                                autocomplete="off"
                                required
                            >

                            @error('apellidos')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                DPI
                            </label>

                            <input
                                type="text"
                                name="dpi"
                                class="form-control solo-dpi
                                    @error('dpi')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('dpi') }}"
                                maxlength="13"
                                minlength="13"
                                inputmode="numeric"
                                pattern="[0-9]{13}"
                                autocomplete="off"
                                required
                            >

                            @error('dpi')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Teléfono
                            </label>

                            <input
                                type="text"
                                name="telefono"
                                class="form-control telefono
                                    @error('telefono')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('telefono') }}"
                                maxlength="9"
                                inputmode="numeric"
                                placeholder="0000-0000"
                                autocomplete="off"
                            >

                            @error('telefono')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Correo
                            </label>

                            <input
                                type="email"
                                name="correo"
                                class="form-control correo-com
                                    @error('correo')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('correo') }}"
                                maxlength="150"
                                pattern=".+\.com"
                                title="El correo debe terminar en .com"
                                autocomplete="off"
                            >

                            @error('correo')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Cargo
                            </label>

                            <select
                                name="id_cargo"
                                class="form-select cargo-select
                                    @error('id_cargo')
                                        is-invalid
                                    @enderror
                                "
                                data-especialidad-target="especialidadNuevo"
                                required
                            >

                                <option value="">
                                    Seleccione
                                </option>

                                @foreach($cargos as $cargo)

                                    <option
                                        value="{{ $cargo->id_cargo }}"
                                        {{
                                            old('id_cargo')
                                            == $cargo->id_cargo
                                                ? 'selected'
                                                : ''
                                        }}
                                    >
                                        {{ $cargo->nombre_cargo }}
                                    </option>

                                @endforeach

                            </select>

                            @error('id_cargo')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Especialidad
                            </label>

                            <select
                                name="id_especialidad"
                                id="especialidadNuevo"
                                class="form-select especialidad-select
                                    @error('id_especialidad')
                                        is-invalid
                                    @enderror
                                "
                                data-seleccion="{{ old('id_especialidad') }}"
                            >

                                <option value="">
                                    No aplica
                                </option>

                            </select>

                            @error('id_especialidad')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Número de colegiado
                            </label>

                            <input
                                type="text"
                                name="numero_colegiado"
                                class="form-control colegiado-mayuscula
                                    @error('numero_colegiado')
                                        is-invalid
                                    @enderror
                                "
                                value="{{ old('numero_colegiado') }}"
                                maxlength="5" inputmode="numeric" pattern="[1-9][0-9]{0,4}" data-mensaje-patron="Ingrese de 1 a 5 dígitos, sin cero inicial."
                                autocomplete="off"
                            >

                            @error('numero_colegiado')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Guardar personal
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection


@section('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Solo letras
    document
        .querySelectorAll('.solo-letras')
        .forEach(function (campo) {

            campo.addEventListener(
                'input',
                function () {

                    campo.value = campo.value
                        .replace(
                            /[^\p{L}\s'-]/gu,
                            ''
                        )
                        .replace(
                            /\s{2,}/g,
                            ' '
                        );

                }
            );

            campo.addEventListener(
                'blur',
                function () {

                    let texto = campo.value
                        .trim()
                        .replace(
                            /\s{2,}/g,
                            ' '
                        );

                    texto = texto
                        .toLocaleLowerCase('es')
                        .replace(
                            /(^|[\s'-])\p{L}/gu,
                            function (letra) {

                                return letra.toLocaleUpperCase('es');

                            }
                        );

                    campo.value = texto;

                }
            );

        });


    // Solo números en DPI
    document
        .querySelectorAll('.solo-dpi')
        .forEach(function (campo) {

            campo.addEventListener(
                'input',
                function () {

                    campo.value = campo.value
                        .replace(/\D/g, '')
                        .substring(0, 13);

                }
            );

        });


    // Formato de teléfono
    document
        .querySelectorAll('.telefono')
        .forEach(function (campo) {

            campo.addEventListener(
                'input',
                function () {

                    let numero = campo.value
                        .replace(/\D/g, '')
                        .substring(0, 8);

                    if (numero.length > 4) {

                        numero =
                            numero.substring(0, 4)
                            + '-'
                            + numero.substring(4);

                    }

                    campo.value = numero;

                }
            );

        });


    const cargosColegiado = @json(\App\Models\Personal::CARGOS_CON_COLEGIADO);
    document.querySelectorAll('form').forEach(form=>{
        const cargo=form.querySelector('[name="id_cargo"]'),campo=form.querySelector('[name="numero_colegiado"]');
        if(!cargo || !campo)return;
        const ayuda=document.createElement('small');ayuda.className='text-muted d-block';campo.after(ayuda);
        const actualizar=()=>{campo.required=cargosColegiado.includes(cargo.selectedOptions[0]?.textContent.trim());ayuda.textContent=campo.required?'Obligatorio para este cargo. De 1 a 5 dígitos, sin cero inicial.':'Opcional para este cargo. Si lo ingresa, use de 1 a 5 dígitos, sin cero inicial.';};
        cargo.addEventListener('change',actualizar);actualizar();
    });
    // Número de colegiado
    document
        .querySelectorAll('.colegiado-mayuscula')
        .forEach(function (campo) {

            campo.addEventListener(
                'input',
                function () {

                    campo.value =
                        campo.value
                            .replace(/[^0-9]/g,'').slice(0,5);

                }
            );

        });


    // Correo
    document
        .querySelectorAll('.correo-com')
        .forEach(function (campo) {

            campo.addEventListener(
                'blur',
                function () {

                    campo.value =
                        campo.value
                            .trim()
                            .toLocaleLowerCase('es');

                }
            );

        });


    // Especialidades por cargo
    const especialidadesPorCargo = @json(
        $cargos->mapWithKeys(function ($cargo) {

            return [
                (string) $cargo->id_cargo =>
                    $cargo->especialidades
                        ->map(function ($especialidad) {

                            return [
                                'id' =>
                                    $especialidad
                                        ->id_especialidad,

                                'nombre' =>
                                    $especialidad
                                        ->nombre_especialidad
                            ];

                        })
                        ->values()
            ];

        })
    );


    function cargarEspecialidades(
        cargoSelect,
        especialidadSelect
    ) {

        const cargoId =
            cargoSelect.value;

        const seleccionAnterior =
            especialidadSelect.dataset
                .seleccion || '';

        const especialidades =
            especialidadesPorCargo[cargoId]
            || [];

        especialidadSelect.innerHTML =
            '<option value="">No aplica</option>';

        especialidades.forEach(
            function (especialidad) {

                const opcion =
                    document.createElement(
                        'option'
                    );

                opcion.value =
                    especialidad.id;

                opcion.textContent =
                    especialidad.nombre;

                if (
                    String(especialidad.id)
                    === String(
                        seleccionAnterior
                    )
                ) {
                    opcion.selected = true;
                }

                especialidadSelect
                    .appendChild(opcion);

            }
        );

        if (especialidades.length === 0) {

            especialidadSelect.value = '';

            especialidadSelect.disabled = true;

        } else {

            especialidadSelect.disabled = false;

        }

    }


    document
        .querySelectorAll('.cargo-select')
        .forEach(function (cargoSelect) {

            const idEspecialidad =
                cargoSelect.dataset
                    .especialidadTarget;

            const especialidadSelect =
                document.getElementById(
                    idEspecialidad
                );

            if (!especialidadSelect) {
                return;
            }

            cargarEspecialidades(
                cargoSelect,
                especialidadSelect
            );

            cargoSelect.addEventListener(
                'change',
                function () {

                    especialidadSelect
                        .dataset
                        .seleccion = '';

                    cargarEspecialidades(
                        cargoSelect,
                        especialidadSelect
                    );

                }
            );

        });


    // Abrir modal correcto
    @if($errors->any())

        const contexto =
            @json(old('form_context'));

        if (contexto === 'nuevo') {

            const modalNuevo =
                document.getElementById(
                    'modalPersonal'
                );

            if (modalNuevo) {

                new bootstrap.Modal(
                    modalNuevo
                ).show();

            }

        } else if (
            contexto
            && contexto.startsWith(
                'editar-'
            )
        ) {

            const idPersonal =
                contexto.replace(
                    'editar-',
                    ''
                );

            const modalEditar =
                document.getElementById(
                    'modalEditar'
                    + idPersonal
                );

            if (modalEditar) {

                new bootstrap.Modal(
                    modalEditar
                ).show();

            }

        }

    @endif

});
</script>

@endsection