<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['kurir_id', 'tanggal', 'daftar_paket_urutan', 'status'])]
class RuteHarian extends Model
{
    protected $table = 'rute_harian';

    protected $casts = [
        'daftar_paket_urutan' => 'array',
        'tanggal' => 'date',
    ];

    public function kurir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kurir_id');
    }
}
