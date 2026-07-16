<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class Institution extends Authenticatable
{
    use Notifiable, HasApiTokens;

    protected $table = 'institutions';

    protected $fillable = [
        'name',
        'description1',
        'description2',
        'description3',
        'location',
        'email',
        'phone',
        'website',
        'verified',
        'logo_url',
        'photo_url',
        'dormitory',
        'grants',
        'password',
        'type',
        'directions',
        'latitude',
        'longitude'
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = ['likes_count'];

    public function getLikesCountAttribute()
    {
        // Не ходим в БД, если счётчик уже загружен через withCount()
        if (array_key_exists('likes_count', $this->attributes)) {
            return (int) $this->attributes['likes_count'];
        }
        if ($this->relationLoaded('likes')) {
            return $this->likes->count();
        }
        return $this->likes()->count();
    }

    public function events()
    {
        return $this->hasMany(EventsCalendar::class);
    }

    public function specializations()
    {
        return $this->belongsToMany(Specialization::class, 'institution_specialties', 'institution_id', 'university_specialization_id')
            ->withPivot('cost', 'duration');
    }

    public function collegeSpecializations()
    {
        return $this->belongsToMany(CollegeSpecialization::class, 'college_institution_specs', 'institution_id', 'college_specialization_id')
                    ->withPivot('cost', 'duration')
            ->with(['qualification' => function($query) {
                $query->with('collegeGlobalSpecialty');
            }]);
    }

    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    public function grants()
    {
        return $this->hasMany(Grant::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function getAverageRatingAttribute()
     {
        return $this->reviews()->avg('rating') ?? 0;
     }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }   

    public function institutionApplications()
    {
        return $this->hasMany(InstitutionApplication::class);
    }

    private function makeAbsoluteUrl($path)
    {
        if (!$path) return null;

        $url = Storage::url($path); // /storage/...

        // Если URL относительный – добавляем APP_URL
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = rtrim(config('app.url'), '/') . $url;
        }
        return $url;
    }

    public function getLogoUrlAttribute($value)
    {
        return $this->makeAbsoluteUrl($value);
    }

    public function getPhotoUrlAttribute($value)
    {
        return $this->makeAbsoluteUrl($value);
    }
}