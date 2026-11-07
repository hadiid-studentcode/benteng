<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MembacaDataPantauan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Jejak audit tindakan analis dan sensor.
 */
class AuditController extends Controller
{
    use MembacaDataPantauan;

    public function index(Request $request): View|string
    {
        $filter = in_array($request->query('pelaku'), ['analis', 'sensor'], true) ? $request->query('pelaku') : 'semua';

        $query = DB::table('audit_logs')->orderByDesc('id');
        if ($filter === 'sensor') {
            $query->where('pelaku', 'like', 'sensor:%');
        } elseif ($filter === 'analis') {
            $query->where('pelaku', 'not like', 'sensor:%');
        }

        return $this->tampilkan($request, 'benteng.audit', [
            'filter' => $filter,
            'ringkasan' => [
                'semua' => DB::table('audit_logs')->count(),
                'sensor' => DB::table('audit_logs')->where('pelaku', 'like', 'sensor:%')->count(),
                'gagal' => DB::table('audit_logs')->where('aksi', 'like', '% gagal')->count(),
            ],
            'audit' => $query->paginate(25)->withQueryString(),
        ]);
    }
}
