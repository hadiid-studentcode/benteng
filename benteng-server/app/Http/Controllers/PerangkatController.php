<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MembacaDataPantauan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar perangkat yang terlihat sensor, dari log DNS dan DHCP.
 */
class PerangkatController extends Controller
{
    use MembacaDataPantauan;

    public function index(Request $request): View|string
    {
        $filter = in_array($request->query('status'), ['bermasalah', 'asing'], true) ? $request->query('status') : 'semua';
        $semua = $this->daftarPerangkat();
        $bermasalah = fn ($p) => in_array($p->status, ['insiden', 'dikarantina'], true);

        // Insiden terpenting per perangkat, untuk tombol "Lihat insiden".
        $insidenPerPerangkat = $this->insidenUrut()
            ->whereIn('status', ['baru', 'dikarantina'])
            ->get(['id', 'sensor', 'client_ip'])
            ->groupBy(fn ($i) => $i->sensor.'|'.$i->client_ip)
            ->map(fn ($grup) => $grup->first()->id);

        return $this->tampilkan($request, 'benteng.perangkat', [
            'filter' => $filter,
            'jumlah' => [
                'semua' => $semua->count(),
                'bermasalah' => $semua->filter($bermasalah)->count(),
                'asing' => $semua->where('status', 'asing')->count(),
            ],
            'perStatus' => $semua->countBy('status'),
            'perangkat' => match ($filter) {
                'bermasalah' => $semua->filter($bermasalah)->values(),
                'asing' => $semua->where('status', 'asing')->values(),
                default => $semua,
            },
            'insidenPerPerangkat' => $insidenPerPerangkat,
        ]);
    }
}
