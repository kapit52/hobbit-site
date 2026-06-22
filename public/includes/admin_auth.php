<?php
// Админка работает в ЕДИНОЙ пользовательской сессии: админ входит через обычную
// форму login.php, доступ сюда даёт роль 'admin' (см. auth_admin.php).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
require_admin_login();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/csrf.php';
