<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaketController;
use App\Http\Controllers\KarungController;
use App\Http\Controllers\KurirController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

// Auth Routes (Public)
Route::post('/login', [AuthController::class, 'login']);
Route::get('/track/{kode_resi}', [PaketController::class, 'trackPublic']);

// Auth Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth info
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // General access: lists and details
    Route::get('/paket', [PaketController::class, 'index']);
    Route::get('/paket/{id}', [PaketController::class, 'show']);
    Route::get('/karung', [KarungController::class, 'index']);
    Route::get('/karung/{id}', [KarungController::class, 'show']);
    Route::get('/kurir', [PaketController::class, 'getKurirs']);

    // Karung Transit Actions (Flexible for Admin & Kurir roles)
    Route::post('/karung/{id}/assign-kurir', [KarungController::class, 'assignKurir']);
    Route::post('/karung/{id}/otw-pelabuhan', [KarungController::class, 'otwPelabuhan']);
    Route::post('/karung/{id}/tiba-pelabuhan-sumenep', [KarungController::class, 'tibaPelabuhanSumenep']);
    Route::post('/karung/{id}/dalam-perjalanan', [KarungController::class, 'dalamPerjalanan']);
    Route::post('/karung/{id}/tiba-pelabuhan-masalembu', [KarungController::class, 'tibaPelabuhanMasalembu']);
    Route::post('/karung/{id}/otw-kantor-masalembu', [KarungController::class, 'otwKantorMasalembu']);
    Route::post('/karung/{id}/sampai-masalembu', [KarungController::class, 'sampaiMasalembu']);
    Route::post('/karung/{id}/bongkar', [KarungController::class, 'bongkar']);

    // Admin Sumenep Actions
    Route::middleware('role:admin_sumenep,owner')->group(function () {
        Route::post('/paket', [PaketController::class, 'store']);
        Route::post('/paket/{id}/assign-karung', [PaketController::class, 'assignKarung']);
        Route::post('/karung', [KarungController::class, 'store']);
        Route::post('/karung/{id}/siap-transport', [KarungController::class, 'siapTransport']);
    });

    // Admin Masalembu Actions
    Route::middleware('role:admin_masalembu,owner')->group(function () {
        Route::post('/paket/{id}/qc', [PaketController::class, 'qcCheck']);
        Route::post('/paket/{id}/assign-kurir', [PaketController::class, 'assignKurir']);
    });

    // Kurir Actions
    Route::middleware('role:kurir,owner')->group(function () {
        Route::post('/kurir/{id}/mulai-rute', [KurirController::class, 'mulaiRute']);
        Route::get('/kurir/{id}/rute-aktif', [KurirController::class, 'ruteAktif']);
        Route::post('/kurir/{id}/update-lokasi', [KurirController::class, 'updateLokasi']);
        
        Route::post('/paket/{id}/konfirmasi-cod', [KurirController::class, 'konfirmasiCod']);
        Route::post('/paket/{id}/upload-bukti', [KurirController::class, 'uploadBukti']);
        Route::post('/paket/{id}/delivered', [KurirController::class, 'delivered']);
    });

    // Owner Actions
    Route::middleware('role:owner')->group(function () {
        Route::get('/dashboard/rekap', [DashboardController::class, 'getRekap']);
        Route::get('/export/csv', [ExportController::class, 'exportCsv']);
    });
});
