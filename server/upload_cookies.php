<?php
// upload_cookies.php — извлекает teacher_id из token_global и сохраняет cookies и timezone

ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/upload_errors.log');
error_reporting(E_ALL);

// Разрешаем CORS-запросы из расширения
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight запрос
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Проверка данных
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cookies']) || !isset($_POST['timezone'])) {
    http_response_code(400);
    echo 'Неверный запрос';
    exit;
}

$cookies  = $_POST['cookies'];
$timezone = $_POST['timezone'];

// Извлечение teacher_id из token_global
if (!preg_match('/token_global=([^;]+)/', $cookies, $matches)) {
    http_response_code(400);
    echo 'token_global не найден в cookies';
    exit;
}

$jwt = $matches[1];
$parts = explode('.', $jwt);
if (count($parts) < 2) {
    http_response_code(400);
    echo 'Недопустимый JWT токен';
    exit;
}

// Раскодировка payload
$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
if (!isset($payload['userId'])) {
    http_response_code(400);
    echo 'userId не найден в token_global';
    exit;
}

$teacherId = $payload['userId'];

// Подготовка директорий
$baseDir     = __DIR__;
$cookiesDir  = "$baseDir/cookies";
$timezoneDir = "$baseDir/timezone";

// Создание директорий при необходимости
if (!is_dir($cookiesDir)) mkdir($cookiesDir, 0755, true);
if (!is_dir($timezoneDir)) mkdir($timezoneDir, 0755, true);

// Пути к файлам
$cookiesFile = "$cookiesDir/{$teacherId}_cookies.txt";
$tzFile      = "$timezoneDir/{$teacherId}_timezone.txt";

// Сохраняем файлы
if (
    file_put_contents($cookiesFile, $cookies) === false ||
    file_put_contents($tzFile, $timezone) === false
) {
    http_response_code(500);
    echo 'Ошибка записи файла';
    exit;
}

// Успешный ответ
http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'teacher_id' => $teacherId
]);
