<?php
require_once 'includes/admin_auth.php';
require_once 'includes/order_helpers.php';
require_once 'includes/mail.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) { header('Location: admin_panel.php?section=orders'); exit; }

$flash = ''; $flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['new_status'] ?? '';
        $comment   = trim($_POST['comment'] ?? '');
        $allowed   = ['pending','confirmed','preparing','ready','completed','cancelled'];
        if (in_array($newStatus, $allowed, true)) {
            if (update_order_status($conn, $orderId, $newStatus, 'admin', $comment ?: null)) {
                $flash = 'Статус обновлён';
                $stmt = $conn->prepare("SELECT u.email FROM orders o LEFT JOIN users u ON o.user_id=u.id WHERE o.id=?");
                $stmt->bind_param('i', $orderId);
                $stmt->execute();
                $em = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!empty($em['email']) && in_array($newStatus, ['confirmed','cancelled','ready'], true)) {
                    notify_order_status($em['email'], $orderId, $newStatus);
                }
            } else { $flashError = 'Не удалось обновить статус'; }
        }
    }
}

$stmt = $conn->prepare("SELECT o.*, u.username, u.full_name, u.email, u.phone AS user_phone FROM orders o LEFT JOIN users u ON o.user_id=u.id WHERE o.id=?");
$stmt->bind_param('i', $orderId); $stmt->execute();
$order = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$order || $order['status'] === 'cart') { header('Location: admin_panel.php?section=orders'); exit; }

$items = [];
$stmt = $conn->prepare("SELECT oi.*, mi.image_path FROM order_items oi LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id=?");
$stmt->bind_param('i', $orderId); $stmt->execute();
$res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $items[] = $row; $stmt->close();

$history = [];
$stmt = $conn->prepare("SELECT * FROM order_status_history WHERE order_id=? ORDER BY created_at DESC");
$stmt->bind_param('i', $orderId); $stmt->execute();
$res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $history[] = $row; $stmt->close();

$counts = get_pending_counts($conn);
$admin_username = $_SESSION['username'] ?? 'Администратор';
$admin_initials = mb_strtoupper(mb_substr($admin_username, 0, 2));

$statusSteps = ['pending'=>1,'confirmed'=>2,'preparing'=>3,'ready'=>4,'completed'=>5];
$currentStep = $statusSteps[$order['status']] ?? ($order['status'] === 'cancelled' ? -1 : 0);
$stepLabels  = ['Принят','Подтверждён','Готовится','Готов','Завершён'];

$guestName  = $order['full_name'] ?: $order['username'] ?: ($order['customer_name'] ?? '—');
$guestPhone = $order['customer_phone'] ?: ($order['user_phone'] ?? '—');
$total      = $order['total'] ?: ($order['total_price'] ?? 0);
$discountVal = (float)($order['discount'] ?? 0);
$promoCodeVal = $order['promo_code'] ?? '';
$subtotalVal = (float)$total + $discountVal;
$typeIcon   = ['delivery'=>'🚲','dine_in'=>'🪑','takeaway'=>'🛍'][$order['order_type'] ?? ''] ?? '🛍';
$typeLabel  = order_type_label($order['order_type'] ?? '');
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Заказ #<?= $orderId ?> · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/admin.css">
<style>
  .order-layout { display:grid; grid-template-columns:1fr 380px; gap:22px; align-items:start; }
  @media(max-width:900px){ .order-layout { grid-template-columns:1fr; } }

  /* Detail panel */
  .od-panel { border:1px solid var(--line); border-radius:var(--r-lg); overflow:hidden; box-shadow:var(--shadow-card); position:sticky; top:84px; }
  .od-head { padding:24px 26px; background:linear-gradient(135deg,#2c1810,#1f1109); color:var(--parch-100); }
  .od-head .num { font-family:'JetBrains Mono',monospace; color:var(--gold); font-size:0.95rem; letter-spacing:0.05em; margin-bottom:6px; }
  .od-head h2 { color:var(--parch-50); margin:0 0 4px; font-size:1.5rem; }
  .od-head .meta { display:flex; gap:14px; font-size:0.88rem; color:var(--parch-200); margin-top:10px; flex-wrap:wrap; }

  /* Progress */
  .order-progress { display:flex; align-items:flex-start; padding:16px 20px; background:var(--parch-100); border-bottom:1px solid var(--line); gap:0; }
  .op-step { flex:1; text-align:center; position:relative; }
  .op-step .dot { width:26px; height:26px; margin:0 auto 5px; border-radius:50%; background:var(--parch-200); border:2px solid var(--line); display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:0.72rem; color:var(--ink-faint); position:relative; z-index:2; }
  .op-step .lbl { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.02em; font-size:0.58rem; color:var(--ink-mute); padding:0 3px; line-height:1.2; overflow-wrap:anywhere; }
  .op-step.done .dot { background:var(--moss); border-color:var(--forest); color:var(--parch-50); }
  .op-step.done .lbl { color:var(--moss); }
  .op-step.active .dot { background:var(--amber); border-color:var(--amber-deep); color:var(--parch-50); box-shadow:0 0 0 4px rgba(184,118,58,0.2); }
  .op-step.active .lbl { color:var(--amber-deep); font-weight:600; }
  .op-step.cancelled-step .dot { background:var(--berry); border-color:#7a1530; color:var(--parch-50); }
  .op-step.cancelled-step .lbl { color:var(--berry); }
  .op-step::after { content:""; position:absolute; top:13px; left:50%; right:-50%; height:2px; background:var(--line); z-index:1; }
  .op-step:last-child::after { display:none; }
  .op-step.done::after { background:var(--moss); }

  /* Items */
  .od-items { padding:18px 22px; border-bottom:1px solid var(--line); background:var(--parch-50); }
  .od-items h4 { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.16em; font-size:0.72rem; color:var(--ink-mute); margin:0 0 12px; }
  .od-item { display:grid; grid-template-columns:46px 1fr auto; gap:12px; align-items:center; padding:9px 0; border-bottom:1px dashed var(--line-soft); }
  .od-item:last-child { border-bottom:none; }
  .od-item .ph-mini { width:46px; height:46px; border-radius:var(--r-sm); overflow:hidden; background:var(--parch-200); flex-shrink:0; }
  .od-item .ph-mini img { width:100%; height:100%; object-fit:cover; display:block; }
  .od-item .ttl { font-family:var(--font-display); font-size:0.88rem; line-height:1.3; }
  .od-item .qty { font-size:0.78rem; color:var(--ink-mute); margin-top:2px; }
  .od-item .sum { font-family:var(--font-display); color:var(--amber-deep); font-size:0.95rem; white-space:nowrap; }

  /* Guest */
  .od-guest { padding:14px 22px; background:var(--parch-50); border-bottom:1px solid var(--line); }
  .od-guest .row { display:flex; justify-content:space-between; align-items:baseline; padding:7px 0; border-bottom:1px dashed var(--line-soft); gap:10px; }
  .od-guest .row:last-child { border-bottom:none; }
  .od-guest .lbl { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.14em; font-size:0.68rem; color:var(--ink-mute); flex-shrink:0; }
  .od-guest .val { font-size:0.92rem; text-align:right; }

  /* Totals */
  .od-totals { padding:14px 22px; background:var(--parch-100); border-bottom:1px solid var(--line); }
  .od-totals .row { display:flex; justify-content:space-between; padding:4px 0; font-size:0.9rem; color:var(--ink-soft); }
  .od-totals .row.total { border-top:1px dashed var(--line); padding-top:10px; margin-top:6px; font-family:var(--font-display); font-size:1.15rem; color:var(--amber-deep); }

  /* Actions */
  .od-actions { padding:14px 22px; background:var(--parch-50); display:flex; gap:8px; }

  /* Left panels */
  .info-panel { background:var(--parch-50); border:1px solid var(--line); border-radius:var(--r-lg); overflow:hidden; box-shadow:var(--shadow-soft); margin-bottom:20px; }
  .info-panel .ph { padding:16px 22px 0; background:var(--parch-100); border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
  .info-panel .ph h3 { font-family:var(--font-display); font-size:0.82rem; text-transform:uppercase; letter-spacing:0.16em; color:var(--ink-mute); margin:0 0 12px; }

  /* Table */
  .ot { width:100%; border-collapse:collapse; }
  .ot th { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.14em; font-size:0.68rem; color:var(--ink-mute); padding:12px 16px; border-bottom:1px solid var(--line); text-align:left; background:var(--parch-100); }
  .ot td { padding:12px 16px; border-bottom:1px solid var(--line-soft); font-size:0.92rem; vertical-align:middle; }
  .ot tr:last-child td { border-bottom:none; }

  /* Status change form */
  .status-form { padding:20px 22px; }
  .status-form .row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px; }
  .status-form .row select, .status-form .row input { font:inherit; font-size:0.9rem; padding:9px 12px; border:1px solid var(--line); border-radius:var(--r-sm); background:var(--parch-50); }
  .quick-btns { display:flex; gap:8px; flex-wrap:wrap; }

  /* History row */
  .hist-row { display:grid; grid-template-columns:150px 100px 100px 80px 1fr; gap:12px; align-items:center; padding:10px 16px; border-bottom:1px solid var(--line-soft); font-size:0.86rem; }
  .hist-row:last-child { border-bottom:none; }
</style>
</head>
<body class="admin-body">

<aside class="admin-side">
  <div class="admin-brand">
    <img src="assets/brand-mark.svg" alt="">
    <div><div class="name">Ширский<br>уголок</div><div class="sub">управление</div></div>
  </div>
  <div class="admin-section-label">Обзор</div>
  <ul class="admin-nav">
    <li><a href="admin_panel.php?section=dashboard">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Дашборд</a></li>
  </ul>
  <div class="admin-section-label">Заявки</div>
  <ul class="admin-nav">
    <li><a href="admin_panel.php?section=orders" class="active">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h2l2.5 12h11l2.5-9H6"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
      Заказы
      <?php if ($counts['orders'] > 0): ?><span class="badge"><?= $counts['orders'] ?></span><?php endif; ?>
    </a></li>
    <li><a href="admin_panel.php?section=bookings">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Бронирования
      <?php if ($counts['bookings'] > 0): ?><span class="badge"><?= $counts['bookings'] ?></span><?php endif; ?>
    </a></li>
  </ul>
  <div class="admin-section-label">Управление</div>
  <ul class="admin-nav">
    <li><a href="admin_panel.php?section=tables">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/></svg>
      План зала / Столики</a></li>
    <li><a href="admin_panel.php?section=menu">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6h16M4 12h16M4 18h12"/></svg>
      Меню и блюда</a></li>
    <li><a href="admin_panel.php?section=reviews">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.5 8.5 0 0 1-7.6-3 8.5 8.5 0 0 1-1-7.7 8.4 8.4 0 0 1 8.4-5.6"/><path d="M16 8.7 11.5 13l-2.3-2.4"/></svg>
      Отзывы</a></li>
    <li><a href="admin_panel.php?section=gallery">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      Галерея</a></li>
  </ul>
  <div class="admin-section-label">Ссылки</div>
  <ul class="admin-nav">
    <li><a href="admin_booking_calendar.php">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Календарь броней</a></li>
    <li><a href="index.php" target="_blank">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      Открыть сайт</a></li>
  </ul>
  <div style="margin-top:auto;"></div>
  <div class="admin-user" style="margin-top:24px;">
    <div class="av"><?= htmlspecialchars($admin_initials) ?></div>
    <div><div class="who"><?= htmlspecialchars($admin_username) ?></div><div class="role">Администратор</div></div>
    <a href="logout.php?type=admin" style="margin-left:auto;color:var(--ink-faint);font-size:0.8rem;">Выйти</a>
  </div>
</aside>

<div class="admin-main">
  <header class="admin-top">
    <h1>Заказ #<?= $orderId ?></h1>
    <div class="admin-quick" style="flex:1;"></div>
    <a href="admin_panel.php?section=orders" class="btn btn-ghost btn-sm">← Все заказы</a>
    <a href="logout.php?type=admin" class="btn btn-ghost btn-sm">Выйти</a>
  </header>

  <div class="admin-content">
    <?php if ($flash): ?><div class="success-message" style="margin-bottom:16px;"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="error-message" style="margin-bottom:16px;"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="order-layout">

      <!-- LEFT: detailed items + status control + history -->
      <div>

        <!-- Items table -->
        <div class="info-panel">
          <div class="ph" style="padding-bottom:0;">
            <h3>Состав заказа · <?= count($items) ?> <?= count($items) === 1 ? 'позиция' : (count($items) < 5 ? 'позиции' : 'позиций') ?></h3>
          </div>
          <table class="ot">
            <thead><tr><th style="width:54px;"></th><th>Блюдо</th><th>Цена</th><th>Кол-во</th><th style="text-align:right;">Сумма</th></tr></thead>
            <tbody>
              <?php foreach ($items as $it):
                $imgSrc = (!empty($it['image_path']) && file_exists(__DIR__.'/'.$it['image_path'])) ? htmlspecialchars($it['image_path']) : '';
              ?>
              <tr>
                <td style="padding:8px 10px 8px 16px;">
                  <?php if ($imgSrc): ?>
                    <img src="<?= $imgSrc ?>" style="width:46px;height:46px;object-fit:cover;border-radius:var(--r-sm);display:block;">
                  <?php else: ?>
                    <div style="width:46px;height:46px;background:var(--parch-200);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--ink-faint);">🍽</div>
                  <?php endif; ?>
                </td>
                <td style="font-family:var(--font-display);font-size:0.9rem;"><?= htmlspecialchars($it['item_name']) ?></td>
                <td style="color:var(--ink-mute);"><?= htmlspecialchars($it['item_price']) ?> ₽</td>
                <td>× <?= (int)$it['quantity'] ?></td>
                <td style="text-align:right;font-family:var(--font-display);color:var(--amber-deep);"><?= number_format($it['item_price'] * $it['quantity'], 0, '.', ' ') ?> ₽</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div style="display:flex;justify-content:flex-end;padding:14px 20px;background:var(--parch-100);border-top:1px solid var(--line);">
            <span style="font-family:var(--font-display);font-size:1.15rem;color:var(--amber-deep);">Итого: <?= number_format((float)$total, 0, '.', ' ') ?> ₽</span>
          </div>
        </div>

        <!-- Status change -->
        <div class="info-panel">
          <div class="ph"><h3>Сменить статус</h3></div>
          <div class="status-form">
            <form method="POST">
              <?= csrf_field() ?>
              <div class="row">
                <select name="new_status">
                  <?php foreach (['pending','confirmed','preparing','ready','completed','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= order_status_label($s) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="comment" placeholder="Комментарий (необязательно)" style="flex:1;min-width:160px;">
                <button type="submit" name="update_status" class="btn btn-primary btn-sm">Обновить</button>
              </div>
            </form>
          </div>
        </div>

        <!-- History -->
        <?php if (!empty($history)): ?>
        <div class="info-panel">
          <div class="ph"><h3>История статусов</h3></div>
          <div style="padding:0;">
            <?php foreach ($history as $h): ?>
            <div class="hist-row">
              <span style="color:var(--ink-mute);font-size:0.82rem;"><?= substr($h['created_at'] ?? '', 0, 16) ?></span>
              <?php if ($h['old_status']): ?>
              <span class="status-badge status-<?= $h['old_status'] ?>"><?= order_status_label($h['old_status']) ?></span>
              <?php else: ?><span>—</span><?php endif; ?>
              <span class="status-badge status-<?= $h['new_status'] ?>"><?= order_status_label($h['new_status']) ?></span>
              <span style="font-size:0.82rem;color:var(--ink-mute);"><?= htmlspecialchars($h['changed_by']) ?></span>
              <span style="font-size:0.82rem;color:var(--ink-soft);font-style:italic;"><?= htmlspecialchars($h['comment'] ?? '') ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>

      <!-- RIGHT: sticky summary panel -->
      <div>
        <div class="od-panel">

          <!-- Dark header -->
          <div class="od-head">
            <div class="num">#<?= $orderId ?> · <?= htmlspecialchars(substr($order['created_at'] ?? '', 0, 16)) ?></div>
            <h2><?= htmlspecialchars($guestName) ?></h2>
            <div class="meta">
              <span><?= $typeIcon ?> <?= htmlspecialchars($typeLabel) ?></span>
              <?php if ($guestPhone && $guestPhone !== '—'): ?>
              <span>📞 <?= htmlspecialchars($guestPhone) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Progress bar -->
          <?php if ($order['status'] !== 'cancelled'): ?>
          <div class="order-progress">
            <?php foreach ($stepLabels as $idx => $lbl):
              $step = $idx + 1;
              $cls = '';
              if ($currentStep > $step) $cls = 'done';
              elseif ($currentStep === $step) $cls = 'active';
            ?>
            <div class="op-step <?= $cls ?>">
              <div class="dot"><?= $cls === 'done' ? '✓' : $step ?></div>
              <div class="lbl"><?= $lbl ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="order-progress">
            <div class="op-step cancelled-step">
              <div class="dot">✗</div>
              <div class="lbl">Отменён</div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Items mini list -->
          <div class="od-items">
            <h4>Состав · <?= count($items) ?> поз.</h4>
            <?php foreach ($items as $it):
              $imgSrc = (!empty($it['image_path']) && file_exists(__DIR__.'/'.$it['image_path'])) ? htmlspecialchars($it['image_path']) : '';
            ?>
            <div class="od-item">
              <div class="ph-mini">
                <?php if ($imgSrc): ?>
                  <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($it['item_name']) ?>">
                <?php else: ?>
                  <div style="height:100%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:var(--ink-faint);">🍽</div>
                <?php endif; ?>
              </div>
              <div>
                <div class="ttl"><?= htmlspecialchars($it['item_name']) ?></div>
                <div class="qty"><?= htmlspecialchars($it['item_price']) ?> ₽ × <?= (int)$it['quantity'] ?></div>
              </div>
              <div class="sum"><?= number_format($it['item_price'] * $it['quantity'], 0, '.', ' ') ?> ₽</div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Guest details -->
          <div class="od-guest">
            <?php if (!empty($order['delivery_address'])): ?>
            <div class="row">
              <span class="lbl">Адрес</span>
              <span class="val"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($order['email'])): ?>
            <div class="row">
              <span class="lbl">Email</span>
              <span class="val"><?= htmlspecialchars($order['email']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($order['notes'])): ?>
            <div class="row">
              <span class="lbl">Комментарий</span>
              <span class="val" style="font-style:italic;color:var(--ink-soft);max-width:200px;text-align:right;">«<?= htmlspecialchars($order['notes']) ?>»</span>
            </div>
            <?php endif; ?>
          </div>

          <!-- Totals -->
          <div class="od-totals">
            <div class="row"><span>Позиций</span><span><?= count($items) ?></span></div>
            <?php if ($discountVal > 0): ?>
            <div class="row"><span>Сумма блюд</span><span><?= number_format($subtotalVal, 0, '.', ' ') ?> ₽</span></div>
            <div class="row" style="color:var(--forest);"><span>Промокод<?= $promoCodeVal ? ' «'.htmlspecialchars($promoCodeVal).'»' : '' ?></span><span>−<?= number_format($discountVal, 0, '.', ' ') ?> ₽</span></div>
            <?php endif; ?>
            <div class="row total"><span>Итого</span><span><?= number_format((float)$total, 0, '.', ' ') ?> ₽</span></div>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

</body>
</html>
