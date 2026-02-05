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
        'emergency',
        // Category breakdown columns (added in 2026_02_01_135240_... migration)
        'foreigner',
        'public',
        'ippm_private',
        'ippm_credit',
        'cost_sharing',
        'nssf',
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
