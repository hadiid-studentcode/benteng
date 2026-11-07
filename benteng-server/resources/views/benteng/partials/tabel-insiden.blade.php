{{-- Tabel insiden. Butuh $incidents (sudah dilengkapi perintah & perangkat) dan $bisaBertindak. --}}
@php
    $relatif = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->diffForHumans();
@endphp
@if ($incidents->isEmpty())
    <div class="empty-state empty-state--success">
        <span class="empty-state__media"><x-ikon nama="centang-bulat" /></span>
        <h3 class="empty-state__title">Tidak ada insiden</h3>
        <p class="empty-state__text">{{ $kosong ?? 'Insiden akan muncul di sini ketika detektor menemukan aktivitas mencurigakan.' }}</p>
    </div>
@else
    <div class="table-responsive">
        <table class="table table--hover">
            <thead class="table__head--alt">
                <tr>
                    <th scope="col">Insiden</th>
                    <th scope="col">Perangkat</th>
                    <th scope="col">Terdeteksi</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($incidents as $i)
                    <tr data-id="{{ $i->id }}">
                        <td class="min-w-72">
                            <div class="flex items-start gap-3">
                                <div class="w-14 shrink-0"><x-tingkat :tingkat="$i->tingkat" /></div>
                                <div class="min-w-0">
                                    <a href="{{ route('benteng.insiden.detail', $i->id) }}" class="font-semibold hover:underline">{{ $i->judul }}</a>
                                    <p class="mt-0.5 text-xs text-muted-foreground">{{ config('benteng.jenis')[$i->jenis] ?? $i->jenis }}, ATT&amp;CK {{ $i->attck }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap">
                            <p class="font-medium">{{ $i->client_ip }}</p>
                            <p class="text-xs text-muted-foreground">{{ $i->perangkat?->hostname ?: 'Sensor '.$i->sensor }}</p>
                        </td>
                        <td class="whitespace-nowrap">
                            <p>{{ \Illuminate\Support\Carbon::parse($i->created_at)->format('H:i') }} WIB</p>
                            <p class="text-xs text-muted-foreground">{{ $relatif($i->created_at) }}</p>
                        </td>
                        <td>
                            <x-status-insiden :insiden="$i" />
                            @if ($i->perintah?->status === 'pending' && $i->status !== 'false_positive')
                                <p class="mt-1 text-xs text-warning-emphasis">Menunggu sensor</p>
                            @endif
                        </td>
                        <td>
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
