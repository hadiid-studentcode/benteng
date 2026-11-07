@props(['warna' => 'abu'])
@php
    $kelas = [
        'merah' => 'badge--danger',
        'oranye' => 'badge--warning',
        'kuning' => 'badge--warning',
        'biru' => 'badge--info',
        'hijau' => 'badge--success',
        'abu' => '',
    ][$warna];
@endphp
<span {{ $attributes->merge(['class' => "badge badge--soft $kelas"]) }}>{{ $slot }}</span>
