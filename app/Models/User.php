<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasRoles, HasFactory,Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'course_id',
        'year_id',
        'department_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function course()
    {
        return $this->belongsTo(\App\Models\Course::class);
    }

    public function year()
    {
        return $this->belongsTo(\App\Models\Year::class);
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class);
    }

    public function logs()
    {
        return $this->hasMany(\App\Models\Log::class);
    }

    public function notifications()
    {
        return $this->hasMany(\App\Models\Notification::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function authoredInformativos()
    {
        return $this->hasMany(\App\Models\Informativo::class, 'author_id');
    }

    public function publishedInformativos()
    {
        return $this->hasMany(\App\Models\Informativo::class, 'published_by');
    }

    public function favorites()
    {
        return $this->hasMany(\App\Models\Favorite::class);
    }
}
