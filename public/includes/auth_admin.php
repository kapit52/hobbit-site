<?php
/**
 * Права администратора в рамках ЕДИНОЙ пользовательской сессии.
 *
 * Отдельной формы/сессии для админа больше нет: админ входит через обычную
 * форму login.php, а доступ к управлению определяется ролью пользователя
 * ($_SESSION['user_role'] === 'admin'). Проверка опирается только на сессию,
 * без обращения к БД, — поэтому работает и в «облегчённой» техкадминке.
 */

function is_admin_logged_in(): bool {
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0
        && ($_SESSION['user_role'] ?? '') === 'admin';
}

function require_admin_login(): void {
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function clear_admin_session(): void {
    // Единая сессия: снять админ-права = сбросить роль пользователя.
    unset($_SESSION['user_role']);
}

function set_admin_session(int $userId, string $username): void {
    // Пометить текущую (единую) сессию как админскую.
    $_SESSION['user_id']   = $userId;
    $_SESSION['username']  = $username;
    $_SESSION['user_role'] = 'admin';
}

function get_admin_username(): string {
    return $_SESSION['username'] ?? 'Админ';
}
