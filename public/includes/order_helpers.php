<?php

function order_status_label(string $status): string {
    $labels = [
        'cart' => 'Корзина',
        'pending' => 'Ожидает подтверждения',
        'confirmed' => 'Подтверждён',
        'preparing' => 'Готовится',
        'ready' => 'Готов',
        'completed' => 'Завершён',
        'cancelled' => 'Отменён',
    ];
    return $labels[$status] ?? $status;
}

function order_type_label(?string $type): string {
    if ($type === 'delivery') return 'Доставка';
    if ($type === 'dine_in') return 'В заведении';
    if ($type === 'takeaway') return 'С собой';
    return '—';
}

/** Гарантирует, что ENUM orders.order_type содержит значение 'takeaway'. */
function ensure_takeaway_order_type(mysqli $conn): void {
    $res = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_type'");
    if ($res && ($row = $res->fetch_assoc())) {
        if (stripos($row['Type'], "'takeaway'") === false) {
            $conn->query("ALTER TABLE orders MODIFY COLUMN order_type ENUM('dine_in','delivery','takeaway') NULL");
        }
    }
}

function record_order_status(mysqli $conn, int $orderId, ?string $oldStatus, string $newStatus, string $changedBy = 'system', ?string $comment = null): void {
    $stmt = $conn->prepare(
        "INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, comment) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issss', $orderId, $oldStatus, $newStatus, $changedBy, $comment);
    $stmt->execute();
    $stmt->close();
}

function update_order_status(mysqli $conn, int $orderId, string $newStatus, string $changedBy = 'admin', ?string $comment = null): bool {
    $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || $row['status'] === 'cart') {
        return false;
    }
    $old = $row['status'];
    if ($old === $newStatus) {
        return true;
    }
    $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $newStatus, $orderId);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        record_order_status($conn, $orderId, $old, $newStatus, $changedBy, $comment);
        if ($changedBy === 'admin' && $old !== $newStatus) {
            require_once __DIR__ . '/notification_helpers.php';
            notify_order_status_change($conn, $orderId, $newStatus, $comment);
        }
    }
    return $ok;
}

function get_pending_counts(mysqli $conn): array {
    $orders = 0;
    $bookings = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'");
    if ($r) $orders = (int)$r->fetch_assoc()['c'];
    $r = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status = 'pending'");
    if ($r) $bookings = (int)$r->fetch_assoc()['c'];
    return ['orders' => $orders, 'bookings' => $bookings];
}

function parse_menu_price($priceString): float {
    $priceString = preg_replace('/[^\d,.]/', '', (string)$priceString);
    $priceString = str_replace(',', '.', $priceString);
    return (float)$priceString;
}
