<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sync_type',
        'sync_date',
        'status',
        'records_synced',
        'error_message',
        'started_at',
        'finished_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sync_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
