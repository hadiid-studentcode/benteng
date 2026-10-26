@props(['status'])
@switch($status)
    @case('dikarantina')
        <x-lencana warna="biru"><x-ikon nama="kunci" class="size-3" />Dikarantina</x-lencana>
        @break
    @case('insiden')
        <x-lencana warna="merah">Ada insiden</x-lencana>
        @break
    @case('asing')
        <x-lencana warna="kuning">Belum dikenal</x-lencana>
        @break
    @default
        <x-lencana warna="hijau">Normal</x-lencana>
@endswitch
