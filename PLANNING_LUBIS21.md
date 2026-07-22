# PLANNING SISTEM — LUBIS 21
### Sistem Manajemen Konsolidasi & Pengiriman Paket (Sumenep → Masalembu)

---

## 1. Latar Belakang & Tujuan

LUBIS 21 adalah jasa konsolidasi/forwarder paket dari berbagai ekspedisi (J&T, JNE, SiCepat, dll) yang masuk ke kantor Sumenep, dikelompokkan ke dalam karung, dikirim via kapal ke kantor Masalembu, lalu didistribusikan ke penerima akhir oleh kurir lokal — termasuk dukungan pembayaran COD (QRIS/Transfer).

Sistem ini dibangun untuk:
- Mendigitalkan pendataan paket masuk dan pengelompokan ke karung
- Melacak status paket dari Sumenep sampai Delivered
- Mengelola proses QC (reject/lolos) di Masalembu
- Mengotomatiskan perhitungan harga tambahan berdasarkan berat
- Menyediakan tracking publik untuk penerima
- Menyediakan laporan export Excel untuk Owner

---

## 2. Tech Stack

| Layer | Teknologi |
|---|---|
| Backend / API | Laravel 11 (REST API, Sanctum untuk auth) |
| Frontend | Next.js 14 (App Router) |
| Database | MySQL |
| Storage foto bukti | Laravel Storage (local/S3-compatible) |
| Export Excel | Laravel Excel (maatwebsite/excel) |
| Auth | Laravel Sanctum (SPA token-based) |
| Notifikasi (opsional fase 2) | WhatsApp API / Fonnte untuk update status ke penerima |

---

## 3. Role & Akses

| Role | Hak Akses |
|---|---|
| **Owner** | Akses penuh, dashboard, export Excel, kelola user |
| **Admin Sumenep** | Input paket masuk, kelola karung, update status transport |
| **Admin Masalembu** | Terima karung, bongkar, QC (reject/lolos), assign kurir |
| **Kurir** | Lihat paket assigned, upload foto bukti, konfirmasi COD, konfirmasi delivered |
| **Publik (tanpa login)** | Cek resi via halaman tracking publik |

---

## 4. Alur Proses (Business Flow)

```
[Paket Masuk Ekspedisi] 
        ↓ (Admin Sumenep input data)
[Generate Kode Resi Baru: ResiAsli + 6 digit acak + "-" + Bulan]
        ↓
[Dimasukkan ke Karung (1 karung bisa >10 paket)]
        ↓ (status: Diisi)
[Karung Siap Transport] → (status: Siap Transport)
        ↓
[Transport via Kapal] → (status: Dalam Perjalanan)
        ↓
[Sampai Kantor Masalembu] → (status: Sampai Masalembu)
        ↓
[Admin Masalembu Bongkar Karung & Cek Kondisi Tiap Paket]
        ↓                              ↓
   [LOLOS QC]                     [REJECT/CACAT]
        ↓                              ↓
[Assign Kurir]                  [Didata & Dikembalikan]
        ↓                         (status: Reject - Dikembalikan)
[Dalam Pengiriman]
        ↓
   COD? ──Ya──→ [Konfirmasi Pembayaran (QRIS/Transfer)] → [Foto Bukti] → [Delivered]
        │
        Tidak
        ↓
[Foto Bukti] → [Delivered]
```

---

## 5. Aturan Bisnis Penting

### 5.1 Generate Kode Resi Baru
Format: `{resi_asli}{6_digit_acak}-{angka_bulan}`
Contoh: resi asli `JNE123456789`, bulan Juli → `JNE123456789482910-07`
- 6 digit acak: random number, unik per paket
- Angka bulan: 2 digit (01–12), otomatis dari tanggal input

### 5.2 Perhitungan Harga Final
```
JIKA berat < 3kg:
    harga_final = harga_asli + 5.000

JIKA berat >= 3kg:
    harga_final = harga_asli x 3.000
```
> ⚠️ **Catatan validasi:** Rumus berat ≥3kg (harga asli dikali 3000) sudah dikonfirmasi oleh klien meski menghasilkan angka besar untuk harga asli yang tinggi. Disarankan rumus ini divalidasi ulang sebelum go-live, karena berpotensi menghasilkan harga akhir yang tidak wajar secara bisnis. Logic akan ditaruh di satu fungsi terpusat (`calculateFinalPrice()`) agar mudah direvisi tanpa mengubah struktur sistem.

### 5.3 Status Paket (Lifecycle)
1. `diterima_sumenep`
2. `masuk_karung`
3. `siap_transport`
4. `dalam_perjalanan`
5. `sampai_masalembu`
6. `qc_reject` → `dikembalikan_ke_sumenep` *(lihat detail final di poin 11–12)*
7. `qc_lolos` → `dalam_pengiriman`
8. `menunggu_pembayaran_cod` (khusus COD)
9. `delivered`

### 5.4 Aturan COD
- Jika `tipe_pembayaran = COD`, kurir **wajib konfirmasi pembayaran** (QRIS/Transfer) terlebih dahulu sebelum bisa upload foto bukti & konfirmasi delivered.
- Jika non-COD, langsung foto bukti → konfirmasi delivered.

---

## 6. Struktur Database (ERD Ringkas)

### `users`
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| nama | string |
| email | string |
| password | string |
| role | enum(owner, admin_sumenep, admin_masalembu, kurir) |
| no_hp | string |

### `karung`
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| kode_karung | string (unik, auto-generate) |
| status | enum(diisi, siap_transport, dalam_perjalanan, sampai_masalembu, dibongkar) |
| created_by | FK users |
| tanggal_transport | date nullable |
| tanggal_sampai | date nullable |

### `paket`
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| resi_asli | string |
| ekspedisi_asal | string (J&T, JNE, SiCepat, dll) |
| kode_resi_baru | string (unik, auto-generate) |
| nama_penerima | string |
| alamat_penerima | text |
| no_hp_penerima | string nullable |
| berat_kg | decimal |
| harga_asli | decimal |
| harga_final | decimal (computed) |
| tipe_pembayaran | enum(cod_qris, cod_transfer, non_cod) |
| status_pembayaran_cod | enum(belum, sudah) nullable |
| karung_id | FK karung nullable |
| status | enum (lihat 5.3) |
| hasil_qc | enum(lolos, reject) nullable |
| catatan_reject | text nullable |
| kurir_id | FK users nullable |
| foto_bukti_delivery | string (path) nullable |
| bukti_pembayaran_cod | string (path) nullable |
| created_at, updated_at | timestamp |

### `paket_status_log`
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| paket_id | FK paket |
| status | string |
| keterangan | text nullable |
| created_at | timestamp |
| created_by | FK users nullable |

---

## 7. Daftar Endpoint API (Ringkas)

### Auth
- `POST /api/login`
- `POST /api/logout`

### Paket (Admin Sumenep)
- `POST /api/paket` — input paket baru (auto-generate kode resi & hitung harga final)
- `GET /api/paket` — list paket (filter status/karung)
- `POST /api/paket/{id}/assign-karung`

### Karung (Admin Sumenep)
- `POST /api/karung` — buat karung baru
- `GET /api/karung`
- `POST /api/karung/{id}/siap-transport`
- `POST /api/karung/{id}/dalam-perjalanan`

### Karung & QC (Admin Masalembu)
- `POST /api/karung/{id}/sampai-masalembu`
- `POST /api/karung/{id}/bongkar`
- `POST /api/paket/{id}/qc` — body: `{ hasil_qc: lolos|reject, catatan }`
- `POST /api/paket/{id}/assign-kurir`

### Delivery (Kurir)
- `GET /api/paket/kurir/{kurir_id}` — daftar tugas
- `POST /api/paket/{id}/konfirmasi-cod` — upload bukti pembayaran
- `POST /api/paket/{id}/upload-bukti` — upload foto delivery
- `POST /api/paket/{id}/delivered`

### Tracking Publik
- `GET /api/track/{kode_resi}` — return status & histori (tanpa auth)

### Export & Dashboard (Owner)
- `GET /api/export/excel?dari=&sampai=&status=`
- `GET /api/dashboard/summary`

---

## 8. Halaman Frontend (Next.js)

| Halaman | Role | Deskripsi |
|---|---|---|
| `/login` | Semua | Login |
| `/sumenep/paket` | Admin Sumenep | Input & list paket masuk |
| `/sumenep/karung` | Admin Sumenep | Kelola karung & transport |
| `/masalembu/karung` | Admin Masalembu | Terima & bongkar karung |
| `/masalembu/qc` | Admin Masalembu | Proses QC reject/lolos |
| `/kurir/tugas` | Kurir | Daftar paket diantar, peta navigasi in-app dengan rute optimal multi-stop, upload bukti, konfirmasi COD |
| `/dashboard` | Owner | Statistik, grafik, ringkasan status |
| `/export` | Owner | Export Excel dengan filter tanggal/status |
| `/track/[kode_resi]` | Publik | Halaman cek resi (tanpa login) |

---

## 9. Export Excel — Format Kolom

| Kode Resi Baru | Nama Penerima | Alamat | Harga Final |
|---|---|---|---|
| JNE123456789482910-07 | Budi Santoso | Jl. Masalembu No. 5 | 25.000 |

Filter export: rentang tanggal, status, karung, ekspedisi asal.

---

## 10. Rencana Tahapan Development (Roadmap)

**Fase 1 — Fondasi (Minggu 1–2)**
- Setup project Laravel API + Next.js
- Auth (Sanctum) + role middleware
- CRUD User, struktur database & migration

**Fase 2 — Modul Sumenep (Minggu 2–3)**
- Input paket (auto kode resi & harga final)
- Kelola karung (buat, isi, siap transport)

**Fase 3 — Modul Masalembu (Minggu 3–4)**
- Terima karung, bongkar
- QC reject/lolos + assign kurir

**Fase 4 — Modul Kurir & Delivery (Minggu 4–5)**
- List tugas kurir
- Upload foto, konfirmasi COD, konfirmasi delivered

**Fase 5 — Tracking Publik & Export (Minggu 5–6)**
- Halaman cek resi publik
- Export Excel + filter
- Dashboard ringkasan (Owner)

**Fase 6 — Testing & Deployment (Minggu 6–7)**
- UAT bersama klien
- Deploy ke VPS/hosting
- Training penggunaan untuk Admin Sumenep, Admin Masalembu, Kurir

---

## 11. Hasil Validasi dengan Klien (Final)

1. **Rumus harga >3kg (harga asli × 3000)** — dikonfirmasi tetap dipakai sesuai aturan awal. Tidak ada perubahan.
2. **Notifikasi WhatsApp otomatis** — tidak diperlukan. Tracking cukup lewat halaman cek resi publik.
3. **Penugasan kurir** — model **1 Kurir = 1 Wilayah**. Sistem perlu tabel/kolom `wilayah` pada `users` (role kurir) dan `paket` (wilayah tujuan), lalu assign kurir otomatis/manual berdasarkan kecocokan wilayah.
4. **Alur reject** — tidak perlu approval/role tambahan. Paket reject dari Masalembu **dikembalikan ke Kantor Sumenep** (bukan langsung ke pengirim/ekspedisi asal). Status `qc_reject` akan mengarah ke status baru: `dikembalikan_ke_sumenep`.
5. **Komponen biaya** — tidak ada biaya lain di luar `harga_final` (tidak ada ongkir kapal terpisah, dll). `harga_final` adalah jumlah yang ditagihkan ke penerima untuk kasus COD.

---

## 12. Penyesuaian Desain Berdasarkan Hasil Validasi

### 12.0 Tambahan Fitur — Laporan Rekap Periodik (Dashboard Owner)
Selain export Excel, dashboard Owner perlu menampilkan rekap periode (harian/mingguan/bulanan) berisi:
- Total paket masuk (per periode)
- Total paket reject vs lolos QC
- Total paket delivered vs masih dalam proses
- Total pendapatan (jumlah `harga_final` dari paket delivered, bisa difilter per periode)
- Breakdown per wilayah kurir (opsional, jika data cukup)
- Breakdown per ekspedisi asal (J&T, JNE, SiCepat, dll)

Endpoint tambahan: `GET /api/dashboard/rekap?periode=harian|mingguan|bulanan&dari=&sampai=`
Halaman: bagian baru di `/dashboard` berupa grafik (chart) + tabel ringkasan, dengan filter rentang tanggal.

### 12.0.2 Tambahan Fitur — Navigasi In-App untuk Kurir (Mirip Gojek/Grab)

**Konsep:** Kurir tidak perlu keluar ke aplikasi Google Maps eksternal. Peta, rute, dan urutan pengantaran ditampilkan langsung di dalam web (embedded map + turn-by-turn arah), dengan urutan lokasi disusun otomatis oleh sistem berdasarkan jarak/waktu tempuh terdekat (route optimization), persis seperti pengalaman navigasi di Gojek/Grab Driver.

**Komponen teknis yang dibutuhkan:**
- **Geocoding** — alamat penerima (`paket.alamat_penerima`) dikonversi ke koordinat (`latitude`, `longitude`) saat input data, menggunakan Google Geocoding API (atau Nominatim/OpenStreetMap sebagai alternatif gratis jika ingin hemat biaya API).
- **Route Optimization** — saat kurir mulai pengantaran, sistem mengambil semua paket assigned hari itu (status `dalam_pengiriman`) milik kurir tsb, lalu menyusun urutan kunjungan optimal berdasarkan posisi kurir saat ini (real-time GPS) dan titik-titik tujuan. Bisa pakai:
  - Google **Directions API** dengan parameter `optimizeWaypoints=true` (maks 25 waypoint per request, paling praktis untuk MVP), atau
  - Algoritma sendiri (Nearest Neighbor / OR-Tools) jika ingin lepas dari ketergantungan Google API.
- **Peta In-App** — menggunakan **Google Maps JavaScript API** (atau Mapbox GL JS sebagai alternatif) untuk render peta interaktif di halaman `/kurir/tugas`, menampilkan:
  - Posisi kurir real-time (live tracking via GPS browser/HP)
  - Marker bernomor urut (1, 2, 3, dst) untuk tiap titik antar
  - Garis rute (polyline) ke tujuan berikutnya
  - Estimasi jarak & waktu tempuh ke titik selanjutnya
- **Update Posisi Real-time** — browser kurir mengirim koordinat GPS secara berkala (`navigator.geolocation.watchPosition`) ke backend untuk update peta & (opsional, future) live tracking oleh Admin/Owner.

**Perubahan struktur database:**

| Tabel | Kolom Tambahan |
|---|---|
| `paket` | `latitude`, `longitude` (hasil geocoding alamat) |
| `users` (kurir) | `last_latitude`, `last_longitude`, `last_location_updated_at` |
| `rute_harian` (tabel baru) | `id`, `kurir_id`, `tanggal`, `daftar_paket_urutan` (JSON array ID paket sesuai urutan optimal), `status` (belum_mulai, berjalan, selesai) |

**Endpoint API tambahan:**
- `POST /api/kurir/{id}/mulai-rute` — generate urutan rute optimal untuk semua paket assigned hari itu
- `GET /api/kurir/{id}/rute-aktif` — ambil data rute + urutan + koordinat untuk ditampilkan di peta
- `POST /api/kurir/{id}/update-lokasi` — update posisi GPS kurir secara berkala
- `POST /api/kurir/{id}/selesai-titik/{paket_id}` — tandai 1 titik selesai dikunjungi, lanjut ke titik berikutnya di rute

**Catatan biaya & teknis:**
- Google Maps Platform (Directions, Geocoding, Maps JavaScript API) berbayar setelah kuota gratis bulanan habis — perlu disiapkan API key + billing account terpisah untuk klien, atau gunakan alternatif open-source (OpenStreetMap + OSRM untuk routing, Leaflet.js untuk peta) jika ingin menghindari biaya API jangka panjang.
- Untuk MVP, batasi optimasi rute pada kurir dengan jumlah titik wajar (≤25 titik/hari sesuai limit Directions API standar).
**Wajib tema terang (light theme), tidak boleh dark mode.**
- Background utama website: **putih**
- Tidak ada toggle dark mode untuk fase awal (bisa jadi catatan future-enhancement jika diminta nanti)
- Berlaku untuk seluruh halaman: dashboard internal (Admin Sumenep, Admin Masalembu, Kurir, Owner) maupun halaman tracking publik
- Akan dipastikan saat implementasi frontend (Next.js + Tailwind) bahwa tidak ada class dark: yang aktif secara default, dan tidak mengikuti preferensi sistem (prefers-color-scheme) untuk dark mode

### 12.1 Tabel `users` (tambahan kolom untuk kurir)
| Kolom | Tipe |
|---|---|
| wilayah | string, nullable (diisi khusus role kurir) |

### 12.2 Tabel `paket` (tambahan kolom)
| Kolom | Tipe |
|---|---|
| wilayah_tujuan | string (untuk pencocokan otomatis dengan kurir) |

### 12.3 Status Paket — Update Lifecycle
1. `diterima_sumenep`
2. `masuk_karung`
3. `siap_transport`
4. `dalam_perjalanan`
5. `sampai_masalembu`
6. `qc_reject` → **`dikembalikan_ke_sumenep`** *(update: tujuan retur adalah kantor Sumenep, bukan pengirim asal)*
7. `qc_lolos` → `dalam_pengiriman`
8. `menunggu_pembayaran_cod` (khusus COD)
9. `delivered`

### 12.4 Logika Assign Kurir
- Saat paket dinyatakan `qc_lolos`, sistem mencocokkan `wilayah_tujuan` paket dengan `wilayah` milik user berrole kurir.
- Jika hanya ada 1 kurir per wilayah, assign otomatis bisa langsung dilakukan sistem (tanpa pilih manual), dengan opsi override manual oleh Admin Masalembu jika diperlukan.

### 12.5 Alur Reject — Final
```
[Sampai Masalembu] → [Bongkar] → [QC: REJECT]
        ↓
[Status: dikembalikan_ke_sumenep]
        ↓
[Dikirim balik via kapal ke Kantor Sumenep]
        ↓
[Diterima Admin Sumenep] → [Selesai / arsip]
```
Tidak ada proses approval tambahan — begitu ditandai reject oleh Admin Masalembu, status otomatis berjalan ke alur retur.

### 12.6 Perhitungan Tagihan COD — Final
```
total_tagihan_cod = harga_final
```
Tidak ada penjumlahan biaya lain (ongkir kapal, biaya admin, dll). `harga_final` sudah final dan menjadi nominal yang dikonfirmasi kurir saat penagihan COD.
