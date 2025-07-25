<?php
// Файл: update_all_ics.php
// Запускается из командной строки: php update_all_ics.php

// Лог-файл
$logFile = __DIR__ . '/logs/update_all_ics.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

// Каталоги проекта
$baseDir   = __DIR__;                     // корень проекта
$cookiesDir = "$baseDir/cookies";
$tzDir      = "$baseDir/timezone";
$icsDir     = "$baseDir/ics";

// Подключаем generate.php как функцию
require_once __DIR__ . '/server/generate.php'; 
// Предполагается, что в generate.php вынесена функция generateIcs($teacherId)
// Если нет – можно вызвать скрипт через CLI.

// Функция логирования
function logMessage($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}

// Найти все файлы cookies/*.txt
foreach (glob("$cookiesDir/*_cookies.txt") as $cookieFile) {
    $base = basename($cookieFile, '_cookies.txt');
    $tzFile = "$tzDir/{$base}_timezone.txt";
    $icsFile = "$icsDir/{$base}.ics";

    if (!file_exists($tzFile)) {
        logMessage("Пропущен $base: отсутствует timezone-файл");
        continue;
    }

    // Лог запуска обработки
    logMessage("Начало обработки teacherId=$base");

    // Вызываем логику генерации (вариант через CLI-скрипт)
    // Допустим, generate.php принимает параметр через PATH_INFO:
    $cmd = escapeshellcmd(PHP_BINARY)
         . ' ' . escapeshellarg(__DIR__ . '/server/generate.php')
         . ' ' . escapeshellarg($base);
    exec($cmd . ' 2>&1', $output, $ret);
    if ($ret !== 0) {
        logMessage("Ошибка генерации ICS для $base: выходной код $ret; вывод: " . implode(' | ', $output));
    } else {
        logMessage("Успешно сгенерирован ICS: $icsFile");
    }
}

logMessage("=== Завершена полная проверка и генерация ICS ===");
