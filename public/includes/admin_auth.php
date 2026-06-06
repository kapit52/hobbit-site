<?php
// Админ-панель использует ОТДЕЛЬНУЮ cookie-сессию (SHIREADMIN), чтобы можно было
// одновременно быть авторизованным как обычный пользователь (в других вкладках) и как админ.
if (session_status() === PHP_SESSION_NONE) {
    session_name('SHIREADMIN');
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
require_admin_login();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/csrf.php';
