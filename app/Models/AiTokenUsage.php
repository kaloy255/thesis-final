<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiTokenUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'feature',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'is_estimated',
        'meta',
    ];

    protected $casts = [
        'is_estimated' => 'boolean',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

