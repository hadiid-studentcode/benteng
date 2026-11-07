@props(['insiden'])
@switch($insiden->status)
    @case('baru')
        <x-lencana warna="merah">Perlu tindakan</x-lencana>
        @break
    @case('dikarantina')
        <x-lencana warna="biru"><x-ikon nama="kunci" class="size-3" />Dikarantina</x-lencana>
        @break
    @case('diblokir')
        <x-lencana warna="biru"><x-ikon nama="larangan" class="size-3" />Domain diblokir</x-lencana>
        @break
    @default
        <x-lencana warna="abu">{{ $insiden->jenis === 'perangkat_asing' ? 'Perangkat sah' : 'Bukan ancaman' }}</x-lencana>
@endswitch
