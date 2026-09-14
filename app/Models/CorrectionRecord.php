<?php

namespace App\Models;

use App\Scoping\Data\Correction;
use App\Scoping\Enums\CorrectionField;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $line_id
 * @property CorrectionField $field
 * @property string $model_value
 * @property string $customer_value
 * @property string|null $reason
 * @property string $source
 */
class CorrectionRecord extends Model
{
    protected $fillable = ['observation_run_id', 'line_id', 'field', 'model_value', 'customer_value', 'reason', 'source'];

    protected function casts(): array
    {
        return ['field' => CorrectionField::class];
    }

    public function toCorrection(): Correction
    {
        return new Correction($this->line_id, $this->field, $this->model_value, $this->customer_value, $this->reason);
    }
}
