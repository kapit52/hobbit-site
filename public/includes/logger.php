<?php
/**
 * Простой файловый логгер приложения для технической админки.
 *
 * Пишет структурированные записи (JSON Lines) в logs/app.log, который лежит
 * ВНЕ веб-корня (public/), поэтому недоступен по HTTP. Все операции защищены:
 * логгер никогда не бросает исключения и ничего не выводит в ответ.
 *
 * Каналы (логеры) и их минимальный уровень настраиваются в logs/loggers.json
 * через вкладку «Логеры» технической админки.
 */

if (!defined('SHIRE_LOG_DIR')) {
    // public/includes -> на два уровня вверх = корень проекта (рядом с public/)
    define('SHIRE_LOG_DIR', dirname(__DIR__, 2) . '/logs');
}
if (!defined('SHIRE_LOG_FILE'))   define('SHIRE_LOG_FILE', SHIRE_LOG_DIR . '/app.log');
if (!defined('SHIRE_LOG_CONFIG')) define('SHIRE_LOG_CONFIG', SHIRE_LOG_DIR . '/loggers.json');
if (!defined('SHIRE_LOG_MAX'))    define('SHIRE_LOG_MAX', 2 * 1024 * 1024); // 2 MB — порог ротации

/** Уровни логирования и их числовой вес (для сравнения с min_level канала). */
function shire_log_levels(): array {
    return ['debug'=>10, 'info'=>20, 'notice'=>25, 'warning'=>30, 'error'=>40, 'critical'=>50];
}

/** Конфигурация каналов по умолчанию. */
function shire_log_default_config(): array {
    return [
        'php'     => ['enabled'=>true,  'min_level'=>'warning', 'label'=>'PHP ошибки'],
        'request' => ['enabled'=>false, 'min_level'=>'info',    'label'=>'HTTP-запросы'],
        'auth'    => ['enabled'=>true,  'min_level'=>'info',    'label'=>'Авторизация'],
        'db'      => ['enabled'=>true,  'min_level'=>'warning', 'label'=>'База данных'],
        'app'     => ['enabled'=>true,  'min_level'=>'debug',   'label'=>'Приложение'],
        'tech'    => ['enabled'=>true,  'min_level'=>'info',    'label'=>'Тех. админка'],
    ];
}

/** Гарантирует, что директория логов существует и защищена от веб-доступа. */
function shire_log_dir_ready(): bool {
    if (!is_dir(SHIRE_LOG_DIR)) {
        @mkdir(SHIRE_LOG_DIR, 0775, true);
        // На случай, если папка окажется внутри веб-корня (XAMPP с AllowOverride All).
        @file_put_contents(SHIRE_LOG_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
        @file_put_contents(SHIRE_LOG_DIR . '/index.html', '');
    }
    return is_dir(SHIRE_LOG_DIR) && is_writable(SHIRE_LOG_DIR);
}

/** Текущая конфигурация каналов (дефолт + переопределения из loggers.json). */
function shire_log_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = shire_log_default_config();
    if (is_file(SHIRE_LOG_CONFIG)) {
        $raw  = @file_get_contents(SHIRE_LOG_CONFIG);
        $data = $raw ? json_decode($raw, true) : null;
        if (is_array($data)) {
            foreach ($data as $ch => $c) {
                if (!is_array($c)) continue;
                $cfg[$ch] = array_merge($cfg[$ch] ?? ['label'=>$ch], $c);
            }
        }
    }
    return $cfg;
}

/** Сохраняет конфигурацию каналов; сбрасывает кэш. */
function shire_log_save_config(array $cfg): bool {
    if (!shire_log_dir_ready()) return false;
    $ok = @file_put_contents(
        SHIRE_LOG_CONFIG,
        json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    return $ok !== false;
}

/** Включён ли канал для указанного уровня. */
function shire_log_enabled_for(string $channel, string $level): bool {
    $levels = shire_log_levels();
    $cfg    = shire_log_config();
    $c      = $cfg[$channel] ?? ['enabled'=>true, 'min_level'=>'info'];
    if (empty($c['enabled'])) return false;
    $min = $levels[$c['min_level'] ?? 'info'] ?? 20;
    $lv  = $levels[$level] ?? 20;
    return $lv >= $min;
}

/** Основная запись в лог. */
function app_log(string $level, string $channel, string $message, array $context = []): void {
    try {
        $level = strtolower($level);
        if (!isset(shire_log_levels()[$level])) $level = 'info';
        if (!shire_log_enabled_for($channel, $level)) return;
        if (!shire_log_dir_ready()) return;

        // Ротация: при превышении порога — переносим в app.log.1.
        if (is_file(SHIRE_LOG_FILE) && @filesize(SHIRE_LOG_FILE) > SHIRE_LOG_MAX) {
            @rename(SHIRE_LOG_FILE, SHIRE_LOG_FILE . '.1');
        }

        $entry = [
            'ts'      => round(microtime(true), 3),
            'level'   => $level,
            'channel' => $channel,
            'msg'     => $message,
        ];
        if ($context) $entry['ctx'] = $context;

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) return;
        @file_put_contents(SHIRE_LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {
        // Логгер не имеет права ломать приложение.
    }
}

function log_debug(string $ch, string $m, array $c = []): void    { app_log('debug', $ch, $m, $c); }
function log_info(string $ch, string $m, array $c = []): void     { app_log('info', $ch, $m, $c); }
function log_warning(string $ch, string $m, array $c = []): void  { app_log('warning', $ch, $m, $c); }
function log_error(string $ch, string $m, array $c = []): void    { app_log('error', $ch, $m, $c); }
function log_critical(string $ch, string $m, array $c = []): void { app_log('critical', $ch, $m, $c); }

/**
 * Регистрирует обработчики ошибок PHP и (опционально) лог HTTP-запросов.
 * Безопасно вызывать многократно — выполнится один раз.
 */
function app_logger_register(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    // Нефатальные ошибки/предупреждения PHP. Возвращаем false, чтобы НЕ подавлять
    // стандартную обработку (отображение/встроенный error_log).
    set_error_handler(function ($severity, $message, $file, $line) {
        $map = [
            E_WARNING         => 'warning', E_USER_WARNING => 'warning',
            E_NOTICE          => 'notice',  E_USER_NOTICE  => 'notice',
            E_DEPRECATED      => 'notice',  E_USER_DEPRECATED => 'notice',
            E_USER_ERROR      => 'error',
        ];
        $lvl = $map[$severity] ?? 'warning';
        app_log($lvl, 'php', $message, ['file' => basename((string)$file), 'line' => $line]);
        return false;
    });

    register_shutdown_function(function () {
        // Фатальные ошибки (в т.ч. необработанные исключения, ставшие E_ERROR).
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            app_log('critical', 'php', $err['message'], [
                'file' => basename((string)$err['file']),
                'line' => $err['line'],
            ]);
        }

        // Лог запроса — только если канал «request» включён в логерах.
        if (!empty($_SERVER['REQUEST_URI']) && shire_log_enabled_for('request', 'info')) {
            $path = strtok((string)$_SERVER['REQUEST_URI'], '?');
            // Не логируем собственные опросы технической админки: дашборд опрашивает
            // свой API каждые несколько секунд, иначе мониторинг засоряет лог сам себя.
            $selfPolling = in_array(basename($path), [
                'admin_tech.php', 'admin_tech_api.php', 'admin_tech_openapi.php',
            ], true);
            if (!$selfPolling) {
                $dur = isset($_SERVER['REQUEST_TIME_FLOAT'])
                    ? (int)round((microtime(true) - (float)$_SERVER['REQUEST_TIME_FLOAT']) * 1000)
                    : null;
                app_log('info', 'request', ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . $path, [
                    'status' => (http_response_code() ?: null),
                    'dur_ms' => $dur,
                    'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            }
        }
    });
}
