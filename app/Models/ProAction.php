<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A request-level decision by the pro: accept the scope, ask the customer for a photo, or adjust
 * the quote with a reason. Line-level changes go through the same corrections as the customer's.
 *
 * @property int $id
 * @property string $kind
 * @property string|null $reason
 * @property int|null $adjusted_price_cents
 * @property CarbonImmutable $created_at
 */
class ProAction extends Model
{
    public const KINDS = ['accept_scope', 'request_photo', 'adjust_quote'];

    protected $fillable = ['job_request_id', 'kind', 'reason', 'adjusted_price_cents'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
