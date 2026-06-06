<?php
require_once 'includes/admin_auth.php';
require_once 'includes/order_helpers.php';
require_once 'includes/booking_helpers.php';
require_once 'includes/promo_helpers.php';
require_once 'includes/site_images.php';
require_once 'includes/upload_helpers.php';
require_once 'includes/mail.php';

$flash = '';
$flashError = '';
$flashSection = ''; // раздел, к которому относится сообщение о статусе

// --- Ensure admin_reply column exists ---
$colCheck = $conn->query("SHOW COLUMNS FROM reviews LIKE 'admin_reply'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE reviews ADD COLUMN admin_reply TEXT NULL AFTER review");
}

// --- Ensure photo_path column on reviews exists ---
$colCheck = $conn->query("SHOW COLUMNS FROM reviews LIKE 'photo_path'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE reviews ADD COLUMN photo_path VARCHAR(500) NULL AFTER review");
}

// --- Ensure orders.order_type supports 'takeaway' (для отображения «С собой») ---
ensure_takeaway_order_type($conn);

// --- Ensure promo_codes table + orders promo columns ---
ensure_promo_schema($conn);

// --- Ensure images_json column on menu_items ---
$colCheck = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'images_json'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE menu_items ADD COLUMN images_json TEXT NULL AFTER image_path");
}

// --- Ensure gallery_images table exists ---
$conn->query("CREATE TABLE IF NOT EXISTS gallery_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_key VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(200),
    image_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Extend gallery_images if needed
$cols_to_add = [
    'category' => "ADD COLUMN category ENUM('gallery','dish','team') DEFAULT 'gallery'",
    'sort_order' => 'ADD COLUMN sort_order INT NOT NULL DEFAULT 0',
    'alt_text' => 'ADD COLUMN alt_text VARCHAR(200) NULL',
    'item_id' => 'ADD COLUMN item_id INT NULL',
];
foreach ($cols_to_add as $col => $def) {
    $chk = $conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gallery_images' AND COLUMN_NAME='$col'");
    if ($chk && $chk->fetch_assoc()['c'] == 0) {
        $conn->query("ALTER TABLE gallery_images $def");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    if (isset($_POST['order_status_quick'])) {
        $oid = (int)$_POST['order_id'];
        $st = $_POST['new_status'];
        $allowedOrderStatuses = ['pending','confirmed','preparing','ready','completed','cancelled'];
        if (in_array($st, $allowedOrderStatuses, true) && update_order_status($conn, $oid, $st, 'admin')) {
            $flash = "Заказ #$oid: статус обновлён";
            $stmt = $conn->prepare("SELECT u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
            $stmt->bind_param('i', $oid);
            $stmt->execute();
            $em = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($em['email']) && in_array($st, ['confirmed', 'cancelled', 'ready'], true)) {
                notify_order_status($em['email'], $oid, $st);
            }
        } else {
            $flashError = 'Не удалось обновить заказ';
        }
    }

    if (isset($_POST['confirm_booking'])) {
        $bid = (int)$_POST['booking_id'];
        $tableId = (int)$_POST['table_id'];
        $adminComment = trim($_POST['admin_comment'] ?? '');
        $stmt = $conn->prepare("SELECT booking_date, booking_time, party_size, guest_email FROM bookings WHERE id = ?");
        $stmt->bind_param('i', $bid);
        $stmt->execute();
        $b = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($b && $tableId > 0 && table_is_available($conn, $tableId, $b['booking_date'], $b['booking_time'], $bid)) {
            $stmt = $conn->prepare("UPDATE bookings SET table_id=?, admin_comment=? WHERE id=?");
            $stmt->bind_param('isi', $tableId, $adminComment, $bid);
            $stmt->execute();
            $stmt->close();
            update_booking_status($conn, $bid, 'confirmed', 'admin', $adminComment);
            if (!empty($b['guest_email'])) {
                notify_booking_status($b['guest_email'], $bid, 'confirmed', $adminComment);
            }
            $flash = "Бронь #$bid подтверждена";
        } else {
            $flashError = 'Стол занят или не выбран';
        }
    }

    if (isset($_POST['booking_status_action'])) {
        $bid = (int)$_POST['booking_id'];
        $st = $_POST['booking_status'];
        if (in_array($st, ['rejected', 'cancelled', 'completed'], true)) {
            update_booking_status($conn, $bid, $st, 'admin', trim($_POST['admin_comment'] ?? ''));
            $stmt = $conn->prepare("SELECT guest_email FROM bookings WHERE id = ?");
            $stmt->bind_param('i', $bid);
            $stmt->execute();
            $em = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($em['guest_email'])) {
                notify_booking_status($em['guest_email'], $bid, $st);
            }
            $flash = "Бронь #$bid: " . booking_status_label($st);
        }
    }

    if (isset($_POST['add_table'])) {
        $name = trim($_POST['table_name']);
        $seats = (int)$_POST['table_seats'];
        $zone = trim($_POST['table_zone'] ?? 'Зал');
        if ($name && $seats > 0) {
            $stmt = $conn->prepare("INSERT INTO restaurant_tables (name, seats, zone) VALUES (?, ?, ?)");
            $stmt->bind_param('sis', $name, $seats, $zone);
            $stmt->execute();
            $stmt->close();
            $flash = 'Стол добавлен';
        }
    }

    if (isset($_POST['toggle_table'])) {
        $tid = (int)$_POST['table_id'];
        $conn->query("UPDATE restaurant_tables SET is_active = NOT is_active WHERE id = $tid");
        $flash = 'Статус стола изменён';
    }

    if (isset($_POST['add_item'])) {
        $title = $conn->real_escape_string(trim($_POST['title']));
        $category = $conn->real_escape_string(trim($_POST['category']));
        $price = $conn->real_escape_string(trim($_POST['price']));
        $image_path = $conn->real_escape_string(trim($_POST['image_path']));
        $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
        $weight = $conn->real_escape_string(trim($_POST['weight'] ?? ''));
        $badge = $conn->real_escape_string(trim($_POST['badge'] ?? ''));
        $is_special = isset($_POST['is_special']) ? 1 : 0;
        $conn->query("INSERT INTO menu_items (title, category, price, image_path, description, weight, badge, is_special) VALUES ('$title', '$category', '$price', '$image_path', '$description', '$weight', '$badge', $is_special)");
        $flash = 'Блюдо добавлено';
    }

    if (isset($_POST['update_item_image'])) {
        $iid = (int)$_POST['item_id'];
        $newImg = trim($_POST['image_path']);
        $ip = $conn->real_escape_string($newImg);
        $oldImg = current_upload_path($conn, 'menu_items', 'image_path', $iid);
        $conn->query("UPDATE menu_items SET image_path='$ip' WHERE id=$iid");
        if ($oldImg !== $newImg) delete_upload_if_unused($conn, $oldImg);
        $flash = 'Изображение обновлено';
    }

    if (isset($_POST['update_item'])) {
        $iid = (int)$_POST['item_id'];
        $title = $conn->real_escape_string(trim($_POST['title']));
        $category = $conn->real_escape_string(trim($_POST['category']));
        $price = $conn->real_escape_string(trim($_POST['price']));
        $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
        $weight = $conn->real_escape_string(trim($_POST['weight'] ?? ''));
        $badge = $conn->real_escape_string(trim($_POST['badge'] ?? ''));
        $is_special = isset($_POST['is_special']) ? 1 : 0;
        $newImg = trim($_POST['image_path'] ?? '');
        $image_path = $conn->real_escape_string($newImg);
        $oldImg = current_upload_path($conn, 'menu_items', 'image_path', $iid);
        $conn->query("UPDATE menu_items SET title='$title', category='$category', price='$price', image_path='$image_path', description='$description', weight='$weight', badge='$badge', is_special=$is_special WHERE id=$iid");
        if ($oldImg !== $newImg) delete_upload_if_unused($conn, $oldImg);
        $flash = 'Блюдо обновлено';
    }

    if (isset($_POST['delete_table'])) {
        $tid = (int)$_POST['table_id'];
        $conn->query("DELETE FROM restaurant_tables WHERE id=$tid");
        $flash = 'Стол удалён';
    }

    if (isset($_POST['update_table'])) {
        $tid = (int)$_POST['table_id'];
        $name = $conn->real_escape_string(trim($_POST['table_name']));
        $seats = (int)$_POST['table_seats'];
        $zone = $conn->real_escape_string(trim($_POST['table_zone']));
        $conn->query("UPDATE restaurant_tables SET name='$name', seats=$seats, zone='$zone' WHERE id=$tid");
        $flash = 'Стол обновлён';
    }

    if (isset($_POST['save_reply'])) {
        $rid = (int)$_POST['review_id'];
        $reply = trim($_POST['admin_reply'] ?? '');
        $stmt = $conn->prepare("UPDATE reviews SET admin_reply=? WHERE id=?");
        $stmt->bind_param('si', $reply, $rid);
        $stmt->execute();
        $stmt->close();
        $flash = 'Ответ сохранён';
    }

    if (isset($_POST['save_gallery_slot'])) {
        $rawSlot = trim($_POST['slot_key'] ?? '');
        $slot_key = $conn->real_escape_string($rawSlot);
        $label = $conn->real_escape_string(trim($_POST['label'] ?? ''));
        $image_path = $conn->real_escape_string(trim($_POST['image_path'] ?? ''));
        $category = in_array($_POST['category'] ?? '', ['gallery','dish','team']) ? $_POST['category'] : 'gallery';
        // Для именованного слота категорию определяем автоматически
        if (is_named_slot($rawSlot)) {
            $category = slot_category($rawSlot);
        }
        if ($slot_key !== '') {
            $newImg = trim($_POST['image_path'] ?? '');
            $oldRes = $conn->query("SELECT image_path FROM gallery_images WHERE slot_key='$slot_key'");
            $oldImg = ($oldRes && ($r = $oldRes->fetch_assoc())) ? (string)($r['image_path'] ?? '') : '';
            $conn->query("INSERT INTO gallery_images (slot_key, label, image_path, category) VALUES ('$slot_key', '$label', '$image_path', '$category')
                          ON DUPLICATE KEY UPDATE image_path='$image_path', label='$label', category='$category'");
            if ($oldImg !== $newImg) delete_upload_if_unused($conn, $oldImg);
        } else {
            $newKey = 'img_' . time() . '_' . mt_rand(100,999);
            $conn->query("INSERT INTO gallery_images (slot_key, label, image_path, category) VALUES ('$newKey', '$label', '$image_path', '$category')");
        }
        $flash = is_named_slot($rawSlot) ? 'Фото назначено на слот сайта' : 'Изображение добавлено';
    }

    // Универсальная загрузка: первый список — тип, второй — конкретная цель.
    // Цель: 'dish:<id>' для блюда, ключ слота (team-*, gallery-*, …) или '' для свободного фото галереи.
    if (isset($_POST['assign_image'])) {
        $target = trim($_POST['assign_target'] ?? '');
        $newImg = trim($_POST['image_path'] ?? '');
        if ($newImg === '') {
            $flashError = 'Сначала загрузите фото или укажите путь к файлу';
        } elseif (strpos($target, 'dish:') === 0) {
            $iid = (int)substr($target, 5);
            if ($iid > 0) {
                $ip = $conn->real_escape_string($newImg);
                $oldImg = current_upload_path($conn, 'menu_items', 'image_path', $iid);
                $conn->query("UPDATE menu_items SET image_path='$ip' WHERE id=$iid");
                if ($oldImg !== $newImg) delete_upload_if_unused($conn, $oldImg);
                $flash = 'Фото назначено блюду';
            } else {
                $flashError = 'Не выбрано блюдо';
            }
        } elseif (is_named_slot($target)) {
            $ip = $conn->real_escape_string($newImg);
            $slot_key = $conn->real_escape_string($target);
            $category = slot_category($target);
            $slots = site_image_slots();
            $label = $conn->real_escape_string($slots[$target][2] ?? ''); // подпись из описания слота
            $oldRes = $conn->query("SELECT image_path FROM gallery_images WHERE slot_key='$slot_key'");
            $oldImg = ($oldRes && ($r = $oldRes->fetch_assoc())) ? (string)($r['image_path'] ?? '') : '';
            $conn->query("INSERT INTO gallery_images (slot_key, label, image_path, category) VALUES ('$slot_key', '$label', '$ip', '$category')
                          ON DUPLICATE KEY UPDATE image_path='$ip', label='$label', category='$category'");
            if ($oldImg !== $newImg) delete_upload_if_unused($conn, $oldImg);
            $flash = 'Фото назначено на слот сайта';
        } else {
            // свободное фото галереи — новая запись
            $ip = $conn->real_escape_string($newImg);
            $newKey = 'img_' . time() . '_' . mt_rand(100,999);
            $conn->query("INSERT INTO gallery_images (slot_key, label, image_path, category) VALUES ('$newKey', '', '$ip', 'gallery')");
            $flash = 'Фото добавлено в галерею';
        }
    }

    if (isset($_POST['delete_gallery_image'])) {
        $gid = (int)$_POST['gallery_id'];
        $oldImg = current_upload_path($conn, 'gallery_images', 'image_path', $gid);
        $conn->query("DELETE FROM gallery_images WHERE id=$gid");
        delete_upload_if_unused($conn, $oldImg);
        $flash = 'Изображение удалено';
    }

    if (isset($_POST['update_gallery_sort'])) {
        $order = json_decode($_POST['sort_data'] ?? '[]', true);
        foreach ($order as $item) {
            $id = (int)$item['id'];
            $sort = (int)$item['sort'];
            $conn->query("UPDATE gallery_images SET sort_order=$sort WHERE id=$id");
        }
        echo json_encode(['ok'=>true]); exit;
    }

    if (isset($_POST['update_gallery_label'])) {
        $gid = (int)$_POST['gallery_id'];
        $label = $conn->real_escape_string(trim($_POST['label'] ?? ''));
        $conn->query("UPDATE gallery_images SET label='$label' WHERE id=$gid");
        $flash = 'Подпись обновлена';
    }

    // --- Промокоды ---
    if (isset($_POST['add_promo'])) {
        $code  = strtoupper(trim($_POST['promo_code'] ?? ''));
        $type  = ($_POST['promo_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $value = max(0, (float)($_POST['promo_value'] ?? 0));
        $min   = max(0, (float)($_POST['promo_min'] ?? 0));
        $exp   = trim($_POST['promo_expires'] ?? '');
        $exp   = $exp !== '' ? $exp : null;
        if ($code === '' || $value <= 0) {
            $flashError = 'Укажите код и размер скидки';
        } else {
            $stmt = $conn->prepare("INSERT INTO promo_codes (code, discount_type, discount_value, min_order, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssdds', $code, $type, $value, $min, $exp);
            if ($stmt->execute()) $flash = 'Промокод добавлен';
            else $flashError = 'Не удалось добавить (возможно, такой код уже есть)';
            $stmt->close();
        }
    }

    if (isset($_POST['update_promo'])) {
        $pid   = (int)$_POST['promo_id'];
        $code  = strtoupper(trim($_POST['code'] ?? ''));
        $type  = ($_POST['discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $value = max(0, (float)($_POST['discount_value'] ?? 0));
        $min   = max(0, (float)($_POST['min_order'] ?? 0));
        $exp   = trim($_POST['expires_at'] ?? '');
        $exp   = $exp !== '' ? $exp : null;
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($code !== '' && $pid > 0) {
            $stmt = $conn->prepare("UPDATE promo_codes SET code=?, discount_type=?, discount_value=?, min_order=?, expires_at=?, is_active=? WHERE id=?");
            $stmt->bind_param('ssddsii', $code, $type, $value, $min, $exp, $active, $pid);
            if ($stmt->execute()) $flash = 'Промокод обновлён';
            else $flashError = 'Не удалось обновить промокод';
            $stmt->close();
        }
    }

    if (isset($_POST['delete_promo'])) {
        $pid = (int)$_POST['promo_id'];
        $conn->query("DELETE FROM promo_codes WHERE id = $pid");
        $flash = 'Промокод удалён';
    }

    // Привязываем сообщение к разделу, где выполнено действие
    if ($flash || $flashError) {
        if (isset($_POST['order_status_quick'])) {
            $flashSection = 'orders';
        } elseif (isset($_POST['confirm_booking']) || isset($_POST['booking_status_action'])) {
            $flashSection = 'bookings';
        } elseif (isset($_POST['add_table']) || isset($_POST['toggle_table']) || isset($_POST['delete_table']) || isset($_POST['update_table'])) {
            $flashSection = 'tables';
        } elseif (isset($_POST['add_item']) || isset($_POST['update_item_image']) || isset($_POST['update_item'])) {
            $flashSection = 'menu';
        } elseif (isset($_POST['save_reply'])) {
            $flashSection = 'reviews';
        } elseif (isset($_POST['assign_image']) || isset($_POST['save_gallery_slot']) || isset($_POST['delete_gallery_image']) || isset($_POST['update_gallery_label'])) {
            $flashSection = 'gallery';
        } elseif (isset($_POST['add_promo']) || isset($_POST['update_promo']) || isset($_POST['delete_promo'])) {
            $flashSection = 'promos';
        }
    }
}

if (isset($_GET['delete_item'])) {
    $id = (int)$_GET['delete_item'];
    $oldImg = current_upload_path($conn, 'menu_items', 'image_path', $id);
    $conn->query("DELETE FROM menu_items WHERE id = $id");
    delete_upload_if_unused($conn, $oldImg);
    header('Location: admin_panel.php?section=menu');
    exit;
}
if (isset($_GET['delete_review'])) {
    $id = (int)$_GET['delete_review'];
    $oldImg = current_upload_path($conn, 'reviews', 'photo_path', $id);
    $conn->query("DELETE FROM reviews WHERE id = $id");
    delete_upload_if_unused($conn, $oldImg);
    header('Location: admin_panel.php?section=reviews');
    exit;
}

$orderFilter = $_GET['order_status'] ?? '';
$bookingFilter = $_GET['booking_status'] ?? '';
$bookingDateFilter = $_GET['booking_date'] ?? '';

$ordersSql = "SELECT o.*, u.username, u.full_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.status != 'cart' AND o.user_id IS NOT NULL";
if ($orderFilter) {
    $ordersSql .= " AND o.status = '" . $conn->real_escape_string($orderFilter) . "'";
}
$ordersSql .= " ORDER BY o.created_at DESC LIMIT 100";
$orders = $conn->query($ordersSql);

$bookingsSql = "SELECT b.*, rt.name AS table_name FROM bookings b LEFT JOIN restaurant_tables rt ON b.table_id = rt.id WHERE b.user_id IS NOT NULL";
if ($bookingFilter) {
    $bookingsSql .= " AND b.status = '" . $conn->real_escape_string($bookingFilter) . "'";
}
if ($bookingDateFilter) {
    $bookingsSql .= " AND b.booking_date = '" . $conn->real_escape_string($bookingDateFilter) . "'";
}
$bookingsSql .= " ORDER BY b.booking_date DESC, b.booking_time DESC LIMIT 100";
$bookings = $conn->query($bookingsSql);

$menu_items = $conn->query("SELECT * FROM menu_items ORDER BY category, id");
$reviews = $conn->query("SELECT * FROM reviews ORDER BY id DESC");
$tables = $conn->query("SELECT * FROM restaurant_tables ORDER BY zone, seats");
$promos = $conn->query("SELECT * FROM promo_codes ORDER BY is_active DESC, id DESC");
$counts = get_pending_counts($conn);
$admin_username = $_SESSION['admin_username'] ?? 'Администратор';
$admin_initials = mb_strtoupper(mb_substr($admin_username, 0, 2));
$active_section = $_GET['section'] ?? ($flashSection ?: (isset($_GET['order_status']) ? 'orders' : (isset($_GET['booking_status']) || isset($_GET['booking_date']) ? 'bookings' : 'dashboard')));

// --- Dashboard queries ---
$dash_pending_orders = 0;
$dash_pending_bookings = 0;
$dash_revenue_today = '0.00';
$dash_menu_count = 0;

$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'");
if ($r) $dash_pending_orders = (int)$r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status='pending'");
if ($r) $dash_pending_bookings = (int)$r->fetch_assoc()['c'];

$r = $conn->query("SELECT COALESCE(SUM(total),0) AS rev FROM orders WHERE DATE(created_at)=CURDATE() AND status NOT IN ('cart','cancelled')");
if ($r) $dash_revenue_today = number_format((float)$r->fetch_assoc()['rev'], 2, '.', ' ');

$r = $conn->query("SELECT COUNT(*) AS c FROM menu_items WHERE category NOT IN ('decor','chef')");
if ($r) $dash_menu_count = (int)$r->fetch_assoc()['c'];

$recent_orders_res = $conn->query("SELECT o.id, o.total, o.status, o.created_at, o.order_type, u.username, u.full_name FROM orders o LEFT JOIN users u ON o.user_id=u.id WHERE o.status != 'cart' ORDER BY o.created_at DESC LIMIT 5");
$recent_bookings_res = $conn->query("SELECT b.id, b.guest_name, b.booking_date, b.booking_time, b.party_size, b.status FROM bookings b ORDER BY b.created_at DESC LIMIT 5");

// --- Extra dashboard data ---
// Orders today count
$dash_orders_today = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND status!='cart'");
if ($r) $dash_orders_today = (int)$r->fetch_assoc()['c'];

// Bookings tonight (from 17:00)
$dash_bookings_evening = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE booking_date=CURDATE() AND booking_time>='17:00:00' AND status IN ('pending','confirmed')");
if ($r) $dash_bookings_evening = (int)$r->fetch_assoc()['c'];

// Average order value (last 30 days)
$dash_avg_check = 0;
$r = $conn->query("SELECT COALESCE(AVG(total),0) AS av FROM orders WHERE status NOT IN ('cart','cancelled') AND total>0 AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)");
if ($r) { $av=(float)$r->fetch_assoc()['av']; $dash_avg_check = $av ? number_format($av,0,'.',' ') : 0; }

// Revenue per day — last 7 days (for SVG chart)
$chartRevByDay = [];
$r = $conn->query("SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS rev FROM orders WHERE status NOT IN ('cart','cancelled') AND created_at>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(created_at)");
if ($r) while ($row=$r->fetch_assoc()) $chartRevByDay[$row['d']]=(float)$row['rev'];
$chartDayRu = ['Mon'=>'Пн','Tue'=>'Вт','Wed'=>'Ср','Thu'=>'Чт','Fri'=>'Пт','Sat'=>'Сб','Sun'=>'Вс'];
$chartLabels = []; $chartValues = [];
for ($i=6;$i>=0;$i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = $chartDayRu[date('D',strtotime($d))] ?? date('D',strtotime($d));
    $chartValues[] = $chartRevByDay[$d] ?? 0;
}
$chartMax = max(array_merge($chartValues,[1]));
// Generate SVG polyline points (x: 40–640, y: 190 bottom → 20 top)
function dashChartPts($vals, $max) {
    $n=count($vals); if($n<2) return ''; $pts=[];
    for($i=0;$i<$n;$i++){
        $x=round(40+($i/($n-1))*600);
        $y=round(190-($vals[$i]/$max)*170);
        $pts[]="$x,$y";
    }
    return implode(' ',$pts);
}
$chartPts = dashChartPts($chartValues, $chartMax);
$chartArea = $chartPts ? ($chartPts.' 640,200 40,200') : '';

// Top dishes (last 7 days)
$topDishes = [];
$r = $conn->query("SELECT oi.item_name, SUM(oi.quantity) AS cnt FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE o.status NOT IN ('cart','cancelled') AND o.created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY oi.item_name ORDER BY cnt DESC LIMIT 6");
if ($r) while ($row=$r->fetch_assoc()) $topDishes[]=$row;

// Event timeline (recent orders + bookings)
$timelineEvents = [];
$r = $conn->query("SELECT 'order' AS etype, id, status, created_at, COALESCE(customer_name,'') AS person, COALESCE(total,0) AS amount FROM orders WHERE status!='cart' ORDER BY created_at DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $timelineEvents[]=$row;
$r = $conn->query("SELECT 'booking' AS etype, id, status, created_at, guest_name AS person, 0 AS amount FROM bookings ORDER BY created_at DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $timelineEvents[]=$row;
usort($timelineEvents, fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
$timelineEvents = array_slice($timelineEvents,0,7);

// Sparkline helpers (7 data points, crude)
function sparkPts($vals) {
    $n=count($vals); if(!$n) return '0,30'; $max=max(array_merge($vals,[1]));
    $pts=[];
    for($i=0;$i<$n;$i++){
        $x=round($i/($n-1)*190);
        $y=round(30-(($vals[$i]/$max)*24));
        $pts[]="$x,$y";
    }
    return implode(' ',$pts);
}
// Revenue sparkline (same 7-day data)
$revSparkPts = sparkPts($chartValues);

// --- Gallery slots ---
$atmo_labels = [
    'slot_01' => 'Зал у очага · вечер',
    'slot_02' => 'Барная стойка · медовый эль',
    'slot_03' => 'Очаг · живой огонь',
    'slot_04' => 'Терраса · летний вечер',
    'slot_05' => 'VIP-зал у камина',
    'slot_06' => 'Детали интерьера',
    'slot_07' => 'Скрипач у очага · пятница',
    'slot_08' => 'Кухня таверны',
    'slot_09' => 'Круглая дверь',
    'slot_10' => 'Дубовые балки',
    'slot_11' => 'Свечи и фонари',
    'slot_12' => 'Зимний сад',
];
$gallery_rows = [];
$gRes = $conn->query("SELECT * FROM gallery_images");
if ($gRes) {
    while ($gRow = $gRes->fetch_assoc()) {
        $gallery_rows[$gRow['slot_key']] = $gRow;
    }
}

// Gallery images available for dish picker (all categories, with image_path)
$dish_gallery = [];
$dgRes = $conn->query("SELECT id, image_path, label FROM gallery_images WHERE image_path != '' ORDER BY sort_order ASC, id ASC");
if ($dgRes) while ($dgRow = $dgRes->fetch_assoc()) $dish_gallery[] = $dgRow;
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Управление · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/admin.css">
<style>
  .admin-table { width:100%; border-collapse:collapse; font-size:0.93rem; }
  .admin-table th { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.14em; font-size:0.7rem; color:var(--ink-mute); background:var(--parch-100); padding:12px 14px; border-bottom:2px solid var(--line); text-align:left; }
  .admin-table td { padding:12px 14px; border-bottom:1px solid var(--line-soft); vertical-align:middle; }
  .admin-table tr:hover td { background:rgba(184,134,11,0.04); }
  .admin-table tr:last-child td { border-bottom:none; }
  .status-badge { display:inline-flex; align-items:center; gap:5px; font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.12em; font-size:0.68rem; padding:4px 10px; border-radius:12px; }
  .status-badge::before { content:""; width:6px; height:6px; border-radius:50%; background:currentColor; }
  .status-pending   { background:rgba(184,134,11,0.12); color:var(--amber-deep); }
  .status-confirmed { background:rgba(107,142,78,0.16); color:var(--forest); }
  .status-preparing { background:rgba(194,84,32,0.12); color:var(--ember); }
  .status-ready     { background:rgba(107,142,78,0.2); color:var(--forest); }
  .status-completed { background:var(--parch-200); color:var(--ink-mute); }
  .status-cancelled { background:rgba(139,31,58,0.1); color:var(--berry); }
  .status-rejected  { background:rgba(139,31,58,0.1); color:var(--berry); }
  .btn-small { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.1em; font-size:0.68rem; padding:5px 10px; border-radius:var(--r-sm); border:1px solid var(--amber); background:transparent; color:var(--amber-deep); cursor:pointer; transition:all 0.15s; }
  .btn-small:hover { background:var(--amber); color:var(--parch-50); }
  .btn-danger { border-color:var(--berry); color:var(--berry); }
  .btn-danger:hover { background:var(--berry); color:var(--parch-50); }
  .admin-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:24px; padding:20px; background:var(--parch-100); border:1px solid var(--line); border-radius:var(--r-md); }
  .admin-form input,.admin-form select,.admin-form textarea { font:inherit; font-size:0.92rem; padding:9px 12px; border:1px solid var(--line); border-radius:var(--r-sm); background:var(--parch-50); color:var(--ink); }
  .admin-form input:focus,.admin-form select:focus,.admin-form textarea:focus { outline:none; border-color:var(--amber); }
  .admin-form button { padding:9px 18px; background:linear-gradient(180deg,var(--amber),var(--amber-deep)); color:var(--parch-50); border:none; border-radius:var(--r-sm); font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.12em; font-size:0.74rem; cursor:pointer; }
  .admin-form label { font-size:0.82rem; color:var(--ink-mute); align-self:center; }
  .admin-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
  .admin-filters input,.admin-filters select,.admin-filters button { font:inherit; font-size:0.88rem; padding:8px 12px; border:1px solid var(--line); border-radius:var(--r-sm); background:var(--parch-50); cursor:pointer; }
  .admin-section-content { display:none; }
  .admin-section-content.active { display:block; }
  .inline-booking-form { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:6px; }
  .inline-booking-form input,.inline-booking-form select { font:inherit; font-size:0.82rem; padding:5px 8px; border:1px solid var(--line); border-radius:var(--r-sm); }
  .delete-link { color:var(--berry); font-size:1.1rem; text-decoration:none; }
  .action-cell { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
  /* Dashboard */
  .dash-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; margin-bottom:32px; }
  @media(max-width:900px){ .dash-stats { grid-template-columns:repeat(2,1fr); } }
  .stat-card { background:var(--parch-50); border:1px solid var(--line); border-radius:var(--r-lg); padding:22px 24px; display:flex; flex-direction:column; gap:8px; }
  .stat-card .stat-label { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.14em; font-size:0.68rem; color:var(--ink-mute); }
  .stat-card .stat-value { font-family:var(--font-display); font-size:2rem; font-weight:700; color:var(--amber-deep); line-height:1; }
  .stat-card .stat-sub { font-size:0.82rem; color:var(--ink-faint); }
  .dash-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
  @media(max-width:800px){ .dash-grid { grid-template-columns:1fr; } }
  .dash-card { background:var(--parch-50); border:1px solid var(--line); border-radius:var(--r-lg); padding:20px; }
  .dash-card h3 { font-family:var(--font-display); font-size:0.8rem; text-transform:uppercase; letter-spacing:0.14em; color:var(--ink-mute); margin:0 0 14px; }
  /* Gallery */
  .gallery-admin-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
  @media(max-width:800px){ .gallery-admin-grid { grid-template-columns:repeat(2,1fr); } }
  .gallery-slot { background:var(--parch-100); border:1px solid var(--line); border-radius:var(--r-md); padding:14px; }
  .gallery-slot .slot-label { font-family:var(--font-display); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--ink-mute); margin-bottom:8px; }
  .gallery-slot img { width:100%; height:120px; object-fit:cover; border-radius:var(--r-sm); margin-bottom:8px; display:block; }
  .gallery-slot .img-placeholder { width:100%; height:120px; background:repeating-linear-gradient(45deg,#3a2817 0,#3a2817 10px,#2c1810 10px,#2c1810 20px); border-radius:var(--r-sm); margin-bottom:8px; display:flex; align-items:center; justify-content:center; color:rgba(255,200,100,0.4); font-size:0.8rem; }
  .gallery-slot form { display:flex; gap:6px; }
  .gallery-slot form input { flex:1; font:inherit; font-size:0.82rem; padding:6px 10px; border:1px solid var(--line); border-radius:var(--r-sm); background:var(--parch-50); }
  .gallery-slot form button { padding:6px 12px; background:var(--amber-deep); color:var(--parch-50); border:none; border-radius:var(--r-sm); font-family:var(--font-display); font-size:0.7rem; text-transform:uppercase; letter-spacing:0.1em; cursor:pointer; white-space:nowrap; }
  /* Reply */
  .reply-block { margin-top:4px; }
  .reply-block textarea { width:100%; font:inherit; font-size:0.85rem; padding:7px 10px; border:1px solid var(--line); border-radius:var(--r-sm); background:var(--parch-50); resize:vertical; min-height:60px; box-sizing:border-box; }
  .existing-reply { font-size:0.85rem; color:var(--forest); font-style:italic; margin-bottom:6px; padding:6px 10px; background:rgba(107,142,78,0.08); border-radius:var(--r-sm); border-left:3px solid var(--forest); }
  /* Edit modals */
  .modal-overlay { display:none; position:fixed; inset:0; background:rgba(20,12,6,0.65); z-index:200; align-items:center; justify-content:center; }
  .modal-overlay.open { display:flex; }
  .modal-box { background:var(--parch-50); border:1px solid var(--line); border-radius:var(--r-lg); padding:36px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; box-shadow:0 24px 60px -16px rgba(20,12,6,0.5); }
  .modal-box h3 { font-family:var(--font-display); font-size:1.3rem; margin:0 0 20px; }
  .modal-close { float:right; background:none; border:none; font-size:1.4rem; color:var(--ink-mute); cursor:pointer; line-height:1; }
  .modal-close:hover { color:var(--berry); }
  /* Field helpers used in modals */
  .fl { font-family:var(--font-display); font-size:0.7rem; text-transform:uppercase; letter-spacing:0.14em; color:var(--ink-mute); display:block; margin-bottom:5px; }
  .fi { width:100%; box-sizing:border-box; font:inherit; font-size:0.92rem; padding:9px 12px; border:1px solid var(--line); border-radius:var(--r-sm); background:var(--parch-50); color:var(--ink); }
  .fi:focus { outline:none; border-color:var(--amber); box-shadow:0 0 0 3px rgba(184,118,58,0.1); }
  .fi-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .fi-grid .span2 { grid-column:1/-1; }
  /* Picker */
  .dish-picker-grid { display:flex; flex-wrap:wrap; gap:8px; max-height:240px; overflow-y:auto; padding:10px; border:1px solid var(--line); border-radius:var(--r-sm); background:var(--parch-50); }
  .dish-picker-thumb { cursor:pointer; border:2px solid var(--line); border-radius:var(--r-sm); overflow:hidden; position:relative; transition:border-color 0.15s; flex-shrink:0; }
  .dish-picker-thumb:hover { border-color:var(--amber); }
  .dish-picker-thumb.picked { border-color:var(--amber-deep); box-shadow:0 0 0 2px var(--amber); }
  .dish-picker-thumb img { width:76px; height:76px; object-fit:cover; display:block; }
  .dish-picker-thumb .pt-label { font-size:0.65rem; color:var(--ink-mute); padding:2px 4px; max-width:76px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .dish-picker-thumb .pt-num { position:absolute; top:3px; left:3px; background:var(--amber-deep); color:#fff; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:0.62rem; font-weight:700; }
  /* Selected images strip */
  .sel-imgs { display:flex; flex-wrap:wrap; gap:8px; min-height:52px; padding:8px; border:1px dashed var(--line); border-radius:var(--r-sm); background:var(--parch-100); margin-bottom:8px; }
  .sel-img-card { position:relative; cursor:grab; border:2px solid var(--amber); border-radius:var(--r-sm); overflow:hidden; }
  .sel-img-card img { width:72px; height:72px; object-fit:cover; display:block; }
  .sel-img-card .si-num { position:absolute; top:3px; left:3px; background:var(--amber-deep); color:#fff; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:0.62rem; }
  .sel-img-card .si-del { position:absolute; top:3px; right:3px; background:var(--berry); color:#fff; border:none; width:16px; height:16px; border-radius:50%; font-size:0.7rem; line-height:1; cursor:pointer; padding:0; display:flex; align-items:center; justify-content:center; }
  .sel-img-card.drag-over { opacity:0.5; outline:2px dashed var(--amber-deep); }
</style>
</head>
<body class="admin-body">

<aside class="admin-side">
  <div class="admin-brand">
    <img src="assets/brand-mark.svg" alt="">
    <div>
      <div class="name">Ширский<br>уголок</div>
      <div class="sub">управление</div>
    </div>
  </div>

  <div class="admin-section-label">Обзор</div>
  <ul class="admin-nav">
    <li><a href="?section=dashboard" class="<?= $active_section==='dashboard'?'active':'' ?>" onclick="showSection('dashboard');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Дашборд
    </a></li>
  </ul>

  <div class="admin-section-label">Заявки</div>
  <ul class="admin-nav">
    <li><a href="?section=orders" class="<?= $active_section==='orders'?'active':'' ?>" onclick="showSection('orders');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h2l2.5 12h11l2.5-9H6"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
      Заказы
      <?php if ($counts['orders'] > 0): ?><span class="badge"><?= $counts['orders'] ?></span><?php endif; ?>
    </a></li>
    <li><a href="?section=bookings" class="<?= $active_section==='bookings'?'active':'' ?>" onclick="showSection('bookings');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Бронирования
      <?php if ($counts['bookings'] > 0): ?><span class="badge"><?= $counts['bookings'] ?></span><?php endif; ?>
    </a></li>
  </ul>

  <div class="admin-section-label">Управление</div>
  <ul class="admin-nav">
    <li><a href="?section=tables" class="<?= $active_section==='tables'?'active':'' ?>" onclick="showSection('tables');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/></svg>
      План зала / Столики
    </a></li>
    <li><a href="?section=menu" class="<?= $active_section==='menu'?'active':'' ?>" onclick="showSection('menu');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6h16M4 12h16M4 18h12"/></svg>
      Меню и блюда
    </a></li>
    <li><a href="?section=reviews" class="<?= $active_section==='reviews'?'active':'' ?>" onclick="showSection('reviews');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.5 8.5 0 0 1-7.6-3 8.5 8.5 0 0 1-1-7.7 8.4 8.4 0 0 1 8.4-5.6"/><path d="M16 8.7 11.5 13l-2.3-2.4"/></svg>
      Отзывы
    </a></li>
    <li><a href="?section=gallery" class="<?= $active_section==='gallery'?'active':'' ?>" onclick="showSection('gallery');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      Галерея
    </a></li>
    <li><a href="?section=promos" class="<?= $active_section==='promos'?'active':'' ?>" onclick="showSection('promos');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 12v7a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7"/><rect x="2" y="7" width="20" height="5" rx="1"/><path d="M12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
      Промокоды
    </a></li>
  </ul>

  <div class="admin-section-label">Система</div>
  <ul class="admin-nav">
    <li><a href="admin_tech.php">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 12h4l2 7 4-14 2 7h6"/></svg>
      Техническая панель
    </a></li>
  </ul>

  <div class="admin-section-label">Ссылки</div>
  <ul class="admin-nav">
    <li><a href="admin_booking_calendar.php">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Календарь броней
    </a></li>
    <li><a href="index.php" target="_blank">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      Открыть сайт
    </a></li>
  </ul>

  <div style="margin-top:auto;"></div>
  <div class="admin-user" style="margin-top:24px;">
    <div class="av"><?= htmlspecialchars($admin_initials) ?></div>
    <div>
      <div class="who"><?= htmlspecialchars($admin_username) ?></div>
      <div class="role">Администратор</div>
    </div>
    <a href="logout.php?type=admin" style="margin-left:auto;color:var(--ink-faint);border:none;font-size:0.8rem;">Выйти</a>
  </div>
</aside>

<div class="admin-main">
  <header class="admin-top">
    <h1 id="pageTitle">
      <?php
      $titles = ['dashboard'=>'Дашборд','orders'=>'Заказы','bookings'=>'Бронирования','tables'=>'Столики','menu'=>'Меню','reviews'=>'Отзывы','gallery'=>'Галерея','promos'=>'Промокоды'];
      echo $titles[$active_section] ?? 'Управление';
      ?>
    </h1>
    <div class="admin-quick">
      <?php if ($counts['orders'] + $counts['bookings'] > 0): ?>
      <span style="font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.12em;font-size:0.74rem;color:var(--ember);">
        <?= $counts['orders'] ?> заказов · <?= $counts['bookings'] ?> броней ожидают
      </span>
      <?php endif; ?>
    </div>
    <a href="logout.php?type=admin" class="btn btn-ghost btn-sm">Выйти</a>
  </header>

  <div class="admin-content">
    <?php if ($flash): ?><div class="success-message admin-flash" data-flash-section="<?= htmlspecialchars($flashSection) ?>" style="margin-bottom:20px;"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="error-message admin-flash" data-flash-section="<?= htmlspecialchars($flashSection) ?>" style="margin-bottom:20px;"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <!-- DASHBOARD -->
    <div id="dashboard" class="admin-section-content <?= $active_section==='dashboard'?'active':'' ?>">

      <!-- Stat cards with sparklines -->
      <div class="stat-grid" style="margin-bottom:24px;">

        <div class="stat-card">
          <div class="row1">
            <span class="lbl">Заказов сегодня</span>
            <span class="ico"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h2l2.5 12h11l2.5-9H6"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg></span>
          </div>
          <div class="val"><?= $dash_orders_today ?></div>
          <?php if ($dash_pending_orders > 0): ?>
          <div class="delta" style="color:var(--ember);">⏳ <?= $dash_pending_orders ?> ожидают</div>
          <?php else: ?>
          <div class="delta up">все обработаны</div>
          <?php endif; ?>
          <svg class="spark" viewBox="0 0 200 40" preserveAspectRatio="none">
            <polyline points="<?= $revSparkPts ?>" fill="none" stroke="#6b8e4e" stroke-width="2"/>
            <polygon points="<?= $revSparkPts ?> 190,40 0,40" fill="#6b8e4e" opacity="0.15"/>
          </svg>
        </div>

        <div class="stat-card">
          <div class="row1">
            <span class="lbl">Выручка сегодня</span>
            <span class="ico"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
          </div>
          <div class="val" style="font-size:1.5rem;"><?= $dash_revenue_today ?> <span style="font-size:1rem;color:var(--ink-mute);">₽</span></div>
          <div class="delta up">без учёта отменённых</div>
          <svg class="spark" viewBox="0 0 200 40" preserveAspectRatio="none">
            <polyline points="<?= $revSparkPts ?>" fill="none" stroke="#b8763a" stroke-width="2"/>
            <polygon points="<?= $revSparkPts ?> 190,40 0,40" fill="#b8763a" opacity="0.15"/>
          </svg>
        </div>

        <div class="stat-card">
          <div class="row1">
            <span class="lbl">Брони на вечер</span>
            <span class="ico"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg></span>
          </div>
          <div class="val"><?= $dash_bookings_evening ?></div>
          <?php if ($dash_pending_bookings > 0): ?>
          <div class="delta" style="color:var(--ember);">⏳ <?= $dash_pending_bookings ?> ждут подтверждения</div>
          <?php else: ?>
          <div class="delta up">от 17:00 · все подтверждены</div>
          <?php endif; ?>
          <svg class="spark" viewBox="0 0 200 40" preserveAspectRatio="none">
            <polyline points="0,24 40,22 80,18 120,14 160,12 190,8" fill="none" stroke="#3d5a2a" stroke-width="2"/>
            <polygon points="0,24 40,22 80,18 120,14 160,12 190,8 190,40 0,40" fill="#3d5a2a" opacity="0.15"/>
          </svg>
        </div>

        <div class="stat-card">
          <div class="row1">
            <span class="lbl">Средний чек</span>
            <span class="ico"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span>
          </div>
          <div class="val" style="font-size:1.5rem;"><?= $dash_avg_check ?: '—' ?> <?php if($dash_avg_check): ?><span style="font-size:1rem;color:var(--ink-mute);">₽</span><?php endif; ?></div>
          <div class="delta">за 30 дней</div>
          <svg class="spark" viewBox="0 0 200 40" preserveAspectRatio="none">
            <polyline points="0,18 40,20 80,16 120,22 160,18 190,20" fill="none" stroke="#b8860b" stroke-width="2"/>
            <polygon points="0,18 40,20 80,16 120,22 160,18 190,20 190,40 0,40" fill="#b8860b" opacity="0.1"/>
          </svg>
        </div>

      </div>

      <!-- Revenue chart + Event timeline -->
      <div class="admin-row" style="margin-bottom:22px;">

        <div class="panel">
          <div class="panel-head">
            <h3>Выручка за 7 дней</h3>
            <div class="actions">
              <span style="font-family:var(--font-display);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.12em;color:var(--ink-mute);">реальные данные</span>
            </div>
          </div>
          <div class="chart">
            <svg viewBox="0 0 700 220" preserveAspectRatio="none">
              <defs>
                <linearGradient id="revGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#b8763a" stop-opacity="0.45"/>
                  <stop offset="100%" stop-color="#b8763a" stop-opacity="0"/>
                </linearGradient>
              </defs>
              <g stroke="var(--line)" stroke-width="1" opacity="0.4">
                <line x1="0" y1="40" x2="700" y2="40"/>
                <line x1="0" y1="90" x2="700" y2="90"/>
                <line x1="0" y1="140" x2="700" y2="140"/>
                <line x1="0" y1="190" x2="700" y2="190"/>
              </g>
              <?php
              $yMax = $chartMax ?: 1;
              $ylabels = [
                round($yMax).'₽',
                round($yMax*0.75).'₽',
                round($yMax*0.5).'₽',
                round($yMax*0.25).'₽',
              ];
              ?>
              <g font-family="Cinzel,serif" font-size="10" fill="#7a5d44">
                <?php foreach ([40,90,140,190] as $ki=>$yy): ?>
                <text x="4" y="<?= $yy-3 ?>"><?= number_format((float)$ylabels[$ki],0,'.',' ') ?></text>
                <?php endforeach; ?>
              </g>
              <?php if ($chartArea): ?>
              <polygon points="<?= htmlspecialchars($chartArea) ?>" fill="url(#revGrad)" opacity="0.8"/>
              <polyline points="<?= htmlspecialchars($chartPts) ?>" fill="none" stroke="#b8763a" stroke-width="2.5"/>
              <?php
              $cN = count($chartValues);
              for($ci=0;$ci<$cN;$ci++){
                $cx = round(40+($ci/($cN-1))*600);
                $cy = round(190-($chartValues[$ci]/$yMax)*170);
                $isLast = $ci === $cN-1;
                echo '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.($isLast?5:3).'" fill="#b8763a"'.($isLast?' stroke="#fff" stroke-width="2"':'').'/>';
              }
              ?>
              <?php endif; ?>
              <g font-family="Cinzel,serif" font-size="11" fill="#7a5d44" text-anchor="middle">
                <?php
                $cN=count($chartLabels);
                for($ci=0;$ci<$cN;$ci++){
                    $cx=round(40+($ci/($cN-1))*600);
                    echo '<text x="'.$cx.'" y="218">'.htmlspecialchars($chartLabels[$ci]).'</text>';
                }
                ?>
              </g>
            </svg>
          </div>
          <div class="legend">
            <span><span class="sw" style="background:#b8763a;"></span>Выручка по дням</span>
            <span style="color:var(--ink-faint);font-size:0.82rem;">исключая отменённые и корзины</span>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <h3>Лента событий</h3>
            <a href="?section=orders" onclick="showSection('orders');return false;" class="btn btn-ghost btn-sm">Все</a>
          </div>
          <div class="timeline">
            <?php foreach ($timelineEvents as $ev):
              $isOrder   = $ev['etype'] === 'order';
              $isNew     = $ev['status'] === 'pending';
              $isCancell = in_array($ev['status'], ['cancelled','rejected']);
              $ico = $isOrder ? '₽' : '+';
              if ($isCancell) $ico = '!';
              $icoColor = $isCancell ? 'var(--berry)' : ($isNew ? 'var(--moss)' : 'var(--amber-deep)');
              $ago = '';
              if (!empty($ev['created_at'])) {
                  $diff = time() - strtotime($ev['created_at']);
                  if ($diff < 60) $ago = $diff.'с';
                  elseif ($diff < 3600) $ago = round($diff/60).'м';
                  elseif ($diff < 86400) $ago = round($diff/3600).'ч';
                  else $ago = round($diff/86400).'д';
              }
            ?>
            <div class="ev">
              <div class="ico" style="color:<?= $icoColor ?>;font-weight:bold;"><?= $ico ?></div>
              <div class="txt">
                <?php if ($isOrder): ?>
                  <strong><a href="admin_order.php?id=<?= $ev['id'] ?>" style="color:inherit;">Заказ #<?= $ev['id'] ?></a></strong>
                  — <?php if($isCancell): ?>отменён<?php elseif($isNew): ?>новый<?php else: ?>статус: <?= order_status_label($ev['status']) ?><?php endif; ?>
                  <?php if($ev['amount'] > 0): ?>, <?= number_format((float)$ev['amount'],0,'.',' ') ?> ₽<?php endif; ?>
                <?php else: ?>
                  <?php if($isCancell): ?>
                    <strong>Отмена брони</strong> — <?= htmlspecialchars(mb_substr($ev['person'],0,20)) ?>
                  <?php elseif($isNew): ?>
                    <strong>Новая бронь</strong> от <?= htmlspecialchars(mb_substr($ev['person'],0,20)) ?>
                  <?php else: ?>
                    <strong>Бронь #<?= $ev['id'] ?></strong> — <?= booking_status_label($ev['status']) ?>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              <div class="when"><?= $ago ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($timelineEvents)): ?>
            <div style="padding:24px;text-align:center;color:var(--ink-faint);font-style:italic;">Событий пока нет</div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- Recent orders + Top dishes -->
      <div class="admin-row" style="grid-template-columns:1.5fr 1fr;">

        <div class="panel">
          <div class="panel-head">
            <h3>Свежие заказы</h3>
            <a href="?section=orders" onclick="showSection('orders');return false;" class="btn btn-ghost btn-sm">Все →</a>
          </div>
          <table class="table">
            <thead><tr><th>№</th><th>Гость</th><th>Когда</th><th>Тип</th><th>Сумма</th><th>Статус</th></tr></thead>
            <tbody>
            <?php if ($recent_orders_res): while ($ro = $recent_orders_res->fetch_assoc()):
              $typeIco = ['delivery'=>'🚲','dine_in'=>'🪑','takeaway'=>'🛍'][$ro['order_type'] ?? ''] ?? '📦';
              $diff = time() - strtotime($ro['created_at'] ?? 'now');
              $agoStr = $diff<60 ? 'только что' : ($diff<3600 ? round($diff/60).' мин' : ($diff<86400 ? round($diff/3600).' ч' : date('d.m',strtotime($ro['created_at']))));
            ?>
            <tr style="cursor:pointer;" onclick="window.location='admin_order.php?id=<?= $ro['id'] ?>'">
              <td class="ord-id"><a href="admin_order.php?id=<?= $ro['id'] ?>">#<?= $ro['id'] ?></a></td>
              <td class="ord-name"><?= htmlspecialchars($ro['full_name'] ?: $ro['username'] ?: '—') ?></td>
              <td class="ord-time"><?= $agoStr ?></td>
              <td><?= $typeIco ?></td>
              <td class="ord-sum"><?= $ro['total'] ? number_format((float)$ro['total'],0,'.',' ').' ₽' : '—' ?></td>
              <td><span class="status <?= ['pending'=>'new','confirmed'=>'ready','preparing'=>'cook','ready'=>'ready','completed'=>'done','cancelled'=>'cancel'][$ro['status']] ?? '' ?>"><?= order_status_label($ro['status']) ?></span></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>

        <div class="panel">
          <div class="panel-head"><h3>Топ блюд недели</h3></div>
          <?php if (!empty($topDishes)): ?>
          <table class="table">
            <thead><tr><th>Блюдо</th><th style="text-align:right;">Продано</th></tr></thead>
            <tbody>
            <?php foreach ($topDishes as $td): ?>
            <tr>
              <td><strong><?= htmlspecialchars($td['item_name']) ?></strong></td>
              <td style="text-align:right;" class="ord-sum"><?= (int)$td['cnt'] ?> шт.</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div style="padding:32px;text-align:center;color:var(--ink-mute);font-style:italic;">Заказов на этой неделе ещё нет</div>
          <?php endif; ?>
        </div>

      </div>

    </div>

    <!-- ORDERS -->
    <div id="orders" class="admin-section-content <?= $active_section==='orders'?'active':'' ?>">
        <h2 style="margin-bottom:20px;">Заказы клиентов</h2>
        <form method="GET" class="admin-filters">
            <input type="hidden" name="section" value="orders">
            <select name="order_status" onchange="this.form.submit()">
                <option value="">Все статусы</option>
                <?php foreach (['pending','confirmed','preparing','ready','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $orderFilter === $s ? 'selected' : '' ?>><?= order_status_label($s) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <table class="admin-table">
            <tr><th>ID</th><th>Дата</th><th>Клиент</th><th>Тип</th><th>Сумма</th><th>Статус</th><th>Действия</th></tr>
            <?php if ($orders): while ($o = $orders->fetch_assoc()): ?>
            <tr>
                <td><a href="admin_order.php?id=<?= $o['id'] ?>">#<?= $o['id'] ?></a></td>
                <td><?= $o['created_at'] ?? '—' ?></td>
                <td><?= htmlspecialchars($o['full_name'] ?: $o['username'] ?: '—') ?></td>
                <td><?= order_type_label($o['order_type']) ?></td>
                <td><?= $o['total'] ?: $o['total_price'] ?></td>
                <td><span class="status-badge status-<?= $o['status'] ?>"><?= order_status_label($o['status']) ?></span></td>
                <td class="action-cell">
                    <a href="admin_order.php?id=<?= $o['id'] ?>">Детали</a>
                    <form method="POST" style="display:inline-flex;gap:5px;align-items:center;margin-left:6px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <select name="new_status" class="status-select" style="padding:5px 8px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;font-size:0.85rem;">
                            <?php foreach (['pending','confirmed','preparing','ready','completed','cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= order_status_label($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="order_status_quick" class="btn-small">Применить</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; endif; ?>
        </table>
    </div>

    <!-- BOOKINGS -->
    <div id="bookings" class="admin-section-content <?= $active_section==='bookings'?'active':'' ?>">
        <h2 style="margin-bottom:20px;">Бронирования</h2>
        <form method="GET" class="admin-filters">
            <input type="hidden" name="section" value="bookings">
            <select name="booking_status">
                <option value="">Все</option>
                <?php foreach (['pending','confirmed','rejected','cancelled','completed'] as $s): ?>
                <option value="<?= $s ?>" <?= $bookingFilter === $s ? 'selected' : '' ?>><?= booking_status_label($s) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="booking_date" value="<?= htmlspecialchars($bookingDateFilter) ?>">
            <button type="submit">Фильтр</button>
        </form>
        <table class="admin-table">
            <tr><th>ID</th><th>Дата</th><th>Время</th><th>Гость</th><th>Гостей</th><th>Стол</th><th>Статус</th><th>Действия</th></tr>
            <?php if ($bookings): while ($b = $bookings->fetch_assoc()): ?>
            <tr>
                <td>#<?= $b['id'] ?></td>
                <td><?= $b['booking_date'] ?></td>
                <td><?= substr($b['booking_time'], 0, 5) ?></td>
                <td><?= htmlspecialchars($b['guest_name']) ?><br><small><?= htmlspecialchars($b['guest_phone']) ?></small></td>
                <td><?= $b['party_size'] ?></td>
                <td><?= htmlspecialchars($b['table_name'] ?? '—') ?></td>
                <td><span class="status-badge status-<?= $b['status'] ?>"><?= booking_status_label($b['status']) ?></span></td>
                <td>
                    <?php if ($b['status'] === 'pending'):
                        $avail = get_available_tables_for_booking($conn, (int)$b['party_size'], $b['booking_date'], $b['booking_time'], (int)$b['id']);
                    ?>
                    <form method="POST" class="inline-booking-form"><?= csrf_field() ?>
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <select name="table_id" required>
                            <option value="">Стол</option>
                            <?php foreach ($avail as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['seats'] ?> мест)</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="admin_comment" placeholder="Комментарий">
                        <button type="submit" name="confirm_booking" class="btn-small">Подтвердить</button>
                    </form>
                    <form method="POST" style="display:inline"><?= csrf_field() ?>
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <input type="hidden" name="booking_status" value="rejected">
                        <button type="submit" name="booking_status_action" class="btn-small btn-danger">Отклонить</button>
                    </form>
                    <?php elseif ($b['status'] === 'confirmed'): ?>
                    <form method="POST" style="display:inline"><?= csrf_field() ?>
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <input type="hidden" name="booking_status" value="completed">
                        <button type="submit" name="booking_status_action" class="btn-small">Завершить</button>
                    </form>
                    <form method="POST" style="display:inline"><?= csrf_field() ?>
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <input type="hidden" name="booking_status" value="cancelled">
                        <button type="submit" name="booking_status_action" class="btn-small btn-danger">Отменить</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($b['notes']): ?><br><small><?= htmlspecialchars($b['notes']) ?></small><?php endif; ?>
                </td>
            </tr>
            <?php endwhile; endif; ?>
        </table>
    </div>

    <!-- TABLES -->
    <div id="tables" class="admin-section-content <?= $active_section==='tables'?'active':'' ?>">
        <h2 style="margin-bottom:20px;">Столики и план зала</h2>
        <form method="POST" class="admin-form"><?= csrf_field() ?>
            <input type="text" name="table_name" placeholder="Название" required>
            <input type="number" name="table_seats" placeholder="Мест" min="1" required>
            <select name="table_zone" style="font:inherit;font-size:0.92rem;padding:9px 12px;border:1px solid var(--line);border-radius:var(--r-sm);background:var(--parch-50);">
                <option value="Зал">Зал</option>
                <option value="Терраса">Терраса</option>
                <option value="VIP-зал">VIP-зал</option>
            </select>
            <button type="submit" name="add_table">Добавить стол</button>
        </form>
        <table class="admin-table">
            <tr><th>ID</th><th>Название</th><th>Мест</th><th>Зона</th><th>Активен</th><th></th></tr>
            <?php if ($tables): while ($t = $tables->fetch_assoc()): ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><?= htmlspecialchars($t['name']) ?></td>
                <td><?= $t['seats'] ?></td>
                <td><?= htmlspecialchars($t['zone']) ?></td>
                <td><?= $t['is_active'] ? 'Да' : 'Нет' ?></td>
                <td style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                    <button class="btn-small" data-edit-table="<?= htmlspecialchars(json_encode([
                      'id'    => (int)$t['id'],
                      'name'  => $t['name'],
                      'seats' => (int)$t['seats'],
                      'zone'  => $t['zone'],
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>" onclick="openEditTableFromData(this)">Изменить</button>
                    <form method="POST" style="display:inline"><?= csrf_field() ?>
                        <input type="hidden" name="table_id" value="<?= $t['id'] ?>">
                        <button type="submit" name="toggle_table" class="btn-small"><?= $t['is_active'] ? 'Выключить' : 'Включить' ?></button>
                    </form>
                    <form method="POST" style="display:inline"><?= csrf_field() ?>
                        <input type="hidden" name="table_id" value="<?= $t['id'] ?>">
                        <button type="submit" name="delete_table" class="btn-small btn-danger" onclick="return confirm('Удалить стол?')">Удалить</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; endif; ?>
        </table>
    </div>

    <!-- MENU -->
    <div id="menu" class="admin-section-content <?= $active_section==='menu'?'active':'' ?>">
        <h2 style="margin-bottom:16px;">Добавить блюдо</h2>
        <form method="POST" class="admin-form" style="flex-direction:column;align-items:stretch;">
            <?= csrf_field() ?>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <input type="text" name="title" placeholder="Название блюда" required style="flex:2;min-width:180px;">
                <select name="category" required style="flex:1;min-width:160px;">
                    <option value="">Категория</option>
                    <option value="Горячие угощения">Горячие угощения</option>
                    <option value="Яства и ломтики">Яства и ломтики</option>
                    <option value="Ласковые лакомства">Ласковые лакомства</option>
                    <option value="Чарующие напитки">Чарующие напитки</option>
                </select>
                <input type="text" name="price" placeholder="Цена" style="flex:0 0 100px;">
                <input type="text" name="weight" placeholder="Вес/объём" style="flex:0 0 110px;">
                <input type="text" name="badge" placeholder="Бейдж (Хит...)" style="flex:0 0 130px;">
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
                <textarea name="description" placeholder="Описание блюда" rows="2" style="flex:3;min-width:200px;resize:vertical;padding:9px 12px;border:1px solid var(--line);border-radius:var(--r-sm);background:var(--parch-50);font:inherit;font-size:0.92rem;"></textarea>
                <input type="hidden" name="image_path" id="addItemImagePath" value="">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;flex:0 0 auto;">
                    <input type="checkbox" name="is_special" value="1"> Спецпредложение
                </label>
                <button type="submit" name="add_item" style="align-self:flex-end;">Добавить</button>
            </div>
        </form>
        <h2 style="margin-bottom:14px;">Меню (<?= $menu_items ? $menu_items->num_rows : 0 ?> позиций)</h2>
        <table class="admin-table">
            <tr><th>ID</th><th>Название</th><th>Категория</th><th>Цена</th><th style="width:120px;"></th></tr>
            <?php if ($menu_items): while ($item = $menu_items->fetch_assoc()):
              $thumbSrc = (!empty($item['image_path']) && file_exists(__DIR__.'/'.$item['image_path'])) ? htmlspecialchars($item['image_path']) : '';
            ?>
            <tr>
                <td style="color:var(--ink-mute);font-size:0.85rem;"><?= $item['id'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <?php if ($thumbSrc): ?>
                          <img src="<?= $thumbSrc ?>" style="width:40px;height:40px;object-fit:cover;border-radius:var(--r-sm);flex-shrink:0;">
                        <?php else: ?>
                          <div style="width:40px;height:40px;background:var(--parch-200);border-radius:var(--r-sm);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1rem;color:var(--ink-faint);">&#128247;</div>
                        <?php endif; ?>
                        <div>
                          <?= htmlspecialchars($item['title']) ?>
                          <?php if (!empty($item['badge'])): ?><br><span class="status-badge status-confirmed" style="margin-top:3px;"><?= htmlspecialchars($item['badge']) ?></span><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><?= htmlspecialchars($item['category']) ?></td>
                <td><?= htmlspecialchars($item['price'] ?? '') ?></td>
                <td>
                  <div style="display:flex;gap:6px;align-items:center;">
                    <button class="btn-small" data-edit-item="<?= htmlspecialchars(json_encode([
                      'id'          => (int)$item['id'],
                      'title'       => $item['title'],
                      'category'    => $item['category'],
                      'price'       => $item['price'] ?? '',
                      'weight'      => $item['weight'] ?? '',
                      'badge'       => $item['badge'] ?? '',
                      'is_special'  => (int)($item['is_special'] ?? 0),
                      'description' => $item['description'] ?? '',
                      'image_path'  => $item['image_path'] ?? '',
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>" onclick="openEditItemFromData(this)">Изменить</button>
                    <a href="?delete_item=<?= $item['id'] ?>&section=menu" class="delete-link" onclick="return confirm('Удалить?')" title="Удалить">&#128465;</a>
                  </div>
                </td>
            </tr>
            <?php endwhile; endif; ?>
        </table>
    </div>

    <!-- REVIEWS -->
    <div id="reviews" class="admin-section-content <?= $active_section==='reviews'?'active':'' ?>">
        <h2 style="margin-bottom:20px;">Отзывы гостей</h2>
        <table class="admin-table">
            <tr><th>ID</th><th>Имя</th><th>Отзыв</th><th>Фото</th><th>Ответ администратора</th><th></th></tr>
            <?php if ($reviews): while ($review = $reviews->fetch_assoc()): ?>
            <tr>
                <td><?= $review['id'] ?></td>
                <td><?= htmlspecialchars($review['name']) ?></td>
                <td><?= htmlspecialchars(substr($review['review'], 0, 120)) ?></td>
                <td>
                    <?php $rvPhoto = $review['photo_path'] ?? ''; if (!empty($rvPhoto) && file_exists(__DIR__.'/'.$rvPhoto)): ?>
                        <a href="<?= htmlspecialchars($rvPhoto) ?>" target="_blank" rel="noopener">
                            <img src="<?= htmlspecialchars($rvPhoto) ?>" alt="Фото" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid var(--line);">
                        </a>
                    <?php else: ?>
                        <span style="color:var(--ink-faint);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="reply-block">
                        <?php if (!empty($review['admin_reply'])): ?>
                        <div class="existing-reply"><?= htmlspecialchars($review['admin_reply']) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                            <textarea name="admin_reply" placeholder="Ответить гостю..."><?= htmlspecialchars($review['admin_reply'] ?? '') ?></textarea>
                            <button type="submit" name="save_reply" class="btn-small" style="margin-top:4px;">Ответить</button>
                        </form>
                    </div>
                </td>
                <td><a href="?delete_review=<?= $review['id'] ?>" class="delete-link" onclick="return confirm('Удалить отзыв?')">&#128465;</a></td>
            </tr>
            <?php endwhile; endif; ?>
        </table>
    </div>

    <!-- PROMOS -->
    <div id="promos" class="admin-section-content <?= $active_section==='promos'?'active':'' ?>">
        <h2 style="margin-bottom:20px;">Промокоды</h2>

        <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;background:var(--parch-100);border:1px solid var(--line);border-radius:var(--r-md);padding:18px 20px;margin-bottom:24px;">
            <?= csrf_field() ?>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.12em;color:var(--ink-mute);">Код</label>
                <input type="text" name="promo_code" required placeholder="SHIRE10" style="padding:9px 12px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;text-transform:uppercase;width:140px;">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.12em;color:var(--ink-mute);">Тип скидки</label>
                <select name="promo_type" style="padding:9px 12px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;">
                    <option value="percent">Процент %</option>
                    <option value="fixed">Сумма ₽</option>
                </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.12em;color:var(--ink-mute);">Размер</label>
                <input type="number" name="promo_value" step="0.01" min="0" required placeholder="10" style="padding:9px 12px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;width:100px;">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.12em;color:var(--ink-mute);">Мин. сумма ₽</label>
                <input type="number" name="promo_min" step="0.01" min="0" value="0" style="padding:9px 12px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;width:120px;">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.12em;color:var(--ink-mute);">Действует до</label>
                <input type="date" name="promo_expires" style="padding:9px 12px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;">
            </div>
            <button type="submit" name="add_promo" class="btn btn-primary">Добавить промокод</button>
        </form>

        <table class="admin-table">
            <tr><th>Код</th><th>Тип</th><th>Размер</th><th>Мин. сумма</th><th>До</th><th>Активен</th><th>Действия</th></tr>
            <?php if ($promos && $promos->num_rows): while ($p = $promos->fetch_assoc()): ?>
            <tr>
                <td colspan="7" style="padding:0;">
                    <form method="POST" style="display:grid;grid-template-columns:1.1fr 1fr 0.8fr 1fr 1.1fr auto auto;gap:8px;align-items:center;padding:10px 16px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
                        <input type="text" name="code" value="<?= htmlspecialchars($p['code']) ?>" style="padding:7px 10px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;text-transform:uppercase;">
                        <select name="discount_type" style="padding:7px 10px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;">
                            <option value="percent" <?= $p['discount_type']==='percent'?'selected':'' ?>>Процент %</option>
                            <option value="fixed" <?= $p['discount_type']==='fixed'?'selected':'' ?>>Сумма ₽</option>
                        </select>
                        <input type="number" name="discount_value" step="0.01" min="0" value="<?= htmlspecialchars(rtrim(rtrim($p['discount_value'],'0'),'.')) ?>" style="padding:7px 10px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;">
                        <input type="number" name="min_order" step="0.01" min="0" value="<?= htmlspecialchars(rtrim(rtrim($p['min_order'],'0'),'.')) ?>" style="padding:7px 10px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;">
                        <input type="date" name="expires_at" value="<?= htmlspecialchars($p['expires_at'] ?? '') ?>" style="padding:7px 10px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;">
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;white-space:nowrap;"><input type="checkbox" name="is_active" <?= (int)$p['is_active']===1?'checked':'' ?>> вкл</label>
                        <span style="display:flex;gap:6px;">
                            <button type="submit" name="update_promo" class="btn-small">Сохранить</button>
                            <button type="submit" name="delete_promo" class="btn-small btn-danger" onclick="return confirm('Удалить промокод?')">&#128465;</button>
                        </span>
                    </form>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="7" style="text-align:center;color:var(--ink-mute);font-style:italic;padding:30px;">Промокодов пока нет — добавьте первый выше</td></tr>
            <?php endif; ?>
        </table>
        <p style="margin-top:14px;color:var(--ink-mute);font-size:0.86rem;">Гости вводят код в корзине при оформлении заказа — скидка применяется к сумме блюд.</p>
    </div>

    <!-- GALLERY -->
    <div id="gallery" class="admin-section-content <?= $active_section==='gallery'?'active':'' ?>">
        <h2 style="margin-bottom:20px;">Управление галереей</h2>

<?php $assignGroups = image_assign_groups($conn); ?>
<script>window.IMG_TARGETS = <?= json_encode($assignGroups, JSON_UNESCAPED_UNICODE) ?>;</script>

<!-- Upload panel -->
<div class="gallery-upload-panel" style="background:var(--parch-100);border:1px solid var(--line);border-radius:var(--r-md);padding:24px;margin-bottom:28px;">
  <h3 style="font-size:1.1rem;margin-bottom:16px;">Добавить изображение</h3>

  <!-- Upload tabs -->
  <div class="gallery-tabs" style="display:flex;gap:4px;margin-bottom:20px;">
    <button type="button" class="btn-small on" id="gtab-upload" onclick="switchGTab('upload')">&#128193; Загрузить</button>
    <button type="button" class="btn-small" id="gtab-url" onclick="switchGTab('url')">&#128279; По ссылке</button>
    <button type="button" class="btn-small" id="gtab-path" onclick="switchGTab('path')">&#128194; Путь к файлу</button>
  </div>

  <!-- Drag & Drop -->
  <div id="gpanel-upload">
    <div id="dropzone" style="border:2px dashed var(--line);border-radius:var(--r-md);padding:40px;text-align:center;cursor:pointer;transition:all 0.2s;background:var(--parch-50);"
         ondragover="event.preventDefault();this.style.borderColor='var(--amber)';this.style.background='rgba(184,118,58,0.06)'"
         ondragleave="this.style.borderColor='';this.style.background='var(--parch-50)'"
         ondrop="handleDrop(event)"
         onclick="document.getElementById('fileInput').click()">
      <div style="font-size:2rem;margin-bottom:8px;">&#128444;&#65039;</div>
      <p style="margin:0;color:var(--ink-mute);">Перетащите файл сюда или <strong style="color:var(--amber-deep);">нажмите для выбора</strong></p>
      <p style="margin:4px 0 0;font-size:0.82rem;color:var(--ink-faint);">JPEG, PNG, GIF, WebP — до 10 МБ</p>
    </div>
    <input type="file" id="fileInput" accept="image/*" style="display:none" onchange="handleFileSelect(this)">
  </div>

  <div id="gpanel-url" style="display:none;">
    <div style="display:flex;gap:10px;">
      <input type="url" id="urlInput" placeholder="https://example.com/image.jpg" style="flex:1;padding:10px 14px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;">
      <button type="button" class="btn btn-ghost" onclick="fetchUrl()">Загрузить</button>
    </div>
  </div>

  <div id="gpanel-path" style="display:none;">
    <form method="POST">
      <?= csrf_field() ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <input type="text" name="image_path" placeholder="uploads/my-photo.jpg или images/h.jpg" style="flex:1;min-width:200px;padding:10px 14px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;">
        <?= render_assign_selects($assignGroups, 'path') ?>
        <button type="submit" name="assign_image" class="btn btn-primary">Сохранить</button>
      </div>
    </form>
  </div>

  <!-- Upload result preview -->
  <div id="uploadPreview" style="display:none;margin-top:16px;padding:16px;background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-sm);">
    <img id="previewImg" src="" style="max-width:200px;max-height:140px;object-fit:cover;border-radius:var(--r-sm);display:block;margin-bottom:12px;">
    <form method="POST" id="saveUploadForm">
      <?= csrf_field() ?>
      <input type="hidden" name="image_path" id="uploadedPath">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <?= render_assign_selects($assignGroups, 'up') ?>
        <button type="submit" name="assign_image" class="btn btn-primary btn-sm">Сохранить</button>
      </div>
    </form>
  </div>
</div>

<!-- Именованные слоты сайта -->
<div style="background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);padding:18px 20px;margin-bottom:24px;">
  <h3 style="font-size:1rem;margin:0 0 6px;">Слоты сайта (главная, команда и галерея)</h3>
  <p style="margin:0 0 14px;color:var(--ink-mute);font-size:0.85rem;">Загрузите фото в панели выше, в первом списке выберите тип (Команда / Галерея / Главная), во втором — конкретное место, и нажмите «Сохранить» — фото сразу появится на сайте. Размеры указаны рекомендованные. Фото блюд назначаются так же: тип «Блюдо» → выбор блюда.</p>
  <?php
    $slotMap = [];
    $smRes = $conn->query("SELECT slot_key, image_path FROM gallery_images WHERE slot_key <> ''");
    if ($smRes) while ($smRow = $smRes->fetch_assoc()) $slotMap[$smRow['slot_key']] = $smRow['image_path'];
  ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;">
    <?php foreach (site_image_slots() as $key => $info):
      $sp = $slotMap[$key] ?? '';
      $hasFile = $sp !== '' && file_exists(__DIR__.'/'.$sp);
    ?>
    <div style="border:1px solid var(--line);border-radius:var(--r-sm);overflow:hidden;background:var(--parch-100);">
      <div style="height:96px;background:var(--parch-200);display:flex;align-items:center;justify-content:center;">
        <?php if ($hasFile): ?>
          <img src="<?= htmlspecialchars($sp) ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <span style="color:var(--ink-faint);font-size:0.78rem;">нет фото</span>
        <?php endif; ?>
      </div>
      <div style="padding:8px 10px;">
        <div style="font-size:0.8rem;font-weight:600;line-height:1.25;"><?= htmlspecialchars($info[2]) ?></div>
        <div style="font-family:'JetBrains Mono',monospace;font-size:0.68rem;color:var(--amber-deep);margin-top:2px;"><?= htmlspecialchars($key) ?></div>
        <div style="font-size:0.7rem;color:var(--ink-mute);margin-top:2px;"><?= htmlspecialchars($info[0].' · '.$info[1]) ?> · <?= $hasFile ? '<span style="color:var(--forest)">✓ задано</span>' : '— пусто' ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Gallery grid -->
<?php
// Reload gallery_images with all columns
$allGalleryImages = [];
$gAllRes = $conn->query("SELECT * FROM gallery_images ORDER BY sort_order ASC, id ASC");
if ($gAllRes) while ($gRow = $gAllRes->fetch_assoc()) $allGalleryImages[] = $gRow;
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
  <div style="font-family:var(--font-display);font-size:0.78rem;text-transform:uppercase;letter-spacing:0.14em;color:var(--ink-mute);">
    <?= count($allGalleryImages) ?> изображений
  </div>
  <div style="display:flex;gap:8px;">
    <button class="btn-small" onclick="filterGallery('all')">Все</button>
    <button class="btn-small" onclick="filterGallery('gallery')">Галерея</button>
    <button class="btn-small" onclick="filterGallery('dish')">Блюда</button>
    <button class="btn-small" onclick="filterGallery('team')">Команда</button>
  </div>
</div>

<div id="galleryGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;" data-sortable="true">
<?php foreach ($allGalleryImages as $gi):
  $imgSrc = (!empty($gi['image_path']) && file_exists(__DIR__.'/'.$gi['image_path'])) ? htmlspecialchars($gi['image_path']) : '';
  $catLabel = ['gallery'=>'Галерея','dish'=>'Блюдо','team'=>'Команда'][$gi['category'] ?? 'gallery'] ?? 'Галерея';
  $catColor = ['gallery'=>'var(--moss)','dish'=>'var(--amber-deep)','team'=>'var(--berry)'][$gi['category'] ?? 'gallery'] ?? 'var(--moss)';
?>
<div class="gallery-card" data-category="<?= htmlspecialchars($gi['category'] ?? 'gallery') ?>" data-id="<?= $gi['id'] ?>"
     style="background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);overflow:hidden;box-shadow:var(--shadow-soft);">
  <div style="aspect-ratio:4/3;background:var(--parch-200);overflow:hidden;position:relative;cursor:grab;">
    <?php if ($imgSrc): ?>
      <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($gi['label'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;">
    <?php elseif (!empty($gi['image_path'])): ?>
      <div style="padding:12px;font-size:0.72rem;color:var(--ink-mute);word-break:break-all;"><?= htmlspecialchars($gi['image_path']) ?></div>
    <?php else: ?>
      <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--ink-faint);font-size:1.5rem;">&#128247;</div>
    <?php endif; ?>
    <span style="position:absolute;top:8px;left:8px;background:<?= $catColor ?>;color:white;font-family:var(--font-display);font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em;padding:3px 7px;border-radius:10px;"><?= $catLabel ?></span>
    <div style="position:absolute;top:8px;right:8px;cursor:grab;color:white;opacity:0.8;font-size:1.1rem;" title="Перетащить">&#10783;</div>
  </div>
  <div style="padding:10px 12px;">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="gallery_id" value="<?= $gi['id'] ?>">
      <input type="text" name="label" value="<?= htmlspecialchars($gi['label'] ?? '') ?>" placeholder="Подпись" style="width:100%;box-sizing:border-box;padding:5px 8px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;font-size:0.82rem;" title="Нажми Enter чтобы сохранить">
      <div style="display:flex;gap:6px;margin-top:6px;">
        <button type="submit" name="update_gallery_label" title="Сохранить подпись" class="btn-small" style="flex:1;">&#10003; Сохранить</button>
        <button type="submit" name="delete_gallery_image" class="btn-small btn-danger" onclick="return confirm('Удалить?')" title="Удалить" style="flex:0 0 auto;">&#128465;</button>
      </div>
    </form>
    <div style="margin-top:6px;font-size:0.72rem;color:var(--ink-faint);word-break:break-all;"><?= htmlspecialchars($gi['image_path'] ?? '') ?></div>
  </div>
</div>
<?php endforeach; ?>
<?php if (empty($allGalleryImages)): ?>
<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--ink-mute);font-style:italic;">Изображений нет — добавьте первое выше</div>
<?php endif; ?>
</div>

<script>
function switchGTab(tab) {
  ['upload','url','path'].forEach(t => {
    document.getElementById('gpanel-'+t).style.display = t===tab?'block':'none';
    document.getElementById('gtab-'+t).classList.toggle('on', t===tab);
  });
}
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('dropzone').style.borderColor='';
  document.getElementById('dropzone').style.background='var(--parch-50)';
  const file = e.dataTransfer.files[0];
  // Не фильтруем по file.type (он бывает пустым у файлов из проводника) — проверит сервер
  if (file) uploadFile(file);
}
function handleFileSelect(input) {
  const file = input.files[0];
  if (file) uploadFile(file);
}
// Аккуратно разбираем ответ: сервер мог вернуть не-JSON (например, при фатальной ошибке PHP)
function parseUploadResponse(r) {
  return r.text().then(function(text){
    try { return JSON.parse(text); }
    catch (e) { return { error: 'Сервер вернул некорректный ответ' + (r.status !== 200 ? ' (код ' + r.status + ')' : '') + '. Возможно, файл слишком большой.' }; }
  });
}
function uploadFile(file) {
  const fd = new FormData();
  fd.append('file', file);
  fd.append('action', 'upload');
  fetch('includes/admin_upload.php', {method:'POST',body:fd})
    .then(parseUploadResponse).then(data=>{
      if (data.ok) showUploadPreview(data.path);
      else alert(data.error || 'Ошибка загрузки');
    }).catch(()=>alert('Ошибка соединения с сервером'));
}
function fetchUrl() {
  const url = document.getElementById('urlInput').value.trim();
  if (!url) return;
  const fd = new FormData(); fd.append('action','fetch_url'); fd.append('url',url);
  fetch('includes/admin_upload.php',{method:'POST',body:fd})
    .then(parseUploadResponse).then(data=>{
      if (data.ok) { showUploadPreview(data.path); document.getElementById('urlInput').value=''; }
      else alert(data.error||'Не удалось загрузить');
    }).catch(()=>alert('Ошибка соединения с сервером'));
}
function showUploadPreview(path) {
  document.getElementById('uploadedPath').value = path;
  document.getElementById('previewImg').src = path + '?t=' + Date.now();
  document.getElementById('uploadPreview').style.display = 'block';
  var t = document.getElementById('up_type');
  if (t) t.focus();
}
// Каскад: второй список заполняется в зависимости от выбранного типа в первом
function populateTargets(prefix) {
  var typeSel = document.getElementById(prefix + '_type');
  var tgtSel  = document.getElementById(prefix + '_target');
  if (!typeSel || !tgtSel) return;
  var list = (window.IMG_TARGETS && window.IMG_TARGETS[typeSel.value]) || [];
  tgtSel.innerHTML = '';
  list.forEach(function (pair) {
    var o = document.createElement('option');
    o.value = pair[0];
    o.textContent = pair[1];
    tgtSel.appendChild(o);
  });
}
document.addEventListener('DOMContentLoaded', function () {
  ['up', 'path'].forEach(function (p) {
    if (document.getElementById(p + '_type')) populateTargets(p);
  });
});
function filterGallery(cat) {
  document.querySelectorAll('.gallery-card').forEach(c => {
    c.style.display = (cat==='all' || c.dataset.category===cat) ? '' : 'none';
  });
}
</script>
    </div>

  </div>
</div>

<!-- Edit Menu Item Modal -->
<div class="modal-overlay" id="editItemModal">
  <div class="modal-box" style="max-width:660px;">
    <button class="modal-close" onclick="closeModal('editItemModal')">&times;</button>
    <h3>Редактировать блюдо</h3>
    <form method="POST" id="editItemForm">
      <?= csrf_field() ?>
      <input type="hidden" name="item_id"    id="eim_id">
      <input type="hidden" name="image_path" id="eim_image_path" value="">
      <div class="fi-grid">
        <div class="span2">
          <label class="fl">Название</label>
          <input class="fi" type="text" name="title" id="eim_title" required>
        </div>
        <div>
          <label class="fl">Категория</label>
          <select class="fi" name="category" id="eim_category">
            <option value="Горячие угощения">Горячие угощения</option>
            <option value="Яства и ломтики">Яства и ломтики</option>
            <option value="Ласковые лакомства">Ласковые лакомства</option>
            <option value="Чарующие напитки">Чарующие напитки</option>
          </select>
        </div>
        <div>
          <label class="fl">Цена (₽)</label>
          <input class="fi" type="text" name="price" id="eim_price" placeholder="490">
        </div>
        <div>
          <label class="fl">Вес / объём</label>
          <input class="fi" type="text" name="weight" id="eim_weight" placeholder="350 г">
        </div>
        <div>
          <label class="fl">Бейдж</label>
          <input class="fi" type="text" name="badge" id="eim_badge" placeholder="Хит · Новинка">
        </div>
        <div class="span2">
          <label class="fl">Описание</label>
          <textarea class="fi" name="description" id="eim_desc" rows="2" style="resize:vertical;"></textarea>
        </div>
        <div class="span2" style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="is_special" id="eim_special" style="width:16px;height:16px;accent-color:var(--amber);cursor:pointer;">
          <label for="eim_special" style="cursor:pointer;font-size:0.92rem;">Блюдо дня / спецпредложение</label>
        </div>
      </div>

      <!-- Image picker (single) -->
      <div style="border-top:1px solid var(--line);margin-top:16px;padding-top:14px;">
        <label class="fl" style="margin-bottom:8px;">Фото блюда</label>
        <div id="eim_sel_imgs" class="sel-imgs">
          <span id="eim_no_imgs" style="font-size:0.82rem;color:var(--ink-faint);font-style:italic;align-self:center;">Фото не выбрано — нажмите «Выбрать из галереи»</span>
        </div>
        <button type="button" class="btn-small" onclick="toggleDishPicker()" id="eim_picker_btn">+ Выбрать из галереи</button>
        <div id="eim_picker" class="dish-picker-grid" style="display:none;margin-top:8px;"></div>
        <p style="font-size:0.75rem;color:var(--ink-faint);margin:6px 0 0;">Нажмите на миниатюру, чтобы выбрать фото для блюда.</p>
      </div>

      <div style="display:flex;gap:12px;margin-top:20px;">
        <button type="submit" name="update_item" class="btn btn-primary">Сохранить</button>
        <button type="button" onclick="closeModal('editItemModal')" class="btn btn-ghost">Отмена</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Table Modal -->
<div class="modal-overlay" id="editTableModal">
  <div class="modal-box" style="max-width:420px;">
    <button class="modal-close" onclick="closeModal('editTableModal')">&times;</button>
    <h3>Редактировать стол</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="table_id" id="etm_id">
      <div style="display:grid;gap:14px;">
        <div>
          <label class="fl">Название</label>
          <input class="fi" type="text" name="table_name" id="etm_name" required>
        </div>
        <div>
          <label class="fl">Количество мест</label>
          <input class="fi" type="number" name="table_seats" id="etm_seats" min="1" max="50" required>
        </div>
        <div>
          <label class="fl">Зона</label>
          <select class="fi" name="table_zone" id="etm_zone">
            <option value="Зал">Зал</option>
            <option value="Терраса">Терраса</option>
            <option value="VIP-зал">VIP-зал</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:12px;margin-top:20px;">
        <button type="submit" name="update_table" class="btn btn-primary">Сохранить</button>
        <button type="button" onclick="closeModal('editTableModal')" class="btn btn-ghost">Отмена</button>
      </div>
    </form>
  </div>
</div>

<script>
// ---- Dish gallery images (PHP → JS) ----
var dishGallery = <?= json_encode($dish_gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
var eimSelected = ''; // currently selected image path (single)

// ---- Section switching ----
var sectionTitles = {dashboard:'Дашборд',orders:'Заказы',bookings:'Бронирования',tables:'Столики',menu:'Меню',reviews:'Отзывы',gallery:'Галерея',promos:'Промокоды'};
function showSection(name) {
  document.querySelectorAll('.admin-section-content').forEach(s => s.classList.remove('active'));
  var el = document.getElementById(name);
  if (el) el.classList.add('active');
  document.querySelectorAll('.admin-nav a').forEach(function(a){
    a.classList.toggle('active', a.getAttribute('href') === '?section='+name);
  });
  document.getElementById('pageTitle').textContent = sectionTitles[name] || name;
  // Сообщение о статусе показываем только в своём разделе
  document.querySelectorAll('.admin-flash').forEach(function(f){
    f.style.display = (f.getAttribute('data-flash-section') === name) ? '' : 'none';
  });
  history.replaceState(null,'','?section='+name);
}
var hash = window.location.hash.replace('#','');
var validSections = ['dashboard','orders','bookings','tables','menu','reviews','gallery','promos'];
if (hash && validSections.indexOf(hash) !== -1) showSection(hash);

// ---- Menu item modal ----
function openEditItemFromData(btn) {
  var d = JSON.parse(btn.dataset.editItem);
  document.getElementById('eim_id').value    = d.id;
  document.getElementById('eim_title').value = d.title;
  document.getElementById('eim_category').value = d.category;
  document.getElementById('eim_price').value  = d.price;
  document.getElementById('eim_weight').value = d.weight;
  document.getElementById('eim_badge').value  = d.badge;
  document.getElementById('eim_special').checked = d.is_special == 1;
  document.getElementById('eim_desc').value   = d.description;
  // Single image
  eimSelected = d.image_path || '';
  eimRenderSelected();
  eimRenderPicker();
  document.getElementById('eim_picker').style.display = 'none';
  document.getElementById('eim_picker_btn').textContent = '+ Выбрать из галереи';
  document.getElementById('editItemModal').classList.add('open');
}

function eimRenderSelected() {
  var container = document.getElementById('eim_sel_imgs');
  container.querySelectorAll('.sel-img-card').forEach(e => e.remove());
  document.getElementById('eim_no_imgs').style.display = eimSelected ? 'none' : '';
  if (eimSelected) {
    var card = document.createElement('div');
    card.className = 'sel-img-card';
    card.innerHTML = '<img src="' + _esc(eimSelected) + '" alt="">'
      + '<button type="button" class="si-del" onclick="eimRemove()" title="Убрать">&times;</button>';
    container.appendChild(card);
  }
  document.getElementById('eim_image_path').value = eimSelected;
}

function eimRenderPicker() {
  var grid = document.getElementById('eim_picker');
  grid.innerHTML = '';
  if (dishGallery.length === 0) {
    grid.innerHTML = '<p style="color:var(--ink-mute);font-style:italic;font-size:0.85rem;padding:16px;text-align:center;">Загрузите фото в раздел «Галерея» — они появятся здесь.</p>';
    return;
  }
  dishGallery.forEach(function(img) {
    if (!img.image_path) return;
    var isPicked = eimSelected === img.image_path;
    var div = document.createElement('div');
    div.className = 'dish-picker-thumb' + (isPicked ? ' picked' : '');
    div.title = img.label || img.image_path;
    div.innerHTML = '<img src="' + _esc(img.image_path) + '" alt="">'
      + '<div class="pt-label">' + _esc(img.label || img.image_path.split('/').pop()) + '</div>'
      + (isPicked ? '<span class="pt-num">&#10003;</span>' : '');
    div.onclick = function() {
      eimSelected = (eimSelected === img.image_path) ? '' : img.image_path;
      eimRenderSelected();
      eimRenderPicker();
    };
    grid.appendChild(div);
  });
}

function eimRemove() {
  eimSelected = '';
  eimRenderSelected();
  eimRenderPicker();
}

function toggleDishPicker() {
  var p = document.getElementById('eim_picker');
  var open = p.style.display === 'none';
  p.style.display = open ? '' : 'none';
  document.getElementById('eim_picker_btn').textContent = open ? '▲ Скрыть галерею' : '+ Выбрать из галереи';
  if (open) eimRenderPicker();
}

// ---- Table modal ----
function openEditTableFromData(btn) {
  var d = JSON.parse(btn.dataset.editTable);
  document.getElementById('etm_id').value    = d.id;
  document.getElementById('etm_name').value  = d.name;
  document.getElementById('etm_seats').value = d.seats;
  document.getElementById('etm_zone').value  = d.zone;
  document.getElementById('editTableModal').classList.add('open');
}

// ---- Shared ----
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(function(m){ m.classList.remove('open'); });
});
function _esc(s) {
  if (!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
