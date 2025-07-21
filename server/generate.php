<?php
// Файл: server/generate.php — генерация {teacher_id}.ics с учётом часового пояса пользователя

// Разрешаем CORS-запросы из расширения
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Получаем teacher_id из PATH_INFO
$path = $_SERVER['PATH_INFO'] ?? '';
if (!preg_match('#/(\d+)\.ics#', $path, $m)) {
    http_response_code(404);
    echo "missing";
    exit;
}
$teacherId = $m[1];

$dir      = __DIR__;
$jsonFile = "$dir/events.json";
$tzFile   = "$dir/my_timezone.txt";

// Проверяем наличие файлов
if (!file_exists($jsonFile) || !file_exists($tzFile)) {
    http_response_code(404);
    echo "missing";
    exit;
}

// Читаем и декодируем события
$data = json_decode(file_get_contents($jsonFile), true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data']['events'])) {
    http_response_code(500);
    echo "invalid";
    exit;
}
$events = $data['data']['events'];

// Читаем целевую временную зону
$tzName = trim(file_get_contents($tzFile));
try {
    $targetTz = new DateTimeZone($tzName);
} catch (Exception $e) {
    http_response_code(500);
    echo "invalid timezone";
    exit;
}

// Штамп времени генерации
$dtStamp = gmdate('Ymd\THis\Z');

// Заголовки для выдачи ICS
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$teacherId.'.ics"');

// Начало календаря
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//bash//skyeng-to-ics\r\n";
echo "CALSCALE:GREGORIAN\r\n";

// Генерация VTIMEZONE (упрощённая, без DST)
echo "BEGIN:VTIMEZONE\r\n";
echo "TZID:{$tzName}\r\n";
echo "X-LIC-LOCATION:{$tzName}\r\n";
echo "BEGIN:STANDARD\r\n";
echo "DTSTART:19700101T000000\r\n";
$offset = (new DateTime('now', $targetTz))->format('O'); // +HHMM
echo "TZOFFSETFROM:{$offset}\r\n";
echo "TZOFFSETTO:{$offset}\r\n";
echo "TZNAME:{$tzName}\r\n";
echo "END:STANDARD\r\n";
echo "END:VTIMEZONE\r\n";

// События
foreach ($events as $evt) {
    if (empty($evt['eventId']) || empty($evt['startAt']) || !isset($evt['durationSeconds'])) {
        continue;
    }

    $start = new DateTime($evt['startAt']);      // исходное время в UTC или с часовым поясом API
    $end   = clone $start;
    $end->modify('+' . intval($evt['durationSeconds']) . ' seconds');

    // Переводим в целевую зону
    $start->setTimezone($targetTz);
    $end->setTimezone($targetTz);

    // Формируем поля
    $uid     = $evt['eventId'];
    $summary = $evt['payload']['student']['person']['name']['fullName'] ?? 'Занятие';
    $sid     = $evt['payload']['student']['person']['id'] ?? null;
    if ($sid) {
        $summary .= " {$sid}";
    }
    $teacher = $evt['payload']['teacher']['person']['name']['fullName'] ?? '';
    $type    = $evt['type'] ?? '';
    $desc    = "Преподаватель: {$teacher}\\nТип: {$type}";

    echo "BEGIN:VEVENT\r\n";
    echo "UID:{$uid}\r\n";
    echo "DTSTAMP:{$dtStamp}\r\n";
    echo "SUMMARY:" . addcslashes($summary, "\n,;") . "\r\n";
    echo "DTSTART;TZID={$tzName}:" . $start->format('Ymd\THis') . "\r\n";
    echo "DTEND;TZID={$tzName}:"   . $end->format('Ymd\THis') . "\r\n";
    echo "DESCRIPTION:" . addcslashes($desc, "\n,;") . "\r\n";
    echo "END:VEVENT\r\n";
}

// Завершаем календарь
echo "END:VCALENDAR\r\n";
