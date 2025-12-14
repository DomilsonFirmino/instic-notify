<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Informativo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title','content','status','category_id','course_id','year_id','department_id','author_id','published_by','published_at','publish_at','unpublished_at','rejection_reason'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'publish_at' => 'datetime',
        'unpublished_at' => 'datetime',
    ];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function year(): BelongsTo { return $this->belongsTo(Year::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by'); }
    public function files(): HasMany { return $this->hasMany(InformativoFile::class); }
    public function favorites(): HasMany { return $this->hasMany(Favorite::class); }
    public function reviews(): HasMany { return $this->hasMany(InformativoReview::class); }
}
