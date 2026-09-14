<?php

namespace App\Scoping\Enums;

enum ValueOrigin: string
{
    case AiObserved = 'ai_observed';
    case CustomerCorrected = 'customer_corrected';
}
