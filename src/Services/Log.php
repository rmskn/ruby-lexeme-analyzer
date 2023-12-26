<?php

declare(strict_types=1);

namespace App\Services;

class Log
{
    public static function info(string $text): void
    {
        echo "\033[32mИнформация: \033[39m{$text}\n";
    }

    public static function error(string $text): void
    {
        echo "\033[31mОшибка: \033[39m{$text}\n";

    }
}
