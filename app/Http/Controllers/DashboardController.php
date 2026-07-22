<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Paket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get consolidated dashboard statistics for Owner.
     */
    public function getRekap()
    {
        // 1. Core Metrics
        $totalPaket = Paket::count();
        $totalDelivered = Paket::where('status', 'delivered')->count();
        $totalProses = Paket::whereNotIn('status', ['delivered', 'dikembalikan_ke_sumenep'])->count();
        $totalReject = Paket::where('status', 'dikembalikan_ke_sumenep')->count();

        $pendapatanTerkumpul = Paket::where('status', 'delivered')->sum('harga_final');
        $pendapatanPotensial = Paket::sum('harga_final');

        // 2. Breakdown by Region (Wilayah Tujuan)
        $breakdownWilayah = Paket::select('wilayah_tujuan', DB::raw('count(*) as total'))
            ->groupBy('wilayah_tujuan')
            ->orderBy('total', 'desc')
            ->get();

        // 3. Breakdown by Courier Performance
        $kurirs = User::where('role', 'kurir')->get();
        $breakdownKurir = [];

        foreach ($kurirs as $kurir) {
            $assignedCount = Paket::where('kurir_id', $kurir->id)->count();
            $deliveredCount = Paket::where('kurir_id', $kurir->id)->where('status', 'delivered')->count();
            $pendingCount = $assignedCount - $deliveredCount;
            
            $breakdownKurir[] = [
                'id' => $kurir->id,
                'nama' => $kurir->nama,
                'wilayah' => $kurir->wilayah,
                'total_assigned' => $assignedCount,
                'total_delivered' => $deliveredCount,
                'total_pending' => $pendingCount,
                'success_rate' => $assignedCount > 0 ? round(($deliveredCount / $assignedCount) * 100, 1) : 0
            ];
        }

        // 4. Breakdown by Original Expedition (Ekspedisi Asal)
        $breakdownEkspedisi = Paket::select('ekspedisi_asal', DB::raw('count(*) as total'))
            ->groupBy('ekspedisi_asal')
            ->orderBy('total', 'desc')
            ->get();

        // 5. Monthly growth (last 6 months)
        $monthlyGrowth = Paket::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw("COUNT(*) as total")
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->limit(6)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'metrics' => [
                    'total_paket' => $totalPaket,
                    'total_delivered' => $totalDelivered,
                    'total_proses' => $totalProses,
                    'total_reject' => $totalReject,
                    'pendapatan_terkumpul' => $pendapatanTerkumpul,
                    'pendapatan_potensial' => $pendapatanPotensial,
                ],
                'wilayah' => $breakdownWilayah,
                'kurir' => $breakdownKurir,
                'ekspedisi' => $breakdownEkspedisi,
                'growth' => $monthlyGrowth
            ]
        ]);
    }
}
