<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enums\RealtimeOutputModeEnum;
use App\Models\Enums\TokenTypeEnum;
use App\Models\Sets;
use App\Models\TokenAddress;

class Compiler
{
    private const numberToTableMap = [
        1 => 'serviceWords',
        2 => 'identifiers',
        3 => 'literals',
        4 => 'separators',
    ];

    private const tokenTypeNameToTableNumberMap = [
        'SERVICE' => 1,
        'IDENTIFIER' => 2,
        'LITERAL' => 3,
        'SEPARATOR' => 4,
    ];

    private bool $headerWasPrint = false;
    /**
     * @var array<int, TokenAddress>
     */
    private array $standardTable;

    /**
     * @var array<int, string>
     */
    private array $serviceWords; // 1

    /**
     * @var array<int, string>
     */
    private array $identifiers; // 2

    /**
     * @var array<int, string>
     */
    private array $literals; // 3

    /**
     * @var array<int, string>
     */
    private array $separators; // 4

    public function __construct(
        private readonly object $config,
        private readonly Sets   $sets,
    )
    {
        $this->standardTable = [];
        $this->serviceWords = [];
        $this->identifiers = [];
        $this->literals = [];
        $this->separators = [];
    }

    public function scan(string $inputFileData): ?bool
    {
        // Подготовка данных входного файла
        $text = $this->prepareInputFileData($inputFileData);

        // Тип лексемы
        /** @var TokenTypeEnum|null $type */
        $type = null;

        // Лексема
        $token = '';

        // Распознование лексемы
        while (!empty($text)) {
            // Получение первого символа
            $char = reset($text);

            switch ($type) {
                case null: // Тип лексемы пока не определён
                    if (
                        ($char === $this->config->globalVariableSymbol) // Условие для глобальных переменных
                        || $this->inArray($char, $this->sets->L)
                    ) {
                        // Сивол является латинской буквой или подчёркиванием, лексема является идентификатором
                        $type = TokenTypeEnum::IDENTIFIER;

                        //Добавление символа к лексеме
                        $token = $char;
                    } elseif ($this->inArray($char, $this->sets->D)) {
                        // Сивол является цифрой, лексема является литералом
                        $type = TokenTypeEnum::LITERAL;

                        //Добавление символа к лексеме
                        $token = $char;
                    } elseif ($this->inArray($char, $this->sets->P)) {
                        // Сивол является цифрой, вероятнее всего лексема является разделителем
                        $type = TokenTypeEnum::SEPARATOR;

                        //Добавление символа к лексеме
                        $token = $char;
                    }

                    // Удаление первого символа
                    array_shift($text);

                    break;
                case TokenTypeEnum::IDENTIFIER: // Тип лексемы - идентификатор
                    if ($this->inArray($char, $this->sets->L) || $this->inArray($char, $this->sets->D)) {
                        // Символ является латинской буквой, цифрой или подчёркиванием,
                        // поэтому добавляем его к лексеме
                        $token .= $char;

                        // Удаление первого символа
                        array_shift($text);
                    } elseif ($this->inArray($char, $this->sets->P) || $this->inArray($char, $this->sets->E)) {
                        if ($this->inArray($token, $this->config->serviceWords)) {
                            // Изменение типа на сервсиное слово
                            $type = TokenTypeEnum::SERVICE;
                        } else {
                            $tokenLength = strlen($token);

                            // Проверка длины распознанного идентификатора
                            if ($tokenLength > $this->config->identifierMaxLength) {
                                Log::error("Слишком длинный идентификатор: {$token}");
                                return null;
                            }
                        }

                        // Добавление лексемы и её предварительного типа в таблицу лексем
                        $this->addToken($token, $type);
                        // Переход к распознованию следующей лексемы
                        $type = null;
                    } else {
                        Log::error("Некорректный идентификатор: {$token}");

                        return null;
                    }

                    break;
                case TokenTypeEnum::LITERAL: // Тип лексемы - литерал
                    if ($this->inArray($char, $this->sets->D)) {
                        // Символ является цифрой,
                        // поэтому добавляем его к лексеме
                        $token .= $char;

                        // Удаление первого символа
                        array_shift($text);
                    } elseif ($this->inArray($char, $this->sets->P) || $this->inArray($char, $this->sets->E)) {
                        // Добавление лексемы и её предварительного типа в таблицу лексем
                        $this->addToken($token, $type);

                        // Переход к распознованию следующей лексемы
                        $type = null;
                    } else {
                        Log::error("Некорректный литерал: {$token}{$char}");

                        return null;
                    }

                    break;
                case TokenTypeEnum::SEPARATOR: // Тип лексемы - разделитель
                    // Проверка на однострочный комментарий
                    if ($token === $this->config->comment->single) {
                        // Пропуск символов в комментарии
                        while (!empty($text)) {
                            if ($char === chr(13)) {
                                $this->addToken($token, TokenTypeEnum::COMMENT);

                                break;
                            }

                            // Удаление первого символа
                            $token .= array_shift($text);

                            // Получение первого символа
                            $char = reset($text);
                        }

                        // Переход к распознованию следующей лексемы
                        $type = null;
                    } elseif ($this->inArray($char, $this->sets->P)) {
                        // Символ является разделителем,
                        // поэтому добавляем его к лексеме
                        $token .= $char;

                        // Удаление первого символа
                        array_shift($text);
                    } else { // Проверка на многострочный комментарий комментарий
                        $commentConfig = $this->config->comment;

                        // Проверка на начало комментария
                        $commentStartLength = strlen($commentConfig->start);
                        $candidateForCommentStart = $token
                            . implode(
                                '',
                                array_slice($text, 0, $commentStartLength - strlen($token))
                            );

                        if ($candidateForCommentStart === $commentConfig->start) {
                            // Удаление обозначения начала комментария
                            $text = array_slice($text, $commentStartLength - strlen($token));

                            // Формирование текста комментария
                            $token = $candidateForCommentStart;

                            $commentIsCloseFlag = false;
                            $commentEndLength = strlen($commentConfig->end);
                            while (!empty($text)) {
                                $candidateForCommentEnd = implode(
                                    '', array_slice($text, 0, $commentEndLength)
                                );

                                if ($candidateForCommentEnd === $commentConfig->end) {
                                    // Удаление обозначения конца комментария и формирования полного текста комментария
                                    $token .= $candidateForCommentEnd;
                                    $text = array_slice($text, $commentEndLength);

                                    $this->addToken($token, TokenTypeEnum::COMMENT);

                                    // Переход к распознованию следующей лексемы
                                    $type = null;

                                    $commentIsCloseFlag = true;
                                    break;
                                }

                                // Удаление первого символа и формирование текста комментария
                                $token .= array_shift($text);
                            }

                            // Проерка на закрытие комментария
                            if (!$commentIsCloseFlag) {
                                Log::error('Комментарий не был закрыт');

                                return null;
                            }
                        } elseif ($this->inArray($token, $this->config->operators)) {
                            // Добавление лексемы и её предварительного типа в таблицу лексем
                            $this->addToken($token, $type);

                            // Переход к распознованию следующей лексемы
                            $type = null;
                        } else {
                            Log::error("Некорректный оператор (разделитель): {$token}");

                            return null;
                        }
                    }

                    break;
            }
        }

        return true;
    }

    public function printResult(): void
    {
        $this->printTable(
            'Служебные слова (' . $this->getNumberOfTableByTokenTypeName(TokenTypeEnum::SERVICE) . ')',
            $this->serviceWords
        );
        $this->printTable(
            'Идентификаторы (' . $this->getNumberOfTableByTokenTypeName(TokenTypeEnum::IDENTIFIER) . ')',
            $this->identifiers
        );
        $this->printTable(
            'Литералы (' . $this->getNumberOfTableByTokenTypeName(TokenTypeEnum::LITERAL) . ')',
            $this->literals
        );
        $this->printTable(
            'Разделители (' . $this->getNumberOfTableByTokenTypeName(TokenTypeEnum::SEPARATOR) . ')',
            $this->separators
        );

        $this->printStandardTable();
    }

    /**
     * @param array<int, string> $array
     */
    private function printTable(string $header, array $array): void
    {
        echo $header . "\n";

        foreach ($array as $index => $item) {
            echo "{$index}:\t{$item}\n";
        }
    }

    private function printStandardTable(): void
    {
        echo "Таблица стандартных символов\n";
        echo "Позиция: (таблица, номер)\n\n";

        foreach ($this->standardTable as $item) {
            echo "{$item->table},{$item->number}\t"
                . $this->getTableByNumber($item->table)[$item->number] . "\n";
        }
    }

    /**
     * @return array<int, string>
     */
    private function prepareInputFileData(string $inputFileData): array
    {
        return str_split($inputFileData);
    }

    private function addToken(string $token, TokenTypeEnum $type): void
    {
        if (!$this->config->displayComments && $type === TokenTypeEnum::COMMENT) {
            return;
        }

        $this->realtimeOutput($token, $type);

        switch ($type) {
            case TokenTypeEnum::SERVICE:
                $this->serviceWords[] = $token;
                $this->standardTable[] = new TokenAddress(
                    $this->getNumberOfTableByTokenTypeName($type),
                    count($this->serviceWords) - 1
                );

                break;
            case TokenTypeEnum::IDENTIFIER:
                $this->identifiers[] = $token;
                $this->standardTable[] = new TokenAddress(
                    $this->getNumberOfTableByTokenTypeName($type),
                    count($this->identifiers) - 1
                );

                break;
            case TokenTypeEnum::LITERAL:
                $this->literals[] = $token;
                $this->standardTable[] = new TokenAddress(
                    $this->getNumberOfTableByTokenTypeName($type),
                    count($this->literals) - 1
                );

                break;
            case TokenTypeEnum::SEPARATOR:
                $this->separators[] = $token;
                $this->standardTable[] = new TokenAddress(
                    $this->getNumberOfTableByTokenTypeName($type),
                    count($this->separators) - 1
                );

                break;
            default:
        }
    }

    private function realtimeOutput(string $token, TokenTypeEnum $type): void
    {
        switch ($this->config->realtimeOutputMode) {
            case RealtimeOutputModeEnum::TOKENS_WITH_HEADER:
                if (!$this->headerWasPrint) {
                    echo "Литерал\t|\tТип\n";
                    $this->headerWasPrint = true;
                }
            case RealtimeOutputModeEnum::ONLY_TOKENS:
                $row = "{$token}\t|\t";

                if ($type === TokenTypeEnum::SERVICE) {
                    $row .= TokenTypeEnum::IDENTIFIER->value;
                } else {
                    $row .= $type->value;
                }

                echo $row . "\n";

                break;
            case RealtimeOutputModeEnum::OFF:
        }
    }

    /**
     * @return array<int, string>
     */
    private function getTableByNumber(int $number): array
    {
        return $this->{self::numberToTableMap[$number]};
    }

    private function getNumberOfTableByTokenTypeName(TokenTypeEnum $number): int
    {
        return self::tokenTypeNameToTableNumberMap[$number->name];
    }

    private function inArray(mixed $needle, array $array): bool
    {
        return in_array($needle, $array, true);
    }
}
