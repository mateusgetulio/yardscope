<?php

namespace App\Scoping;

use App\Scoping\Data\Observation;
use App\Scoping\Data\PhotoInput;
use App\Scoping\Exceptions\ExtractionFailed;
use App\Scoping\Exceptions\NoRecordedObservation;

interface VisionExtractor
{
    /**
     * @param  list<PhotoInput>  $photos
     *
     * @throws ExtractionFailed when the model cannot produce a readable observation (rule R6)
     * @throws NoRecordedObservation in fixture mode, when nothing was recorded for this request
     */
    public function extract(array $photos, string $sentence): Observation;
}
