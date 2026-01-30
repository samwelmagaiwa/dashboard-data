<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'mr_number',
        'visit_num',
        'visit_type',
        'visit_date',
        'doct_code',
        'cons_time',
        'cons_no',
        'clinic_code',
        'clinic_name',
        'cons_doctor',
        'visit_status',
        'accomp_code',
        'doct_cons_dt',
        'doct_cons_tm',
        'dept_code',
        'dept_name',
        'pat_catg',
        'ref_hosp',
        'nhi_yn',
        'pat_catg_nm',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'visit_date' => 'date',
        'doct_cons_dt' => 'date',
    ];

    /**
     * Mutator to handle YYYYMMDD date format from API for visit_date
     */
    public function setVisitDateAttribute($value)
    {
        $value = trim($value);
        if (is_string($value) && strlen($value) === 8 && is_numeric($value)) {
            $this->attributes['visit_date'] = \Carbon\Carbon::createFromFormat('Ymd', $value)->toDateString();
        } else {
            $this->attributes['visit_date'] = empty($value) ? null : $value;
        }
    }

    /**
     * Mutator to handle YYYYMMDD date format from API for doct_cons_dt
     */
    public function setDoctConsDtAttribute($value)
    {
        $value = trim($value);
        if (is_string($value) && strlen($value) === 8 && is_numeric($value)) {
            $this->attributes['doct_cons_dt'] = \Carbon\Carbon::createFromFormat('Ymd', $value)->toDateString();
        } else {
            $this->attributes['doct_cons_dt'] = empty($value) ? null : $value;
        }
    }

    /**
     * Mutator to sync nhi_yn with is_nhif ('Y'/'N')
     */
    public function setNhiYnAttribute($value)
    {
        $value = trim($value);
        $this->attributes['nhi_yn'] = empty($value) ? null : $value;
        $this->attributes['is_nhif'] = ($value === 'Y') ? 'Y' : 'N';
    }
}
