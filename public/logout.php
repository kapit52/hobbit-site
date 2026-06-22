<?php
require_once 'includes/auth_user.php';
require_once 'includes/auth_admin.php';

// Единая сессия: выход из админки и из кабинета — это один и тот же сеанс.
$type = $_GET['type'] ?? 'user';

session_start();
clear_user_session();   // снимает и user_role, т.е. и админ-права

// Из управления возвращаем на форму входа, из кабинета — на главную.
header('Location: ' . ($type === 'admin' ? 'login.php' : 'index.php'));
exit;
