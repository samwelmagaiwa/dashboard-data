<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicateVisit extends Model
{
    protected $fillable = [
        'mr_number',
        'visit_num',
        'visit_date',
        'clinic_code',
        'clinic_name',
        'cons_time',
        'cons_no',
        'dept_code',
        'dept_name',
        'cons_doctor',
        'pat_catg_nm',
        'occurrence_count',
        'synchronized_at',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'occurrence_count' => 'integer',
        'synchronized_at' => 'datetime',
    ];
}
