<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'body'])]
class Note extends Model
{
    protected function casts(): array
    {
        // Encrypted at rest with APP_KEY (AES-256-GCM, see config/app.php `cipher`).
        return [
            'title' => 'encrypted',
            'body' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
