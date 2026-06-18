<?php
declare(strict_types=1);

/**
 * Bootstrap для PHPUnit: подключает тестируемый код (только определения функций,
 * без побочных эффектов при загрузке). Путь к include-файлам — относительно корня
 * проекта, поэтому тесты не зависят от текущего рабочего каталога.
 */

$inc = dirname(__DIR__) . '/public/includes';

require_once $inc . '/phone.php';
require_once $inc . '/order_helpers.php';
require_once $inc . '/promo_helpers.php';
require_once $inc . '/booking_helpers.php';
