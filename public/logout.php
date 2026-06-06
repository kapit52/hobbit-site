<?php
require_once 'includes/auth_user.php';
require_once 'includes/auth_admin.php';

$type = $_GET['type'] ?? 'user';

if ($type === 'admin') {
    // Выходим только из админ-сессии (SHIREADMIN), пользовательская не трогается
    session_name('SHIREADMIN');
    session_start();
    clear_admin_session();
    header('Location: admin_login.php');
    exit;
}

// Пользовательская сессия (имя cookie по умолчанию)
session_start();
clear_user_session();
header('Location: index.php');
exit;
