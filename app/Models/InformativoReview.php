<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformativoReview extends Model
{
    use HasFactory;

    public $timestamps = false; // we only have created_at

    protected $table = 'informativo_reviews';

    protected $fillable = [
        'informativo_id', 'reviewer_id', 'decision', 'comment', 'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function informativo(): BelongsTo
    {
        return $this->belongsTo(Informativo::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
