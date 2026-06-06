<?php
/**
 * Сессия администратора (независима от сессии клиента в той же PHP-сессии).
 */

function is_admin_logged_in(): bool {
    return isset($_SESSION['admin_user_id']) && (int)$_SESSION['admin_user_id'] > 0;
}

function require_admin_login(): void {
    if (!is_admin_logged_in()) {
        header('Location: admin_login.php');
        exit;
    }
}

function clear_admin_session(): void {
    unset(
        $_SESSION['admin_user_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin'],
        $_SESSION['admin_role']
    );
}

function set_admin_session(int $userId, string $username): void {
    $_SESSION['admin_user_id'] = $userId;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_role'] = 'admin';
}

function get_admin_username(): string {
    return $_SESSION['admin_username'] ?? 'Админ';
}
