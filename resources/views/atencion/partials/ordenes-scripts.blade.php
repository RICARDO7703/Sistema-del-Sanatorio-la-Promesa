{{-- Se carga una sola vez por página aunque varias ventanas la incluyan. --}}
@once
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Cada bloque de órdenes maneja sus propias filas y su dieta.
    const prepararOrdenes = bloque => {
        if (bloque.dataset.ordenesPreparadas) return null;
        bloque.dataset.ordenesPreparadas = '1';
        const contenedorDe = tipo => bloque.querySelector(`[data-filas="${tipo}"]`);
        const siguienteIndice = tipo => [...contenedorDe(tipo).querySelectorAll('[name]')].reduce((maximo, campo) => {
            const indice = Number(campo.name.split('[')[2]?.split(']')[0]);
            return Number.isFinite(indice) ? Math.max(maximo, indice + 1) : maximo;
        }, 0);
        const agregarFila = (tipo, valores = null) => {
            const contenedor = contenedorDe(tipo);
            if (contenedor.children.length >= (tipo === 'soluciones' ? 1 : 50)) return null;
            const indice = siguienteIndice(tipo);
            const plantilla = bloque.querySelector(`template[data-plantilla="${tipo}"]`).content.cloneNode(true);
            plantilla.querySelectorAll('[name]').forEach(campo => {
                campo.name = campo.name.replace('__INDEX__', indice);
                const clave = campo.name.match(/\]\[([a-z_]+)\]$/)?.[1];
                if (valores && clave && valores[clave] !== undefined && valores[clave] !== null) {
                    const valor = String(valores[clave]);
                    if (campo.tagName === 'SELECT' && ![...campo.options].some(opcion => opcion.value === valor)) return;
                    if (campo.type === 'number' && !/^\d+(\.\d+)?$/.test(valor)) return;
                    campo.value = valor;
                }
            });
            contenedor.append(plantilla);
            return contenedor.lastElementChild;
        };
        const actualizarBotonSolucion = () => {
            const boton = bloque.querySelector('[data-agregar="soluciones"]');
            if (boton) boton.hidden = contenedorDe('soluciones').children.length >= 1;
        };
        actualizarBotonSolucion();
        bloque.addEventListener('click', event => {
            const quitar = event.target.closest('[data-quitar]');
            if (quitar) {
                quitar.closest('.fila-orden').remove();
                actualizarBotonSolucion();
            }
            const agregar = event.target.closest('[data-agregar]');
            if (!agregar) return;
            agregarFila(agregar.dataset.agregar);
            actualizarBotonSolucion();
        });
        const dieta = bloque.querySelector('[data-orden-dieta]');
        const bloqueDietaOtra = bloque.querySelector('[data-dieta-otra]');
        const mostrarDietaOtra = () => {
            const otra = dieta.value === 'Otra';
            bloqueDietaOtra.hidden = !otra;
            bloqueDietaOtra.querySelector('input').disabled = !otra;
        };
        dieta?.addEventListener('change', mostrarDietaOtra);
        return { bloque, contenedorDe, agregarFila, actualizarBotonSolucion, dieta, bloqueDietaOtra, mostrarDietaOtra };
    };
    const bloques = new Map([...document.querySelectorAll('[data-ordenes]')].map(bloque => [bloque, prepararOrdenes(bloque)]));

    // Primera valoración del internamiento: las órdenes son opcionales y solo se envían si se marca la casilla.
    document.querySelectorAll('[data-activar-ordenes]').forEach(casilla => {
        if (casilla.dataset.ordenesPreparadas) return;
        casilla.dataset.ordenesPreparadas = '1';
        const cuerpo = document.getElementById(casilla.dataset.activarOrdenes);
        const campos = cuerpo?.querySelector('[data-ordenes]');
        if (!cuerpo || !campos) return;
        const aplicar = () => {
            cuerpo.hidden = !casilla.checked;
            const clinica = cuerpo.closest('[data-valoracion-columnas]')?.querySelector('[data-valoracion-clinica]');
            clinica?.classList.toggle('col-xl-6', casilla.checked);
            casilla.setAttribute('aria-expanded', String(casilla.checked));
            campos.disabled = !casilla.checked;
        };
        casilla.addEventListener('change', aplicar);
        aplicar();
    });

    document.querySelectorAll('[data-habilitar-ordenes-canceladas]').forEach(casilla => {
        const editor = casilla.closest('[data-editor-ordenes-canceladas]');
        const aplicar = () => {
            const campos = editor.querySelector('[data-edicion-canceladas]');
            campos.hidden = !casilla.checked;
            campos.disabled = !casilla.checked;
            editor.querySelector('[data-resumen-canceladas]').hidden = casilla.checked;
        };
        casilla.addEventListener('change', aplicar);
        aplicar();
    });

    const form = document.getElementById('form-evolucion');
    if (!form || form.dataset.ordenesFormPreparado) return;
    form.dataset.ordenesFormPreparado = '1';
    const ordenes = bloques.get(form.querySelector('[data-ordenes]'));

    const grupoDiagnostico = form.querySelector('[data-diagnostico-evolucion]');
    const diagnostico = form.querySelector('#evo-diagnostico');
    let diagnosticoNuevo = grupoDiagnostico?.querySelector('#evo-diagnostico-nuevo:checked') ? diagnostico.value : '';
    grupoDiagnostico?.addEventListener('change', evento => {
        if (evento.target.name !== 'origen_diagnostico') return;
        if (evento.target.value === 'anterior') {
            diagnosticoNuevo = diagnostico.value;
            diagnostico.value = grupoDiagnostico.dataset.diagnosticoAnterior;
            diagnostico.readOnly = true;
        } else {
            diagnostico.value = diagnosticoNuevo;
            diagnostico.readOnly = false;
            diagnostico.focus();
        }
        diagnostico.dispatchEvent(new Event('input', { bubbles: true }));
    });

    const repetir = form.querySelector('[data-repetir-ordenes]');
    repetir?.addEventListener('click', () => {
        if (!ordenes) return;
        let anteriores = {};
        try { anteriores = JSON.parse(document.getElementById('ordenes-anteriores').textContent) || {}; } catch { return; }
        const { dieta, bloqueDietaOtra } = ordenes;
        const conocida = [...dieta.options].some(opcion => opcion.value === anteriores.dieta && opcion.value !== 'Otra');
        dieta.value = anteriores.dieta ? (conocida ? anteriores.dieta : 'Otra') : '';
        bloqueDietaOtra.querySelector('input').value = anteriores.dieta && !conocida ? anteriores.dieta : '';
        ordenes.mostrarDietaOtra();
        ['soluciones', 'medicamentos'].forEach(tipo => {
            ordenes.contenedorDe(tipo).replaceChildren();
            (anteriores[tipo] || []).forEach(valores => ordenes.agregarFila(tipo, valores));
        });
        ordenes.actualizarBotonSolucion();
        ordenes.bloque.querySelector('[data-orden-indicaciones]').value = anteriores.indicaciones || '';
        ordenes.bloque.querySelector('[data-orden-plan]').value = anteriores.plan_educacional || '';
        ordenes.bloque.querySelectorAll('[name]').forEach(campo => campo.dispatchEvent(new Event('input', { bubbles: true })));
        repetir.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i> Órdenes anteriores copiadas; puede modificarlas';
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-presion-arterial]').forEach(campo => {
        campo.addEventListener('input', evento => {
            const original = campo.value;
            const posicion = campo.selectionStart ?? original.length;
            const cifrasAntes = original.slice(0, posicion).replace(/\D/g, '').length;
            const separador = original.indexOf('/');
            const izquierda = separador >= 0 ? original.slice(0, separador).replace(/\D/g, '').slice(0, 3) : original.replace(/\D/g, '').slice(0, 3);
            const derecha = separador >= 0 ? original.slice(separador + 1).replace(/\D/g, '').slice(0, 3) : original.replace(/\D/g, '').slice(3, 6);
            const borrando = evento.inputType?.startsWith('delete');
            const mostrarSeparador = izquierda.length >= 2 && (separador >= 0 || derecha.length > 0 || (izquierda.length === 3 && !borrando));
            campo.value = izquierda + (mostrarSeparador ? '/' + derecha : '');
            const nuevaPosicion = Math.min(campo.value.length, cifrasAntes + (mostrarSeparador && cifrasAntes >= izquierda.length ? 1 : 0));
            campo.setSelectionRange(nuevaPosicion, nuevaPosicion);
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const resumen = document.getElementById('resumen-valoracion');
    const editor = document.getElementById('editor-valoracion');
    if (!resumen || !editor || editor.dataset.ordenesEditorPreparado) return;
    editor.dataset.ordenesEditorPreparado = '1';
    // Igual que las demás ventanas: el plazo a la izquierda del pie y «Editar / Confirmar cambios» junto a «Cerrar».
    const botonEditar=document.querySelector('[data-editar-valoracion]');
    const limite=document.querySelector('[data-limite-valoracion]');
    const pie=editor.closest('.modal-content')?.querySelector('.modal-footer');
    if(botonEditar){
        if(pie){
            botonEditar.className='btn btn-primary';
            pie.prepend(botonEditar);
            if(limite){limite.className='small text-muted me-auto';pie.prepend(limite);}
        }else editor.parentElement.append(botonEditar);
    }
    const cambiarVista = editando => {
        if(botonEditar)botonEditar.innerHTML=editando ? '<i class="bi bi-check-lg"></i> Confirmar cambios' : '<i class="bi bi-pencil"></i> Editar';
        resumen.hidden = editando;
        editor.hidden = !editando;
        const cuerpo = editor.closest('.modal-body');
        if (cuerpo) cuerpo.scrollTop = 0;
        if (editando) editor.querySelector('input:not([type="hidden"])')?.focus({preventScroll: true});
    };
    botonEditar?.addEventListener('click', () => { if(editor.hidden)cambiarVista(true);else editor.querySelector('form')?.requestSubmit(); });
    cambiarVista(!editor.hidden);
    // Al cerrar la ventana sin confirmar, vuelve a mostrarse la valoración guardada.
    editor.closest('.modal')?.addEventListener('hidden.bs.modal', () => cambiarVista(false));
});
</script>
@endonce
