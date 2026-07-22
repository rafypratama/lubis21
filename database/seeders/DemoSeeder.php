<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Karung;
use App\Models\Paket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Seed demo data for all roles and workflow stages.
     */
    public function run(): void
    {
        // ============================================
        // 1. Create Users (Owner, Admin Sumenep, Admin Masalembu, 2 Kurir)
        // ============================================
        $owner = User::create([
            'nama' => 'Pak Hasan (Owner)',
            'email' => 'owner@lubis21.id',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'no_hp' => '081234567000',
        ]);

        $adminSumenep = User::create([
            'nama' => 'Bu Ratna (Admin Sumenep)',
            'email' => 'sumenep@lubis21.id',
            'password' => Hash::make('password'),
            'role' => 'admin_sumenep',
            'no_hp' => '081234567001',
        ]);

        $adminMasalembu = User::create([
            'nama' => 'Pak Doni (Admin Masalembu)',
            'email' => 'masalembu@lubis21.id',
            'password' => Hash::make('password'),
            'role' => 'admin_masalembu',
            'no_hp' => '081234567002',
        ]);

        $kurirUtara = User::create([
            'nama' => 'Budi (Kurir Utara)',
            'email' => 'kurir.utara@lubis21.id',
            'password' => Hash::make('password'),
            'role' => 'kurir',
            'no_hp' => '081234567003',
            'wilayah' => 'Masalembu Utara',
            'last_latitude' => -5.300,
            'last_longitude' => 114.430,
        ]);

        $kurirSelatan = User::create([
            'nama' => 'Andi (Kurir Selatan)',
            'email' => 'kurir.selatan@lubis21.id',
            'password' => Hash::make('password'),
            'role' => 'kurir',
            'no_hp' => '081234567004',
            'wilayah' => 'Masalembu Selatan',
            'last_latitude' => -5.320,
            'last_longitude' => 114.440,
        ]);

        $this->command->info('✅ Users seeded (5 accounts)');

        // ============================================
        // 2. Create Karung (2 buah)
        // ============================================
        $karung1 = Karung::create([
            'kode_karung' => 'KRG-' . date('Ymd') . '-001',
            'status' => 'dibongkar',
            'created_by' => $adminSumenep->id,
            'tanggal_transport' => now()->subDays(3),
            'tanggal_sampai' => now()->subDays(1),
        ]);

        $karung2 = Karung::create([
            'kode_karung' => 'KRG-' . date('Ymd') . '-002',
            'status' => 'siap_transport',
            'created_by' => $adminSumenep->id,
            'kurir_id' => $kurirUtara->id,
        ]);

        $this->command->info('✅ Karung seeded (2 bags)');

        // ============================================
        // 3. Create Paket (beragam status)
        // ============================================
        $paketData = [
            // Paket sudah delivered
            [
                'resi_asli' => 'JT12345001',
                'ekspedisi_asal' => 'J&T',
                'nama_penerima' => 'Ahmad Fauzi',
                'alamat_penerima' => 'Jl. Masjid RT 01 RW 02, Desa Masalembu, Kec. Masalembu',
                'no_hp_penerima' => '082111222001',
                'berat_kg' => 2.5,
                'harga_asli' => 50000,
                'tipe_pembayaran' => 'non_cod',
                'wilayah_tujuan' => 'Masalembu Utara',
                'status' => 'delivered',
                'karung_id' => $karung1->id,
                'kurir_id' => $kurirUtara->id,
                'latitude' => -5.308,
                'longitude' => 114.432,
            ],
            [
                'resi_asli' => 'JNE98765002',
                'ekspedisi_asal' => 'JNE',
                'nama_penerima' => 'Siti Aminah',
                'alamat_penerima' => 'Jl. Pelabuhan No. 15, Masalembu Selatan',
                'no_hp_penerima' => '082111222002',
                'berat_kg' => 1.0,
                'harga_asli' => 35000,
                'tipe_pembayaran' => 'cod_qris',
                'wilayah_tujuan' => 'Masalembu Selatan',
                'status' => 'delivered',
                'karung_id' => $karung1->id,
                'kurir_id' => $kurirSelatan->id,
                'latitude' => -5.325,
                'longitude' => 114.438,
                'status_pembayaran_cod' => 'sudah',
            ],

            // Paket dalam pengiriman kurir
            [
                'resi_asli' => 'SCP55553003',
                'ekspedisi_asal' => 'SiCepat',
                'nama_penerima' => 'Rahmat Hidayat',
                'alamat_penerima' => 'Dusun Tengah RT 03 RW 01, Masalembu',
                'no_hp_penerima' => '082111222003',
                'berat_kg' => 4.5,
                'harga_asli' => 120000,
                'tipe_pembayaran' => 'cod_transfer',
                'wilayah_tujuan' => 'Masalembu Utara',
                'status' => 'dalam_pengiriman',
                'karung_id' => $karung1->id,
                'kurir_id' => $kurirUtara->id,
                'latitude' => -5.305,
                'longitude' => 114.428,
            ],
            [
                'resi_asli' => 'SPX88884004',
                'ekspedisi_asal' => 'Shopee Express',
                'nama_penerima' => 'Dewi Lestari',
                'alamat_penerima' => 'Jl. Pantai Indah, Masalembu Selatan',
                'no_hp_penerima' => '082111222004',
                'berat_kg' => 0.8,
                'harga_asli' => 25000,
                'tipe_pembayaran' => 'non_cod',
                'wilayah_tujuan' => 'Masalembu Selatan',
                'status' => 'dalam_pengiriman',
                'karung_id' => $karung1->id,
                'kurir_id' => $kurirSelatan->id,
                'latitude' => -5.318,
                'longitude' => 114.445,
            ],

            // Paket QC Reject (dikembalikan ke Sumenep)
            [
                'resi_asli' => 'NJV77775005',
                'ekspedisi_asal' => 'Ninja',
                'nama_penerima' => 'Putri Rahayu',
                'alamat_penerima' => 'Jl. Nelayan No. 7, Karamian',
                'no_hp_penerima' => '082111222005',
                'berat_kg' => 3.2,
                'harga_asli' => 80000,
                'tipe_pembayaran' => 'non_cod',
                'wilayah_tujuan' => 'Karamian',
                'status' => 'dikembalikan_ke_sumenep',
                'karung_id' => $karung1->id,
                'hasil_qc' => 'reject',
                'catatan_reject' => 'Paket rusak karena terkena air laut saat perjalanan kapal.',
            ],

            // Paket masih dalam perjalanan kapal (karung 2)
            [
                'resi_asli' => 'JT22226006',
                'ekspedisi_asal' => 'J&T',
                'nama_penerima' => 'Hendra Wijaya',
                'alamat_penerima' => 'Desa Masakambing, RT 02 RW 01',
                'no_hp_penerima' => '082111222006',
                'berat_kg' => 5.0,
                'harga_asli' => 200000,
                'tipe_pembayaran' => 'cod_qris',
                'wilayah_tujuan' => 'Masakambing',
                'status' => 'siap_transport',
                'karung_id' => $karung2->id,
            ],
            [
                'resi_asli' => 'JNE11117007',
                'ekspedisi_asal' => 'JNE',
                'nama_penerima' => 'Nur Hasanah',
                'alamat_penerima' => 'Jl. Merdeka No. 10, Masalembu Utara',
                'no_hp_penerima' => '082111222007',
                'berat_kg' => 1.5,
                'harga_asli' => 40000,
                'tipe_pembayaran' => 'non_cod',
                'wilayah_tujuan' => 'Masalembu Utara',
                'status' => 'siap_transport',
                'karung_id' => $karung2->id,
            ],

            // Paket baru diterima, belum masuk karung
            [
                'resi_asli' => 'SCP33338008',
                'ekspedisi_asal' => 'SiCepat',
                'nama_penerima' => 'Agus Santoso',
                'alamat_penerima' => 'Jl. Pasar Lama, Masalembu Selatan',
                'no_hp_penerima' => '082111222008',
                'berat_kg' => 2.0,
                'harga_asli' => 55000,
                'tipe_pembayaran' => 'cod_transfer',
                'wilayah_tujuan' => 'Masalembu Selatan',
                'status' => 'diterima_sumenep',
            ],
            [
                'resi_asli' => 'SPX44449009',
                'ekspedisi_asal' => 'Shopee Express',
                'nama_penerima' => 'Rina Marlina',
                'alamat_penerima' => 'Jl. Kampung Baru, Karamian',
                'no_hp_penerima' => '082111222009',
                'berat_kg' => 6.0,
                'harga_asli' => 150000,
                'tipe_pembayaran' => 'non_cod',
                'wilayah_tujuan' => 'Karamian',
                'status' => 'diterima_sumenep',
            ],
            [
                'resi_asli' => 'JT55550010',
                'ekspedisi_asal' => 'J&T',
                'nama_penerima' => 'Bambang Suharto',
                'alamat_penerima' => 'Dusun Utara RT 05, Masalembu Utara',
                'no_hp_penerima' => '082111222010',
                'berat_kg' => 1.2,
                'harga_asli' => 30000,
                'tipe_pembayaran' => 'non_cod',
                'wilayah_tujuan' => 'Masalembu Utara',
                'status' => 'diterima_sumenep',
            ],
        ];

        foreach ($paketData as $data) {
            // Calculate harga_final
            $beratKg = $data['berat_kg'];
            $hargaAsli = $data['harga_asli'];
            $data['harga_final'] = Paket::calculateFinalPrice($hargaAsli, $beratKg);

            $status = $data['status'];
            $paket = Paket::create($data);

            // Clear the default log automatically created by boot method so we can write historical steps
            $paket->statusLogs()->delete();

            $logs = [];

            // 1. Diterima Sumenep (all packages start here)
            $logs[] = [
                'status' => 'diterima_sumenep',
                'keterangan' => 'Paket diterima di kantor Lubis 21 Sumenep.',
                'created_at' => now()->subDays(4),
            ];

            if ($status !== 'diterima_sumenep') {
                $logs[] = [
                    'status' => 'masuk_karung',
                    'keterangan' => 'Paket dalam tahap sortir.',
                    'created_at' => now()->subDays(3)->addHours(2),
                ];
                $logs[] = [
                    'status' => 'siap_transport',
                    'keterangan' => 'Paket siap diberangkatkan dari Sumenep.',
                    'created_at' => now()->subDays(3)->addHours(4),
                ];
            }

            if (!in_array($status, ['diterima_sumenep', 'masuk_karung', 'siap_transport'])) {
                $logs[] = [
                    'status' => 'otw_pelabuhan_sumenep',
                    'keterangan' => 'Paket dalam perjalanan menuju Pelabuhan Sumenep.',
                    'created_at' => now()->subDays(3)->addHours(6),
                ];
                $logs[] = [
                    'status' => 'di_pelabuhan_sumenep',
                    'keterangan' => 'Paket telah sampai di Pelabuhan Sumenep.',
                    'created_at' => now()->subDays(3)->addHours(8),
                ];
                $logs[] = [
                    'status' => 'dalam_perjalanan',
                    'keterangan' => 'Paket dalam pelayaran menuju Pelabuhan Masalembu.',
                    'created_at' => now()->subDays(3)->addHours(12),
                ];
                $logs[] = [
                    'status' => 'di_pelabuhan_masalembu',
                    'keterangan' => 'Paket telah tiba di Pelabuhan Masalembu.',
                    'created_at' => now()->subDays(1)->addHours(2),
                ];
                $logs[] = [
                    'status' => 'otw_kantor_masalembu',
                    'keterangan' => 'Paket dalam perjalanan menuju kantor Lubis 21 Masalembu.',
                    'created_at' => now()->subDays(1)->addHours(4),
                ];
                $logs[] = [
                    'status' => 'sampai_masalembu',
                    'keterangan' => 'Paket telah tiba di kantor Lubis 21 Masalembu.',
                    'created_at' => now()->subDays(1)->addHours(6),
                ];
            }

            if ($status === 'dikembalikan_ke_sumenep') {
                $logs[] = [
                    'status' => 'dikembalikan_ke_sumenep',
                    'keterangan' => 'Paket tidak lolos QC dan dikembalikan ke kantor Sumenep. Catatan: ' . ($data['catatan_reject'] ?? 'Kerusakan fisik paket.'),
                    'created_at' => now()->subDays(1)->addHours(8),
                ];
            }

            if (in_array($status, ['dalam_pengiriman', 'menunggu_pembayaran_cod', 'delivered'])) {
                $logs[] = [
                    'status' => 'dalam_pengiriman',
                    'keterangan' => 'Paket sedang dibawa oleh kurir untuk dikirim ke alamat tujuan.',
                    'created_at' => now()->subDays(1)->addHours(10),
                ];
            }

            if ($status === 'menunggu_pembayaran_cod') {
                $logs[] = [
                    'status' => 'menunggu_pembayaran_cod',
                    'keterangan' => 'Kurir menunggu pembayaran COD (QRIS/Transfer) oleh penerima.',
                    'created_at' => now()->subDays(1)->addHours(11),
                ];
            }

            if ($status === 'delivered') {
                $logs[] = [
                    'status' => 'delivered',
                    'keterangan' => 'Paket telah berhasil diserahkan kepada penerima.',
                    'created_at' => now()->subDays(1)->addHours(12),
                ];
            }

            foreach ($logs as $log) {
                $paket->statusLogs()->create([
                    'status' => $log['status'],
                    'keterangan' => $log['keterangan'],
                    'created_at' => $log['created_at'],
                ]);
            }
        }

        $this->command->info('✅ Paket seeded (10 packages with diverse statuses)');

        // ============================================
        // Summary
        // ============================================
        $this->command->info('');
        $this->command->info('======================================');
        $this->command->info('  DEMO SEEDER BERHASIL DIJALANKAN');
        $this->command->info('======================================');
        $this->command->info('');
        $this->command->info('  Akun Login Demo:');
        $this->command->info('  ──────────────────────────────────');
        $this->command->info('  Owner        : owner@lubis21.id');
        $this->command->info('  Admin Sumenep: sumenep@lubis21.id');
        $this->command->info('  Admin Masalembu: masalembu@lubis21.id');
        $this->command->info('  Kurir Utara  : kurir.utara@lubis21.id');
        $this->command->info('  Kurir Selatan: kurir.selatan@lubis21.id');
        $this->command->info('  ──────────────────────────────────');
        $this->command->info('  Password     : password (semua akun)');
        $this->command->info('');
    }
}
