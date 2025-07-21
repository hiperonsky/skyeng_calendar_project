<?php
// generate.php — генерация {teacher_id}.ics с учетом часового пояса пользователя

date_default_timezone_set('UTC');

$dir      = __DIR__;
$jsonFile = "$dir/events.json";
$tzFile   = "$dir/my_timezone.txt";

if (!file_exists($jsonFile) || !file_exists($tzFile)) {
    http_response_code(404);
    echo "missing";
    exit;
}

$data = json_decode(file_get_contents($jsonFile), true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data']['events'][0]['payload']['teacher']['person']['id'])) {
    http_response_code(500);
    echo "invalid";
    exit;
}

$teacherId = $data['data']['events'][0]['payload']['teacher']['person']['id'];
$icsFile   = "$dir/$teacherId.ics";

$tzName = trim(file_get_contents($tzFile));
$targetTz = new DateTimeZone($tzName);
$dtStamp = gmdate('Ymd\THis\Z');

$vtz = [
    "BEGIN:VTIMEZONE",
    "TZID:$tzName",
    "X-LIC-LOCATION:$tzName",
    "BEGIN:STANDARD",
    "TZOFFSETFROM:+0500",
    "TZOFFSETTO:+0500",
    "TZNAME:YEKT",
    "DTSTART:19700101T000000",
    "END:STANDARD",
    "END:VTIMEZONE"
];

$lines = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//bash//skyeng-to-ics",
    "CALSCALE:GREGORIAN",
];
$lines = array_merge($lines, $vtz);

foreach ($data['data']['events'] as $evt) {
    if (empty($evt['eventId']) || empty($evt['startAt']) || !isset($evt['durationSeconds'])) continue;
    $start = new DateTime($evt['startAt']);
    $end   = clone $start;
    $end->modify('+' . intval($evt['durationSeconds']) . ' seconds');
    $start->setTimezone($targetTz);
    $end->setTimezone($targetTz);

    $summary = $evt['payload']['student']['person']['name']['fullName'] ?? 'Занятие';
    $sid     = $evt['payload']['student']['person']['id'] ?? null;
    if ($sid) $summary .= " $sid";
    $teacher = $evt['payload']['teacher']['person']['name']['fullName'] ?? '';
    $type    = $evt['type'] ?? '';
    $desc    = "Преподаватель: $teacher\\nТип: $type";

    $lines[] = "BEGIN:VEVENT";
    $lines[] = "UID:" . $evt['eventId'];
    $lines[] = "DTSTAMP:$dtStamp";
    $lines[] = "SUMMARY:" . addcslashes($summary, "\n,;");
    $lines[] = "DTSTART;TZID=$tzName:" . $start->format('Ymd\THis');
    $lines[] = "DTEND;TZID=$tzName:"   . $end->format('Ymd\THis');
    $lines[] = "DESCRIPTION:" . addcslashes($desc, "\n,;");
    $lines[] = "END:VEVENT";
}

$lines[] = "END:VCALENDAR";

$content = implode("\r\n", $lines) . "\r\n";
if (file_put_contents($icsFile, $content) === false) {
    http_response_code(500);
    echo "fail";
    exit;
}

// Выдаём только имя ICS-файла
echo "$teacherId.ics";
