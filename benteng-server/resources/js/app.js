// Perilaku halaman dashboard BENTENG: komponen Stisla, modal konfirmasi, notifikasi,
// penyegaran otomatis, tooltip grafik/topologi, dan pencarian tabel.
import { Stisla } from '@stisla/vanilla';
import './app-shell.js';

const modal = document.getElementById('modal-konfirmasi');
const isi = document.querySelector('#isi[data-segar]');
const JUDUL = document.title;
const JEDA_SEGAR_MS = 5000;

// --- Mode terang/gelap ------------------------------------------------------------
// Tema awal dipasang di <head> (partials/tema.blade.php) agar halaman tidak berkedip.

document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-ganti-tema]')) {
        return;
    }
    const tema = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = tema;
    try {
        localStorage.setItem('benteng-tema', tema);
    } catch {
        // Penyimpanan browser diblokir: tema tetap berganti, hanya tidak diingat.
    }
});

// --- Notifikasi setelah tindakan ------------------------------------------------

// Ikon bawaan toast Stisla butuh pustaka Lucide; di sini cukup dua ikon statis.
const IKON_TOAST = {
    success: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
    danger: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
};

function ikonToast(varian) {
    const wadah = document.createElement('template');
    wadah.innerHTML = `<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${IKON_TOAST[varian]}</svg>`;
    return wadah.content.firstElementChild;
}

document.querySelectorAll('[data-flash]').forEach((flash) => {
    Stisla.toast({
        icon: ikonToast(flash.dataset.varian),
        title: flash.dataset.judul,
        description: flash.dataset.pesan,
        variant: flash.dataset.varian,
        delay: 6000,
    });
});

// --- Modal konfirmasi untuk form bertanda data-konfirmasi ----------------------

let formMenunggu = null;
const modalTerbuka = () => modal?.dataset.state === 'open';

function bukaModal(form) {
    formMenunggu = form;
    const varian = form.dataset.varian || 'utama';

    modal.querySelector('[data-modal-judul]').textContent = form.dataset.judul || 'Lanjutkan tindakan ini?';
    modal.querySelector('[data-modal-pesan]').textContent = form.dataset.pesan || '';

    // Rincian berasal dari data insiden, jadi selalu diisi lewat textContent.
    let rincian = [];
    try {
        rincian = JSON.parse(form.dataset.rincian || '[]');
    } catch {
        rincian = [];
    }
    const daftar = modal.querySelector('[data-modal-rincian]');
    daftar.replaceChildren(
        ...rincian.flatMap(([label, nilai]) => {
            const dt = document.createElement('dt');
            dt.className = 'text-muted-foreground';
            dt.textContent = label;
            const dd = document.createElement('dd');
            dd.className = 'font-medium break-words';
            dd.textContent = nilai;
            return [dt, dd];
        }),
    );
    daftar.hidden = rincian.length === 0;

    modal.querySelectorAll('[data-ikon-varian]').forEach((el) => {
        el.hidden = el.dataset.ikonVarian !== varian;
    });
    modal.querySelectorAll('[data-modal-ya]').forEach((tombol) => {
        tombol.hidden = tombol.dataset.modalYa !== varian;
        tombol.disabled = false;
        tombol.textContent = form.dataset.tombol || 'Ya, lanjutkan';
    });

    document.getElementById('pembuka-modal').click();
}

document.addEventListener('submit', (e) => {
    const form = e.target;

    if (modal && form.matches('[data-konfirmasi]') && form.dataset.terkonfirmasi !== '1') {
        e.preventDefault();
        bukaModal(form);
        return;
    }

    // Cegah klik ganda.
    const tombol = form.querySelector('button[type=submit]');
    if (tombol) {
        tombol.disabled = true;
        tombol.textContent = 'Memproses...';
    }
});

modal?.addEventListener('click', (e) => {
    const ya = e.target.closest('[data-modal-ya]');
    if (ya && formMenunggu) {
        ya.disabled = true;
        ya.textContent = 'Memproses...';
        formMenunggu.dataset.terkonfirmasi = '1';
        formMenunggu.requestSubmit();
    }
});

// --- Penyegaran otomatis isi halaman -----------------------------------------

function terapkanInfoSegar() {
    const info = document.querySelector('[data-segar-info]');
    if (!info) {
        return;
    }

    ['insiden', 'asing'].forEach((kunci) => {
        const lencana = document.querySelector(`[data-lencana="${kunci}"]`);
        if (lencana) {
            lencana.textContent = info.dataset[kunci];
            lencana.hidden = info.dataset[kunci] === '0';
        }
    });

    const aktif = Number(info.dataset.insiden || 0);
    document.title = aktif > 0 ? `(${aktif}) ${JUDUL}` : JUDUL;

    const teks = document.querySelector('[data-teks-segar]');
    if (teks) {
        teks.textContent = `Diperbarui ${info.dataset.waktu}`;
    }
}

function tandaiKoneksi(tersambung) {
    const titik = document.querySelector('[data-titik-segar]');
    titik?.classList.toggle('indicator--success', tersambung);
    titik?.classList.toggle('indicator--danger', !tersambung);
    if (!tersambung) {
        const teks = document.querySelector('[data-teks-segar]');
        if (teks) {
            teks.textContent = 'Koneksi ke server terputus';
        }
    }
}

let sedangMemuat = false;

async function segarkan() {
    if (!isi || sedangMemuat || document.hidden || modalTerbuka()) {
        return;
    }
    // Jangan ganti isi saat analis sedang mengarahkan kursor atau fokus ke tombol tindakan.
    if (isi.querySelector('[data-aksi]:hover, [data-aksi] :focus')) {
        return;
    }

    sedangMemuat = true;
    try {
        const res = await fetch(location.href, { headers: { 'X-Benteng-Sebagian': '1' }, cache: 'no-store' });
        if (res.redirected) {
            // Sesi habis: server mengarahkan ke halaman masuk.
            location.reload();
            return;
        }
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        const html = await res.text();
        const sebelumnya = new Set([...isi.querySelectorAll('[data-id]')].map((el) => el.dataset.id));
        isi.innerHTML = html;
        isi.querySelectorAll('[data-id]').forEach((el) => {
            if (!sebelumnya.has(el.dataset.id)) {
                el.classList.add('baru-muncul');
            }
        });

        terapkanInfoSegar();
        terapkanPencarian();
        tandaiKoneksi(true);
    } catch {
        tandaiKoneksi(false);
    } finally {
        sedangMemuat = false;
    }
}

terapkanInfoSegar();
if (isi) {
    setInterval(segarkan, JEDA_SEGAR_MS);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            segarkan();
        }
    });
}

// --- Tooltip untuk batang grafik dan perangkat di topologi ---------------------

const tip = document.getElementById('tip');

document.addEventListener('pointermove', (e) => {
    if (!tip) {
        return;
    }
    const sasaran = e.target.closest?.('[data-nilai]');
    if (!sasaran) {
        tip.hidden = true;
        return;
    }
    tip.querySelector('[data-tip-nilai]').textContent = sasaran.dataset.nilai;
    tip.querySelector('[data-tip-label]').textContent = sasaran.dataset.label;
    tip.hidden = false;
    tip.style.left = `${Math.min(e.clientX + 12, window.innerWidth - tip.offsetWidth - 8)}px`;
    tip.style.top = `${e.clientY - tip.offsetHeight - 12}px`;
});

// --- Pencarian di tabel (mis. halaman Perangkat) -------------------------------

function terapkanPencarian() {
    document.querySelectorAll('input[data-cari-tabel]').forEach((input) => {
        const tabel = document.querySelector(input.dataset.cariTabel);
        if (!tabel) {
            return;
        }
        const kata = input.value.trim().toLowerCase();
        let tampil = 0;
        tabel.querySelectorAll('tr[data-cari]').forEach((baris) => {
            const cocok = kata === '' || baris.dataset.cari.includes(kata);
            baris.hidden = !cocok;
            tampil += cocok ? 1 : 0;
        });
        const kosong = tabel.parentElement.querySelector('[data-cari-kosong]');
        if (kosong) {
            kosong.hidden = tampil > 0;
        }
    });
}

document.addEventListener('input', (e) => {
    if (e.target.matches('input[data-cari-tabel]')) {
        terapkanPencarian();
    }
});
