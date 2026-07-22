<?php

namespace App\Http\Controllers;

use App\Models\Karung;
use App\Models\Paket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class KarungController extends Controller
{
    /**
    /**
     * Display a listing of bags.
     */
    public function index(Request $request)
    {
        $query = Karung::query()->with(['creator', 'kurir'])->withCount('pakets');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('kurir_id')) {
            $query->where('kurir_id', $request->kurir_id);
        }

        $karungs = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $karungs
        ]);
    }

    /**
     * Store a newly created bag.
     */
    public function store(Request $request)
    {
        // Generate a unique kode_karung
        // Format: KR-YYYYMMDD-XXXX
        $date = date('Ymd');
        do {
            $random = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $kodeKarung = "KR-{$date}-{$random}";
            $exists = Karung::where('kode_karung', $kodeKarung)->exists();
        } while ($exists);

        $karung = Karung::create([
            'kode_karung' => $kodeKarung,
            'status' => 'diisi',
            'created_by' => $request->user()->id
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Karung berhasil dibuat',
            'data' => $karung
        ], 201);
    }

    /**
     * Display the specified bag.
     */
    public function show($id)
    {
        $karung = Karung::with(['pakets', 'creator', 'kurir'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $karung
        ]);
    }

    /**
     * Assign courier to a bag.
     */
    public function assignKurir(Request $request, $id)
    {
        $request->validate([
            'kurir_id' => 'required|exists:users,id'
        ]);

        $karung = Karung::findOrFail($id);
        $karung->kurir_id = $request->kurir_id;
        $karung->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Kurir transit berhasil ditetapkan untuk karung ini.',
            'data' => $karung->load('kurir')
        ]);
    }

    /**
     * Mark bag as ready for transport.
     */
    public function siapTransport($id)
    {
        $karung = Karung::findOrFail($id);
        
        DB::transaction(function () use ($karung) {
            $karung->status = 'siap_transport';
            $karung->save();

            // Update all packets inside this karung
            $pakets = Paket::where('karung_id', $karung->id)
                ->where('status', 'masuk_karung')
                ->get();
            foreach ($pakets as $paket) {
                $paket->status = 'siap_transport';
                $paket->save();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Karung siap untuk ditransportasikan',
            'data' => $karung->load('pakets')
        ]);
    }

    /**
     * Courier moves bag to Sumenep Port.
     */
    public function otwPelabuhan($id)
    {
        $karung = Karung::findOrFail($id);
        
        DB::transaction(function () use ($karung) {
            $karung->status = 'otw_pelabuhan_sumenep';
            $karung->save();

            // Update all packets inside this karung
            $pakets = Paket::where('karung_id', $karung->id)
                ->where('status', 'siap_transport')
                ->get();
            foreach ($pakets as $paket) {
                $paket->status = 'otw_pelabuhan_sumenep';
                $paket->save();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Karung dalam perjalanan ke Pelabuhan Sumenep',
            'data' => $karung->load('pakets')
        ]);
    }

    /**
     * Courier arrives at Sumenep Port.
     */
    public function tibaPelabuhanSumenep($id)
    {
        $karung = Karung::findOrFail($id);
        
        DB::transaction(function () use ($karung) {
            $karung->status = 'di_pelabuhan_sumenep';
            $karung->save();

            // Update all packets inside this karung
            $pakets = Paket::where('karung_id', $karung->id)
                ->where('status', 'otw_pelabuhan_sumenep')
                ->get();
            foreach ($pakets as $paket) {
                $paket->status = 'di_pelabuhan_sumenep';
                $paket->save();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Karung telah sampai di Pelabuhan Sumenep',
            'data' => $karung->load('pakets')
        ]);
    }

    /**
     * Ship departs (In transit over sea).
     */
    public function dalamPerjalanan($id)
    {
        $karung = Karung::findOrFail($id);
        
        DB::transaction(function () use ($karung) {
            $karung->status = 'dalam_perjalanan';
            $karung->tanggal_transport = date('Y-m-d');
            $karung->save();

            // Update all packets inside this karung
            $pakets = Paket::where('karung_id', $karung->id)
                ->whereIn('status', ['di_pelabuhan_sumenep', 'siap_transport'])
                ->get();
            foreach ($pakets as $paket) {
                $paket->status = 'dalam_perjalanan';
                $paket->save();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Kapal telah berangkat, karung dalam pelayaran ke Masalembu',
            'data' => $karung->load('pakets')
        ]);
    }

    /**
     * Ship arrives at Masalembu Port.
     */
    public function tibaPelabuhanMasalembu($id)
    {
        $karung = Karung::findOrFail($id);
        
        DB::transaction(function () use ($karung) {
            $karung->status = 'di_pelabuhan_masalembu';
            $karung->save();

            // Update all packets inside this karung
            $pakets = Paket::where('karung_id', $karung->id)
                ->where('status', 'dalam_perjalanan')
                ->get();
            foreach ($pakets as $paket) {
                $paket->status = 'di_pelabuhan_masalembu';
                $paket->save();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Kapal bersandar, karung telah tiba di Pelabuhan Masalembu',
            'data' => $karung->load('pakets')
        ]);
    }

    /**
     * Courier moves bag from Masalembu Port to Office.
     */
    public function otwKantorMasalembu($id)
    {
        $karung = Karung::findOrFail($id);
        
        DB::transaction(function () use ($karung) {
            $karung->status = 'otw_kantor_masalembu';
            $karung->save();

            // Update all packets inside this karung
            $pakets = Paket::where('karung_id', $karung->id)
                ->where('status', 'di_pelabuhan_masalembu')
                ->get();
            foreach ($pakets as $paket) {
                $paket->status = 'otw_kantor_masalembu';
                $paket->save();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Paket dalam pengiriman ke Kantor Lubis 21 Masalembu',
            'data' => $karung->load('pakets')
        ]);
    }

    /**
     * Courier arrives at Masalembu Office.
     */
    public function sampaiMasalembu($id)
    {
        $karung = Karung::findOrFail($id);
        
        DB::transaction(function () use ($karung) {
            $karung->status = 'sampai_masalembu';
            $karung->tanggal_sampai = date('Y-m-d');
            $karung->save();

            // Update all packets inside this karung
            $pakets = Paket::where('karung_id', $karung->id)
                ->whereIn('status', ['otw_kantor_masalembu', 'dalam_perjalanan'])
                ->get();
            foreach ($pakets as $paket) {
                $paket->status = 'sampai_masalembu';
                $paket->save();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Paket telah diterima di Kantor Lubis 21 Masalembu',
            'data' => $karung->load('pakets')
        ]);
    }

    /**
     * Mark bag as unpacked (Admin Masalembu confirms and starts QC).
     */
    public function bongkar($id)
    {
        $karung = Karung::findOrFail($id);
        $karung->status = 'dibongkar';
        $karung->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Penyortiran paket telah dimulai',
            'data' => $karung->load('pakets')
        ]);
    }
}
