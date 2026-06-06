<?php
session_start();
require_once 'db.php';

if (isset($_GET['id']) && isset($_SESSION['order_id'])) {
    $item_id = (int)$_GET['id'];
    $order_id = (int)$_SESSION['order_id'];
    $stmt = $conn->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
    $stmt->bind_param('ii', $item_id, $order_id);
    $stmt->execute();
    $stmt->close();
    echo "Товар удалён из корзины";
}
