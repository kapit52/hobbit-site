<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['order_id'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $order_id = (int)$_SESSION['order_id'];

    if ($quantity <= 0) {
        $stmt = $conn->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
        $stmt->bind_param('ii', $item_id, $order_id);
        $stmt->execute();
        $stmt->close();
        echo "Товар удалён";
    } else {
        $stmt = $conn->prepare("UPDATE order_items SET quantity = ? WHERE id = ? AND order_id = ?");
        $stmt->bind_param('iii', $quantity, $item_id, $order_id);
        $stmt->execute();
        $stmt->close();
        echo "Количество обновлено";
    }
}
