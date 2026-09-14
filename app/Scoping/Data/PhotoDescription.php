<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\PhotoView;
use App\Scoping\Enums\Section;

final readonly class PhotoDescription
{
    /**
     * @param  list<Section>  $sections
     */
    public function __construct(
        public int $photo,
        public PhotoView $view,
        public array $sections,
        public bool $usable,
    ) {}

    public function covers(Section $section): bool
    {
        return $this->usable && $this->view->coversSection() && in_array($section, $this->sections, true);
    }
}
