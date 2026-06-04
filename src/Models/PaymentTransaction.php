<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The PayFast transaction row.
 *
 * The package keeps this class lean (no Auditable / Activitylog trait pulled in)
 * so consumers without those packages can use it as-is. Bring your own observer
 * or extend the class to add per-app behaviour.
 */
class PaymentTransaction extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_INITIATED = 'initiated';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const SOURCE_EXTERNAL = 'external';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_API = 'api';

    public const NOTIFICATION_REDIRECT = 'Redirection';

    public const NOTIFICATION_IPN = 'IPN';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'payfast_webhook_payload' => 'array',
        'payfast_error_payload' => 'array',
        'order_date' => 'datetime',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function getTable()
    {
        return parent::getTable() ?: 'payment_transactions';
    }

    public function user(): BelongsTo
    {
        $userModel = config('payfast-laravel-package.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
        ], true);
    }
}
