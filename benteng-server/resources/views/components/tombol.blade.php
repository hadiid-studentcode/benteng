@props(['varian' => 'sekunder', 'ukuran' => 'sedang', 'href' => null])
@php
    $warna = [
        'utama' => 'bg-brand-600 text-white shadow-xs hover:bg-brand-700 focus-visible:outline-brand-600',
        'bahaya' => 'bg-red-600 text-white shadow-xs hover:bg-red-700 focus-visible:outline-red-600',
        'info' => 'bg-sky-600 text-white shadow-xs hover:bg-sky-700 focus-visible:outline-sky-600',
        'sekunder' => 'bg-white text-slate-700 shadow-xs ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus-visible:outline-slate-400',
        'teks' => 'text-brand-700 hover:bg-brand-50 focus-visible:outline-brand-600',
    ][$varian];
    $besar = [
        'kecil' => 'gap-1 px-2.5 py-1.5 text-xs',
        'sedang' => 'gap-1.5 px-3.5 py-2 text-sm',
    ][$ukuran];
    $kelas = "inline-flex items-center justify-center rounded-md font-semibold whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-wait disabled:opacity-60 $warna $besar";
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $kelas]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $kelas]) }}>{{ $slot }}</button>
@endif
