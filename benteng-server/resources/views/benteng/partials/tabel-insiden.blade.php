{{-- Tabel insiden. Butuh $incidents (sudah dilengkapi perintah & perangkat) dan $bisaBertindak. --}}
@php
    $relatif = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->diffForHumans();
@endphp
@if ($incidents->isEmpty())
    <div class="flex flex-col items-center px-6 py-12 text-center">
        <span class="flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><x-ikon nama="centang-bulat" class="size-6" /></span>
        <p class="mt-3 text-sm font-semibold text-slate-800">Tidak ada insiden</p>
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $kosong ?? 'Insiden akan muncul di sini ketika detektor menemukan aktivitas mencurigakan.' }}</p>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3">Insiden</th>
                    <th scope="col" class="px-3 py-3">Perangkat</th>
                    <th scope="col" class="px-3 py-3">Terdeteksi</th>
                    <th scope="col" class="px-3 py-3">Status</th>
                    <th scope="col" class="px-5 py-3 text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($incidents as $i)
                    <tr data-id="{{ $i->id }}" class="align-top">
                        <td class="min-w-72 px-5 py-3.5">
                            <div class="flex items-start gap-3">
                                <x-tingkat :tingkat="$i->tingkat" />
                                <div class="min-w-0">
                                    <a href="{{ route('benteng.insiden.detail', $i->id) }}" class="font-semibold text-slate-900 hover:text-brand-700 hover:underline">{{ $i->judul }}</a>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ config('benteng.jenis')[$i->jenis] ?? $i->jenis }}, ATT&amp;CK {{ $i->attck }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3.5 whitespace-nowrap">
                            <p class="font-medium text-slate-800">{{ $i->client_ip }}</p>
                            <p class="text-xs text-slate-500">{{ $i->perangkat?->hostname ?: 'Sensor '.$i->sensor }}</p>
                        </td>
                        <td class="px-3 py-3.5 whitespace-nowrap">
                            <p class="text-slate-700">{{ \Illuminate\Support\Carbon::parse($i->created_at)->format('H:i') }} WIB</p>
                            <p class="text-xs text-slate-500">{{ $relatif($i->created_at) }}</p>
                        </td>
                        <td class="px-3 py-3.5">
                            <x-status-insiden :insiden="$i" />
                            @if ($i->perintah?->status === 'pending' && $i->status !== 'false_positive')
                                <p class="mt-1 text-xs text-amber-700">Menunggu sensor</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-2">
                                <x-aksi-insiden :insiden="$i" :bisa-bertindak="$bisaBertindak" ringkas />
                                <x-tombol ukuran="kecil" :href="route('benteng.insiden.detail', $i->id)">Detail</x-tombol>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
