<?php
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/upload_errors.log');
error_reporting(E_ALL);

// Разрешаем CORS-запросы из расширения
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обрабатываем preflight-запрос
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Основная логика: ожидаем application/x-www-form-urlencoded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cookies']) && isset($_POST['timezone'])) {
    $cookies  = $_POST['cookies'];
    $timezone = $_POST['timezone'];

    $dir = __DIR__;
    $cookiesFile = "$dir/my_cookies.txt";
    $tzFile      = "$dir/my_timezone.txt";

    // Пишем данные в файлы
    if (file_put_contents($cookiesFile, $cookies) === false ||
        file_put_contents($tzFile, $timezone) === false) {
        http_response_code(500);
        echo 'Ошибка записи файла';
        exit;
    }

    // Успешный ответ
    http_response_code(200);
    echo 'OK';
} else {
    http_response_code(400);
    echo 'Неверный запрос';
}
