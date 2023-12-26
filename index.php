<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Enums\RealtimeOutputModeEnum;
use App\Services\Compiler;
use App\Services\Log;
use App\Services\SetsUtils;

// Получение базовой конфигурации
$config = json_decode(file_get_contents('config.json'), false, 512, JSON_THROW_ON_ERROR);
$config->realtimeOutputMode = RealtimeOutputModeEnum::tryFrom($config->realtimeOutputMode) ?? 0;

// Генерация сетов
$sets = SetsUtils::generateSets();

// Создание экземпляра класса "компилятора"
$compiler = new Compiler($config, $sets);

foreach ($config->inputFileNames as $inputFileName) {
    Log::info("Сканирование файла {$inputFileName}\n");

    // Чтение информации из входного файла
    $inputFileData = file_get_contents($config->pathToDataDir . $inputFileName);

    // Сканирование файла
    $result = $compiler->scan($inputFileData);

    // Вывод результата
    if ($result === true) {
        if ($config->printResult) {
            $compiler->printResult();
        }

        Log::info("Сканирование файла {$inputFileName} успешно завершено\n");
    } else {
        Log::error('Не удалось выполнить сканирование');
    }
}
