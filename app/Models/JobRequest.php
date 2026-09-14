<?php

namespace App\Models;

use App\Scoping\Data\PhotoInput;
use App\Scoping\Data\PropertyProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $sentence
 * @property array<string, string> $profile
 * @property list<array{number: int, path: string, mime_type: string}> $photos
 * @property string|null $readiness
 * @property int|null $booked_price_cents
 * @property CarbonImmutable|null $booked_at
 */
class JobRequest extends Model
{
    use HasUlids;

    protected $fillable = ['sentence', 'profile', 'photos', 'readiness', 'booked_price_cents', 'booked_at'];

    protected function casts(): array
    {
        return [
            'profile' => 'array',
            'photos' => 'array',
            'booked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<ObservationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ObservationRun::class);
    }

    /**
     * @return HasMany<ProAction, $this>
     */
    public function proActions(): HasMany
    {
        return $this->hasMany(ProAction::class)->orderBy('id');
    }

    public function latestRun(): ?ObservationRun
    {
        return $this->runs()->latest('id')->first();
    }

    public function propertyProfile(): PropertyProfile
    {
        return PropertyProfile::fromArray($this->profile);
    }

    /**
     * @return list<PhotoInput>
     */
    public function photoInputs(): array
    {
        return array_map(fn (array $photo): PhotoInput => new PhotoInput(
            $photo['number'],
            Storage::disk('local')->path($photo['path']),
            $photo['mime_type'],
        ), $this->photos);
    }

    public function isBooked(): bool
    {
        return $this->booked_at !== null;
    }
}
