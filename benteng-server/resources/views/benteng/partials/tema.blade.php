{{-- Pasang tema sebelum halaman digambar agar tidak berkedip. Pilihan tersimpan di browser; bila belum pernah memilih, ikut pengaturan sistem. --}}
<script>
    (function () {
        var tema = null;
        try { tema = localStorage.getItem('benteng-tema'); } catch (e) {}
        if (tema !== 'light' && tema !== 'dark') {
            tema = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.dataset.theme = tema;
    })();
</script>
