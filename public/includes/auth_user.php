<?php
require_once __DIR__ . '/auth_admin.php';

/**
 * Сессия клиента (независима от админ-сессии в той же вкладке/браузере).
 */

function is_user_logged_in(): bool {
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function require_user_login(string $returnTo = 'booking'): void {
    if (!is_user_logged_in()) {
        $allowed = ['cart', 'booking', 'profile', 'reviews'];
        $target = in_array($returnTo, $allowed, true) ? $returnTo : 'booking';
        header('Location: login.php?return_to=' . urlencode($target));
        exit;
    }
}

function clear_user_session(): void {
    unset(
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['order_id'],
        $_SESSION['order_type'],
        $_SESSION['login_success'],
        $_SESSION['register_success']
    );
}

function set_user_session(int $userId, string $username): void {
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
}

function redirect_after_auth(string $returnTo): void {
    switch ($returnTo) {
        case 'cart':
            header('Location: cart.php');
            break;
        case 'booking':
            header('Location: booking.php');
            break;
        case 'profile':
            header('Location: profile.php');
            break;
        case 'reviews':
            header('Location: reviews.php');
            break;
        default:
            header('Location: index.php');
    }
    exit;
}
