<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Qualification extends Model
{
    use HasFactory;

    protected $table = 'qualifications';

    protected $fillable = [
        'qualification_name',
        'global_specialty_id',
        'description',
    ];

    public function globalSpecialty(): BelongsTo
    {
        return $this->belongsTo(GlobalSpecialty::class, 'global_specialty_id');
    }

    public function specializations(): HasMany
    {
        return $this->hasMany(Specialization::class);
    }
    
}


