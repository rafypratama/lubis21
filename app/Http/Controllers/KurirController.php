<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Paket;
use App\Models\RuteHarian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class KurirController extends Controller
{
    /**
     * Start the daily route optimization.
     */
    public function mulaiRute(Request $request, $id)
    {
        $kurir = User::findOrFail($id);
        if ($kurir->role !== 'kurir') {
            return response()->json(['status' => 'error', 'message' => 'User bukan kurir'], 422);
        }

        // Get all packages assigned to this kurir in status 'dalam_pengiriman' or 'menunggu_pembayaran_cod'
        $pakets = Paket::where('kurir_id', $kurir->id)
            ->whereIn('status', ['dalam_pengiriman', 'menunggu_pembayaran_cod'])
            ->get();

        if ($pakets->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada paket aktif untuk dikirim hari ini.'
            ], 422);
        }

        // Deactivate any currently active routes
        RuteHarian::where('kurir_id', $kurir->id)
            ->where('status', 'berjalan')
            ->update(['status' => 'selesai']);

        // Optimize the route sequence
        $sortedPakets = $this->optimizeRoute(
            $pakets->all(),
            $kurir->last_latitude ? (float) $kurir->last_latitude : null,
            $kurir->last_longitude ? (float) $kurir->last_longitude : null
        );

        $urutanIds = array_map(fn($p) => $p->id, $sortedPakets);

        // Create new route
        $rute = RuteHarian::create([
            'kurir_id' => $kurir->id,
            'tanggal' => date('Y-m-d'),
            'daftar_paket_urutan' => $urutanIds,
            'status' => 'berjalan'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Rute berhasil dioptimalkan dan dimulai',
            'data' => [
                'rute' => $rute,
                'pakets' => $sortedPakets
            ]
        ]);
    }

    /**
     * Get active route for a courier.
     */
    public function ruteAktif($id)
    {
        $rute = RuteHarian::where('kurir_id', $id)
            ->where('status', 'berjalan')
            ->first();

        if (!$rute) {
            return response()->json([
                'status' => 'success',
                'data' => null
            ]);
        }

        // Load packages in the specific optimized order
        $paketIds = $rute->daftar_paket_urutan;
        
        $pakets = Paket::whereIn('id', $paketIds)->get()->sortBy(function ($paket) use ($paketIds) {
            return array_search($paket->id, $paketIds);
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'rute' => $rute,
                'pakets' => $pakets
            ]
        ]);
    }

    /**
     * Update courier live GPS location.
     */
    public function updateLokasi(Request $request, $id)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric'
        ]);

        $kurir = User::findOrFail($id);
        $kurir->last_latitude = $request->latitude;
        $kurir->last_longitude = $request->longitude;
        $kurir->last_location_updated_at = now();
        $kurir->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi kurir berhasil diperbarui'
        ]);
    }

    /**
     * Confirm COD payment by uploading receipt.
     */
    public function konfirmasiCod(Request $request, $id)
    {
        $request->validate([
            'bukti_pembayaran_cod' => 'required|image|max:4096'
        ]);

        $paket = Paket::findOrFail($id);

        if ($request->hasFile('bukti_pembayaran_cod')) {
            $path = $request->file('bukti_pembayaran_cod')->store('bukti_cod', 'public');
            
            $paket->bukti_pembayaran_cod = Storage::url($path);
            $paket->status_pembayaran_cod = 'sudah';
            $paket->status = 'dalam_pengiriman'; // Ensure it's ready for delivery finalization
            $paket->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Bukti pembayaran COD berhasil diunggah',
                'data' => $paket
            ]);
        }

        return response()->json(['status' => 'error', 'message' => 'File tidak valid'], 400);
    }

    /**
     * Upload delivery proof image.
     */
    public function uploadBukti(Request $request, $id)
    {
        $request->validate([
            'foto_bukti_delivery' => 'required|image|max:4096'
        ]);

        $paket = Paket::findOrFail($id);

        if ($request->hasFile('foto_bukti_delivery')) {
            $path = $request->file('foto_bukti_delivery')->store('bukti_delivery', 'public');
            
            $paket->foto_bukti_delivery = Storage::url($path);
            $paket->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Foto bukti penyerahan berhasil diunggah',
                'data' => $paket
            ]);
        }

        return response()->json(['status' => 'error', 'message' => 'File tidak valid'], 400);
    }

    /**
     * Mark package as delivered.
     */
    public function delivered($id)
    {
        $paket = Paket::findOrFail($id);

        // Verification checks
        if (in_array($paket->tipe_pembayaran, ['cod_qris', 'cod_transfer']) && $paket->status_pembayaran_cod !== 'sudah') {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembayaran COD belum dikonfirmasi.'
            ], 422);
        }

        if (empty($paket->foto_bukti_delivery)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Foto bukti penyerahan paket wajib diunggah terlebih dahulu.'
            ], 422);
        }

        $paket->status = 'delivered';
        $paket->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Paket berhasil diserahkan (Delivered)',
            'data' => $paket
        ]);
    }

    /**
     * Nearest-Neighbor routing optimizer solver.
     */
    private function optimizeRoute(array $pakets, ?float $startLat, ?float $startLng): array
    {
        $unvisited = $pakets;
        $sorted = [];
        
        $currentLat = $startLat ?? -5.312; // default Masalembu Lat
        $currentLng = $startLng ?? 114.435; // default Masalembu Lng
        
        while (count($unvisited) > 0) {
            $nearestKey = null;
            $minDistance = INF;
            
            foreach ($unvisited as $key => $paket) {
                if (is_null($paket->latitude) || is_null($paket->longitude)) {
                    $nearestKey = $key;
                    break;
                }
                
                $distance = $this->distance($currentLat, $currentLng, (float) $paket->latitude, (float) $paket->longitude);
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $nearestKey = $key;
                }
            }
            
            if (!is_null($nearestKey)) {
                $paket = $unvisited[$nearestKey];
                $sorted[] = $paket;
                if (!is_null($paket->latitude) && !is_null($paket->longitude)) {
                    $currentLat = (float) $paket->latitude;
                    $currentLng = (float) $paket->longitude;
                }
                unset($unvisited[$nearestKey]);
                $unvisited = array_values($unvisited);
            }
        }
        
        return $sorted;
    }

    private function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // in meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
}
