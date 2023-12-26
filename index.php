<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Enums\RealtimeOutputModeEnum;
use App\Services\Compiler;
use App\Services\Log;
use App\Services\SetsUtils;

// Получение базовой конфигурации
$config = json_decode(file_get_contents('config.json'), false, 512, JSON_THROW_ON_ERROR);

// Генерация сетов
$sets = SetsUtils::generateSets();

// Создание экземпляра класса "компилятора"
$compiler = new Compiler($config, $sets, RealtimeOutputModeEnum::TOKENS_WITH_HEADER);

foreach ($config->inputFileNames as $inputFileName) {
    echo "Сканирование файла {$inputFileName}\n";
    // Чтение информации из входного файла
    $inputFileData = file_get_contents('data/' . $inputFileName);

    // Сканирование файла
    $result = $compiler->scan($inputFileData);

    // Вывод результата
    if ($result === true) {
        $compiler->printResult();
    } else {
        Log::error('Не удалось выполнить сканирование');
    }
}
