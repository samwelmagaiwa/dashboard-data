<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Icd extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'description',
        'abbreviation',
    ];

    /**
     * Relationship with visits as provisional diagnosis
     */
    public function provisionalVisits()
    {
        return $this->hasMany(Visit::class, 'prov_diag', 'code');
    }

    /**
     * Relationship with visits as final diagnosis
     */
    public function finalVisits()
    {
        return $this->hasMany(Visit::class, 'final_diag', 'code');
    }
}
