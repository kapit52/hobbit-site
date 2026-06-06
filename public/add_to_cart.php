<?php
session_start();
require_once 'db.php';
require_once 'includes/order_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_item_id = (int)($_POST['menu_item_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    $stmt = $conn->prepare("SELECT title, price FROM menu_items WHERE id = ?");
    $stmt->bind_param('i', $menu_item_id);
    $stmt->execute();
    $menu_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($menu_item) {
        if (!isset($_SESSION['order_id'])) {
            $stmt = $conn->prepare("INSERT INTO orders (status) VALUES ('cart')");
            $stmt->execute();
            $_SESSION['order_id'] = $conn->insert_id;
            $stmt->close();
        }

        $order_id = (int)$_SESSION['order_id'];
        $price = parse_menu_price($menu_item['price']);

        $stmt = $conn->prepare(
            "INSERT INTO order_items (order_id, menu_item_id, item_name, item_price, quantity) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('iisdi', $order_id, $menu_item_id, $menu_item['title'], $price, $quantity);
        $stmt->execute();
        $stmt->close();

        echo "Товар добавлен в корзину!";
    } else {
        echo "Товар не найден!";
    }
}
