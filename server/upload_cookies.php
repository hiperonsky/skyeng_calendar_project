<?php
// upload_cookies.php — сохраняет POST['cookies'] в my_cookies.txt и POST['timezone'] в my_timezone.txt

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cookies']) && isset($_POST['timezone'])) {
    $cookies  = $_POST['cookies'];
    $timezone = $_POST['timezone'];

    $dir = __DIR__;
    $cookiesFile  = "$dir/my_cookies.txt";
    $tzFile       = "$dir/my_timezone.txt";

    if (file_put_contents($cookiesFile, $cookies) === false ||
        file_put_contents($tzFile, $timezone) === false) {
        http_response_code(500);
        echo 'Ошибка записи файла';
        exit;
    }

    echo 'OK';
} else {
    http_response_code(400);
    echo 'Неверный запрос';
}
