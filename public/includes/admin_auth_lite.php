<?php
/**
 * Облегчённый bootstrap авторизации для ТЕХНИЧЕСКОЙ админки.
 *
 * В отличие от admin_auth.php здесь НЕТ подключения к БД (db.php), которое
 * делает die() при недоступной базе. Это принципиально: страница «Статус систем»
 * и логи должны оставаться доступными даже когда БД лежит — иначе индикатор
 * «БД: down» физически невозможно показать.
 *
 * Проверка прав опирается только на $_SESSION (admin_user_id выставляется при
 * логине в admin_login.php), поэтому БД для авторизации уже не нужна.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('SHIREADMIN');
    session_start();
}

require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';

app_logger_register();
require_admin_login();
