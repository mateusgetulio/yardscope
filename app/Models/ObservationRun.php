<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $job_request_id
 * @property int $photo_count
 * @property array<string, mixed>|null $observation
 * @property string|null $failure
 */
class ObservationRun extends Model
{
    protected $fillable = ['job_request_id', 'photo_count', 'observation', 'failure'];

    protected function casts(): array
    {
        return ['observation' => 'array'];
    }

    /**
     * @return BelongsTo<JobRequest, $this>
     */
    public function jobRequest(): BelongsTo
    {
        return $this->belongsTo(JobRequest::class);
    }

    /**
     * @return HasMany<CorrectionRecord, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(CorrectionRecord::class)->orderBy('id');
    }
}
