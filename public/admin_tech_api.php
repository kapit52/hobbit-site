<?php
/**
 * JSON-бэкенд технической админки (admin_tech.php).
 *
 * GET-действия (только чтение):
 *   ?action=metrics       — метрики приложения (логи + БД + рантайм)
 *   ?action=status        — здоровье систем: фронт / бэк / БД
 *   ?action=sysinfo       — системная и PHP-информация
 *   ?action=logs&offset=… — инкрементальная выборка логов (реальное время)
 *   ?action=loggers_get   — конфигурация каналов-логеров
 *
 * POST-действия (требуют CSRF-токен):
 *   action=loggers_set    — включить/настроить канал
 *   action=log_clear      — очистить лог
 *   action=log_test       — записать тестовые строки в лог
 *
 * НЕ зависит от db.php (который делает die() при недоступной БД), поэтому
 * статус и логи остаются доступны даже когда база лежит.
 */
require_once __DIR__ . '/includes/admin_auth_lite.php';

// JSON-эндпоинт: ошибки не должны попадать в тело ответа (иначе ломается JSON).
// Логгер всё равно зафиксирует их в журнале (вкладка «Логи»).
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** Безопасное (не бросающее исключений) подключение к БД. */
function tech_db_connect(): array {
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF); // не бросать исключения — отчитываемся статусом
    }
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASSWORD'); if ($pass === false) $pass = '';
    $db   = getenv('DB_NAME') ?: 'shire_corner';
    $port = (int)(getenv('DB_PORT') ?: 3306);

    $t0 = microtime(true);
    $conn = @new mysqli($host, $user, $pass, $db, $port);
    if ($conn->connect_errno) {
        return [null, $conn->connect_error ?: 'connect failed', null];
    }
    $latency = (int)round((microtime(true) - $t0) * 1000);
    @$conn->set_charset('utf8mb4');
    return [$conn, null, $latency];
}

/** Скалярный запрос с защитой от ошибок. */
function tech_scalar(mysqli $c, string $sql) {
    $r = @$c->query($sql);
    if ($r instanceof mysqli_result) {
        $row = $r->fetch_row();
        $r->free();
        return $row[0] ?? null;
    }
    return null;
}

/** Чтение журнала: tail при initial-загрузке, инкремент по байтовому offset. */
function tech_read_logs(?int $offset, ?string $levelMin, ?string $channel, int $limit): array {
    $file = SHIRE_LOG_FILE;
    $size = is_file($file) ? (int)@filesize($file) : 0;
    $initial = ($offset === null);

    if ($initial) {
        $start = max(0, $size - 65536); // последние ~64 КБ при первом открытии
    } else {
        $start = $offset;
        if ($start > $size) $start = 0; // файл очищен или ротация — читаем сначала
    }

    $entries = [];
    if ($size > 0 && $start < $size) {
        $fh = @fopen($file, 'rb');
        if ($fh) {
            @fseek($fh, $start);
            $data = (string)@stream_get_contents($fh);
            @fclose($fh);
            $lines = explode("\n", $data);
            // Если читаем не с начала файла при initial-загрузке — первая строка может быть обрезана.
            if ($start > 0 && $initial) array_shift($lines);

            $levels = shire_log_levels();
            $minNum = ($levelMin && isset($levels[$levelMin])) ? $levels[$levelMin] : 0;
            foreach ($lines as $ln) {
                $ln = trim($ln);
                if ($ln === '') continue;
                $e = json_decode($ln, true);
                if (!is_array($e)) continue;
                if ($channel && ($e['channel'] ?? '') !== $channel) continue;
                if ($minNum && (($levels[$e['level'] ?? 'info'] ?? 20) < $minNum)) continue;
                $entries[] = $e;
            }
        }
    }
    if (count($entries) > $limit) $entries = array_slice($entries, -$limit);
    return ['entries' => $entries, 'offset' => $size, 'size' => $size];
}

/** Полный разбор лога для метрик (с ограничением читаемого объёма). */
function tech_log_stats(): array {
    $file = SHIRE_LOG_FILE;
    $size = is_file($file) ? (int)@filesize($file) : 0;
    $stats = [
        'total' => 0, 'by_level' => [], 'by_channel' => [],
        'errors_24h' => 0, 'warnings_24h' => 0, 'entries_1h' => 0,
        'requests' => 0, 'req_avg_ms' => null, 'req_max_ms' => null,
        'last_error' => null, 'file_size' => $size, 'series_24h' => array_fill(0, 24, 0),
    ];
    if ($size === 0) return $stats;

    $start = max(0, $size - 1048576); // последние ~1 МБ
    $fh = @fopen($file, 'rb');
    if (!$fh) return $stats;
    @fseek($fh, $start);
    $data = (string)@stream_get_contents($fh);
    @fclose($fh);
    $lines = explode("\n", $data);
    if ($start > 0) array_shift($lines);

    $now = time();
    $reqSum = 0; $reqN = 0; $reqMax = 0;
    foreach ($lines as $ln) {
        $ln = trim($ln);
        if ($ln === '') continue;
        $e = json_decode($ln, true);
        if (!is_array($e)) continue;
        $stats['total']++;
        $lvl = $e['level'] ?? 'info';
        $ch  = $e['channel'] ?? 'app';
        $ts  = (float)($e['ts'] ?? 0);
        $stats['by_level'][$lvl]   = ($stats['by_level'][$lvl] ?? 0) + 1;
        $stats['by_channel'][$ch]  = ($stats['by_channel'][$ch] ?? 0) + 1;

        $age = $now - $ts;
        if ($age <= 86400) {
            if ($lvl === 'error' || $lvl === 'critical') $stats['errors_24h']++;
            if ($lvl === 'warning') $stats['warnings_24h']++;
            $bucket = 23 - (int)floor($age / 3600);
            if ($bucket >= 0 && $bucket < 24) $stats['series_24h'][$bucket]++;
        }
        if ($age <= 3600) $stats['entries_1h']++;
        if (($lvl === 'error' || $lvl === 'critical')) $stats['last_error'] = $e;
        if ($ch === 'request') {
            $stats['requests']++;
            $d = $e['ctx']['dur_ms'] ?? null;
            if (is_numeric($d)) { $reqSum += $d; $reqN++; if ($d > $reqMax) $reqMax = (int)$d; }
        }
    }
    if ($reqN > 0) { $stats['req_avg_ms'] = (int)round($reqSum / $reqN); $stats['req_max_ms'] = $reqMax; }
    return $stats;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

// ---- POST: мутации (нужен CSRF) ----
if ($method === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF token invalid']); exit;
    }

    if ($action === 'loggers_set') {
        $channel = trim($_POST['channel'] ?? '');
        $cfg = shire_log_config();
        if ($channel === '' || !isset($cfg[$channel])) {
            echo json_encode(['ok' => false, 'error' => 'unknown channel']); exit;
        }
        if (isset($_POST['enabled']))   $cfg[$channel]['enabled'] = (int)$_POST['enabled'] === 1;
        if (isset($_POST['min_level'])) {
            $lvl = $_POST['min_level'];
            if (isset(shire_log_levels()[$lvl])) $cfg[$channel]['min_level'] = $lvl;
        }
        $ok = shire_log_save_config($cfg);
        log_info('tech', "Логер «{$channel}» обновлён", [
            'enabled' => $cfg[$channel]['enabled'], 'min_level' => $cfg[$channel]['min_level'],
            'by' => get_admin_username(),
        ]);
        echo json_encode(['ok' => $ok, 'config' => $cfg]); exit;
    }

    if ($action === 'log_clear') {
        $ok = @file_put_contents(SHIRE_LOG_FILE, '') !== false;
        @unlink(SHIRE_LOG_FILE . '.1');
        log_info('tech', 'Журнал очищен', ['by' => get_admin_username()]);
        echo json_encode(['ok' => $ok]); exit;
    }

    if ($action === 'log_test') {
        log_debug('app', 'Тестовая отладочная запись', ['rand' => mt_rand(1, 999)]);
        log_info('app', 'Тестовое информационное событие', ['user' => get_admin_username()]);
        log_warning('app', 'Тестовое предупреждение: высокая нагрузка', ['load' => 0.87]);
        log_error('app', 'Тестовая ошибка: не удалось отправить письмо', ['to' => 'guest@example.com']);
        echo json_encode(['ok' => true]); exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'unknown action']); exit;
}

// ---- GET: чтение ----
if ($action === 'logs') {
    $offset  = isset($_GET['offset']) && $_GET['offset'] !== '' ? (int)$_GET['offset'] : null;
    $level   = $_GET['level']   ?? '';
    $channel = $_GET['channel'] ?? '';
    $limit   = min(1000, max(50, (int)($_GET['limit'] ?? 400)));
    echo json_encode(tech_read_logs($offset, $level ?: null, $channel ?: null, $limit), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'loggers_get') {
    echo json_encode(['config' => shire_log_config(), 'levels' => array_keys(shire_log_levels())], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'metrics') {
    $logs = tech_log_stats();
    [$conn, $dbErr, $dbLatency] = tech_db_connect();

    $db = ['up' => $conn !== null, 'error' => $dbErr, 'latency_ms' => $dbLatency,
           'version' => null, 'size_mb' => null, 'tables' => []];
    $business = ['orders_today' => null, 'orders_pending' => null, 'bookings_pending' => null,
                 'users' => null, 'reviews' => null, 'menu_items' => null];

    if ($conn) {
        $db['version'] = $conn->server_info;
        $bytes = tech_scalar($conn, "SELECT SUM(DATA_LENGTH+INDEX_LENGTH) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()");
        $db['size_mb'] = $bytes !== null ? round(((float)$bytes) / 1048576, 2) : null;

        $res = @$conn->query("SELECT TABLE_NAME, TABLE_ROWS, (DATA_LENGTH+INDEX_LENGTH) AS sz
                              FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
                              ORDER BY sz DESC");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $db['tables'][] = [
                    'name' => $row['TABLE_NAME'],
                    'rows' => (int)$row['TABLE_ROWS'],
                    'size_kb' => round(((float)$row['sz']) / 1024, 1),
                ];
            }
            $res->free();
        }

        $business['orders_today']     = (int)(tech_scalar($conn, "SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE() AND status<>'cart'") ?? 0);
        $business['orders_pending']   = (int)(tech_scalar($conn, "SELECT COUNT(*) FROM orders WHERE status='pending'") ?? 0);
        $business['bookings_pending'] = (int)(tech_scalar($conn, "SELECT COUNT(*) FROM bookings WHERE status='pending'") ?? 0);
        $business['users']            = (int)(tech_scalar($conn, "SELECT COUNT(*) FROM users") ?? 0);
        $business['reviews']          = (int)(tech_scalar($conn, "SELECT COUNT(*) FROM reviews") ?? 0);
        $business['menu_items']       = (int)(tech_scalar($conn, "SELECT COUNT(*) FROM menu_items") ?? 0);
        $conn->close();
    }

    echo json_encode([
        'ts' => time(),
        'logs' => $logs,
        'db' => $db,
        'business' => $business,
        'runtime' => [
            'php' => PHP_VERSION,
            'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'log_size_kb' => round($logs['file_size'] / 1024, 1),
            'server_time' => date('Y-m-d H:i:s'),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'status') {
    // --- Фронт (веб-сервер отдаёт эту страницу => жив) ---
    $front = [
        'status' => 'ok',
        'latency_ms' => null,
        'checks' => [
            ['name' => 'Веб-сервер',  'ok' => true, 'detail' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name()],
            ['name' => 'Doc root',    'ok' => true, 'detail' => $_SERVER['DOCUMENT_ROOT'] ?? '—'],
            ['name' => 'Протокол',    'ok' => true, 'detail' => ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP') . ' ' . (!empty($_SERVER['HTTPS']) ? 'TLS' : '')],
        ],
    ];

    // --- Бэк (PHP, расширения, права на запись) ---
    $required = ['mysqli', 'json'];
    $optional = ['fileinfo', 'gd', 'mbstring', 'curl'];
    $backChecks = [];
    $backDown = false;
    foreach ($required as $ext) {
        $has = extension_loaded($ext);
        if (!$has) $backDown = true;
        $backChecks[] = ['name' => "ext: $ext", 'ok' => $has, 'detail' => $has ? 'загружено' : 'ОТСУТСТВУЕТ (обязательно)'];
    }
    $backDegraded = false;
    foreach ($optional as $ext) {
        $has = extension_loaded($ext);
        if (!$has) $backDegraded = true;
        $backChecks[] = ['name' => "ext: $ext", 'ok' => $has, 'detail' => $has ? 'загружено' : 'нет (опционально)'];
    }
    $logWritable = shire_log_dir_ready();
    if (!$logWritable) $backDegraded = true;
    $backChecks[] = ['name' => 'Запись логов', 'ok' => $logWritable, 'detail' => $logWritable ? SHIRE_LOG_DIR : 'нет прав на запись'];

    $uploadsDir = __DIR__ . '/uploads';
    $uploadsOk = is_dir($uploadsDir) && is_writable($uploadsDir);
    if (!$uploadsOk) $backDegraded = true;
    $backChecks[] = ['name' => 'Запись uploads', 'ok' => $uploadsOk, 'detail' => $uploadsOk ? 'доступно' : 'нет прав / нет папки'];

    $back = [
        'status' => $backDown ? 'down' : ($backDegraded ? 'degraded' : 'ok'),
        'latency_ms' => null,
        'checks' => $backChecks,
    ];

    // --- БД ---
    [$conn, $dbErr, $dbLatency] = tech_db_connect();
    $dbChecks = [];
    if ($conn) {
        $t0 = microtime(true);
        $ping = @$conn->query('SELECT 1');
        $qLatency = (int)round((microtime(true) - $t0) * 1000);
        $dbChecks[] = ['name' => 'Соединение', 'ok' => true, 'detail' => 'установлено за ' . $dbLatency . ' мс'];
        $dbChecks[] = ['name' => 'SELECT 1',   'ok' => (bool)$ping, 'detail' => $qLatency . ' мс'];
        $dbChecks[] = ['name' => 'Версия',     'ok' => true, 'detail' => $conn->server_info];
        // SHOW STATUS возвращает пару (Variable_name, Value) — берём значение (второй столбец).
        $threads = null; $uptime = null;
        if (($rs = @$conn->query("SHOW STATUS LIKE 'Threads_connected'")) instanceof mysqli_result) { $threads = ($rs->fetch_row()[1] ?? null); $rs->free(); }
        if (($rs = @$conn->query("SHOW STATUS LIKE 'Uptime'")) instanceof mysqli_result) { $uptime = ($rs->fetch_row()[1] ?? null); $rs->free(); }
        $dbChecks[] = ['name' => 'Подключений', 'ok' => true, 'detail' => $threads !== null ? "$threads активно" : '—'];
        if ($uptime !== null) {
            $dbChecks[] = ['name' => 'Аптайм', 'ok' => true, 'detail' => round($uptime / 3600, 1) . ' ч'];
        }
        $conn->close();
        $db = ['status' => 'ok', 'latency_ms' => $dbLatency, 'checks' => $dbChecks];
    } else {
        $dbChecks[] = ['name' => 'Соединение', 'ok' => false, 'detail' => $dbErr ?: 'недоступна'];
        log_warning('db', 'Проверка статуса: БД недоступна', ['error' => $dbErr]);
        $db = ['status' => 'down', 'latency_ms' => null, 'checks' => $dbChecks];
    }

    echo json_encode(['ts' => time(), 'front' => $front, 'back' => $back, 'db' => $db], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'sysinfo') {
    $root = dirname(__DIR__);
    $diskFree = @disk_free_space($root);
    $diskTotal = @disk_total_space($root);
    echo json_encode([
        'php' => [
            'version' => PHP_VERSION,
            'sapi' => php_sapi_name(),
            'os' => PHP_OS . ' ' . php_uname('r'),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? '—',
            'timezone' => date_default_timezone_get(),
            'time' => date('Y-m-d H:i:s'),
        ],
        'ini' => [
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'display_errors' => ini_get('display_errors') ? 'On' : 'Off',
            'error_reporting' => (string)error_reporting(),
        ],
        'extensions' => get_loaded_extensions(),
        'disk' => [
            'free_gb' => $diskFree ? round($diskFree / 1073741824, 1) : null,
            'total_gb' => $diskTotal ? round($diskTotal / 1073741824, 1) : null,
            'used_pct' => ($diskFree && $diskTotal) ? round((1 - $diskFree / $diskTotal) * 100) : null,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown action']);
