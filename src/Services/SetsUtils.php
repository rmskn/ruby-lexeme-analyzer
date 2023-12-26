<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sets;
use Illuminate\Support\Collection;

class SetsUtils
{
    public static function generateSets(): Sets
    {
        return new Sets(
            self::generateL(),
            self::generateD(),
            self::generateP(),
            self::generateE(),
        );
    }

    /**
     * @return Collection<int, string>
     */
    private static function generateL(): Collection // Формирование множества прописных латинских букв и символа '_'
    {
        $stringL = '';
        for ($i = 65; $i <= 90; $i++) {
            $stringL .= chr($i);
        }

        return collect(str_split($stringL . strtolower($stringL)))->push('_');
    }

    /**
     * @return Collection<int, string>
     */
    private static function generateD(): Collection // Формирование множества цифр
    {
        $D = collect();

        for ($i = 48; $i <= 57; $i++) {
            $D[] = chr($i);
        }

        return $D;
    }

    /**
     * @return Collection<int, string>
     */
    private static function generateP(): Collection // Формирование множества знаков препинания
    {
        return collect(
            [
                '=',
                '+',
                '-',
                '*',
                '/',
                ',',
                ';',
                '!',
                '<',
                '>',
                '(',
                ')',
                '{',
                '}',
                '_',
                '#',
                '&',
                '^',
                '%',
                '$',
                '@',
            ]
        );
    }

    /**
     * @return Collection<int, string>
     */
    private static function generateE(): Collection // Создание множества игнорируемых символов
    {
        return collect(
            [
                chr(32),
                chr(8),
                chr(10),
                chr(13),
            ]
        );
    }
}
