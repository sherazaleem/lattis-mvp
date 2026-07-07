<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only job outcome log. Every job logs here (job_type, entity_id,
 * status, message, payload) — see .cursorrules style rule. Foundation for
 * future alerts/monitoring, not optional logging.
 */
class SystemLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'job_type', 'entity_type', 'entity_id', 'status', 'message', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
