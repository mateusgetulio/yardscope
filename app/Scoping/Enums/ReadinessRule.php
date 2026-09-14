<?php

namespace App\Scoping\Enums;

enum ReadinessRule: string
{
    case UsablePhotos = 'R1';
    case SectionCoverage = 'R2';
    case RequestedUnseen = 'R3';
    case Evidence = 'R4';
    case ManualOnly = 'R5';
    case RepeatedFailure = 'R6';
}
