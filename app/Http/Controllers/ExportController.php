<?php

namespace App\Http\Controllers;

use App\Models\Paket;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Export packages data to Microsoft Excel compatible CSV.
     */
    public function exportCsv(Request $request)
    {
        $query = Paket::query();

        // Filters
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('created_at', '>=', $request->tanggal_mulai);
        }
        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('created_at', '<=', $request->tanggal_akhir);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('karung_id')) {
            if ($request->karung_id === 'null') {
                $query->whereNull('karung_id');
            } else {
                $query->where('karung_id', $request->karung_id);
            }
        }
        if ($request->filled('ekspedisi_asal')) {
            $query->where('ekspedisi_asal', $request->ekspedisi_asal);
        }

        $pakets = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="lubis21_rekap_paket_' . date('Ymd_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () use ($pakets) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Microsoft Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, [
                'Kode Resi Baru',
                'Resi Asli',
                'Ekspedisi Asal',
                'Nama Penerima',
                'Alamat Penerima',
                'Wilayah Tujuan',
                'Berat (kg)',
                'Harga Asli',
                'Harga Final',
                'Tipe Pembayaran',
                'Status Bayar COD',
                'Bukti Bayar COD',
                'Foto Bukti Delivery',
                'Status Paket',
                'ID Karung',
                'Tanggal Masuk'
            ]);

            // Data rows
            foreach ($pakets as $paket) {
                fputcsv($file, [
                    $paket->kode_resi_baru,
                    $paket->resi_asli,
                    $paket->ekspedisi_asal,
                    $paket->nama_penerima,
                    $paket->alamat_penerima,
                    $paket->wilayah_tujuan,
                    $paket->berat_kg,
                    $paket->harga_asli,
                    $paket->harga_final,
                    strtoupper($paket->tipe_pembayaran),
                    strtoupper($paket->status_pembayaran_cod ?? 'N/A'),
                    $paket->bukti_pembayaran_cod ?? '',
                    $paket->foto_bukti_delivery ?? '',
                    strtoupper($paket->status),
                    $paket->karung_id ?? '',
                    $paket->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
