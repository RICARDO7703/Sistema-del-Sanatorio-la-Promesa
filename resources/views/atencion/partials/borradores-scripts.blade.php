<script>
(() => {
    const prefijoSesion = 'sanatorio:borrador:' + @json(hash('sha256', session()->getId())) + ':';
    const prefijoIngreso = prefijoSesion + @json((string) $ingreso->getKey()) + ':';
    const guardado = @json(session('borrador_clinico_guardado'));
    const formularioConErrores = @json(old('_formulario'));
    const ingresoActivo = @json($ingreso->estado === 'Activo');
    const formularios = [...document.querySelectorAll('#form-evolucion, #modalValoracion form, form[data-borrador]')];
    const leer = clave => {
        try { return JSON.parse(sessionStorage.getItem(clave)); } catch { return null; }
    };
    const eliminar = clave => {
        try { sessionStorage.removeItem(clave); } catch {}
    };
    try {
        Object.keys(sessionStorage).filter(clave => clave.startsWith('sanatorio:borrador:') && !clave.startsWith(prefijoSesion)).forEach(eliminar);
        if (!ingresoActivo) Object.keys(sessionStorage).filter(clave => clave.startsWith(prefijoIngreso)).forEach(eliminar);
    } catch {}
    if (typeof guardado === 'string') eliminar(prefijoIngreso + guardado);
    if (!ingresoActivo) return;

    formularios.forEach(form => {
        const identificador = form.querySelector('[name="_formulario"]')?.value;
        if (!identificador) return;
        const clave = prefijoIngreso + identificador;
        const campos = () => [...form.querySelectorAll('input[name], textarea[name], select[name]')].filter(campo => campo.type !== 'hidden' && campo.type !== 'password' && campo.type !== 'file');
        const borrador = leer(clave);
        if (borrador && borrador.version === 1 && Array.isArray(borrador.campos) && formularioConErrores !== identificador) {
            ['soluciones', 'medicamentos'].forEach(tipo => {
                const contenedor = form.querySelector('[data-filas="' + tipo + '"]');
                const plantilla = form.querySelector('template[data-plantilla="' + tipo + '"]');
                if (!contenedor || !plantilla) return;
                const indices = borrador.filas?.[tipo];
                if (!Array.isArray(indices)) return;
                contenedor.replaceChildren();
                indices.slice(0, tipo === 'soluciones' ? 1 : 50).forEach(indice => {
                    if (!Number.isSafeInteger(indice) || indice < 0) return;
                    const fila = plantilla.content.cloneNode(true);
                    fila.querySelectorAll('[name]').forEach(campo => campo.name = campo.name.replace('__INDEX__', indice));
                    contenedor.append(fila);
                });
            });
            campos().forEach(campo => {
                const anterior = borrador.campos.find(dato => dato.nombre === campo.name && dato.tipo === campo.type && ((campo.type !== 'radio' && campo.type !== 'checkbox') || dato.valor === campo.value));
                if (!anterior || typeof anterior.valor !== 'string') return;
                if (campo.type === 'radio' || campo.type === 'checkbox') campo.checked = anterior.marcado === true;
                else if (campo.name === 'examen_fisico[peso]' && borrador.unidadPeso !== 'lb' && anterior.valor.trim() !== '' && Number.isFinite(Number(anterior.valor))) campo.value = (Number(anterior.valor) * 2.2046226218).toFixed(2);
                else campo.value = anterior.valor;
            });
            const aviso = form.querySelector('[data-aviso-borrador]');
            if (aviso) aviso.hidden = false;
        }
        let modificado = formularioConErrores === identificador || Boolean(borrador);
        const conservar = () => {
            if (!modificado) return;
            const filas = {};
            ['soluciones', 'medicamentos'].forEach(tipo => {
                const contenedor = form.querySelector('[data-filas="' + tipo + '"]');
                if (!contenedor) return;
                filas[tipo] = [...contenedor.querySelectorAll('.fila-orden')].map(fila => Number(fila.querySelector('[name]')?.name.split('[')[2]?.split(']')[0])).filter(Number.isSafeInteger);
            });
            const datos = {version: 1, unidadPeso: 'lb', campos: campos().map(campo => ({nombre: campo.name, tipo: campo.type, valor: campo.value, marcado: campo.checked === true})), filas};
            try { sessionStorage.setItem(clave, JSON.stringify(datos)); } catch {}
        };
        const cambiar = () => { modificado = true; conservar(); };
        form.addEventListener('input', cambiar);
        form.addEventListener('change', cambiar);
        form.addEventListener('click', evento => {
            if (evento.target.closest('[data-agregar], [data-quitar]')) {
                modificado = true;
                queueMicrotask(conservar);
            }
        });
        form.querySelector('[data-descartar-borrador]')?.addEventListener('click', () => {
            form.reset();
            form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(campo => campo.dispatchEvent(new Event('change', {bubbles: true})));
            form.querySelectorAll('.is-invalid').forEach(campo => campo.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback').forEach(aviso => aviso.classList.remove('d-block'));
            modificado = false;
            eliminar(clave);
            form.querySelectorAll('[data-aviso-borrador]').forEach(aviso => { aviso.hidden = true; });
            document.querySelectorAll('[data-aviso-borrador-pagina="' + identificador + '"]').forEach(aviso => { aviso.hidden = true; });
        });
        if (borrador && formularioConErrores !== identificador) {
            document.querySelectorAll('[data-aviso-borrador-pagina="' + identificador + '"]').forEach(aviso => { aviso.hidden = false; });
        }
        form.closest('.modal')?.addEventListener('hide.bs.modal', conservar);
        window.addEventListener('pagehide', conservar);
        if (formularioConErrores === identificador) conservar();
    });
})();
</script>
