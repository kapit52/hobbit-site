<?php
/**
 * Промокоды: таблица promo_codes + колонки orders.promo_code / orders.discount.
 */

function ensure_promo_schema(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS promo_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
        discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
        min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        expires_at DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $c = $conn->query("SHOW COLUMNS FROM orders LIKE 'promo_code'");
    if ($c && $c->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN promo_code VARCHAR(50) NULL AFTER notes");
    }
    $c = $conn->query("SHOW COLUMNS FROM orders LIKE 'discount'");
    if ($c && $c->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER promo_code");
    }
}

function get_promo_by_code(mysqli $conn, string $code): ?array {
    $code = trim($code);
    if ($code === '') return null;
    $stmt = $conn->prepare("SELECT * FROM promo_codes WHERE code = ?");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function promo_discount_amount(array $promo, float $subtotal): float {
    if ($promo['discount_type'] === 'percent') {
        $d = $subtotal * ((float)$promo['discount_value'] / 100);
    } else {
        $d = (float)$promo['discount_value'];
    }
    return round(min($d, $subtotal), 2);
}

/**
 * Проверяет промокод для суммы заказа.
 * Возвращает ['ok'=>bool, 'error'=>string, 'promo'=>?array, 'discount'=>float].
 */
function validate_promo(mysqli $conn, string $code, float $subtotal): array {
    $promo = get_promo_by_code($conn, $code);
    if (!$promo) {
        return ['ok' => false, 'error' => 'Промокод не найден', 'promo' => null, 'discount' => 0.0];
    }
    if ((int)$promo['is_active'] !== 1) {
        return ['ok' => false, 'error' => 'Промокод больше не действует', 'promo' => null, 'discount' => 0.0];
    }
    if (!empty($promo['expires_at']) && $promo['expires_at'] < date('Y-m-d')) {
        return ['ok' => false, 'error' => 'Срок действия промокода истёк', 'promo' => null, 'discount' => 0.0];
    }
    if ($subtotal < (float)$promo['min_order']) {
        return ['ok' => false, 'error' => 'Промокод действует от ' . number_format((float)$promo['min_order'], 0, '.', ' ') . ' ₽', 'promo' => null, 'discount' => 0.0];
    }
    return ['ok' => true, 'error' => '', 'promo' => $promo, 'discount' => promo_discount_amount($promo, $subtotal)];
}

function promo_label(array $promo): string {
    if ($promo['discount_type'] === 'percent') {
        return rtrim(rtrim(number_format((float)$promo['discount_value'], 2, '.', ''), '0'), '.') . '%';
    }
    return number_format((float)$promo['discount_value'], 0, '.', ' ') . ' ₽';
}
