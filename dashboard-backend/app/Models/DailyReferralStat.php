<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReferralStat extends Model
{
    protected $fillable = [
        'stat_date',
        'ref_hosp_code',
        'ref_hosp_name',
        'count',
    ];

    protected $casts = [
        'stat_date' => 'date',
    ];
}
