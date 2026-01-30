<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyDashboardStat extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'stat_date',
        'total_visits',
        'consulted',
        'pending',
        'new_visits',
        'followups',
        'nhif_visits',
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
