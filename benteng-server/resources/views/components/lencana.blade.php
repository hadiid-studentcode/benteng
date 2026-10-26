@props(['warna' => 'abu'])
@php
    $kelas = [
        'merah' => 'bg-red-50 text-red-700 ring-red-600/20',
        'oranye' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'kuning' => 'bg-amber-50 text-amber-800 ring-amber-600/25',
        'biru' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'hijau' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'abu' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    ][$warna];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset $kelas"]) }}>{{ $slot }}</span>
