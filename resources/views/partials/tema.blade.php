{{-- Aplica el modo oscuro antes de dibujar la página para que no parpadee. La elección se guarda en este dispositivo. --}}
<script>
    try {
        if (localStorage.getItem('sanatorio:tema') === 'oscuro') {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
    } catch (error) {}
</script>
