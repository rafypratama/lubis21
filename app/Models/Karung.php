<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['kode_karung', 'status', 'created_by', 'kurir_id', 'tanggal_transport', 'tanggal_sampai'])]
class Karung extends Model
{
    protected $table = 'karung';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kurir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kurir_id');
    }

    public function pakets(): HasMany
    {
        return $this->hasMany(Paket::class, 'karung_id');
    }
}
