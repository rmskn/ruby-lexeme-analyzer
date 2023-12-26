<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enums\RealtimeOutputModeEnum;
use App\Models\Enums\TokenTypeEnum;
use App\Models\Sets;
use App\Models\TokenAddress;
use Illuminate\Support\Collection;

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
     * @var Collection<int, TokenAddress>
     */
    private Collection $standardTable;

    /**
     * @var Collection<int, string>
     */
    private Collection $serviceWords; // 1

    /**
     * @var Collection<int, string>
     */
    private Collection $identifiers; // 2

    /**
     * @var Collection<int, string>
     */
    private Collection $literals; // 3

    /**
     * @var Collection<int, string>
     */
    private Collection $separators; // 4

    public function __construct(
        private readonly object $config,
        private readonly Sets $sets,
        private readonly RealtimeOutputModeEnum $realtimeOutputMode,
    )
    {
        $this->standardTable = collect();
        $this->serviceWords = collect();
        $this->identifiers = collect();
        $this->literals = collect();
        $this->separators = collect();
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
        while (!$text->isEmpty()) {
            // Получение первого символа
            $char = $text->first();

            switch ($type) {
                case null: // Тип лексемы пока не определён
                    if (
                        ($char === $this->config->globalVariableSymbol) // Условие для глобальных переменных
                        || $this->sets->L->contains($char)
                    ) {
                        // Сивол является латинской буквой или подчёркиванием, лексема является идентификатором
                        $type = TokenTypeEnum::IDENTIFIER;

                        //Добавление символа к лексеме
                        $token = $char;
                    } elseif ($this->sets->D->contains($char)) {
                        // Сивол является цифрой, лексема является литералом
                        $type = TokenTypeEnum::LITERAL;

                        //Добавление символа к лексеме
                        $token = $char;
                    } elseif ($this->sets->P->contains($char)) {
                        // Сивол является цифрой, вероятнее всего лексема является разделителем
                        $type = TokenTypeEnum::SEPARATOR;

                        //Добавление символа к лексеме
                        $token = $char;
                    }

                    // Удаление первого символа
                    $text->shift();

                    break;
                case TokenTypeEnum::IDENTIFIER: // Тип лексемы - идентификатор
                    if ($this->sets->L->contains($char) || $this->sets->D->contains($char)) {
                        // Символ является латинской буквой, цифрой или подчёркиванием,
                        // поэтому добавляем его к лексеме
                        $token .= $char;

                        // Удаление первого символа
                        $text->shift();
                    } elseif ($this->sets->P->contains($char) || $this->sets->E->contains($char)) {
                        if (in_array($token, $this->config->serviceWords, true)) {
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
                    if ($this->sets->D->contains($char)) {
                        // Символ является цифрой,
                        // поэтому добавляем его к лексеме
                        $token .= $char;

                        // Удаление первого символа
                        $text->shift();
                    } elseif ($this->sets->P->contains($char) || $this->sets->E->contains($char)) {
                        // Добавление лексемы и её предварительного типа в таблицу лексем
                        $this->addToken($token, $type);

                        // Переход к распознованию следующей лексемы
                        $type = null;
                    } else {
                        Log::error("Некорректный литерал: {$token}");

                        return null;
                    }

                    break;
                case TokenTypeEnum::SEPARATOR: // Тип лексемы - разделитель
                    // Проверка на однострочный комментарий
                    if ($token === $this->config->comment->single) {
                        // Пропуск символов в комментарии
                        while (!$text->isEmpty()) {
                            if ($char === chr(13)) {
                                $this->addToken($token, TokenTypeEnum::COMMENT);

                                break;
                            }

                            // Удаление первого символа
                            $token .= $text->shift();

                            // Получение первого символа
                            $char = $text->first();
                        }

                        // Переход к распознованию следующей лексемы
                        $type = null;
                    } elseif ($this->sets->P->contains($char)) {
                        // Символ является разделителем,
                        // поэтому добавляем его к лексеме
                        $token .= $char;

                        // Удаление первого символа
                        $text->shift();
                    } else { // Проверка на многострочный комментарий комментарий
                        $commentConfig = $this->config->comment;

                        // Проверка на начало комментария
                        $commentStartLength = strlen($commentConfig->start);
                        $candidateForCommentStart = $token
                            . implode('', $text->take($commentStartLength - strlen($token))->toArray());

                        if ($candidateForCommentStart === $commentConfig->start) {
                            // Удаление обозначения начала комментария
                            $text->shift($commentStartLength - strlen($token));

                            // Формирование текста комментария
                            $token = $candidateForCommentStart;

                            $commentIsCloseFlag = false;
                            $commentEndLength = strlen($commentConfig->end);
                            while (!$text->isEmpty()) {
                                $candidateForCommentEnd = implode(
                                    '', $text->take($commentEndLength)->toArray()
                                );

                                if ($candidateForCommentEnd === $commentConfig->end) {
                                    // Удаление обозначения конца комментария и формирования полного текста комментария
                                    $token .= implode('', $text->shift($commentEndLength)?->toArray());

                                    $this->addToken($token, TokenTypeEnum::COMMENT);

                                    // Переход к распознованию следующей лексемы
                                    $type = null;

                                    $commentIsCloseFlag = true;
                                    break;
                                }

                                // Удаление первого символа и формирование текста комментария
                                $token .= $text->shift();
                            }

                            // Проерка на закрытие комментария
                            if (!$commentIsCloseFlag) {
                                Log::error('Комментарий не был закрыт');

                                return null;
                            }
                        } elseif (in_array($token, $this->config->operators, true)) {
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
            'Идентификаторы ('  . $this->getNumberOfTableByTokenTypeName(TokenTypeEnum::IDENTIFIER) . ')',
            $this->identifiers
        );
        $this->printTable(
            'Литералы ('  . $this->getNumberOfTableByTokenTypeName(TokenTypeEnum::LITERAL) . ')',
            $this->literals
        );
        $this->printTable(
            'Разделители (' . $this->getNumberOfTableByTokenTypeName(TokenTypeEnum::SEPARATOR) . ')',
            $this->separators
        );

        $this->printStandardTable();
    }

    /**
     * @param Collection<int, string> $collection
     */
    private function printTable(string $header, Collection $collection): void
    {
        echo $header . "\n";

        foreach ($collection as $index => $item) {
            echo "{$index}:\t{$item}\n";
        }
    }

    private function printStandardTable(): void
    {
        echo "Таблица стандартных символов\n";
        echo "Позиция: (таблица, номер)\n\n";

        foreach ($this->standardTable as $item) {
            echo "{$item->table},{$item->number}\t"
                . $this->getTableCollectionByNumber($item->table)[$item->number]. "\n";
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function prepareInputFileData(string $inputFileData): Collection
    {
        return collect(str_split($inputFileData));
    }

    private function addToken(string $token, TokenTypeEnum $type): void
    {
        if (!$this->config->displayComments && $type === TokenTypeEnum::COMMENT) {
            return;
        }

        switch ($this->realtimeOutputMode) {
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

        switch ($type) {
            case TokenTypeEnum::SERVICE:
                $this->serviceWords[] = $token;
                $this->standardTable[] =new TokenAddress(
                    $this->getNumberOfTableByTokenTypeName($type),
                    $this->serviceWords->count() - 1
                );

                break;
            case TokenTypeEnum::IDENTIFIER:
                $this->identifiers[] = $token;
                $this->standardTable[] =new TokenAddress(
                    $this->getNumberOfTableByTokenTypeName($type),
                    $this->identifiers->count() - 1
                );

                break;
            case TokenTypeEnum::LITERAL:
                $this->literals[] = $token;
                $this->standardTable[] =new TokenAddress(
                    $this->getNumberOfTableByTokenTypeName($type),
                    $this->literals->count() - 1
                );

                break;
            case TokenTypeEnum::SEPARATOR:
                $this->separators[] = $token;
                $this->standardTable[] =new TokenAddress(
                    $this->getNumberOfTableByTokenTypeName($type),
                    $this->separators->count() - 1
                );

                break;
            default:
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function getTableCollectionByNumber(int $number): Collection
    {
        return $this->{self::numberToTableMap[$number]};
    }

    private function getNumberOfTableByTokenTypeName(TokenTypeEnum $number): int
    {
        return self::tokenTypeNameToTableNumberMap[$number->name];
    }
}
