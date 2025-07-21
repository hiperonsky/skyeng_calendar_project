<?php
// Файл: server/fetch.php

// Включаем вывод ошибок для отладки (при необходимости отключить в продакшене)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('error_log', __DIR__ . '/fetch_errors.log');
error_reporting(E_ALL);

// Разрешаем CORS-запросы из расширения
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Безопасно читаем метод запроса
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'OPTIONS') {
    exit;
}

// 1. Конфигурация диапазона дат
$fromDate = "2025-07-01T00:00:00+05:00";
$tillDate = "2025-07-31T23:59:59+05:00";

// 2. Читаем cookies из файла
$cookiesFile = __DIR__ . '/my_cookies.txt';
if (!file_exists($cookiesFile)) {
    http_response_code(400);
    echo "Файл с cookies не найден: " . $cookiesFile;
    exit;
}
$cookies = trim(file_get_contents($cookiesFile));
if (empty($cookies)) {
    http_response_code(400);
    echo "Файл с cookies пуст";
    exit;
}

// 3. Подготавливаем тело POST-запроса к API Skyeng
$postData = json_encode([
    "from" => $fromDate,
    "till" => $tillDate
]);

// 4. Выполняем cURL-запрос
$ch = curl_init('https://api-teachers.skyeng.ru/v2/schedule/events');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json',
        'Cookie: ' . $cookies
    ],
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 5. Обработка ошибок cURL
if ($response === false || !empty($curlError)) {
    http_response_code(500);
    echo "Ошибка cURL: " . $curlError;
    exit;
}

// 6. Проверка HTTP-кода ответа
if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo "HTTP ошибка: " . $httpCode . "\nОтвет: " . $response;
    exit;
}

// 7. Декодируем JSON и проверяем на ошибки авторизации
$jsonData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo "Ошибка декодирования JSON: " . json_last_error_msg();
    exit;
}
if (isset($jsonData['code']) && $jsonData['code'] === 'internal_error') {
    http_response_code(401);
    echo "Ошибка авторизации - cookies устарели, обновите их вручную";
    exit;
}

// 8. Сохраняем сырые события в файл events.json
$eventsJsonFile = __DIR__ . '/events.json';
if (file_put_contents($eventsJsonFile, $response) === false) {
    http_response_code(500);
    echo "Не удалось записать файл: " . $eventsJsonFile;
    exit;
}

// 9. Формируем вывод JSON с teacher_id и списком событий
$teacherId = $jsonData['data']['teacherId'] ?? null;
$events    = $jsonData['data']['events']   ?? [];

if (!$teacherId) {
    http_response_code(500);
    echo "Не удалось определить teacher_id в ответе API";
    exit;
}

// 10. Возвращаем JSON для расширения
header('Content-Type: application/json');
echo json_encode([
    'teacher_id' => $teacherId,
    'events'     => $events
]);
