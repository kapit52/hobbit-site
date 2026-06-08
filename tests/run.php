<?php
declare(strict_types=1);

/**
 * Точка входа для юнит-тестов.
 *
 * Запуск (PHP есть в контейнере web):
 *   docker compose exec web php /var/www/html/tests/run.php
 * Либо локально, если установлен PHP CLI:
 *   php tests/run.php
 *
 * Код выхода: 0 — все тесты прошли, 1 — есть упавшие (удобно для CI).
 */

$root = dirname(__DIR__);
$inc  = $root . '/public/includes';

require __DIR__ . '/framework.php';

// Тестируемый код — только определения функций, без побочных эффектов при подключении.
require $inc . '/phone.php';
require $inc . '/order_helpers.php';
require $inc . '/promo_helpers.php';
require $inc . '/booking_helpers.php';

// Подключаем все файлы test_*.php из этой папки.
foreach (glob(__DIR__ . '/test_*.php') as $file) {
    require $file;
}

exit(run_tests());
