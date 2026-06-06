<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/order_helpers.php';
require_once __DIR__ . '/booking_helpers.php';

function send_site_mail(string $to, string $subject, string $body): bool {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $headers = "From: " . MAIL_FROM . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

function notify_order_status(string $email, int $orderId, string $newStatus): void {
    $label = order_status_label($newStatus);
    $body = "Здравствуйте!\n\nСтатус вашего заказа №{$orderId} изменён: {$label}.\n\n— " . SITE_NAME;
    send_site_mail($email, "Заказ №{$orderId}: {$label}", $body);
}

function notify_booking_status(string $email, int $bookingId, string $newStatus, ?string $extra = null): void {
    $label = booking_status_label($newStatus);
    $body = "Здравствуйте!\n\nСтатус брони №{$bookingId}: {$label}.";
    if ($extra) $body .= "\n\n" . $extra;
    $body .= "\n\n— " . SITE_NAME;
    send_site_mail($email, "Бронь №{$bookingId}: {$label}", $body);
}
