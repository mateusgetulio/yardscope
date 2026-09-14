<?php

namespace App\Extraction;

use App\Scoping\Data\PhotoInput;
use App\Scoping\Exceptions\ExtractionFailed;

/**
 * A live extractor that can hand back the model's raw answer, which is what the record command saves.
 */
interface RecordsObservations
{
    /**
     * @param  list<PhotoInput>  $photos
     * @return array<string, mixed>
     *
     * @throws ExtractionFailed
     */
    public function observe(array $photos, string $sentence): array;
}
