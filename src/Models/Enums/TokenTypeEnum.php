<?php

declare(strict_types=1);

namespace App\Models\Enums;

enum TokenTypeEnum: string
{
    case IDENTIFIER = 'Идентификатор';
    case LITERAL = 'Литерал';
    case SEPARATOR = 'Разделитель';
    case SERVICE = 'Служебное слово';
    case COMMENT = 'Комментарий';
}
