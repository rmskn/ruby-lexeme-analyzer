<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sets;

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
     * @return array<int, string>
     */
    private static function generateL(): array // Формирование множества прописных латинских букв и символа '_'
    {
        $stringL = '';
        for ($i = 65; $i <= 90; $i++) {
            $stringL .= chr($i);
        }

        $result = str_split($stringL . strtolower($stringL));

        $result[] = '_';

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private static function generateD(): array // Формирование множества цифр
    {
        $D = [];

        for ($i = 48; $i <= 57; $i++) {
            $D[] = chr($i);
        }

        return $D;
    }

    /**
     * @return array<int, string>
     */
    private static function generateP(): array // Формирование множества знаков препинания
    {
        return [
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
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function generateE(): array // Создание множества игнорируемых символов
    {
        return [
            chr(32),
            chr(8),
            chr(10),
            chr(13),
        ];
    }
}
