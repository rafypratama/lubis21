<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('paket', function (Blueprint $table) {
            $table->id();
            $table->string('resi_asli');
            $table->string('ekspedisi_asal');
            $table->string('kode_resi_baru')->unique();
            $table->string('nama_penerima');
            $table->text('alamat_penerima');
            $table->string('no_hp_penerima')->nullable();
            $table->decimal('berat_kg', 8, 2);
            $table->decimal('harga_asli', 15, 2);
            $table->decimal('harga_final', 15, 2);
            $table->enum('tipe_pembayaran', ['cod_qris', 'cod_transfer', 'non_cod']);
            $table->enum('status_pembayaran_cod', ['belum', 'sudah'])->nullable();
            $table->foreignId('karung_id')->nullable()->constrained('karung')->onDelete('set null');
            $table->enum('status', [
                'diterima_sumenep',
                'masuk_karung',
                'siap_transport',
                'otw_pelabuhan_sumenep',
                'di_pelabuhan_sumenep',
                'dalam_perjalanan',
                'di_pelabuhan_masalembu',
                'otw_kantor_masalembu',
                'sampai_masalembu',
                'dikembalikan_ke_sumenep',
                'dalam_pengiriman',
                'menunggu_pembayaran_cod',
                'delivered'
            ])->default('diterima_sumenep');
            $table->enum('hasil_qc', ['lolos', 'reject'])->nullable();
            $table->text('catatan_reject')->nullable();
            $table->foreignId('kurir_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('foto_bukti_delivery')->nullable();
            $table->string('bukti_pembayaran_cod')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('wilayah_tujuan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paket');
    }
};
