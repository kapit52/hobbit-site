<?php
// Логгер приложения: перехват ошибок PHP + (опционально) лог запросов.
require_once __DIR__ . '/includes/logger.php';
app_logger_register();

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD');
if ($pass === false) {
    $pass = '';
}
$db   = getenv('DB_NAME') ?: 'shire_corner';
$port = (int)(getenv('DB_PORT') ?: 3306);

// Подключение с несколькими попытками — на случай, когда контейнер БД
// ещё поднимается и отвергает соединение (cold start в Docker).
$conn = null;
$attempts = 10;
for ($i = 1; $i <= $attempts; $i++) {
    try {
        $conn = new mysqli($host, $user, $pass, $db, $port);
        break;
    } catch (mysqli_sql_exception $e) {
        if ($i === $attempts) {
            log_critical('db', 'Не удалось подключиться к БД', ['error' => $e->getMessage(), 'host' => $host, 'attempts' => $attempts]);
            http_response_code(503);
            die('База данных недоступна. Попробуйте обновить страницу через несколько секунд.');
        }
        sleep(1);
    }
}
$conn->set_charset('utf8mb4');
