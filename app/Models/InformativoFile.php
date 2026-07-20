<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformativoFile extends Model
{
    use HasFactory;

    protected $fillable = ['informativo_id','path','original_name','size'];
    protected $table = 'informativos_files';

    public function informativo(): BelongsTo
    {
        return $this->belongsTo(Informativo::class);
    }
}
