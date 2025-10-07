<?php
// Файл: server/fetch.php

// Включаем вывод ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORS-заголовки
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'OPTIONS') {
    exit;
}

// Диапазон дат (можно добавить динамику через параметры запроса)
$fromDate = "2025-10-01T00:00:00+05:00";
$tillDate = "2025-10-31T23:59:59+05:00";

// Предполагаем, что $teacherId уже получен, и файлы существуют
/*
// Читаем таймзону
$timezone = trim(file_get_contents(__DIR__ . "/timezone/{$teacherId}_timezone.txt"));
$tz = new DateTimeZone($timezone);

// 1. Вычисляем первый день текущего месяца 00:00:00
$dtStart = new DateTime('now', $tz);
$dtStart->modify('first day of this month')->setTime(0, 0, 0);
$fromDate = $dtStart->format('Y-m-d\T00:00:00P');

// 2. Вычисляем последний день текущего месяца 23:59:59
$dtEnd = new DateTime('now', $tz);
$dtEnd->modify('last day of this month')->setTime(23, 59, 59);
$tillDate = $dtEnd->format('Y-m-d\T23:59:59P');
*/

// Для отладки, можно залогировать, что уходит в API:
error_log("DEBUG postData from={$fromDate} till={$tillDate}");



// teacher_id должен приходить через POST (или GET) — пример для POST:
$teacherId = $_POST['teacher_id'] ?? null;
if ($teacherId === null) {
    http_response_code(400);
    echo json_encode(['error' => 'teacher_id required']);
    exit;
}

// Пути к файлам
$baseDir = __DIR__;
$cookiesFile = "$baseDir/cookies/{$teacherId}_cookies.txt";
$timezoneFile = "$baseDir/timezone/{$teacherId}_timezone.txt";
$jsonDir = "$baseDir/json";

if (!file_exists($cookiesFile) || !file_exists($timezoneFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cookies or timezone files not found']);
    exit;
}

$cookies = trim(file_get_contents($cookiesFile));
$timezone = trim(file_get_contents($timezoneFile));

// CURL-запрос к Skyeng API
$postData = json_encode(['from' => $fromDate, 'till' => $tillDate]);
$ch = curl_init('https://api-teachers.skyeng.ru/v2/schedule/events');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Cookie: ' . $cookies
    ],
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
]);
$response = curl_exec($ch);
// После $response = curl_exec($ch);
error_log("DEBUG RESPONSE HTTP_CODE={$httpCode} body={$response}");
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false || $curlErr) {
    http_response_code(500);
    echo json_encode(['error' => "Curl error: $curlErr"]);
    exit;
}
if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => "HTTP error: $httpCode", 'response' => $response]);
    exit;
}

// Декодируем ответ
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'JSON decode error: ' . json_last_error_msg()]);
    exit;
}

// Проверяем наличие teacher_id в событиях
if (empty($data['data']['events'][0]['payload']['teacher']['person']['id'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Teacher ID not found in API response']);
    exit;
}

// Сохраняем результат в json/{teacher_id}_events.json
if (!is_dir($jsonDir)) {
    mkdir($jsonDir, 0755, true);
}
$eventsFile = "$jsonDir/{$teacherId}_events.json";
if (file_put_contents($eventsFile, $response) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write events file']);
    exit;
}

// Возвращаем успешный ответ с teacher_id и событиями
header('Content-Type: application/json');
echo json_encode([
    'teacher_id' => $teacherId,
    'events'     => $data['data']['events']
]);
?>
