<?php

declare(strict_types=1);

namespace App\Models\Enums;

enum RealtimeOutputModeEnum: int
{
    case OFF = 0;
    case ONLY_TOKENS = 1;
    case TOKENS_WITH_HEADER = 2;
}
