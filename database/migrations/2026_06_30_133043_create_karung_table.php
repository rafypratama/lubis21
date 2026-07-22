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
        Schema::create('karung', function (Blueprint $table) {
            $table->id();
            $table->string('kode_karung')->unique();
            $table->enum('status', [
                'diisi', 
                'siap_transport', 
                'otw_pelabuhan_sumenep', 
                'di_pelabuhan_sumenep', 
                'dalam_perjalanan', 
                'di_pelabuhan_masalembu', 
                'otw_kantor_masalembu', 
                'sampai_masalembu', 
                'dibongkar'
            ])->default('diisi');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('kurir_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('tanggal_transport')->nullable();
            $table->date('tanggal_sampai')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('karung');
    }
};
