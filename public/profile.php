<?php
session_start();
include 'db.php';
require_once 'includes/auth_user.php';
require_once 'includes/order_helpers.php';
require_once 'includes/booking_helpers.php';
require_once 'includes/notification_helpers.php';
require_once 'includes/phone.php';

require_user_login('profile');
$user_id = (int)$_SESSION['user_id'];

// Обработка уведомлений
if (isset($_GET['read_notif'])) {
    mark_notification_read($conn, (int)$_GET['read_notif'], $user_id);
    header('Location: profile.php'); exit;
}
if (isset($_GET['read_all_notif'])) {
    mark_all_notifications_read($conn, $user_id);
    header('Location: profile.php'); exit;
}

// Обновление профиля
$success_msg = '';
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    [$phone, $phoneOk] = normalize_phone($_POST['phone'] ?? '');
    if (!$phoneOk) {
        $error_msg = 'Телефон должен быть в формате +7 и 10 цифр';
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=? WHERE id=?");
        $stmt->bind_param('ssi', $full_name, $phone, $user_id);
        $stmt->execute(); $stmt->close();
        $success_msg = 'Профиль обновлён';
    }
}

// Загрузка данных
$stmt = $conn->prepare("SELECT username, email, full_name, phone, created_at FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();

$user_orders = [];
$stmt = $conn->prepare("SELECT id, order_type, status, COALESCE(total,total_price,0) AS total, created_at FROM orders WHERE user_id = ? AND status != 'cart' ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param('i', $user_id); $stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $user_orders[] = $row;
$stmt->close();

$user_bookings = [];
$stmt = $conn->prepare("SELECT b.id, b.booking_date, b.booking_time, b.party_size, b.status, b.notes, b.admin_comment, rt.name AS table_name FROM bookings b LEFT JOIN restaurant_tables rt ON b.table_id = rt.id WHERE b.user_id = ? ORDER BY b.booking_date DESC LIMIT 20");
$stmt->bind_param('i', $user_id); $stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $user_bookings[] = $row;
$stmt->close();

$notifications = get_user_notifications($conn, $user_id);
$unread_count = count(array_filter($notifications, fn($n) => !$n['is_read']));

$initials = mb_strtoupper(mb_substr($user['full_name'] ?: $user['username'], 0, 1));
$initials2 = '';
if ($user['full_name']) {
    $parts = explode(' ', $user['full_name']);
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
    $initials2 = count($parts) > 1 ? mb_strtoupper(mb_substr($parts[1], 0, 1)) : '';
}

$section = $_GET['tab'] ?? 'overview';

$months_ru = ['January'=>'января','February'=>'февраля','March'=>'марта','April'=>'апреля','May'=>'мая','June'=>'июня','July'=>'июля','August'=>'августа','September'=>'сентября','October'=>'октября','November'=>'ноября','December'=>'декабря'];
function ru_date2($dt) { global $months_ru; foreach ($months_ru as $en=>$ru) $dt=str_replace($en,$ru,$dt); return $dt; }

$booking_status_labels = ['pending'=>'Ожидает','confirmed'=>'Подтверждена','rejected'=>'Отклонена','cancelled'=>'Отменена','completed'=>'Завершена'];
$order_status_labels = ['pending'=>'Ожидает','confirmed'=>'Подтверждён','preparing'=>'Готовится','ready'=>'Готов','completed'=>'Выполнен','cancelled'=>'Отменён'];
$pill_class = ['pending'=>'pending','confirmed'=>'confirmed','rejected'=>'cancelled','cancelled'=>'cancelled','completed'=>'past','preparing'=>'confirmed','ready'=>'confirmed'];
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Мой уголок · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/account.css">
</head>
<body>

<!-- Шапка -->
<header class="site-header">
  <div class="bar">
    <a href="index.php" class="brand"><img src="assets/brand-mark.svg" alt="" class="brand-mark"><span>Ширский уголок</span></a>
    <button class="burger" id="burger" aria-label="Меню" onclick="document.getElementById('mainNav').classList.toggle('open');this.classList.toggle('open');">
      <span></span><span></span><span></span>
    </button>
    <nav class="nav" id="mainNav">
      <a href="index.php">Главная</a>
      <a href="menu.php">Меню</a>
      <a href="booking.php">Бронь стола</a>
      <a href="gallery.php">Атмосфера</a>
      <a href="reviews.php">Отзывы</a>
      <a href="contacts.php">Контакты</a>
      <span class="nav-divider"></span>
      <a href="profile.php" class="nav-profile-btn active">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="none"><path d="M12 3c-1.7 0-3 1.3-3 3s1.3 3 3 3 3-1.3 3-3-1.3-3-3-3zM12 12c-5.3 0-8 2.7-8 4v1h16v-1c0-1.3-2.7-4-8-4z"/></svg>
        Мой уголок
      </a>
    </nav>
  </div>
</header>
<script>
document.addEventListener('click', function(e) {
  var nav = document.getElementById('mainNav'), btn = document.getElementById('burger');
  if (nav && btn && !nav.contains(e.target) && !btn.contains(e.target)) {
    nav.classList.remove('open'); btn.classList.remove('open');
  }
});
</script>

<!-- Лента приветствия -->
<div class="acc-ribbon">
  <div class="inner">
    <div>
      <div class="crumbs"><a href="index.php">Главная</a><span class="sep">/</span><span>Личный кабинет</span></div>
      <h1>Мой уголок <span class="greet-hand">с возвращением, <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>!</span></h1>
    </div>
    <div class="ribbon-aside">
      <a href="booking.php" class="btn btn-ghost btn-sm">Забронировать стол</a>
      <a href="menu.php" class="btn btn-primary btn-sm">К меню</a>
    </div>
  </div>
</div>

<!-- Сетка кабинета -->
<div class="acc-shell">
  <!-- Сайдбар -->
  <aside class="acc-side">
    <div class="who">
      <div class="acc-avatar"><?= htmlspecialchars($initials.$initials2) ?></div>
      <div class="name"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div>
      <div class="acc-tier">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M12 2 15 8l7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg>
        Гость таверны
      </div>
    </div>
    <ul class="acc-nav">
      <li><a href="?tab=overview" class="<?= $section==='overview'?'active':'' ?>">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>Обзор
      </a></li>
      <li><a href="?tab=bookings" class="<?= $section==='bookings'?'active':'' ?>">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Мои брони
        <?php if (count($user_bookings) > 0): ?><span class="count"><?= count($user_bookings) ?></span><?php endif; ?>
      </a></li>
      <li><a href="?tab=orders" class="<?= $section==='orders'?'active':'' ?>">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h2l2.5 12h11l2.5-9H6"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>История заказов
        <?php if (count($user_orders) > 0): ?><span class="count"><?= count($user_orders) ?></span><?php endif; ?>
      </a></li>
      <li><a href="?tab=notifications" class="<?= $section==='notifications'?'active':'' ?>">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>Уведомления
        <?php if ($unread_count > 0): ?><span class="count"><?= $unread_count ?></span><?php endif; ?>
      </a></li>
      <li><a href="?tab=profile" class="<?= $section==='profile'?'active':'' ?>">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>Профиль
      </a></li>
    </ul>
    <div class="side-foot">
      <a href="logout.php?type=user" class="acc-logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></svg>
        Выйти
      </a>
    </div>
  </aside>

  <!-- Контент -->
  <main class="acc-main">
    <?php if (!empty($success_msg)): ?><div class="success-message" style="margin-bottom:20px;"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if (!empty($error_msg)): ?><div class="error-message" style="margin-bottom:20px;"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <?php if ($section === 'overview'): ?>
    <!-- Метрики -->
    <div class="acc-stats">
      <div class="acc-stat">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div class="val"><?= count($user_bookings) ?></div>
        <div class="lbl">Бронирований</div>
      </div>
      <div class="acc-stat">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h2l2.5 12h11l2.5-9H6"/></svg></div>
        <div class="val"><?= count($user_orders) ?></div>
        <div class="lbl">Заказов</div>
      </div>
      <div class="acc-stat">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/></svg></div>
        <div class="val"><?= $unread_count ?></div>
        <div class="lbl">Новых уведомлений</div>
      </div>
      <div class="acc-stat">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5h3.5a1.5 1.5 0 0 1 0 3H10a1.5 1.5 0 0 0 0 3h4"/></svg></div>
        <div class="val">200</div>
        <div class="lbl">Медовых монет</div>
      </div>
    </div>

    <div class="acc-cols">
      <div>
        <!-- Последние брони -->
        <div class="acc-panel">
          <div class="acc-panel-head"><h3>Последние брони</h3><a href="?tab=bookings" class="link">Все брони →</a></div>
          <?php if (empty($user_bookings)): ?>
          <div class="acc-empty" style="padding:30px;"><p>Нет броней</p><a href="booking.php" class="btn btn-primary btn-sm">Забронировать стол</a></div>
          <?php else: ?>
          <?php foreach (array_slice($user_bookings, 0, 3) as $b):
            $dt = new DateTime($b['booking_date']);
          ?>
          <div class="booking-item">
            <div class="book-date"><span class="d"><?= $dt->format('d') ?></span><span class="m"><?= ru_date2($dt->format('M')) ?></span></div>
            <div class="book-info">
              <div class="t"><?= htmlspecialchars($b['table_name'] ?? 'Стол') ?></div>
              <div class="sub"><span><?= substr($b['booking_time'],0,5) ?></span><span><?= $b['party_size'] ?> гостей</span></div>
            </div>
            <div class="book-act"><span class="pill <?= $pill_class[$b['status']] ?? 'pending' ?>"><?= $booking_status_labels[$b['status']] ?? $b['status'] ?></span></div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Последние заказы -->
        <div class="acc-panel">
          <div class="acc-panel-head"><h3>История заказов</h3><a href="?tab=orders" class="link">Все →</a></div>
          <?php if (empty($user_orders)): ?>
          <div class="acc-empty" style="padding:30px;"><p>Нет заказов</p><a href="menu.php" class="btn btn-primary btn-sm">К меню</a></div>
          <?php else: ?>
          <table class="acc-table">
            <thead><tr><th>Заказ</th><th>Дата</th><th>Статус</th><th>Сумма</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($user_orders, 0, 5) as $o): ?>
            <tr>
              <td class="ord-id">#<?= $o['id'] ?></td>
              <td><?= date('d.m.Y', strtotime($o['created_at'])) ?></td>
              <td><span class="pill <?= $pill_class[$o['status']] ?? 'pending' ?>"><?= $order_status_labels[$o['status']] ?? $o['status'] ?></span></td>
              <td class="ord-sum"><?= number_format((float)$o['total'], 0, '.', ' ') ?> ₽</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- Правая колонка -->
      <div>
        <!-- Медовые монеты -->
        <div class="honey-card" style="margin-bottom:22px;">
          <div class="label">Медовые монеты</div>
          <div class="honey-balance">
            <div class="honey-coin"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2 15 8l7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg></div>
            <div class="amt">200<small>монет</small></div>
          </div>
          <p class="note">Приветственные монеты за регистрацию</p>
          <div class="honey-progress"><span style="width:5%"></span></div>
          <div class="next"><span>До «Завсегдатая»</span><span>800 монет</span></div>
        </div>

        <!-- Уведомления превью -->
        <?php if ($unread_count > 0): ?>
        <div class="acc-panel">
          <div class="acc-panel-head"><h3>Уведомления <span class="count"><?= $unread_count ?></span></h3><a href="?tab=notifications" class="link">Все →</a></div>
          <?php foreach (array_filter($notifications, fn($n)=>!$n['is_read']) as $n): ?>
          <div style="padding:14px 22px;border-bottom:1px solid var(--line);font-size:0.92rem;">
            <div style="color:var(--ink);"><?= htmlspecialchars($n['message']) ?></div>
            <div style="font-size:0.8rem;color:var(--ink-mute);margin-top:4px;"><?= date('d.m.Y H:i', strtotime($n['created_at'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php elseif ($section === 'bookings'): ?>
    <div class="acc-section-title"><h2>Мои брони</h2><a href="booking.php" class="btn btn-primary btn-sm">Новая бронь</a></div>
    <?php if (empty($user_bookings)): ?>
    <div class="acc-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg><p>У тебя пока нет броней</p><a href="booking.php" class="btn btn-primary btn-sm">Забронировать стол</a></div>
    <?php else: ?>
    <div class="acc-panel">
      <?php foreach ($user_bookings as $b):
        $dt = new DateTime($b['booking_date']);
      ?>
      <div class="booking-item">
        <div class="book-date"><span class="d"><?= $dt->format('d') ?></span><span class="m"><?= ru_date2($dt->format('M')) ?></span></div>
        <div class="book-info">
          <div class="t"><?= htmlspecialchars($b['table_name'] ?? 'Стол без номера') ?></div>
          <div class="sub">
            <span><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= substr($b['booking_time'],0,5) ?></span>
            <span><?= $b['party_size'] ?> гостей</span>
            <?php if ($b['notes']): ?><span><?= htmlspecialchars(mb_substr($b['notes'],0,40)) ?></span><?php endif; ?>
          </div>
          <?php if ($b['admin_comment']): ?><div style="font-size:0.85rem;color:var(--forest);margin-top:6px;font-style:italic;">Комментарий: <?= htmlspecialchars($b['admin_comment']) ?></div><?php endif; ?>
        </div>
        <div class="book-act"><span class="pill <?= $pill_class[$b['status']] ?? 'pending' ?>"><?= $booking_status_labels[$b['status']] ?? $b['status'] ?></span></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php elseif ($section === 'orders'): ?>
    <div class="acc-section-title"><h2>История заказов</h2></div>
    <?php if (empty($user_orders)): ?>
    <div class="acc-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h2l2.5 12h11l2.5-9H6"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg><p>Заказов пока нет</p><a href="menu.php" class="btn btn-primary btn-sm">Открыть меню</a></div>
    <?php else: ?>
    <div class="acc-panel">
      <table class="acc-table">
        <thead><tr><th>№</th><th>Дата</th><th>Тип</th><th>Статус</th><th>Сумма</th></tr></thead>
        <tbody>
        <?php foreach ($user_orders as $o):
          $type_label = ['dine_in'=>'В зале','delivery'=>'Доставка','takeaway'=>'С собой'][$o['order_type']] ?? '—';
        ?>
        <tr>
          <td class="ord-id">#<?= $o['id'] ?></td>
          <td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
          <td><?= $type_label ?></td>
          <td><span class="pill <?= $pill_class[$o['status']] ?? 'pending' ?>"><?= $order_status_labels[$o['status']] ?? $o['status'] ?></span></td>
          <td class="ord-sum"><?= number_format((float)$o['total'], 0, '.', ' ') ?> ₽</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php elseif ($section === 'notifications'): ?>
    <div class="acc-section-title"><h2>Уведомления</h2><?php if ($unread_count > 0): ?><a href="?tab=notifications&read_all_notif=1" style="font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.12em;font-size:0.72rem;color:var(--amber-deep);border:none;">Прочитать все</a><?php endif; ?></div>
    <?php if (empty($notifications)): ?>
    <div class="acc-empty"><p>Уведомлений нет</p></div>
    <?php else: ?>
    <div class="acc-panel">
      <?php foreach ($notifications as $n): ?>
      <div class="notification-item <?= !$n['is_read'] ? 'unread' : '' ?>" style="padding:16px 22px;border-bottom:1px solid var(--line);">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
          <div>
            <div style="color:var(--ink);margin-bottom:4px;"><?= htmlspecialchars($n['message']) ?></div>
            <div style="font-size:0.8rem;color:var(--ink-mute);"><?= date('d.m.Y H:i', strtotime($n['created_at'])) ?></div>
          </div>
          <?php if (!$n['is_read']): ?><a href="?tab=notifications&read_notif=<?= $n['id'] ?>" style="font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.12em;font-size:0.68rem;color:var(--amber-deep);border:none;white-space:nowrap;">Прочитано</a><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php elseif ($section === 'profile'): ?>
    <div class="acc-section-title"><h2>Профиль и настройки</h2></div>
    <div class="acc-panel" style="margin-bottom:22px;">
      <div class="acc-panel-head"><h3>Личные данные</h3></div>
      <div class="acc-panel-body">
        <form method="POST">
          <input type="hidden" name="update_profile" value="1">
          <div class="avatar-edit">
            <div class="big"><?= htmlspecialchars($initials.$initials2) ?></div>
            <div><div style="font-family:var(--font-display);font-size:1.1rem;margin-bottom:4px;"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div><div style="color:var(--ink-mute);font-size:0.9rem;"><?= htmlspecialchars($user['email']) ?></div></div>
          </div>
          <div class="profile-grid">
            <div class="field"><label>Полное имя</label><input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" placeholder="Имя путника"></div>
            <div class="field"><label>Телефон</label><input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+7 900 000-00-00"></div>
            <div class="field"><label>Email (нельзя изменить)</label><input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity:0.6;"></div>
          </div>
          <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>
      </div>
    </div>

    <div class="acc-panel">
      <div class="acc-panel-head"><h3>Безопасность</h3></div>
      <div class="acc-panel-body">
        <a href="logout.php?type=user" class="btn btn-ghost btn-sm">Выйти из аккаунта</a>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>

<script src="atmosphere.js"></script>
<script src="assets/phone-validate.js"></script>
</body>
</html>