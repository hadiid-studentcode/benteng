@props(['insiden', 'bisaBertindak' => false, 'ringkas' => false])
{{-- Tombol tindakan insiden. Form bertanda data-konfirmasi dibuka lewat modal oleh resources/js/app.js. --}}
@php
    $i = $insiden;
    $asing = $i->jenis === 'perangkat_asing';
    $ukuran = $ringkas ? 'kecil' : 'sedang';
    $rincian = json_encode([
        ['Perangkat', $i->client_ip],
        ['Sensor', $i->sensor],
        ['Insiden', '#'.$i->id.' '.$i->judul],
    ]);
@endphp
@if (! $bisaBertindak)
    @unless ($ringkas)
        <p class="text-sm text-muted-foreground">Akun pengamat hanya dapat melihat. Tindakan dilakukan oleh analis.</p>
    @endunless
@elseif ($i->status === 'baru')
    <div class="flex items-center gap-2 {{ $ringkas ? 'flex-nowrap' : 'flex-wrap' }}" data-aksi>
        <form method="POST" action="{{ route('benteng.karantina', $i->id) }}"
            data-konfirmasi
            data-varian="bahaya"
            data-judul="Karantina perangkat {{ $i->client_ip }}?"
            data-pesan="Sensor {{ $i->sensor }} akan memutus perangkat ini dari internet dan jaringan satuan. Pengguna perangkat akan melihat halaman pemberitahuan karantina."
            data-tombol="Ya, karantina"
            data-rincian="{{ $rincian }}">
            @csrf
            <x-tombol type="submit" varian="bahaya" :ukuran="$ukuran"><x-ikon nama="kunci" class="size-4" />Karantina</x-tombol>
        </form>
        <form method="POST" action="{{ route('benteng.false-positive', $i->id) }}"
            data-konfirmasi
            data-varian="utama"
            data-judul="{{ $asing ? 'Daftarkan '.$i->client_ip.' sebagai perangkat sah?' : 'Tandai insiden ini bukan ancaman?' }}"
            data-pesan="{{ $asing ? 'Perangkat akan masuk daftar perangkat satuan dan tidak dilaporkan lagi sebagai perangkat asing.' : 'Insiden ditutup tanpa karantina. Pola yang sama tidak akan dilaporkan lagi untuk perangkat ini.' }}"
            data-tombol="{{ $asing ? 'Ya, daftarkan' : 'Ya, bukan ancaman' }}"
            data-rincian="{{ $rincian }}">
            @csrf
            <x-tombol type="submit" :ukuran="$ukuran">{{ $asing ? 'Daftarkan sah' : 'Bukan ancaman' }}</x-tombol>
        </form>
    </div>
@elseif ($i->status === 'dikarantina')
    <form method="POST" action="{{ route('benteng.lepas', $i->id) }}" data-aksi
        data-konfirmasi
        data-varian="info"
        data-judul="Lepas karantina {{ $i->client_ip }}?"
        data-pesan="Perangkat akan tersambung kembali ke jaringan. Lepas karantina hanya setelah perangkat diperiksa petugas dan dinyatakan aman."
        data-tombol="Ya, lepas karantina"
        data-rincian="{{ $rincian }}">
        @csrf
        <x-tombol type="submit" :ukuran="$ukuran"><x-ikon nama="buka-kunci" class="size-4" />Lepas karantina</x-tombol>
    </form>
@endif
