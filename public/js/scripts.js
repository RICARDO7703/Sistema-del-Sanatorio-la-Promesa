(() => {
    // Menú lateral según el tamaño de pantalla:
    // - computadora (1200 px o más): se muestra u oculta con «Ocultar menú» y se recuerda la preferencia;
    // - tablet (768 a 1199 px): queda como una franja de íconos y el botón la abre completa encima del contenido;
    // - teléfono (menos de 768 px): está oculto y se abre con «Menú» en la barra de abajo.
    const button = document.querySelector('.sidebar-toggle');
    const sidebar = document.getElementById('barra-lateral');
    if (!button || !sidebar) return;
    const fondo = document.querySelector('[data-cerrar-menu]');
    const botonesMenu = document.querySelectorAll('[data-abrir-menu]');
    const telefono = window.matchMedia('(max-width: 767.98px)');
    const tablet = window.matchMedia('(min-width: 768px) and (max-width: 1199.98px)');
    const key = 'sanatorio-menu-oculto';
    const texto = button.querySelector('span');
    let oculto = false;
    try { oculto = localStorage.getItem(key) === 'true'; } catch (_) {}

    function render() {
        const abierto = document.body.classList.contains('menu-abierto');
        if (telefono.matches) {
            sidebar.hidden = !abierto;
        } else if (tablet.matches) {
            sidebar.hidden = false;
        } else {
            document.body.classList.remove('menu-abierto');
            sidebar.hidden = oculto;
        }
        const flotante = telefono.matches || tablet.matches;
        if (fondo) fondo.hidden = !(flotante && abierto);
        const expandido = flotante ? abierto : !oculto;
        button.setAttribute('aria-expanded', String(expandido));
        botonesMenu.forEach(boton => boton.setAttribute('aria-expanded', String(abierto)));
        texto.textContent = flotante ? (abierto ? 'Cerrar menú' : 'Menú') : (oculto ? 'Mostrar menú' : 'Ocultar menú');
    }
    function abrirFlotante(abrir) {
        document.body.classList.toggle('menu-abierto', abrir);
        render();
        if (abrir) sidebar.querySelector('.nav-link.activo, .nav-link')?.focus({ preventScroll: true });
    }
    button.addEventListener('click', () => {
        if (telefono.matches || tablet.matches) {
            abrirFlotante(!document.body.classList.contains('menu-abierto'));
            return;
        }
        oculto = !oculto;
        try { localStorage.setItem(key, String(oculto)); } catch (_) {}
        render();
    });
    botonesMenu.forEach(boton => boton.addEventListener('click', () => abrirFlotante(!document.body.classList.contains('menu-abierto'))));
    fondo?.addEventListener('click', () => abrirFlotante(false));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && document.body.classList.contains('menu-abierto')) {
            abrirFlotante(false);
            button.focus();
        }
    });
    [telefono, tablet].forEach(consulta => consulta.addEventListener('change', () => {
        document.body.classList.remove('menu-abierto');
        render();
    }));
    render();
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
                    // En el teléfono la fila es una ficha: el primer dato es el título, los botones van al final
                    // y las celdas sin dato no ocupan espacio. El contenido va junto para que ícono y texto no se separen.
                    const sinBotones = cell.cloneNode(true);
                    sinBotones.querySelectorAll('.btn, .dropdown-menu, .visually-hidden, input, select').forEach(elemento => elemento.remove());
                    const soloAcciones = Boolean(cell.querySelector('.btn')) && !sinBotones.textContent.trim();
                    if (index === 0 && row.cells.length > 1) cell.classList.add('celda-titulo');
                    else if (soloAcciones) cell.classList.add('celda-acciones');
                    else if (!cell.textContent.trim() && !cell.querySelector('img, i, input, select')) cell.classList.add('celda-vacia');
                    const valor = document.createElement('div');
                    valor.className = 'valor-movil';
                    valor.append(...cell.childNodes);
                    const label = document.createElement('span');
                    label.className = 'etiqueta-movil';
                    label.setAttribute('aria-hidden', 'true');
                    label.textContent = headers[index]?.textContent.trim() || '';
                    cell.append(label, valor);
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
    const botones = document.querySelectorAll('[data-tema-alternar]');
    const aviso = document.querySelector('[data-tema-aviso]');
    const sincronizar = () => {
        const oscuro = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        opciones.forEach(opcion => { opcion.checked = opcion.value === (oscuro ? 'oscuro' : 'claro'); });
        botones.forEach(boton => {
            boton.querySelector('[data-tema-texto]').textContent = oscuro ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
            boton.querySelector('[data-tema-icono]').className = oscuro ? 'bi bi-sun' : 'bi bi-moon-stars';
        });
    };
    const aplicar = tema => {
        if (tema === 'oscuro') document.documentElement.setAttribute('data-bs-theme', 'dark');
        else document.documentElement.removeAttribute('data-bs-theme');
        let guardado = true;
        try { localStorage.setItem('sanatorio:tema', tema); } catch (error) { guardado = false; }
        sincronizar();
        if (aviso) aviso.textContent = guardado
            ? (tema === 'oscuro' ? 'Modo oscuro activado.' : 'Modo claro activado.')
            : 'Se aplicó el cambio, pero este navegador no permite recordarlo.';
    };
    opciones.forEach(opcion => opcion.addEventListener('change', () => {
        if (opcion.checked) aplicar(opcion.value);
    }));
    botones.forEach(boton => boton.addEventListener('click', () => {
        aplicar(document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'claro' : 'oscuro');
    }));
    sincronizar();
})();

// Si la opción de la página actual queda abajo en el menú, se desplaza para que se vea.
(() => {
    const activa = document.querySelector('.sidebar-menu .nav-link.activo');
    if (activa) activa.scrollIntoView({ block: 'nearest' });
})();

// Campos de medidas (talla, peso): solo números y un punto decimal, con hasta 2 decimales.
// El navegador deja escribir «e», «+» y «-» en los campos numéricos; aquí se impiden.
(() => {
    document.addEventListener('keydown', evento => {
        const campo = evento.target;
        if (!(campo instanceof HTMLInputElement) || !campo.hasAttribute('data-solo-decimal')) return;
        if (['e', 'E', '+', '-'].includes(evento.key)) evento.preventDefault();
    });
    document.addEventListener('paste', evento => {
        const campo = evento.target;
        if (!(campo instanceof HTMLInputElement) || !campo.hasAttribute('data-solo-decimal')) return;
        const texto = (evento.clipboardData || window.clipboardData).getData('text').trim().replace(',', '.');
        if (!/^\d{1,3}(\.\d{1,2})?$/.test(texto)) {
            evento.preventDefault();
            campo.setCustomValidity('Solo números, con hasta 2 decimales.');
            campo.reportValidity();
            setTimeout(() => campo.setCustomValidity(''), 2000);
        }
    });
})();

// La firma se adjunta desde la hoja de ingreso sin abrir la edición del ingreso.
document.addEventListener('submit', async evento => {
    const form=evento.target.closest('form[data-firma-en-linea]');
    if (!form || evento.defaultPrevented) return;
    evento.preventDefault();
    if (!form.reportValidity()) return;
    const boton=form.querySelector('button');
    if (boton.disabled) return;
    const aviso=form.querySelector('[data-resultado-firma]') || document.createElement('p');
    aviso.dataset.resultadoFirma=''; aviso.setAttribute('role','status');
    form.append(aviso); aviso.className='text-muted mt-2'; aviso.textContent='Subiendo documento…';
    boton.disabled=true;
    try {
        const respuesta=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
        const datos=await respuesta.json();
        if (!respuesta.ok || !datos.url) throw new Error(Object.values(datos.errors || {}).flat().join(' ') || datos.message || 'No se pudo guardar el documento.');
        const seccion=form.closest('section');
        const estado=seccion?.querySelector('.doc-pastilla');
        if (estado) { estado.className='doc-pastilla doc-pastilla-exito'; estado.textContent='Firmado'; }
        form.replaceChildren();
        aviso.className='text-success mt-2'; aviso.textContent=datos.mensaje;
        const enlace=document.createElement('a'); enlace.href='#'; enlace.dataset.verFirmaEnlinea=datos.contenido; enlace.dataset.tipoFirma=datos.tipo; enlace.textContent='Ver documento'; enlace.className='btn btn-outline-primary btn-sm';
        form.append(aviso,enlace);
    } catch(error) { aviso.className='text-danger mt-2'; aviso.textContent=error.message || 'No se pudo subir el archivo. Inténtelo nuevamente.'; boton.disabled=false; }
});

document.addEventListener('click',evento=>{
    const boton=evento.target.closest('[data-editar-ingreso-enlinea]');
    if(!boton) return;
    const editor=document.getElementById(boton.dataset.editarIngresoEnlinea);
    if(!editor) return;
    if(!editor.hidden && !boton.closest('form')) { editor.requestSubmit(); return; }
    editor.hidden=!editor.hidden;
    const pie=editor.closest('.modal-content')?.querySelector('.modal-footer [data-editar-ingreso-enlinea]');
    if(pie)pie.innerHTML=editor.hidden ? '<i class="bi bi-pencil"></i> Editar' : '<i class="bi bi-check-lg"></i> Confirmar cambios';
    if(!editor.hidden)editor.scrollIntoView({block:'nearest',behavior:'smooth'});
});
document.addEventListener('submit',async evento=>{
    const form=evento.target.closest('form[data-edicion-ingreso-enlinea]');
    if(!form || evento.defaultPrevented) return;
    evento.preventDefault(); if(!form.reportValidity()) return;
    const boton=form.closest('.modal-content')?.querySelector('.modal-footer [data-editar-ingreso-enlinea]') || form.querySelector('button'); if(boton.disabled)return;boton.disabled=true;
    const aviso=form.querySelector('[data-resultado-edicion]') || document.createElement('p');aviso.dataset.resultadoEdicion='';aviso.setAttribute('role','status');form.append(aviso);
    try {
        const respuesta=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
        const datos=await respuesta.json();
        if(!respuesta.ok || !datos.fecha)throw new Error(Object.values(datos.errors||{}).flat().join(' ')||datos.message||'No se pudieron confirmar los cambios.');
        const doc=form.closest('.doc-clinico');
        const fecha=doc?.querySelector('.doc-meta dd');if(fecha)fecha.textContent=datos.fecha;
        const motivo=doc?.querySelector('.doc-seccion .doc-texto');if(motivo)motivo.textContent=datos.motivo||'Sin registro';
        aviso.className='text-success mt-2';aviso.textContent=datos.mensaje;
        form.hidden=true;boton.innerHTML='<i class="bi bi-pencil"></i> Editar';doc?.append(aviso);
        // Marca «Se editó» en la lista de documentos, igual que las demás hojas, sin recargar.
        const modal=form.closest('.modal');
        if(datos.editado && modal){
            document.querySelectorAll(`[data-bs-target="#${modal.id}"]`).forEach(item=>{
                const columna=item.querySelector('.text-end');if(!columna)return;
                let marca=columna.querySelector('.documento-editado');
                if(!marca){marca=document.createElement('span');marca.className='d-block documento-editado';columna.append(marca);}
                marca.innerHTML='<i class="bi bi-pencil-square" aria-hidden="true"></i> '+datos.editado;
            });
        }
    }catch(error){aviso.className='text-danger mt-2';aviso.textContent=error.message||'No se pudo guardar.';}finally{boton.disabled=false;}
});

document.addEventListener('click',evento=>{
    const enlace=evento.target.closest('[data-ver-firma-enlinea]');if(!enlace)return;
    evento.preventDefault();
    const seccion=enlace.closest('section') || enlace.parentElement;
    let vista=seccion.querySelector('[data-vista-firma]');
    if(!vista){vista=document.createElement('div');vista.dataset.vistaFirma='';vista.hidden=true;seccion.append(vista);}
    vista.hidden=!vista.hidden;if(vista.hidden)return;
    if(!vista.children.length){const pdf=enlace.dataset.tipoFirma==='pdf';const visor=document.createElement(pdf?'iframe':'img');visor.src=enlace.dataset.verFirmaEnlinea;visor.style.width='100%';if(pdf){visor.style.height='65vh';visor.title='Documento de ingreso firmado';}else{visor.style.height='auto';visor.alt='Documento de ingreso firmado';}vista.append(visor);}
    vista.scrollIntoView({block:'nearest',behavior:'smooth'});
});

document.addEventListener('click',e=>{const b=e.target.closest('[data-editar-nota]');if(!b)return;const f=document.getElementById(b.dataset.editarNota);if(!f)return;if(f.hidden){f.hidden=false;b.innerHTML='<i class="bi bi-check-lg"></i> Confirmar cambios';f.scrollIntoView({block:'nearest'});}else f.requestSubmit();});

document.addEventListener('click',evento=>{
    const boton=evento.target.closest('[data-ver-archivo]');if(!boton)return;
    const vista=document.getElementById(boton.dataset.verArchivo);if(!vista)return;
    vista.hidden=!vista.hidden;boton.setAttribute('aria-expanded',String(!vista.hidden));
    boton.innerHTML=vista.hidden ? '<i class="bi bi-eye"></i> Ver' : '<i class="bi bi-eye-slash"></i> Ocultar';
    if(!vista.hidden){vista.querySelectorAll('[data-src]').forEach(visor=>{visor.src=visor.dataset.src;visor.removeAttribute('data-src');});}
});


document.querySelectorAll('[data-filtro-equipo]').forEach(grupo=>{
    const cargo=grupo.querySelector('[data-cargo-equipo]'),buscar=grupo.querySelector('[data-buscar-equipo]');
    const filas=[...grupo.querySelectorAll('[data-personal-cirugia]')],lista=grupo.querySelector('[data-equipo-seleccionado]');
    const normalizar=texto=>texto.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase().trim();
    const actualizar=()=>{
        let visibles=0;const seleccionados=filas.filter(f=>f.querySelector('input').checked);
        filas.forEach(fila=>{
            fila.hidden=Boolean(cargo.value && fila.dataset.cargo!==cargo.value)||!normalizar(fila.querySelector('span').textContent).includes(normalizar(buscar.value));if(!fila.hidden)visibles++;
            const agregado=fila.querySelector('input').checked,b=fila.querySelector('[data-agregar-equipo]');b.disabled=agregado||seleccionados.length>=30;b.innerHTML=agregado?'<i class="bi bi-check-lg"></i> Agregado':'<i class="bi bi-plus-lg"></i> Agregar';
        });
        lista.replaceChildren();
        if(!seleccionados.length){const vacio=document.createElement('span');vacio.className='text-muted';vacio.textContent='Aún no ha agregado personal.';lista.append(vacio);}
        seleccionados.forEach(fila=>{
            const item=document.createElement('div');item.className='d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded p-2';
            const texto=document.createElement('span');texto.textContent=fila.querySelector('strong').textContent.trim()+' · '+fila.dataset.cargo;
            const quitar=document.createElement('button');quitar.type='button';quitar.className='btn btn-outline-danger btn-sm';quitar.innerHTML='<i class="bi bi-x-lg"></i> Quitar';quitar.addEventListener('click',()=>{fila.querySelector('input').checked=false;actualizar();});item.append(texto,quitar);lista.append(item);
        });
        grupo.querySelector('[data-total-equipo]').textContent=seleccionados.length+' personas agregadas · Hasta 30 participantes.';
        grupo.querySelector('[data-equipo-vacio]').hidden=visibles!==0;
    };
    grupo.addEventListener('click',evento=>{const boton=evento.target.closest('[data-agregar-equipo]');if(!boton)return;boton.closest('[data-personal-cirugia]').querySelector('input').checked=true;actualizar();});
    cargo.addEventListener('change',actualizar);buscar.addEventListener('input',actualizar);actualizar();
});


const actualizarTrasladoNota=casilla=>{const caja=casilla.closest('.border').querySelector('[data-destino-nota]');if(!caja)return;caja.hidden=!casilla.checked;const select=caja.querySelector('select');select.disabled=!casilla.checked;select.required=casilla.checked;};
document.addEventListener('change',e=>{if(e.target.matches('[data-traslado-nota]'))actualizarTrasladoNota(e.target);});
document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('[data-traslado-nota]').forEach(actualizarTrasladoNota));

// Cirugía: una sola acción de edición y confirmación en el pie de la ventana.
function prepararEdicionCirugias() {
    document.querySelectorAll('[data-editor-cirugia]').forEach(modal => {
        const boton = modal.querySelector('[data-editar-cirugia]');
        if (!boton) return;
        const correccion = modal.querySelector('[data-correccion-cirugia]');
        const recuperacion = modal.querySelector('[data-form-recuperacion]');
        const detalleRecuperacion = recuperacion?.closest('details');
        const estado = modal.querySelector('[data-estado-cirugia]');
        const horario = modal.querySelector('[data-horario-cirugia]');
        const cambiarHorario = modal.querySelector('[data-cambiar-horario]');
        const camposEstado = estado?.querySelector('[data-campos-estado]');
        const accion = estado?.querySelector('[name="accion"]');
        const motivo = estado?.querySelector('[data-motivo-cancelacion]');
        const fechaEstado = estado?.querySelector('[name="fecha_hora"]');
        const ahoraGuatemala = () => new Intl.DateTimeFormat('sv-SE', {timeZone: 'America/Guatemala', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23'}).format(new Date()).replace(' ', 'T');
        accion?.addEventListener('change', () => {
            if (['iniciar', 'terminar', 'cancelar'].includes(accion.value)) {
                fechaEstado.value = ahoraGuatemala();
                fechaEstado.setCustomValidity('');
                fechaEstado.classList.remove('is-invalid');
            }
        });
        const valores = form => form ? JSON.stringify([...form.querySelectorAll('input[name]:not([type="hidden"]),select[name],textarea[name]')].map(campo => [campo.name, campo.type === 'checkbox' ? campo.checked : campo.value])) : '';
        const originales = new Map([[correccion, valores(correccion)], [estado, valores(estado)], [horario, valores(horario)], [recuperacion, valores(recuperacion)]]);
        let editando = false;
        let formularioPendiente = null;
        function actualizar() {
            const internarAlFinal = estado?.querySelector('[data-internar-al-final]');
            if (internarAlFinal) {
                const finalizando = accion?.value === 'terminar';
                internarAlFinal.hidden = !finalizando;
                internarAlFinal.disabled = !editando || !finalizando;
                const habitacion = internarAlFinal.querySelector('[data-habitacion-al-final]');
                const marcada = internarAlFinal.querySelector('[name="internar_al_finalizar"]')?.checked;
                if (habitacion) { habitacion.hidden = !marcada; const select = habitacion.querySelector('select'); select.disabled = !finalizando || !marcada; select.required = finalizando && marcada; }
            }
            const finalizando = accion?.value === 'terminar';
            const personalAlFinal = estado?.querySelector('[data-personal-al-final]');
            if (personalAlFinal) {
                personalAlFinal.hidden = !finalizando;
                personalAlFinal.disabled = !editando || !finalizando;
                // La lista del personal solo aparece si el médico decide editarlo.
                const lista = personalAlFinal.querySelector('[data-lista-personal-final]');
                const editarPersonal = personalAlFinal.querySelector('[data-mostrar-personal-final]')?.checked;
                if (lista) { lista.hidden = !editarPersonal; lista.disabled = !editarPersonal; }
            }
            // Orden de recuperación: solo si el paciente está internado o quedará internado al finalizar.
            const ordenAlFinal = estado?.querySelector('[data-orden-al-final]');
            if (ordenAlFinal) {
                const internado = ordenAlFinal.dataset.internado === '1' || Boolean(estado.querySelector('[name="internar_al_finalizar"]')?.checked);
                const visible = finalizando && internado;
                ordenAlFinal.hidden = !visible;
                ordenAlFinal.disabled = !editando || !visible;
                const campos = ordenAlFinal.querySelector('[data-campos-orden-final]');
                const agregar = ordenAlFinal.querySelector('[name="agregar_orden_recuperacion"]')?.checked;
                if (campos) { campos.hidden = !agregar; campos.disabled = !agregar; }
            }
            const cancelar = accion?.value === 'cancelar';
            const reprogramando = !accion?.value;
            if (fechaEstado) {
                fechaEstado.readOnly = ['iniciar', 'terminar', 'cancelar'].includes(accion.value);
                fechaEstado.closest('.col-12').hidden = cancelar;
                fechaEstado.required = !cancelar;
                if (cancelar) { fechaEstado.setCustomValidity(''); fechaEstado.classList.remove('is-invalid'); }
                if (reprogramando) {
                    fechaEstado.removeAttribute('max');
                    fechaEstado.min = ahoraGuatemala();
                } else {
                    fechaEstado.removeAttribute('min');
                    fechaEstado.max = ahoraGuatemala();
                }
                accion.required = !reprogramando;
                estado.querySelector('[data-etiqueta-fecha-cirugia]').textContent = reprogramando ? 'Fecha y hora programada' : 'Fecha y hora real';
            }
            if (motivo) {
                motivo.hidden = !cancelar;
                motivo.querySelector('input').disabled = !cancelar;
                motivo.querySelector('input').required = cancelar;
            }
            const cambioCorreccion = valores(correccion) !== originales.get(correccion);
            const permiteEstado = estado?.dataset.permiteEstado === '1';
            const puedeGuardarFecha = !accion?.value && estado?.dataset.permiteHorario === '1';
            const cambioEstado = ((permiteEstado && Boolean(accion?.value)) || puedeGuardarFecha) && valores(estado) !== originales.get(estado);
            const cambioHorario = valores(horario) !== originales.get(horario);
            const cambioRecuperacion = recuperacion && valores(recuperacion) !== originales.get(recuperacion);
            formularioPendiente = cambioRecuperacion ? recuperacion : cambioCorreccion ? correccion : (cambioHorario ? horario : (cambioEstado ? estado : null));
            if (horario) horario.querySelector('[name="fecha_hora"]').disabled = cambioEstado || cambioCorreccion;
            if (camposEstado) camposEstado.disabled = !editando;
            if (accion) accion.disabled = !permiteEstado;
            if (motivo) motivo.querySelector('input').disabled = !permiteEstado || !cancelar;
            correccion?.querySelectorAll('input:not([type="hidden"]),select,textarea').forEach(campo => campo.disabled = !editando || campo.hasAttribute('data-participante-guardado'));
            boton.disabled = editando && !formularioPendiente;
            boton.innerHTML = formularioPendiente ? '<i class="bi bi-check-lg"></i> Confirmar cambios' : (boton.dataset.enCurso === '1' ? '<i class="bi bi-check-lg"></i> Finalizar cirugía' : '<i class="bi bi-pencil"></i> Editar');
        }
        cambiarHorario?.addEventListener('click', () => {
            editando = true;
            horario.hidden = !horario.hidden;
            actualizar();
            if (!horario.hidden) horario.querySelector('[name="fecha_hora"]').focus();
        });
        [correccion, estado, horario, recuperacion].filter(Boolean).forEach(form => {
            form.addEventListener('input', actualizar);
            form.addEventListener('change', actualizar);
        });
        detalleRecuperacion?.addEventListener('toggle', () => {
            if (detalleRecuperacion.open) editando = true;
            actualizar();
        });
        boton.addEventListener('click', () => {
            if (!editando) {
                editando = true;
                if (boton.dataset.enCurso === '1' && accion) {
                    accion.value = 'terminar';
                    accion.dispatchEvent(new Event('change', {bubbles: true}));
                }
                if (detalleRecuperacion && !correccion) detalleRecuperacion.open = true;
                const bloque = correccion?.closest('.collapse');
                if (bloque) bootstrap.Collapse.getOrCreateInstance(bloque, {toggle: false}).show();
                actualizar();
            } else if (formularioPendiente) {
                if (formularioPendiente === correccion && estado) {
                    correccion.querySelectorAll('[data-cambio-estado-combinado]').forEach(campo => campo.remove());
                    if (valores(estado) !== originales.get(estado)) {
                        ['accion', 'fecha_hora', 'motivo_cancelacion'].forEach(nombre => {
                            const origen = estado.querySelector('[name="' + nombre + '"]');
                            if (!origen || origen.disabled) return;
                            const campo = document.createElement('input');
                            campo.type = 'hidden'; campo.name = nombre; campo.value = origen.value;
                            campo.dataset.cambioEstadoCombinado = '1';
                            correccion.append(campo);
                        });
                    }
                }
                formularioPendiente.requestSubmit();
            }
        });
        const filasIniciales = new Map([...modal.querySelectorAll('[data-form-recuperacion] [data-filas]')].map(lista => [lista, lista.innerHTML]));
        modal.addEventListener('hidden.bs.modal', evento => {
            if (evento.target !== modal || modal.dataset.abriendoArchivo === '1') return;
            filasIniciales.forEach((contenido, lista) => { lista.innerHTML = contenido; });
            [correccion, estado, horario, recuperacion].filter(Boolean).forEach(form => form.reset());
            if (detalleRecuperacion) detalleRecuperacion.open = false;
            const bloque = correccion?.closest('.collapse');
            if (bloque) bootstrap.Collapse.getOrCreateInstance(bloque, {toggle: false}).hide();
            editando = false;
            actualizar();
        });
        actualizar();
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', prepararEdicionCirugias);
else prepararEdicionCirugias();

document.addEventListener('submit', async evento => {
    const form = evento.target.closest('[data-cancelar-orden-cirugia]');
    if (!form) return;
    evento.preventDefault();
    if (form.dataset.enviando === '1') return;
    const modal = form.closest('.modal');
    const bloque = form.closest('[data-recuperacion-cirugia]');
    const cuerpo = modal.querySelector('.modal-body');
    const posicion = cuerpo.scrollTop;
    const boton = form.querySelector('button');
    form.dataset.enviando = '1';
    boton.disabled = true;
    const aviso = document.createElement('div');
    aviso.className = 'alert alert-info mt-2';
    aviso.setAttribute('role', 'status');
    aviso.textContent = 'Cancelando orden…';
    bloque.prepend(aviso);
    let cancelada = false;
    try {
        const respuesta = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.message || 'No se pudo cancelar la orden.');
        cancelada = true;
        aviso.className = 'alert alert-success mt-2';
        aviso.textContent = datos.mensaje;
        form.closest('details').remove();
        const pagina = await fetch(datos.vista, {headers: {'Accept': 'text/html'}});
        if (!pagina.ok) throw new Error('La orden se canceló. No se pudo actualizar el detalle.');
        const documento = new DOMParser().parseFromString(await pagina.text(), 'text/html');
        const actualizado = documento.getElementById(modal.id)?.querySelector('[data-recuperacion-cirugia]');
        if (!actualizado) throw new Error('La orden se canceló. No se pudo actualizar el detalle.');
        const resumen = bloque.querySelector('[data-resumen-recuperacion]');
        const resumenNuevo = actualizado.querySelector('[data-resumen-recuperacion]');
        if (!resumen || !resumenNuevo) throw new Error('La orden se canceló. No se pudo actualizar el resumen.');
        resumen.replaceWith(resumenNuevo);
        bloque.prepend(aviso);
        cuerpo.scrollTop = posicion;
    } catch (error) {
        aviso.className = cancelada ? 'alert alert-warning mt-2' : 'alert alert-danger mt-2';
        aviso.textContent = error.message || 'No se pudo completar la operación.';
        if (!cancelada) boton.disabled = false;
    } finally {
        delete form.dataset.enviando;
    }
});

// Cancelar o reactivar órdenes desde la ventana del documento: al marcar el cambio, «Editar» se vuelve «Confirmar cambios».
document.addEventListener('change', evento => {
    const casilla = evento.target.closest?.('[data-cambio-ordenes]');
    if (!casilla) return;
    const form = casilla.closest('[data-ordenes-documento]');
    const detalle = form.querySelector('[data-detalle-cambio-ordenes]');
    if (detalle) { detalle.hidden = !casilla.checked; detalle.disabled = !casilla.checked; }
    const pie = form.closest('.modal-content')?.querySelector('.modal-footer');
    const editar = pie?.querySelector('[data-editar-documento]');
    if (editar) editar.hidden = casilla.checked;
    const confirmar = pie?.querySelector(`[data-confirmar-ordenes="${form.id}"]`);
    if (confirmar) confirmar.hidden = !casilla.checked;
    if (casilla.checked) detalle?.querySelector('textarea')?.focus();
});
document.addEventListener('click', evento => {
    const boton = evento.target.closest('[data-confirmar-ordenes]');
    if (!boton || boton.disabled) return;
    document.getElementById(boton.dataset.confirmarOrdenes)?.requestSubmit();
});
document.addEventListener('submit', evento => {
    const form = evento.target.closest('[data-ordenes-documento]');
    if (!form || evento.defaultPrevented) return;
    // Evita guardar dos veces mientras se envía.
    document.querySelectorAll(`[data-confirmar-ordenes="${form.id}"]`).forEach(boton => { boton.disabled = true; });
});


// Un único visor para todos los PDF e imágenes del expediente.
(() => {
    const modal = document.getElementById('visorDocumentoSubido');
    if (!modal) return;
    let hojaAnterior = null;
    document.addEventListener('click', evento => {
        const enlace = evento.target.closest('[data-abrir-documento]');
        if (!enlace) return;
        evento.preventDefault();
        const url = enlace.dataset.archivoUrl || enlace.href;
        if (!url || new URL(url, location.href).origin !== location.origin) return;
        modal.querySelector('#visorDocumentoTitulo').textContent = enlace.dataset.tituloArchivo || 'Documento del expediente';
        modal.querySelector('[data-visor-nombre]').textContent = enlace.dataset.nombreArchivo || '';
        const visor = document.createElement(enlace.dataset.tipoArchivo === 'pdf' ? 'iframe' : 'img');
        visor.src = url;
        if (visor.tagName === 'IFRAME') {
            visor.title = enlace.dataset.tituloArchivo || 'Documento del expediente';
            visor.style.cssText = 'width:100%;height:70vh;border:0';
        } else {
            visor.alt = enlace.dataset.tituloArchivo || 'Imagen del expediente';
            visor.className = 'img-fluid rounded d-block mx-auto';
        }
        modal.querySelector('[data-visor-contenido]').replaceChildren(visor);
        const descargar = modal.querySelector('[data-visor-descargar]');
        descargar.href = url;
        descargar.download = enlace.dataset.nombreArchivo || 'documento';
        hojaAnterior = enlace.closest('.modal.show');
        const abrir = () => bootstrap.Modal.getOrCreateInstance(modal).show();
        if (hojaAnterior) {
            hojaAnterior.dataset.abriendoArchivo = '1';
            hojaAnterior.addEventListener('hidden.bs.modal', abrir, {once: true});
            bootstrap.Modal.getOrCreateInstance(hojaAnterior).hide();
        } else abrir();
    });
    modal.addEventListener('hidden.bs.modal', () => {
        modal.querySelector('[data-visor-contenido]').replaceChildren();
        if (hojaAnterior?.isConnected) {
            delete hojaAnterior.dataset.abriendoArchivo;
            bootstrap.Modal.getOrCreateInstance(hojaAnterior).show();
        }
        hojaAnterior = null;
    });
    document.addEventListener('DOMContentLoaded', () => document.querySelector('[data-documento-inicial]')?.click());
})();

// El filtro conserva las casillas seleccionadas y no modifica los datos de la cirugía.
document.querySelectorAll('[data-filtro-personal-casillas]').forEach(grupo => {
    const cargo = grupo.querySelector('[data-filtrar-cargo]');
    const buscar = grupo.querySelector('[data-filtrar-nombre]');
    const filas = [...grupo.querySelectorAll('[data-persona-filtrable]')];
    const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
    const filtrar = () => {
        filas.forEach(fila => {
            fila.hidden = Boolean(cargo.value && fila.dataset.cargo !== cargo.value)
                || !normalizar(fila.querySelector('strong').textContent).includes(normalizar(buscar.value));
        });
        grupo.querySelector('[data-personal-sin-resultados]').hidden = filas.some(fila => !fila.hidden);
    };
    cargo.addEventListener('change', filtrar);
    buscar.addEventListener('input', filtrar);
    filtrar();
});

// Documentos por día: en el teléfono se elige el día en una fila deslizable y se muestra solo ese día.
document.querySelectorAll('[data-dias-documentos]').forEach(contenedor => {
    const botones = [...contenedor.querySelectorAll('[data-elegir-dia]')];
    if (!botones.length) return;
    const lista = contenedor.querySelector('.selector-dias-lista');
    const centrar = (boton, comportamiento = 'auto') => {
        if (boton && lista && lista.clientWidth) lista.scrollTo({ left: boton.offsetLeft - lista.offsetLeft - (lista.clientWidth - boton.offsetWidth) / 2, behavior: comportamiento });
    };
    // Si la lista estaba plegada al cargar, se centra el día elegido al desplegarla.
    contenedor.closest('.collapse')?.addEventListener('shown.bs.collapse', () => centrar(botones.find(boton => boton.getAttribute('aria-pressed') === 'true')));
    const elegir = dia => {
        botones.forEach(boton => boton.setAttribute('aria-pressed', String(boton.dataset.elegirDia === dia)));
        contenedor.querySelectorAll(':scope > [data-dia]').forEach(seccion => seccion.classList.toggle('dia-activo', seccion.dataset.dia === dia));
        const activo = botones.find(boton => boton.dataset.elegirDia === dia);
        centrar(activo, 'smooth');
        const indice = botones.indexOf(activo);
        contenedor.querySelector('[data-dia-anterior]').disabled = indice <= 0;
        contenedor.querySelector('[data-dia-siguiente]').disabled = indice >= botones.length - 1;
    };
    const actual = () => botones.findIndex(boton => boton.getAttribute('aria-pressed') === 'true');
    botones.forEach(boton => boton.addEventListener('click', () => elegir(boton.dataset.elegirDia)));
    contenedor.querySelector('[data-dia-anterior]').addEventListener('click', () => elegir(botones[Math.max(0, actual() - 1)].dataset.elegirDia));
    contenedor.querySelector('[data-dia-siguiente]').addEventListener('click', () => elegir(botones[Math.min(botones.length - 1, actual() + 1)].dataset.elegirDia));
    contenedor.classList.add('con-selector-dias');
    elegir(botones[botones.length - 1].dataset.elegirDia);
});
