<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Audience match: each non-null targeting dimension on the informativo must match the user (AND).
     * Fully global items (all dimensions null) are visible to everyone.
     */
    public function scopeVisibleToAudience(Builder $query, User $user): Builder
    {
        return $query
            ->where(function (Builder $course) use ($user) {
                $course->whereNull('course_id')->orWhere('course_id', $user->course_id);
            })
            ->where(function (Builder $year) use ($user) {
                $year->whereNull('year_id')->orWhere('year_id', $user->year_id);
            })
            ->where(function (Builder $department) use ($user) {
                $department->whereNull('department_id')->orWhere('department_id', $user->department_id);
            });
    }

    public function isVisibleTo(User $user): bool
    {
        if (!is_null($this->course_id) && $this->course_id !== $user->course_id) {
            return false;
        }
        if (!is_null($this->year_id) && $this->year_id !== $user->year_id) {
            return false;
        }
        if (!is_null($this->department_id) && $this->department_id !== $user->department_id) {
            return false;
        }

        return true;
    }

    /**
     * Readers who should receive notifications for this informativo (AND on set dimensions).
     */
    public function relevantReadersQuery(): Builder
    {
        $query = User::query()
            ->permission('informativo.receive_notifications')
            ->role('leitor');

        if (!is_null($this->course_id)) {
            $query->where('course_id', $this->course_id);
        }
        if (!is_null($this->year_id)) {
            $query->where('year_id', $this->year_id);
        }
        if (!is_null($this->department_id)) {
            $query->where('department_id', $this->department_id);
        }

        return $query;
    }
}
