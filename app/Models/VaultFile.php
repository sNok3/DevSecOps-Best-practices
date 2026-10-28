<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// No fillable fields: every column is set by the server, never from request input.
class VaultFile extends Model
{
    protected function casts(): array
    {
        return [
            'original_name' => 'encrypted',
            'size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
