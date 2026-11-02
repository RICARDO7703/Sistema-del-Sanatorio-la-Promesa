(() => {
    const button = document.querySelector('.sidebar-toggle');
    const sidebar = document.getElementById('barra-lateral');
    if (!button || !sidebar) return;
    const narrow = window.matchMedia('(max-width: 767.98px)');
    const key = 'sanatorio-menu-oculto';
    function render(hidden) {
        sidebar.hidden = hidden;
        button.setAttribute('aria-expanded', String(!hidden));
        button.querySelector('span').textContent = hidden ? 'Mostrar menú' : 'Ocultar menú';
    }
    let saved = null;
    try { saved = localStorage.getItem(key); } catch (_) {}
    render(narrow.matches || saved === 'true');
    button.addEventListener('click', () => {
        render(!sidebar.hidden);
        try { localStorage.setItem(key, String(sidebar.hidden)); } catch (_) {}
    });
    narrow.addEventListener('change', () => render(narrow.matches));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && narrow.matches && !sidebar.hidden) {
            render(true);
            button.focus();
        }
    });
})();


// Conserva las tablas de escritorio y prepara etiquetas para las fichas móviles.
(() => {
    document.querySelectorAll('table.table').forEach(table => {
        const headers = Array.from(table.tHead?.rows[0]?.cells || []);
        if (!headers.length || headers.some(cell => cell.colSpan !== 1)) return;
        table.classList.add('tabla-adaptable');
        table.setAttribute('role', 'table');
        table.tHead.setAttribute('role', 'rowgroup');
        Array.from(table.tHead.rows).forEach(row => row.setAttribute('role', 'row'));
        headers.forEach(header => header.setAttribute('role', 'columnheader'));
        Array.from(table.tBodies).forEach(body => {
            body.setAttribute('role', 'rowgroup');
            Array.from(body.rows).forEach(row => {
                row.setAttribute('role', 'row');
                Array.from(row.cells).forEach((cell, index) => {
                    cell.setAttribute('role', 'cell');
                    if (cell.colSpan !== 1) return;
                    const label = document.createElement('span');
                    label.className = 'etiqueta-movil';
                    label.setAttribute('aria-hidden', 'true');
                    label.textContent = headers[index]?.textContent.trim() || '';
                    cell.prepend(label);
                });
            });
        });
    });
})();


// Signos vitales a elección: solo se muestran los que el personal decide tomar.
(() => {
    document.querySelectorAll('[data-signos-seleccionables]').forEach(grupo => {
        const campoDe = signo => grupo.querySelector(`[data-signo="${signo}"]`);
        const mostrar = (signo, visible) => {
            const bloque = campoDe(signo);
            if (!bloque) return;
            bloque.hidden = !visible;
            const entradas = [...bloque.querySelectorAll('input')];
            if (!visible) {
                entradas.filter(entrada => entrada.value !== '').forEach(entrada => {
                    entrada.value = '';
                    entrada.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }
            if (visible) entradas[0]?.focus({ preventScroll: true });
        };
        grupo.querySelectorAll('[data-elegir-signo]').forEach(casilla => {
            casilla.addEventListener('change', () => mostrar(casilla.dataset.elegirSigno, casilla.checked));
        });
        // Un borrador recuperado puede traer valores en signos ocultos: se muestran.
        document.addEventListener('DOMContentLoaded', () => {
            grupo.querySelectorAll('[data-elegir-signo]').forEach(casilla => {
                const lleno = [...(campoDe(casilla.dataset.elegirSigno)?.querySelectorAll('input') ?? [])].some(entrada => entrada.value !== '');
                if (lleno && !casilla.checked) {
                    casilla.checked = true;
                    campoDe(casilla.dataset.elegirSigno).hidden = false;
                }
            });
        });
    });
})();


// Validación en línea: el mensaje aparece debajo del campo, sin los avisos del navegador.
window.validacionEnLinea = (() => {
    const ahoraLocal = () => {
        const ahora = new Date();
        ahora.setMinutes(ahora.getMinutes() - ahora.getTimezoneOffset());
        return ahora.toISOString().slice(0, 16);
    };
    // Mantiene al día el límite de "no futuro" aunque la página lleve rato abierta.
    document.addEventListener('focusin', evento => {
        if (evento.target.matches?.('input[data-max-ahora]')) evento.target.max = ahoraLocal();
    });
    const mensajeDe = campo => {
        if (campo.disabled || campo.type === 'hidden' || campo.closest('[hidden], template')) return '';
        if (campo.type === 'radio') {
            const grupo = campo.form ? [...campo.form.querySelectorAll(`input[type="radio"][name="${CSS.escape(campo.name)}"]`)] : [campo];
            return grupo.some(opcion => opcion.required) && !grupo.some(opcion => opcion.checked)
                ? (grupo.find(opcion => opcion.dataset.mensajeRequerido)?.dataset.mensajeRequerido || 'Seleccione una opción.')
                : '';
        }
        const valor = campo.value.trim();
        if (campo.required && valor === '') return campo.dataset.mensajeRequerido || 'Este campo es obligatorio.';
        // Campos que van en pareja, como la presión sistólica y la diastólica.
        if (valor === '' && campo.dataset.requeridoCon) {
            const pareja = campo.form?.querySelector(`[name="${CSS.escape(campo.dataset.requeridoCon)}"]`);
            if (pareja && pareja.value.trim() !== '') return campo.dataset.mensajeRequerido || 'Complete también este campo.';
        }
        if (valor === '') return '';
        if (campo.type === 'datetime-local') {
            if (campo.hasAttribute('data-max-ahora')) campo.max = ahoraLocal();
            if (campo.max && valor > campo.max) return campo.dataset.mensajeMaximo || 'La fecha y hora no pueden ser futuras.';
            if (campo.min && valor < campo.min) return campo.dataset.mensajeMinimo || 'La fecha es anterior a la permitida.';
        }
        if (campo.type === 'date' && campo.max && valor > campo.max) return campo.dataset.mensajeMaximo || 'La fecha no puede ser futura.';
        if (campo.type === 'number') {
            const numero = Number(valor);
            if (!Number.isFinite(numero)) return 'Escriba un número válido.';
            if ((campo.min !== '' && numero < Number(campo.min)) || (campo.max !== '' && numero > Number(campo.max))) {
                return campo.dataset.mensajeRango || `Debe estar entre ${campo.min} y ${campo.max}.`;
            }
            if (campo.step === '1' && !Number.isInteger(numero)) return 'Escriba un número entero, sin decimales.';
        }
        if (campo.pattern && !new RegExp(`^(?:${campo.pattern})$`).test(campo.value)) {
            return campo.dataset.mensajeFormato || campo.title || 'El formato no es válido.';
        }
        return '';
    };
    const mostrar = (campo, mensaje) => {
        // Las casillas no llevan aviso propio: un elemento entre la casilla y su etiqueta rompería los botones de selección.
        if (campo.type === 'checkbox') return;
        if (campo.type === 'radio') {
            const grupo = campo.closest('fieldset') || campo.parentElement;
            let avisoGrupo = grupo.querySelector(':scope > .invalid-feedback[data-grupo]');
            if (!avisoGrupo) {
                avisoGrupo = document.createElement('div');
                avisoGrupo.className = 'invalid-feedback';
                avisoGrupo.dataset.grupo = '';
                grupo.append(avisoGrupo);
            }
            avisoGrupo.textContent = mensaje;
            avisoGrupo.classList.toggle('d-block', mensaje !== '');
            return;
        }
        // En un grupo con unidad o selector (goteo, dosis, presión) el aviso va debajo del grupo, no dentro:
        // dentro del grupo empujaba y encogía los campos.
        const grupo = campo.closest('.input-group');
        let aviso = null;
        if (grupo) {
            for (let siguiente = grupo.nextElementSibling; siguiente?.classList.contains('invalid-feedback'); siguiente = siguiente.nextElementSibling) {
                if (siguiente.dataset.avisoDe === campo.name) { aviso = siguiente; break; }
            }
        } else if (campo.nextElementSibling?.classList.contains('invalid-feedback')) {
            aviso = campo.nextElementSibling;
        }
        if (!aviso) {
            aviso = document.createElement('div');
            aviso.className = 'invalid-feedback';
            if (grupo) {
                aviso.dataset.avisoDe = campo.name;
                grupo.insertAdjacentElement('afterend', aviso);
            } else {
                campo.insertAdjacentElement('afterend', aviso);
            }
        }
        aviso.textContent = mensaje;
        aviso.classList.toggle('d-block', mensaje !== '');
        campo.classList.toggle('is-invalid', mensaje !== '');
        campo.setAttribute('aria-invalid', mensaje !== '' ? 'true' : 'false');
    };
    const campos = (form, excluir) => [...form.querySelectorAll('input[name], textarea[name], select[name]')]
        .filter(campo => !(excluir && campo.closest(excluir)));
    // Formularios que exigen llenar al menos uno de varios campos (data-al-menos-uno="campo1,campo2,...").
    const faltaAlMenosUno = form => {
        const nombres = (form.dataset.alMenosUno || '').split(',').map(nombre => nombre.trim()).filter(Boolean);
        if (!nombres.length) return false;
        const lleno = nombres.some(nombre => [...form.querySelectorAll(`[name="${CSS.escape(nombre)}"]`)]
            .some(campo => !campo.disabled && !campo.closest('[hidden]') && ((campo.type === 'checkbox' || campo.type === 'radio') ? campo.checked : campo.value.trim() !== '')));
        return !lleno;
    };
    const mostrarAlMenosUno = (form, visible) => {
        const aviso = form.querySelector('[data-aviso-al-menos-uno]');
        if (aviso) aviso.hidden = !visible;
    };
    const validar = (form, excluir = null) => {
        let primero = null;
        campos(form, excluir).forEach(campo => {
            const mensaje = mensajeDe(campo);
            mostrar(campo, mensaje);
            if (mensaje && !primero) primero = campo;
        });
        const falta = faltaAlMenosUno(form);
        mostrarAlMenosUno(form, falta);
        if (falta && !primero) {
            primero = form.querySelector('[data-aviso-al-menos-uno]');
            primero?.scrollIntoView({block: 'center'});
            const resumen = form.querySelector('[data-resumen-errores]');
            if (resumen) resumen.hidden = false;
            return false;
        }
        const resumen = form.querySelector('[data-resumen-errores]');
        if (resumen) resumen.hidden = !primero;
        if (primero) primero.focus({ preventScroll: false });
        return !primero;
    };
    document.querySelectorAll('form[data-validar-en-linea]').forEach(form => {
        const esperas = new WeakMap();
        const revisar = evento => {
            const campo = evento.target;
            if (!campo.matches('input[name], textarea[name], select[name]')) return;
            if (form.querySelector('[data-aviso-al-menos-uno]:not([hidden])') && !faltaAlMenosUno(form)) mostrarAlMenosUno(form, false);
            clearTimeout(esperas.get(campo));
            if (campo.type === 'radio') {
                mostrar(campo, mensajeDe(campo));
                return;
            }
            if (evento.type === 'input' && !campo.classList.contains('is-invalid')) {
                // Mientras escribe: si el valor está mal, el aviso aparece al hacer una pausa breve.
                if (campo.value.trim() !== '') esperas.set(campo, setTimeout(() => mostrar(campo, mensajeDe(campo)), 700));
                return;
            }
            mostrar(campo, mensajeDe(campo));
            // Si la pareja de este campo (presión sistólica/diastólica) estaba marcada, se vuelve a revisar.
            const pareja = form.querySelector(`[data-requerido-con="${CSS.escape(campo.name)}"]`);
            if (pareja?.classList.contains('is-invalid')) mostrar(pareja, mensajeDe(pareja));
        };
        form.addEventListener('input', revisar);
        form.addEventListener('focusout', revisar);
        if (form.hasAttribute('data-validar-al-enviar')) {
            form.addEventListener('submit', evento => {
                if (!validar(form)) evento.preventDefault();
            });
        }
    });
    return { validar };
})();


document.querySelectorAll('[data-consulta-historial]').forEach(contenedor => {
    const boton = contenedor.querySelector('[data-ver-consultas]');
    const historial = contenedor.querySelector('.historial-consultas');
    if (!boton || !historial) return;
    function mostrar(abierto) {
        historial.hidden = !abierto;
        contenedor.classList.toggle('historial-abierto', abierto);
        boton.setAttribute('aria-expanded', String(abierto));
        boton.querySelector('span').textContent = abierto ? 'Ocultar consultas anteriores' : 'Ver consultas anteriores';
    }
    boton.addEventListener('click', () => mostrar(historial.hidden));
    historial.querySelector('[data-cerrar-consultas]').addEventListener('click', () => { mostrar(false); boton.focus(); });
});


// Filtro de documentos del ingreso: todos, solo evoluciones o solo notas de enfermería.
document.querySelectorAll('[data-filtro-documentos]').forEach(contenedor => {
    const aplicar = () => {
        const elegido = contenedor.querySelector('input[type="radio"]:checked')?.value || 'todos';
        let visibles = 0;
        contenedor.querySelectorAll('[data-tipo-documento]').forEach(documento => {
            const mostrar = elegido === 'todos' || documento.dataset.tipoDocumento === elegido;
            documento.hidden = !mostrar;
            documento.classList.toggle('d-flex', mostrar);
            if (mostrar) visibles++;
        });
        contenedor.querySelectorAll('[data-dia-documentos]').forEach(dia => {
            dia.hidden = ![...dia.querySelectorAll('[data-tipo-documento]')].some(documento => !documento.hidden);
        });
        const vacio = contenedor.querySelector('[data-sin-documentos]');
        if (vacio) vacio.hidden = visibles > 0;
    };
    contenedor.addEventListener('change', evento => {
        if (evento.target.matches('input[type="radio"]')) aplicar();
    });
    aplicar();
});

document.querySelectorAll('[data-contactos-multiples]').forEach(grupo => {
    const lista = grupo.querySelector('[data-contactos-lista]');
    const modelo = lista.querySelector('[data-contacto-fila]').cloneNode(true);
    let siguiente = Math.max(-1, ...[...lista.querySelectorAll('input[name]')].map(c => Number(c.name.match(/\[(\d+)\]/)[1]))) + 1;
    grupo.addEventListener('click', evento => {
        const quitar = evento.target.closest('[data-quitar-contacto]');
        if (quitar) quitar.closest('[data-contacto-fila]').remove();
        if (!evento.target.closest('[data-agregar-contacto]')) return;
        const fila = modelo.cloneNode(true);
        fila.querySelectorAll('input').forEach(campo => {
            campo.name = campo.name.replace(/\[\d+\]/, '[' + siguiente + ']');
            campo.value = '';
            campo.classList.remove('is-invalid', 'is-valid');
        });
        if (!fila.querySelector('[data-quitar-contacto]')) {
            const contenedor = document.createElement('div');
            contenedor.className = 'col-12';
            const boton = document.createElement('button');
            boton.type = 'button'; boton.className = 'btn btn-outline-secondary btn-sm';
            boton.dataset.quitarContacto = ''; boton.textContent = 'Quitar este contacto';
            contenedor.append(boton); fila.append(contenedor);
        }
        siguiente++;
        lista.append(fila);
        fila.querySelector('input:not([type="hidden"])').focus();
    });
});


// «Documento de ingreso firmado»: al marcar la casilla aparece el campo para subirlo y se vuelve obligatorio.
(() => {
    const alternar = casilla => {
        const caja = document.getElementById(casilla.dataset.archivoFirmado);
        const campo = caja?.querySelector('input[type="file"]');
        if (!campo) return;
        caja.hidden = !casilla.checked;
        campo.disabled = !casilla.checked;
        campo.required = casilla.checked;
        if (!casilla.checked) {
            campo.value = '';
            campo.classList.remove('is-invalid');
            const aviso = campo.nextElementSibling;
            if (aviso?.classList.contains('invalid-feedback')) aviso.classList.remove('d-block');
        }
    };
    document.addEventListener('change', evento => {
        const casilla = evento.target.closest?.('input[type="checkbox"][data-archivo-firmado]');
        if (casilla) alternar(casilla);
    });
    document.querySelectorAll('input[type="checkbox"][data-archivo-firmado]').forEach(alternar);
})();


// Nota de enfermería: al marcar «Se avisó al médico» se pide a qué médico.
(() => {
    const alternar = casilla => {
        const bloque = casilla.closest('form')?.querySelector('[data-medico-avisado]');
        const lista = bloque?.querySelector('select');
        if (!lista) return;
        bloque.hidden = !casilla.checked;
        lista.disabled = !casilla.checked;
        lista.required = casilla.checked;
        if (!casilla.checked) {
            lista.value = '';
            lista.classList.remove('is-invalid');
        }
    };
    document.addEventListener('change', evento => {
        const casilla = evento.target.closest?.('input[type="checkbox"][data-aviso-medico]');
        if (casilla) alternar(casilla);
    });
    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('input[type="checkbox"][data-aviso-medico]').forEach(alternar));
})();


// Apariencia: modo claro u oscuro, guardado en este dispositivo.
(() => {
    const opciones = document.querySelectorAll('[data-tema-opcion]');
    if (!opciones.length) return;
    const aviso = document.querySelector('[data-tema-aviso]');
    let actual = 'claro';
    try { actual = localStorage.getItem('sanatorio:tema') === 'oscuro' ? 'oscuro' : 'claro'; } catch (error) {}
    opciones.forEach(opcion => {
        opcion.checked = opcion.value === actual;
        opcion.addEventListener('change', () => {
            if (!opcion.checked) return;
            const oscuro = opcion.value === 'oscuro';
            if (oscuro) document.documentElement.setAttribute('data-bs-theme', 'dark');
            else document.documentElement.removeAttribute('data-bs-theme');
            let guardado = true;
            try { localStorage.setItem('sanatorio:tema', opcion.value); } catch (error) { guardado = false; }
            if (aviso) aviso.textContent = guardado
                ? (oscuro ? 'Modo oscuro activado.' : 'Modo claro activado.')
                : 'Se aplicó el cambio, pero este navegador no permite recordarlo.';
        });
    });
})();
