{{-- Diagram topologi: server pusat -> sensor -> perangkat. Butuh $topologi, $detektor, $sekarang. --}}
@php
    $SX = 330; $SW = 220; $DX = 690; $DW = 300; $DH = 50; $DG = 10;
    $BX = ($SX + $SW + $DX) / 2;
    $blokH = 164;
    $atas = 40;
    $y = $atas;
    $grup = [];
    foreach ($topologi as $t) {
        $baris = max(1, count($t['perangkat']) + ($t['lainnya'] ? 1 : 0));
        $devH = $baris * $DH + ($baris - 1) * $DG;
        $tinggi = max($blokH, $devH);
        $blokTop = $y + ($tinggi - $blokH) / 2;
        $grup[] = ['t' => $t, 'internetY' => $blokTop, 'sensorY' => $blokTop + 60, 'devTop' => $y + ($tinggi - $devH) / 2];
        $y += $tinggi + 40;
    }
    $isiBawah = $y - 40;
    $serverH = 112;
    $serverY = ($atas + $isiBawah) / 2 - $serverH / 2;
    $serverMid = $serverY + $serverH / 2;
    $tinggiSvg = $isiBawah + 56;
    $serverInfo = config('benteng.server_info');

    // Kelas lengkap per status (ditulis utuh agar terbaca Tailwind).
    $gaya = [
        'normal' => ['kotak' => 'stroke-border-strong', 'cabang' => 'stroke-success', 'titik' => 'fill-success', 'lencana' => 'fill-success', 'teks' => 'fill-success-emphasis', 'label' => 'Normal', 'garis' => null],
        'insiden' => ['kotak' => 'stroke-danger', 'cabang' => 'stroke-danger', 'titik' => 'fill-danger', 'lencana' => 'fill-danger', 'teks' => 'fill-danger-emphasis', 'label' => 'Ada insiden', 'garis' => null],
        'dikarantina' => ['kotak' => 'stroke-info', 'cabang' => 'stroke-info', 'titik' => 'fill-info', 'lencana' => 'fill-info', 'teks' => 'fill-info-emphasis', 'label' => 'Dikarantina', 'garis' => '5 5'],
        'asing' => ['kotak' => 'stroke-warning', 'cabang' => 'stroke-warning', 'titik' => 'fill-warning', 'lencana' => 'fill-warning', 'teks' => 'fill-warning-emphasis', 'label' => 'Belum dikenal', 'garis' => '2 4'],
    ];
@endphp
@if ($topologi->isEmpty())
    <div class="rounded-lg border border-dashed border-border-strong px-4 py-10 text-center text-sm text-muted-foreground">
        Belum ada sensor yang melapor. Diagram muncul setelah sensor mengirim data DNS atau DHCP.
    </div>
@else
    <div class="overflow-x-auto">
        <svg class="block h-auto w-full min-w-[760px] font-sans" viewBox="0 0 1000 {{ $tinggiSvg }}" role="img" aria-labelledby="topologi-judul topologi-desk">
            <title id="topologi-judul">Topologi jaringan BENTENG</title>
            <desc id="topologi-desk">Server pusat terhubung ke {{ $topologi->count() }} sensor. @foreach ($topologi as $t)Sensor {{ $t['nama'] }} {{ $t['online'] ? 'online' : 'tidak merespons' }} dengan {{ count($t['perangkat']) + $t['lainnya'] }} perangkat. @endforeach</desc>

            {{-- Judul kolom --}}
            <g aria-hidden="true" class="fill-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                <text x="16" y="16">Server satuan</text>
                <text x="{{ $SX }}" y="16">Tepi jaringan</text>
                <text x="{{ $DX }}" y="16">Perangkat di jaringan</text>
            </g>

            @foreach ($grup as $g)
                @php
                    $t = $g['t'];
                    $sy = $g['sensorY'];
                    $sMid = $sy + 52;
                    $tengahPerangkat = collect($t['perangkat'])->keys()->map(fn ($k) => $g['devTop'] + $k * ($DH + $DG) + $DH / 2);
                    $trunkAtas = min($sMid, $tengahPerangkat->min() ?? $sMid);
                    $trunkBawah = max($sMid, $tengahPerangkat->max() ?? $sMid);
                    $detikDetak = $t['detak'] ? max(0, (int) $t['detak']->diffInSeconds($sekarang)) : null;
                @endphp

                {{-- Server ke sensor --}}
                <path d="M 206 {{ $serverMid }} C 268 {{ $serverMid }}, 268 {{ $sMid }}, {{ $SX }} {{ $sMid }}" fill="none" stroke-width="2"
                    class="{{ $t['online'] ? 'stroke-success tautan-alir' : 'stroke-danger' }}" @unless ($t['online']) stroke-dasharray="4 6" @endunless />
                <text x="268" y="{{ min($serverMid, $sMid) - 10 }}" text-anchor="middle" class="text-[11px] {{ $t['online'] ? 'fill-success-emphasis' : 'fill-danger-emphasis' }}">{{ $t['online'] ? 'terhubung' : 'terputus' }}</text>

                {{-- Internet ke sensor --}}
                <line x1="{{ $SX + $SW / 2 }}" y1="{{ $g['internetY'] + 34 }}" x2="{{ $SX + $SW / 2 }}" y2="{{ $sy }}" class="stroke-border-strong" stroke-width="2" />
                <rect x="{{ $SX + 30 }}" y="{{ $g['internetY'] }}" width="160" height="34" rx="17" class="fill-surface-2 stroke-border-strong" stroke-dasharray="3 3" />
                <g transform="translate({{ $SX + 62 }} {{ $g['internetY'] + 10 }}) scale(.6)" aria-hidden="true" fill="none" stroke-width="2.4" class="stroke-muted-foreground">
                    <circle cx="12" cy="12" r="10" /><path d="M2 12 H22 M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                </g>
                <text x="{{ $SX + $SW / 2 + 8 }}" y="{{ $g['internetY'] + 22 }}" text-anchor="middle" class="fill-muted-foreground text-[13px]">Internet</text>

                {{-- Sensor --}}
                <rect x="{{ $SX }}" y="{{ $sy }}" width="{{ $SW }}" height="104" rx="10" class="fill-surface {{ $t['online'] ? 'stroke-border-strong' : 'stroke-danger' }}" />
                <g transform="translate({{ $SX + 14 }} {{ $sy + 12 }}) scale(.8)" aria-hidden="true">
                    <path d="M16 3 L27 7 V15 C27 22 22 27 16 29 C10 27 5 22 5 15 V7 Z" fill="none" stroke-width="2.5" stroke-linejoin="round" class="stroke-primary" />
                    <path d="M11 16 L15 20 L21 12" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="stroke-primary" />
                </g>
                <text x="{{ $SX + 50 }}" y="{{ $sy + 28 }}" class="fill-foreground text-[14px] font-semibold">Sensor {{ $t['nama'] }}</text>
                <text x="{{ $SX + 50 }}" y="{{ $sy + 45 }}" class="fill-muted-foreground text-[11px]">{{ $t['info']['model'] ?? 'Sensor tepi jaringan' }}</text>
                <text x="{{ $SX + 16 }}" y="{{ $sy + 70 }}" class="fill-muted-foreground text-[12px]">LAN {{ $t['info']['lan'] ?? '-' }}</text>
                <circle cx="{{ $SX + 20 }}" cy="{{ $sy + 87 }}" r="4" class="{{ $t['online'] ? 'fill-success' : 'fill-danger' }}" />
                <text x="{{ $SX + 30 }}" y="{{ $sy + 91 }}" class="fill-muted-foreground text-[11px]">{{ $t['online'] ? 'Online' : 'Tidak merespons' }}{{ $detikDetak !== null ? ', '.($detikDetak < 60 ? $detikDetak.' dtk' : intdiv($detikDetak, 60).' mnt').' lalu' : '' }}</text>

                {{-- Sensor ke perangkat --}}
                <path d="M {{ $SX + $SW }} {{ $sMid }} H {{ $BX }}" fill="none" class="stroke-border-strong" stroke-width="2" />
                <line x1="{{ $BX }}" y1="{{ $trunkAtas }}" x2="{{ $BX }}" y2="{{ $trunkBawah }}" class="stroke-border-strong" stroke-width="2" />
                @foreach ($t['perangkat'] as $k => $p)
                    @php
                        $dy = $g['devTop'] + $k * ($DH + $DG);
                        $dc = $dy + $DH / 2;
                        $s = $gaya[$p->status];
                        $nama = $p->hostname ? \Illuminate\Support\Str::limit($p->hostname, 22, '...') : 'tanpa nama';
                    @endphp
                    <path d="M {{ $BX }} {{ $dc }} H {{ $DX }}" fill="none" stroke-width="2" class="{{ $s['cabang'] }}" @if ($s['garis']) stroke-dasharray="{{ $s['garis'] }}" @endif />
                    @if ($p->status === 'dikarantina')
                        @php $cx = ($BX + $DX) / 2; @endphp
                        <g aria-hidden="true">
                            <circle cx="{{ $cx }}" cy="{{ $dc }}" r="9" class="fill-surface stroke-info" stroke-width="2" />
                            <path d="M {{ $cx - 4 }} {{ $dc - 4 }} L {{ $cx + 4 }} {{ $dc + 4 }} M {{ $cx + 4 }} {{ $dc - 4 }} L {{ $cx - 4 }} {{ $dc + 4 }}" class="stroke-info" stroke-width="2" stroke-linecap="round" />
                        </g>
                    @endif
                    <g class="group cursor-default" data-nilai="{{ $p->ip }}: {{ $s['label'] }}" data-label="{{ $p->hostname ?: 'tanpa nama host' }}, MAC {{ $p->mac ?? 'belum dilaporkan' }}, {{ number_format($p->jumlah, 0, ',', '.') }} query DNS">
                        <rect x="{{ $DX }}" y="{{ $dy }}" width="{{ $DW }}" height="{{ $DH }}" rx="8" class="fill-surface group-hover:fill-surface-2 {{ $s['kotak'] }}" />
                        <circle cx="{{ $DX + 26 }}" cy="{{ $dc }}" r="15" fill-opacity=".12" class="{{ $s['titik'] }}" />
                        <g transform="translate({{ $DX + 18 }} {{ $dc - 8 }}) scale(.667)" aria-hidden="true" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="{{ $s['cabang'] }}">
                            <rect x="2" y="3" width="20" height="14" rx="2" /><path d="M8 21 H16 M12 17 V21" />
                        </g>
                        <text x="{{ $DX + 50 }}" y="{{ $dy + 21 }}" class="fill-foreground text-[13px] font-semibold">{{ $p->ip }}</text>
                        <text x="{{ $DX + 50 }}" y="{{ $dy + 38 }}" class="fill-muted-foreground text-[11px]">{{ $nama }}</text>
                        <rect x="{{ $DX + $DW - 94 }}" y="{{ $dy + 15 }}" width="84" height="20" rx="6" fill-opacity=".12" class="{{ $s['lencana'] }}" />
                        <text x="{{ $DX + $DW - 52 }}" y="{{ $dy + 29 }}" text-anchor="middle" class="{{ $s['teks'] }} text-[11px] font-semibold">{{ $s['label'] }}</text>
                    </g>
                @endforeach
                @if ($t['lainnya'])
                    @php $dy = $g['devTop'] + count($t['perangkat']) * ($DH + $DG); @endphp
                    <text x="{{ $DX + 16 }}" y="{{ $dy + 30 }}" class="fill-muted-foreground text-[12px]">dan {{ $t['lainnya'] }} perangkat lain (lihat menu Perangkat)</text>
                @endif
                @if (! count($t['perangkat']))
                    <text x="{{ $DX + 16 }}" y="{{ $g['devTop'] + 30 }}" class="fill-muted-foreground text-[12px]">Belum ada perangkat terlihat</text>
                @endif
            @endforeach

            {{-- Server pusat --}}
            <rect x="16" y="{{ $serverY }}" width="190" height="{{ $serverH }}" rx="10" class="fill-surface stroke-border-strong" />
            <g transform="translate(30 {{ $serverY + 14 }})" aria-hidden="true" class="stroke-primary" fill="none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round">
                <rect x="1" y="1" width="22" height="15" rx="2" />
                <path d="M8 21 H16 M12 16 V21" />
            </g>
            <text x="62" y="{{ $serverY + 28 }}" class="fill-foreground text-[14px] font-semibold">{{ $serverInfo['nama'] }}</text>
            <text x="62" y="{{ $serverY + 45 }}" class="fill-muted-foreground text-[11px]">{{ $serverInfo['keterangan'] }}</text>
            <text x="30" y="{{ $serverY + 70 }}" class="fill-muted-foreground text-[12px]">{{ $serverInfo['alamat'] }}</text>
            <circle cx="34" cy="{{ $serverY + 89 }}" r="4" class="{{ $detektor['jalan'] ? 'fill-success' : 'fill-warning' }}" />
            <text x="44" y="{{ $serverY + 93 }}" class="fill-muted-foreground text-[11px]">Detektor {{ $detektor['jalan'] ? 'berjalan' : 'tidak berjalan' }}</text>

            {{-- Keterangan warna --}}
            @php $ly = $isiBawah + 32; @endphp
            <g aria-hidden="true" class="fill-muted-foreground text-[12px]">
                <circle cx="22" cy="{{ $ly - 4 }}" r="5" class="fill-success" /><text x="34" y="{{ $ly }}">Normal</text>
                <circle cx="112" cy="{{ $ly - 4 }}" r="5" class="fill-danger" /><text x="124" y="{{ $ly }}">Ada insiden</text>
                <circle cx="222" cy="{{ $ly - 4 }}" r="5" class="fill-info" /><text x="234" y="{{ $ly }}">Dikarantina (koneksi diputus)</text>
                <circle cx="446" cy="{{ $ly - 4 }}" r="5" class="fill-warning" /><text x="458" y="{{ $ly }}">Belum dikenal (perangkat asing)</text>
            </g>
        </svg>
    </div>
@endif
