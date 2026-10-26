@props(['tingkat'])
@if ($tingkat === 'kritis')
    <x-lencana warna="merah">Kritis</x-lencana>
@elseif ($tingkat === 'tinggi')
    <x-lencana warna="oranye">Tinggi</x-lencana>
@else
    <x-lencana warna="abu">{{ ucfirst($tingkat) }}</x-lencana>
@endif
