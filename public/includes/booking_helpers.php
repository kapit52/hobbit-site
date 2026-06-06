<?php
require_once __DIR__ . '/../config.php';

function booking_status_label(string $status): string {
    $labels = [
        'pending' => 'Ожидает подтверждения',
        'confirmed' => 'Подтверждена',
        'rejected' => 'Отклонена',
        'cancelled' => 'Отменена',
        'completed' => 'Завершена',
    ];
    return $labels[$status] ?? $status;
}

function record_booking_status(mysqli $conn, int $bookingId, ?string $oldStatus, string $newStatus, string $changedBy = 'system', ?string $comment = null): void {
    $stmt = $conn->prepare(
        "INSERT INTO booking_status_history (booking_id, old_status, new_status, changed_by, comment) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issss', $bookingId, $oldStatus, $newStatus, $changedBy, $comment);
    $stmt->execute();
    $stmt->close();
}

function update_booking_status(mysqli $conn, int $bookingId, string $newStatus, string $changedBy = 'admin', ?string $comment = null): bool {
    $stmt = $conn->prepare("SELECT status FROM bookings WHERE id = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;
    $old = $row['status'];
    if ($old === $newStatus) return true;
    $stmt = $conn->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $newStatus, $bookingId);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        record_booking_status($conn, $bookingId, $old, $newStatus, $changedBy, $comment);
        if ($changedBy === 'admin' && $old !== $newStatus) {
            require_once __DIR__ . '/notification_helpers.php';
            notify_booking_status_change($conn, $bookingId, $newStatus, $comment);
        }
    }
    return $ok;
}

/** Слоты времени с шагом BOOKING_SLOT_MINUTES */
function booking_time_slots(): array {
    $slots = [];
    $open = BOOKING_OPEN_HOUR * 60;
    $close = BOOKING_CLOSE_HOUR * 60;
    for ($m = $open; $m < $close; $m += BOOKING_SLOT_MINUTES) {
        $h = intdiv($m, 60);
        $min = $m % 60;
        $slots[] = sprintf('%02d:%02d:00', $h, $min);
    }
    return $slots;
}

/**
 * Проверка: достаточно ли суммарной вместимости активных столов на дату/время (pending+confirmed без table_id).
 */
function hall_has_capacity(mysqli $conn, string $date, string $time, int $partySize, ?int $excludeBookingId = null): bool {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(seats), 0) AS cap FROM restaurant_tables WHERE is_active = 1");
    $stmt->execute();
    $totalSeats = (int)$stmt->get_result()->fetch_assoc()['cap'];
    $stmt->close();
    if ($totalSeats < $partySize) return false;

    $sql = "SELECT COALESCE(SUM(party_size), 0) AS booked FROM bookings
            WHERE booking_date = ? AND booking_time = ?
            AND status IN ('pending', 'confirmed')";
    if ($excludeBookingId) {
        $sql .= " AND id != ?";
    }
    $stmt = $conn->prepare($sql);
    if ($excludeBookingId) {
        $stmt->bind_param('ssi', $date, $time, $excludeBookingId);
    } else {
        $stmt->bind_param('ss', $date, $time);
    }
    $stmt->execute();
    $booked = (int)$stmt->get_result()->fetch_assoc()['booked'];
    $stmt->close();
    return ($booked + $partySize) <= $totalSeats;
}

/**
 * Конфликт по столу: confirmed брони в окне ±BOOKING_CONFLICT_HOURS.
 */
function table_is_available(mysqli $conn, int $tableId, string $date, string $time, ?int $excludeBookingId = null): bool {
    $minutes = BOOKING_CONFLICT_HOURS * 60;
    $sql = "SELECT id FROM bookings
            WHERE table_id = ? AND booking_date = ? AND status = 'confirmed'
            AND ABS(TIMESTAMPDIFF(MINUTE, booking_time, ?)) < ?";
    if ($excludeBookingId) {
        $sql .= " AND id != ?";
    }
    $stmt = $conn->prepare($sql);
    if ($excludeBookingId) {
        $stmt->bind_param('issii', $tableId, $date, $time, $minutes, $excludeBookingId);
    } else {
        $stmt->bind_param('issi', $tableId, $date, $time, $minutes);
    }
    $stmt->execute();
    $conflict = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return !$conflict;
}

/** Список id выключенных админом столов (is_active = 0). */
function get_disabled_table_ids(mysqli $conn): array {
    $ids = [];
    $res = $conn->query("SELECT id FROM restaurant_tables WHERE is_active = 0");
    if ($res) while ($row = $res->fetch_assoc()) $ids[] = (int)$row['id'];
    return $ids;
}

/** Стол выключен админом? */
function table_is_disabled(mysqli $conn, int $tableId): bool {
    $stmt = $conn->prepare("SELECT is_active FROM restaurant_tables WHERE id = ?");
    $stmt->bind_param('i', $tableId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    // Нет такой строки в restaurant_tables — считаем доступным (план зала статичен).
    if (!$row) return false;
    return (int)$row['is_active'] === 0;
}

function get_available_tables_for_booking(mysqli $conn, int $partySize, string $date, string $time, ?int $excludeBookingId = null): array {
    $tables = [];
    $res = $conn->query("SELECT * FROM restaurant_tables WHERE is_active = 1 AND seats >= " . (int)$partySize . " ORDER BY seats, name");
    while ($row = $res->fetch_assoc()) {
        if (table_is_available($conn, (int)$row['id'], $date, $time, $excludeBookingId)) {
            $tables[] = $row;
        }
    }
    return $tables;
}
