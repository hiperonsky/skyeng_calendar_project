<?php
// Файл: server/fetch.php

// Для отладки: показываем все ошибки сразу в ответе
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORS-заголовки
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обрабатываем preflight
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'OPTIONS') {
    exit;
}

// Диапазон дат
$fromDate = "2025-07-01T00:00:00+05:00";
$tillDate = "2025-07-31T23:59:59+05:00";

// Читаем cookies
$cookiesFile = __DIR__ . '/my_cookies.txt';
if (!file_exists($cookiesFile)) {
    http_response_code(400);
    echo "Файл с cookies не найден: $cookiesFile";
    exit;
}
$cookies = trim(file_get_contents($cookiesFile));
if ($cookies === '') {
    http_response_code(400);
    echo "Файл с cookies пуст";
    exit;
}

// CURL-запрос к API Skyeng
$postData = json_encode(['from'=>$fromDate, 'till'=>$tillDate]);
$ch = curl_init('https://api-teachers.skyeng.ru/v2/schedule/events');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Cookie: '.$cookies
    ],
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false || $curlErr) {
    http_response_code(500);
    echo "Ошибка cURL: $curlErr";
    exit;
}
if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo "HTTP ошибка: $httpCode\n$response";
    exit;
}

// Декодируем ответ
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo "Ошибка декодирования JSON: ".json_last_error_msg();
    exit;
}

// Извлекаем teacher_id из первого события
if (empty($data['data']['events'][0]['payload']['teacher']['person']['id'])) {
    http_response_code(500);
    echo "Не удалось определить teacher_id";
    exit;
}
$teacherId = $data['data']['events'][0]['payload']['teacher']['person']['id'];

// Сохраняем полный ответ в events.json
$eventsFile = __DIR__ . '/events.json';
if (file_put_contents($eventsFile, $response) === false) {
    http_response_code(500);
    echo "Не удалось записать файл: $eventsFile";
    exit;
}

// Возвращаем JSON для расширения
header('Content-Type: application/json');
echo json_encode([
    'teacher_id' => $teacherId,
    'events'     => $data['data']['events']
]);
