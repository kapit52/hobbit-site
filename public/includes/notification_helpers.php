<?php
require_once __DIR__ . '/order_helpers.php';
require_once __DIR__ . '/booking_helpers.php';

function create_user_notification(
    mysqli $conn,
    int $userId,
    string $entityType,
    int $entityId,
    string $message
): void {
    if ($userId <= 0) {
        return;
    }
    $stmt = $conn->prepare(
        "INSERT INTO user_notifications (user_id, entity_type, entity_id, message) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('isis', $userId, $entityType, $entityId, $message);
    $stmt->execute();
    $stmt->close();
}

function notify_order_status_change(mysqli $conn, int $orderId, string $newStatus, ?string $comment = null): void {
    $stmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ? AND user_id IS NOT NULL");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return;
    }
    $label = order_status_label($newStatus);
    $msg = "Заказ №{$orderId}: статус изменён на «{$label}»";
    if ($comment) {
        $msg .= '. ' . $comment;
    }
    create_user_notification($conn, (int)$row['user_id'], 'order', $orderId, $msg);
}

function notify_booking_status_change(mysqli $conn, int $bookingId, string $newStatus, ?string $comment = null): void {
    $stmt = $conn->prepare("SELECT user_id FROM bookings WHERE id = ? AND user_id IS NOT NULL");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return;
    }
    $label = booking_status_label($newStatus);
    $msg = "Бронь №{$bookingId}: статус изменён на «{$label}»";
    if ($comment) {
        $msg .= '. ' . $comment;
    }
    create_user_notification($conn, (int)$row['user_id'], 'booking', $bookingId, $msg);
}

function get_unread_notification_count(mysqli $conn, int $userId): int {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM user_notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $c = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $c;
}

function get_user_notifications(mysqli $conn, int $userId, int $limit = 30): array {
    $list = [];
    $stmt = $conn->prepare(
        "SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $list[] = $row;
    }
    $stmt->close();
    return $list;
}

function mark_notification_read(mysqli $conn, int $notificationId, int $userId): void {
    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $notificationId, $userId);
    $stmt->execute();
    $stmt->close();
}

function mark_all_notifications_read(mysqli $conn, int $userId): void {
    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}
