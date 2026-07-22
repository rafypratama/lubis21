<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'resi_asli',
    'ekspedisi_asal',
    'kode_resi_baru',
    'nama_penerima',
    'alamat_penerima',
    'no_hp_penerima',
    'berat_kg',
    'harga_asli',
    'harga_final',
    'tipe_pembayaran',
    'status_pembayaran_cod',
    'karung_id',
    'status',
    'hasil_qc',
    'catatan_reject',
    'kurir_id',
    'foto_bukti_delivery',
    'bukti_pembayaran_cod',
    'latitude',
    'longitude',
    'wilayah_tujuan'
])]
class Paket extends Model
{
    protected $table = 'paket';

    protected $casts = [
        'berat_kg' => 'decimal:2',
        'harga_asli' => 'decimal:2',
        'harga_final' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected static function booted()
    {
        static::creating(function ($paket) {
            // 1. Generate kode_resi_baru
            $resiAsli = $paket->resi_asli;
            $month = date('m');
            
            do {
                $randomDigits = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $kodeResiBaru = "{$resiAsli}{$randomDigits}-{$month}";
                $exists = static::where('kode_resi_baru', $kodeResiBaru)->exists();
            } while ($exists);

            $paket->kode_resi_baru = $kodeResiBaru;

            // 2. Hitung harga_final
            $paket->harga_final = static::calculateFinalPrice($paket->harga_asli, $paket->berat_kg);
            
            // 3. Set default status_pembayaran_cod jika COD
            if (in_array($paket->tipe_pembayaran, ['cod_qris', 'cod_transfer'])) {
                $paket->status_pembayaran_cod = 'belum';
            }

            // 4. Geocode alamat
            try {
                $geocoder = app(\App\Services\GeocodingService::class);
                $coords = $geocoder->geocode($paket->alamat_penerima);
                if ($coords) {
                    $paket->latitude = $coords['latitude'];
                    $paket->longitude = $coords['longitude'];
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Geocoding failed during package creation: " . $e->getMessage());
            }
        });
        
        static::updating(function ($paket) {
            // Recalculate price if original price or weight changes
            if ($paket->isDirty(['harga_asli', 'berat_kg'])) {
                $paket->harga_final = static::calculateFinalPrice($paket->harga_asli, $paket->berat_kg);
            }

            // Re-geocode if address changes
            if ($paket->isDirty('alamat_penerima')) {
                try {
                    $geocoder = app(\App\Services\GeocodingService::class);
                    $coords = $geocoder->geocode($paket->alamat_penerima);
                    if ($coords) {
                        $paket->latitude = $coords['latitude'];
                        $paket->longitude = $coords['longitude'];
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Geocoding failed during package update: " . $e->getMessage());
                }
            }
        });

        // Catat ke log secara otomatis saat status berubah
        static::created(function ($paket) {
            $paket->statusLogs()->create([
                'status' => $paket->status,
                'keterangan' => 'Paket diterima di kantor Sumenep.',
                'created_by' => auth()->id(),
            ]);
        });

        static::updated(function ($paket) {
            if ($paket->isDirty('status')) {
                $messages = [
                    'diterima_sumenep' => 'Paket diterima di kantor Lubis 21 Sumenep.',
                    'masuk_karung' => 'Paket dalam tahap sortir.',
                    'siap_transport' => 'Paket siap diberangkatkan dari Sumenep.',
                    'otw_pelabuhan_sumenep' => 'Paket dalam perjalanan menuju Pelabuhan Sumenep.',
                    'di_pelabuhan_sumenep' => 'Paket telah sampai di Pelabuhan Sumenep.',
                    'dalam_perjalanan' => 'Paket dalam pelayaran menuju Pelabuhan Masalembu.',
                    'di_pelabuhan_masalembu' => 'Paket telah tiba di Pelabuhan Masalembu.',
                    'otw_kantor_masalembu' => 'Paket dalam perjalanan menuju kantor Lubis 21 Masalembu.',
                    'sampai_masalembu' => 'Paket telah tiba di kantor Lubis 21 Masalembu.',
                    'dikembalikan_ke_sumenep' => 'Paket tidak lolos QC dan dikembalikan ke kantor Sumenep.',
                    'dalam_pengiriman' => 'Paket sedang dibawa oleh kurir untuk dikirim ke alamat tujuan.',
                    'menunggu_pembayaran_cod' => 'Kurir menunggu pembayaran COD (QRIS/Transfer) oleh penerima.',
                    'delivered' => 'Paket telah berhasil diserahkan kepada penerima.',
                ];
                
                $keterangan = $messages[$paket->status] ?? "Status paket diupdate ke: " . str_replace('_', ' ', $paket->status);

                $paket->statusLogs()->create([
                    'status' => $paket->status,
                    'keterangan' => $keterangan,
                    'created_by' => auth()->id(),
                ]);
            }
        });
    }

    public static function calculateFinalPrice(float $hargaAsli, float $beratKg): float
    {
        if ($beratKg < 3.0) {
            return $hargaAsli + 5000.0;
        } else {
            return $hargaAsli * 3000.0;
        }
    }

    public function karung(): BelongsTo
    {
        return $this->belongsTo(Karung::class, 'karung_id');
    }

    public function kurir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kurir_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PaketStatusLog::class, 'paket_id');
    }
}
