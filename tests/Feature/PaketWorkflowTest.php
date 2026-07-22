<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Karung;
use App\Models\Paket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test calculateFinalPrice logic.
     */
    public function test_calculate_final_price(): void
    {
        // Under 3 kg: hargaAsli + 5000
        $price1 = Paket::calculateFinalPrice(10000, 2.5);
        $this->assertEquals(15000, $price1);

        // 3 kg or more: hargaAsli * 3000
        $price2 = Paket::calculateFinalPrice(10000, 3.0);
        $this->assertEquals(30000000.0, $price2);
    }

    /**
     * Test the entire package lifecycle and state transitions.
     */
    public function test_complete_paket_lifecycle(): void
    {
        // 1. Setup roles
        $adminSumenep = User::factory()->create(['role' => 'admin_sumenep']);
        $adminMasalembu = User::factory()->create(['role' => 'admin_masalembu']);
        $kurir = User::factory()->create([
            'role' => 'kurir',
            'wilayah' => 'Masalembu Utara'
        ]);

        // 2. Create Paket (Sumenep Admin)
        $this->actingAs($adminSumenep);
        
        $response = $this->postJson('/api/paket', [
            'resi_asli' => 'TESTRESI999',
            'ekspedisi_asal' => 'J&T',
            'nama_penerima' => 'Penerima Test',
            'alamat_penerima' => 'Jl. Kebangsaan Masalembu',
            'no_hp_penerima' => '08123456789',
            'berat_kg' => 1.5,
            'harga_asli' => 50000,
            'tipe_pembayaran' => 'non_cod',
            'wilayah_tujuan' => 'Masalembu Utara',
        ]);

        $response->assertStatus(201);
        $paketId = $response->json('data.id');
        $kodeResiBaru = $response->json('data.kode_resi_baru');

        $this->assertDatabaseHas('paket', [
            'id' => $paketId,
            'status' => 'diterima_sumenep',
            'harga_final' => 55000.00 // 50000 + 5000 (under 3kg)
        ]);

        // 3. Create Karung & Assign Paket
        $responseKarung = $this->postJson('/api/karung');
        $responseKarung->assertStatus(201);
        $karungId = $responseKarung->json('data.id');

        $responseAssign = $this->postJson("/api/paket/{$paketId}/assign-karung", [
            'karung_id' => $karungId
        ]);
        $responseAssign->assertStatus(200);

        $this->assertDatabaseHas('paket', [
            'id' => $paketId,
            'status' => 'masuk_karung',
            'karung_id' => $karungId
        ]);

        // 4. Send Karung
        $this->postJson("/api/karung/{$karungId}/siap-transport")->assertStatus(200);
        $this->postJson("/api/karung/{$karungId}/dalam-perjalanan")->assertStatus(200);

        $this->assertDatabaseHas('paket', [
            'id' => $paketId,
            'status' => 'dalam_perjalanan'
        ]);

        // 5. Arrive & Unpack (Masalembu Admin)
        $this->actingAs($adminMasalembu);
        
        $this->postJson("/api/karung/{$karungId}/sampai-masalembu")->assertStatus(200);
        $this->postJson("/api/karung/{$karungId}/bongkar")->assertStatus(200);

        $this->assertDatabaseHas('paket', [
            'id' => $paketId,
            'status' => 'sampai_masalembu'
        ]);

        // 6. QC Check & Auto Assign Courier
        $responseQc = $this->postJson("/api/paket/{$paketId}/qc", [
            'hasil_qc' => 'lolos'
        ]);
        $responseQc->assertStatus(200);

        // Auto assignment based on matching wilayah
        $this->assertDatabaseHas('paket', [
            'id' => $paketId,
            'status' => 'dalam_pengiriman',
            'kurir_id' => $kurir->id
        ]);

        // 7. Courier delivery action
        $this->actingAs($kurir);

        // Start route
        $this->postJson("/api/kurir/{$kurir->id}/mulai-rute")->assertStatus(200);

        // Upload delivery proof first
        $fakePhoto = \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg');
        $responseUpload = $this->postJson("/api/paket/{$paketId}/upload-bukti", [
            'foto_bukti_delivery' => $fakePhoto
        ]);
        $responseUpload->assertStatus(200);

        // Confirm delivery
        $responseDelivered = $this->postJson("/api/paket/{$paketId}/delivered");
        $responseDelivered->assertStatus(200);

        $this->assertDatabaseHas('paket', [
            'id' => $paketId,
            'status' => 'delivered'
        ]);
    }
}
