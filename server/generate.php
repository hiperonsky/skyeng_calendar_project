<?php
// Файл: server/generate.php — только сохраняет ics/{teacher_id}.ics без вывода в браузер

// Включаем ошибки для отладки
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Получаем teacher_id из URL
$path = $_SERVER['PATH_INFO'] ?? '';
if (!preg_match('#/(\d+)\.ics#', $path, $match)) {
    http_response_code(400);
    exit("Invalid request — expected /{teacher_id}.ics");
}

$teacherId = $match[1];

// Пути к файлам
$baseDir = __DIR__;
$jsonFile = "$baseDir/json/{$teacherId}_events.json";
$tzFile   = "$baseDir/timezone/{$teacherId}_timezone.txt";
$icsDir   = "$baseDir/ics";
$icsFile  = "$icsDir/{$teacherId}.ics";

// Проверка наличия файлов json и timezone
if (!file_exists($jsonFile)) {
    error_log("Не найден файл JSON: $jsonFile");
    http_response_code(404);
    exit;
}

if (!file_exists($tzFile)) {
    error_log("Не найден файл таймзоны: $tzFile");
    http_response_code(404);
    exit;
}

// Создание папки ics, если нужно
if (!is_dir($icsDir)) {
    mkdir($icsDir, 0755, true);
}

// Чтение JSON расписания
$data = json_decode(file_get_contents($jsonFile), true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data']['events'])) {
    http_response_code(500);
    exit("Ошибка JSON: " . json_last_error_msg());
}
$events = $data['data']['events'];

// Чтение timezone
$tzName = trim(file_get_contents($tzFile));
try {
    $targetTz = new DateTimeZone($tzName);
} catch (Throwable $e) {
    http_response_code(500);
    exit("Неверный timezone: $tzName");
}

$dtStamp = gmdate('Ymd\THis\Z');

// Построение .ics-файла
$ics = [];
$ics[] = "BEGIN:VCALENDAR";
$ics[] = "VERSION:2.0";
$ics[] = "PRODID:-//skyeng//export";
$ics[] = "CALSCALE:GREGORIAN";

// Временная зона
$offset = (new DateTime('now', $targetTz))->format('O');  // +0500
$ics[] = "BEGIN:VTIMEZONE";
$ics[] = "TZID:$tzName";
$ics[] = "X-LIC-LOCATION:$tzName";
$ics[] = "BEGIN:STANDARD";
$ics[] = "DTSTART:19700101T000000";
$ics[] = "TZOFFSETFROM:$offset";
$ics[] = "TZOFFSETTO:$offset";
$ics[] = "TZNAME:$tzName";
$ics[] = "END:STANDARD";
$ics[] = "END:VTIMEZONE";

// События
foreach ($events as $evt) {
    if (empty($evt['eventId']) || empty($evt['startAt']) || !isset($evt['durationSeconds'])) {
        continue;
    }

    $start = new DateTime($evt['startAt']);
    $end = clone $start;
    $end->modify('+' . intval($evt['durationSeconds']) . ' seconds');

    $start->setTimezone($targetTz);
    $end->setTimezone($targetTz);

    $uid = $evt['eventId'];
    $summary = $evt['payload']['student']['person']['name']['fullName'] ?? 'Занятие';
    if (isset($evt['payload']['student']['person']['id'])) {
        $summary .= ' ' . $evt['payload']['student']['person']['id'];
    }

    $teacher = $evt['payload']['teacher']['person']['name']['fullName'] ?? '';
    $type = $evt['type'] ?? '';
    $desc = "Преподаватель: $teacher\\nТип: $type";

    $ics[] = "BEGIN:VEVENT";
    $ics[] = "UID:$uid";
    $ics[] = "DTSTAMP:$dtStamp";
    $ics[] = "SUMMARY:" . addcslashes($summary, "\n,;");
    $ics[] = "DTSTART;TZID=$tzName:" . $start->format('Ymd\THis');
    $ics[] = "DTEND;TZID=$tzName:" . $end->format('Ymd\THis');
    $ics[] = "DESCRIPTION:" . addcslashes($desc, "\n,;");
    $ics[] = "END:VEVENT";
}

$ics[] = "END:VCALENDAR";

// Сохранение в файл
$result = file_put_contents($icsFile, implode("\r\n", $ics) . "\r\n");

if ($result === false) {
    http_response_code(500);
    exit("Не удалось сохранить файл: $icsFile");
}

// ✅ Успех без отдачи ICS пользователю
http_response_code(200);
echo "ICS файл сохранен: $icsFile";
