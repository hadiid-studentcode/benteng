@props(['varian' => 'sekunder', 'ukuran' => 'sedang', 'href' => null])
@php
    $warna = [
        'utama' => 'button--primary',
        'bahaya' => 'button--danger',
        'info' => 'button--primary',
        'sekunder' => 'button--outline button--neutral',
        'teks' => 'button--ghost button--primary',
    ][$varian];
    $kelas = 'button '.$warna.($ukuran === 'kecil' ? ' button--sm' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $kelas]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $kelas]) }}>{{ $slot }}</button>
@endif
