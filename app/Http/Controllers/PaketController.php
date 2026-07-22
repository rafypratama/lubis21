<?php

namespace App\Http\Controllers;

use App\Models\Paket;
use App\Models\User;
use App\Models\Karung;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaketController extends Controller
{
    /**
     * Display a listing of packages with filters.
     */
    public function index(Request $request)
    {
        $query = Paket::query()->with(['karung', 'kurir']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by karung_id (can be 'null' string to find unassigned packages)
        if ($request->has('karung_id')) {
            if ($request->karung_id === 'null') {
                $query->whereNull('karung_id');
            } else {
                $query->where('karung_id', $request->karung_id);
            }
        }

        // Filter by kurir_id (can be 'null' to find unassigned)
        if ($request->has('kurir_id')) {
            if ($request->kurir_id === 'null') {
                $query->whereNull('kurir_id');
            } else {
                $query->where('kurir_id', $request->kurir_id);
            }
        }

        // Filter by search query (resi_asli, kode_resi_baru, nama_penerima)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('resi_asli', 'like', "%{$search}%")
                  ->orWhere('kode_resi_baru', 'like', "%{$search}%")
                  ->orWhere('nama_penerima', 'like', "%{$search}%");
            });
        }

        $pakets = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $pakets
        ]);
    }

    /**
     * Store a newly created package.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'resi_asli' => 'required|string',
            'ekspedisi_asal' => 'required|string',
            'nama_penerima' => 'required|string',
            'alamat_penerima' => 'required|string',
            'no_hp_penerima' => 'nullable|string',
            'berat_kg' => 'required|numeric|min:0.01',
            'harga_asli' => 'required|numeric|min:0',
            'tipe_pembayaran' => ['required', Rule::in(['cod_qris', 'cod_transfer', 'non_cod'])],
            'wilayah_tujuan' => 'required|string',
        ]);

        // Default status is 'diterima_sumenep'
        $validated['status'] = 'diterima_sumenep';

        $paket = Paket::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket berhasil didaftarkan',
            'data' => $paket->load('statusLogs')
        ], 201);
    }

    /**
     * Display a specific package with its status logs.
     */
    public function show($id)
    {
        $paket = Paket::with(['karung', 'kurir', 'statusLogs.creator'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $paket
        ]);
    }

    /**
     * Assign package to a karung.
     */
    public function assignKarung(Request $request, $id)
    {
        $request->validate([
            'karung_id' => 'required|exists:karung,id'
        ]);

        $paket = Paket::findOrFail($id);
        
        // Update karung and status to 'masuk_karung'
        $paket->karung_id = $request->karung_id;
        $paket->status = 'masuk_karung';
        $paket->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Paket berhasil dimasukkan ke karung',
            'data' => $paket->load('karung')
        ]);
    }

    /**
     * Perform Quality Control (QC) check on the package.
     */
    public function qcCheck(Request $request, $id)
    {
        $request->validate([
            'hasil_qc' => ['required', Rule::in(['lolos', 'reject'])],
            'catatan_reject' => 'required_if:hasil_qc,reject|nullable|string'
        ]);

        $paket = Paket::findOrFail($id);
        $paket->hasil_qc = $request->hasil_qc;
        $paket->catatan_reject = $request->catatan_reject;

        if ($request->hasil_qc === 'reject') {
            $paket->status = 'dikembalikan_ke_sumenep';
        } else {
            // Lolos QC: Assign status to 'dalam_pengiriman' and try to auto-match kurir
            $paket->status = 'dalam_pengiriman';

            // Auto-match kurir: 1 kurir per wilayah
            $kurirList = User::where('role', 'kurir')
                ->where('wilayah', $paket->wilayah_tujuan)
                ->get();

            if ($kurirList->count() === 1) {
                $paket->kurir_id = $kurirList->first()->id;
            }
        }

        $paket->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil QC paket berhasil disimpan',
            'data' => $paket->load(['kurir', 'statusLogs'])
        ]);
    }

    /**
     * Manually assign / override kurir for a package.
     */
    public function assignKurir(Request $request, $id)
    {
        $request->validate([
            'kurir_id' => 'required|exists:users,id'
        ]);

        $kurir = User::findOrFail($request->kurir_id);
        if ($kurir->role !== 'kurir') {
            return response()->json([
                'status' => 'error',
                'message' => 'User yang dipilih bukan kurir'
            ], 422);
        }

        $paket = Paket::findOrFail($id);
        $paket->kurir_id = $kurir->id;
        $paket->status = 'dalam_pengiriman';
        $paket->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Kurir berhasil ditugaskan',
            'data' => $paket->load('kurir')
        ]);
    }

    /**
     * Get all couriers.
     */
    public function getKurirs()
    {
        $kurirs = User::where('role', 'kurir')->get();
        return response()->json([
            'status' => 'success',
            'data' => $kurirs
        ]);
    }

    /**
     * Track package status logs publicly.
     */
    public function trackPublic($kode_resi)
    {
        $paket = Paket::where('kode_resi_baru', $kode_resi)
            ->orWhere('resi_asli', $kode_resi)
            ->first();

        if (!$paket) {
            return response()->json([
                'status' => 'error',
                'message' => 'Paket dengan resi tersebut tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'paket' => $paket,
                'logs' => $paket->statusLogs()->orderBy('created_at', 'desc')->get()
            ]
        ]);
    }
}
