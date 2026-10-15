<script>
(() => {
    const form = document.getElementById('form-evolucion');
    if (!form) return;
    const indices = {};
    ['soluciones', 'medicamentos'].forEach(tipo => {
        const campos = [...document.getElementById('filas-' + tipo).querySelectorAll('[name]')];
        indices[tipo] = campos.reduce((maximo, campo) => {
            const indice = Number(campo.name.split('[')[2]?.split(']')[0]);
            return Number.isFinite(indice) ? Math.max(maximo, indice + 1) : maximo;
        }, 0);
    });
    const actualizarBotonSolucion = () => {
        const boton = form.querySelector('[data-agregar="soluciones"]');
        if (boton) boton.hidden = document.getElementById('filas-soluciones').children.length >= 1;
    };
    actualizarBotonSolucion();
    form.addEventListener('click', event => {
        const quitar = event.target.closest('[data-quitar]');
        if (quitar) {
            quitar.closest('.fila-orden').remove();
            actualizarBotonSolucion();
        }
        const agregar = event.target.closest('[data-agregar]');
        if (!agregar) return;
        const tipo = agregar.dataset.agregar;
        const contenedor = document.getElementById('filas-' + tipo);
        if (contenedor.children.length >= (tipo === 'soluciones' ? 1 : 50)) return;
        const plantilla = document.getElementById('plantilla-' + tipo).content.cloneNode(true);
        plantilla.querySelectorAll('[name]').forEach(campo => campo.name = campo.name.replace('__INDEX__', indices[tipo]));
        indices[tipo]++;
        contenedor.append(plantilla);
        actualizarBotonSolucion();
    });
})();
</script>

<script>
(() => {
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
})();
</script>
<script>
(() => {
    const resumen = document.getElementById('resumen-valoracion');
    const editor = document.getElementById('editor-valoracion');
    if (!resumen || !editor) return;
    const cambiarVista = editando => {
        resumen.hidden = editando;
        editor.hidden = !editando;
        const cuerpo = editor.closest('.modal-body');
        if (cuerpo) cuerpo.scrollTop = 0;
        if (editando) editor.querySelector('input:not([type="hidden"])')?.focus({preventScroll: true});
    };
    document.querySelector('[data-editar-valoracion]')?.addEventListener('click', () => cambiarVista(true));
    document.querySelector('[data-cancelar-valoracion]')?.addEventListener('click', () => cambiarVista(false));
})();
</script>