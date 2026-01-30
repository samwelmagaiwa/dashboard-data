<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicStat extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'stat_date',
        'clinic_code',
        'clinic_name',
        'total_visits',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'stat_date' => 'date',
    ];
}
