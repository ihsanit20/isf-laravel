<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'recipient_phone',
    'normalized_phone',
    'message',
    'status',
    'provider_code',
    'http_status',
    'response_body',
    'error_message',
    'smsable_type',
    'smsable_id',
])]
class SmsLog extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public function smsable(): MorphTo
    {
        return $this->morphTo();
    }
}
