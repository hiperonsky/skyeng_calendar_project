<?php
// Файл: server/generate.php — генерация и сохранение ics/{teacher_id}.ics

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Извлекаем teacher_id из PATH_INFO
$path = $_SERVER['PATH_INFO'] ?? '';
if (!preg_match('#/(\d+)\.ics#', $path, $m)) {
    http_response_code(404);
    echo "missing";
    exit;
}
$teacherId = $m[1];

// Пути к файлам
$dir = __DIR__;
$jsonFile = "$dir/json/{$teacherId}_events.json";
$tzFile   = "$dir/timezone/{$teacherId}_timezone.txt";
$icsDir   = "$dir/ics";
$icsFile  = "$icsDir/{$teacherId}.ics";

// Проверка файлов и директорий
if (!file_exists($jsonFile) || !file_exists($tzFile)) {
    http_response_code(404);
    echo "missing files";
    exit;
}
if (!is_dir($icsDir)) {
    mkdir($icsDir, 0755, true);
}

// Чтение и парсинг событий
$data = json_decode(file_get_contents($jsonFile), true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data']['events'])) {
    http_response_code(500);
    echo "invalid events data";
    exit;
}
$events = $data['data']['events'];

// Чтение timezone
$tzName = trim(file_get_contents($tzFile));
try {
    $targetTz = new DateTimeZone($tzName);
} catch (Throwable $e) {
    http_response_code(500);
    echo "invalid timezone";
    exit;
}

$dtStamp = gmdate('Ymd\THis\Z');

// Сбор .ics-контента
$icsLines = [];
$icsLines[] = "BEGIN:VCALENDAR";
$icsLines[] = "VERSION:2.0";
$icsLines[] = "PRODID:-//skyeng//calendar-export";
$icsLines[] = "CALSCALE:GREGORIAN";

$offset = (new DateTime('now', $targetTz))->format('O');
$icsLines[] = "BEGIN:VTIMEZONE";
$icsLines[] = "TZID:{$tzName}";
$icsLines[] = "X-LIC-LOCATION:{$tzName}";
$icsLines[] = "BEGIN:STANDARD";
$icsLines[] = "DTSTART:19700101T000000";
$icsLines[] = "TZOFFSETFROM:{$offset}";
$icsLines[] = "TZOFFSETTO:{$offset}";
$icsLines[] = "TZNAME:{$tzName}";
$icsLines[] = "END:STANDARD";
$icsLines[] = "END:VTIMEZONE";

// События
foreach ($events as $event) {
    if (empty($event['eventId']) || empty($event['startAt']) || !isset($event['durationSeconds'])) {
        continue;
    }

    $start = new DateTime($event['startAt']);
    $end = clone $start;
    $end->modify('+' . intval($event['durationSeconds']) . ' seconds');
    $start->setTimezone($targetTz);
    $end->setTimezone($targetTz);

    $uid = $event['eventId'];
    $summary = $event['payload']['student']['person']['name']['fullName'] ?? 'Занятие';
    if (isset($event['payload']['student']['person']['id'])) {
        $summary .= ' ' . $event['payload']['student']['person']['id'];
    }

    $teacher = $event['payload']['teacher']['person']['name']['fullName'] ?? '';
    $type = $event['type'] ?? '';
    $desc = "Преподаватель: $teacher\\nТип: $type";

    $icsLines[] = "BEGIN:VEVENT";
    $icsLines[] = "UID:$uid";
    $icsLines[] = "DTSTAMP:$dtStamp";
    $icsLines[] = "SUMMARY:" . addcslashes($summary, "\n,;");
    $icsLines[] = "DTSTART;TZID=$tzName:" . $start->format('Ymd\THis');
    $icsLines[] = "DTEND;TZID=$tzName:" . $end->format('Ymd\THis');
    $icsLines[] = "DESCRIPTION:" . addcslashes($desc, "\n,;");
    $icsLines[] = "END:VEVENT";
}

$icsLines[] = "END:VCALENDAR";

// Конечный текст
$icsContent = implode("\r\n", $icsLines) . "\r\n";

// Сохраняем файл в папку ics
file_put_contents($icsFile, $icsContent);

// Отдаём пользователю для загрузки
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $teacherId . '.ics"');
echo $icsContent;
