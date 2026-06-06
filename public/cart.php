<?php
session_start();
include 'db.php';
require_once 'includes/csrf.php';
require_once 'includes/order_helpers.php';
require_once 'includes/notification_helpers.php';
require_once 'includes/phone.php';
require_once 'includes/promo_helpers.php';
ensure_promo_schema($conn);

$is_logged_in = isset($_SESSION['user_id']);
$user_phone = '';
$user_name  = '';
if ($is_logged_in) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT phone, full_name, username FROM users WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $user_phone = $u['phone'] ?? '';
    $user_name  = $u['full_name'] ?: ($u['username'] ?? '');
}

$cart_items  = [];
$total_price = 0;
if (isset($_SESSION['order_id'])) {
    $order_id = (int)$_SESSION['order_id'];
    $stmt = $conn->prepare("SELECT oi.id, oi.menu_item_id, oi.item_name, oi.item_price, oi.quantity, mi.image_path, mi.description AS item_desc FROM order_items oi LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cart_items[]  = $row;
        $total_price  += $row['item_price'] * $row['quantity'];
    }
    $stmt->close();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quantity']) && isset($_SESSION['order_id'])) {
        $item_id  = (int)$_POST['item_id'];
        $quantity = (int)$_POST['quantity'];
        $order_id = (int)$_SESSION['order_id'];
        if ($quantity <= 0) {
            $stmt = $conn->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
            $stmt->bind_param('ii', $item_id, $order_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("UPDATE order_items SET quantity = ? WHERE id = ? AND order_id = ?");
            $stmt->bind_param('iii', $quantity, $item_id, $order_id);
            $stmt->execute();
        }
        $stmt->close();
        header('Location: cart.php');
        exit;
    }
    if (isset($_POST['remove_item']) && isset($_SESSION['order_id'])) {
        $item_id  = (int)$_POST['item_id'];
        $order_id = (int)$_SESSION['order_id'];
        $stmt = $conn->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
        $stmt->bind_param('ii', $item_id, $order_id);
        $stmt->execute();
        $stmt->close();
        header('Location: cart.php');
        exit;
    }
    if (isset($_POST['apply_promo'])) {
        $code = trim($_POST['promo_code'] ?? '');
        if ($code !== '') {
            $v = validate_promo($conn, $code, (float)$total_price);
            if ($v['ok']) {
                $_SESSION['promo_code'] = $v['promo']['code'];
                unset($_SESSION['promo_error']);
            } else {
                unset($_SESSION['promo_code']);
                $_SESSION['promo_error'] = $v['error'];
            }
        }
        header('Location: cart.php');
        exit;
    }
    if (isset($_POST['remove_promo'])) {
        unset($_SESSION['promo_code'], $_SESSION['promo_error']);
        header('Location: cart.php');
        exit;
    }
    if (isset($_POST['order_type'])) {
        if (!csrf_verify()) {
            $error = 'Ошибка безопасности. Обновите страницу.';
        } elseif (!$is_logged_in) {
            $error = 'Войдите или зарегистрируйтесь, чтобы оформить заказ.';
        } elseif (empty($cart_items) || !isset($_SESSION['order_id'])) {
            $error = 'Корзина пуста';
        } else {
            $order_type      = in_array($_POST['order_type'], ['delivery','takeaway']) ? $_POST['order_type'] : 'dine_in';
            $delivery_address= trim($_POST['delivery_address'] ?? '');
            [$customer_phone, $customerPhoneOk] = normalize_phone($_POST['customer_phone'] ?? '');
            $notes           = trim($_POST['notes'] ?? '');
            if ($order_type === 'delivery' && $delivery_address === '') {
                $error = 'Укажите адрес доставки';
            } elseif ($customer_phone === '') {
                $error = !$customerPhoneOk ? 'Телефон должен быть в формате +7 и 10 цифр' : 'Укажите контактный телефон';
            } else {
                $order_id      = (int)$_SESSION['order_id'];
                $user_id       = (int)$_SESSION['user_id'];
                $subtotal      = round($total_price, 2);
                // Применяем промокод, если он сохранён в сессии и всё ещё валиден
                $promoCode = null; $discount = 0.0;
                if (!empty($_SESSION['promo_code'])) {
                    $v = validate_promo($conn, $_SESSION['promo_code'], $subtotal);
                    if ($v['ok']) { $promoCode = $v['promo']['code']; $discount = $v['discount']; }
                }
                $total         = max(0, round($subtotal - $discount, 2));
                $addr          = $order_type === 'delivery' ? $delivery_address : '';
                $customer_name = $user_name ?: $_SESSION['username'];
                ensure_takeaway_order_type($conn);
                $db_order_type = $order_type;
                $stmt = $conn->prepare("
                    UPDATE orders SET user_id=?,order_type=?,status='pending',
                    total=?,total_price=?,customer_name=?,customer_phone=?,
                    delivery_address=?,customer_address=?,notes=?,promo_code=?,discount=?,updated_at=NOW()
                    WHERE id=? AND status='cart'
                ");
                $stmt->bind_param('isddssssssdi', $user_id, $db_order_type, $total, $total, $customer_name, $customer_phone, $addr, $addr, $notes, $promoCode, $discount, $order_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    record_order_status($conn, $order_id, 'cart', 'pending', 'customer', 'Заказ оформлен');
                    create_user_notification($conn, $user_id, 'order', $order_id, 'Заказ №'.$order_id.' оформлен и ожидает подтверждения');
                    unset($_SESSION['order_id'], $_SESSION['promo_code'], $_SESSION['promo_error']);
                    $success = $order_type === 'delivery'
                        ? 'Заявка принята! Гонец свяжется с вами в течение 10 минут.'
                        : ($order_type === 'takeaway' ? 'Заказ оформлен! Ждём вас через 30 минут.' : 'Заявка принята! Ждём у тёплого очага. Статус в <a href="profile.php">кабинете</a>.');
                    $cart_items  = [];
                    $total_price = 0;
                } else {
                    $error = 'Не удалось оформить заказ. Попробуйте обновить корзину.';
                }
                $stmt->close();
            }
        }
    }
}

// Состояние применённого промокода (для отображения)
$applied_promo = null; $promo_discount = 0.0;
$promo_error = $_SESSION['promo_error'] ?? '';
unset($_SESSION['promo_error']);
if (!empty($_SESSION['promo_code']) && $total_price > 0) {
    $v = validate_promo($conn, $_SESSION['promo_code'], (float)$total_price);
    if ($v['ok']) { $applied_promo = $v['promo']; $promo_discount = $v['discount']; }
    else { unset($_SESSION['promo_code']); if ($promo_error === '') $promo_error = $v['error']; }
}
$grand_total = max(0, $total_price - $promo_discount);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Корзина · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .cart-hero{text-align:center;padding:50px 0 30px;background:linear-gradient(180deg,rgba(60,80,50,0.12),transparent),var(--parch-100);}
  .cart-hero h1{margin-bottom:8px;}
  .cart-hero .lede{color:var(--ink-soft);font-style:italic;font-size:1.05rem;}
  .cart-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:40px;padding:40px 0 80px;align-items:start;}
  .cart-list{background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);overflow:hidden;box-shadow:var(--shadow-soft);}
  .cart-row{display:grid;grid-template-columns:110px 1fr auto auto;gap:22px;align-items:center;padding:22px 26px;border-bottom:1px solid var(--line);}
  .cart-row:last-child{border-bottom:none;}
  .cart-row .ph{width:110px;height:90px;border-radius:var(--r-sm);overflow:hidden;background:var(--parch-200);}
  .cart-row .ph img{width:100%;height:100%;object-fit:cover;}
  .cart-row .title{font-family:var(--font-display);font-size:1.1rem;color:var(--ink);margin-bottom:4px;}
  .cart-row .desc{font-size:0.88rem;color:var(--ink-mute);margin:0;}
  .cart-row .price-each{font-family:var(--font-display);font-size:0.85rem;color:var(--ink-faint);margin-top:6px;}
  .cart-row .price-sum{font-family:var(--font-display);font-size:1.2rem;color:var(--amber-deep);min-width:90px;text-align:right;font-weight:600;}
  .cart-row .remove{background:transparent;border:none;color:var(--ink-faint);cursor:pointer;padding:8px;transition:color 0.2s;}
  .cart-row .remove:hover{color:var(--berry);}
  .qty-mini{display:inline-flex;border:1px solid var(--line);border-radius:var(--r-sm);overflow:hidden;}
  .qty-mini button{width:32px;height:32px;border:none;background:transparent;font-family:var(--font-display);cursor:pointer;color:var(--ink);font-size:1rem;line-height:1;}
  .qty-mini button:hover{background:var(--parch-100);}
  .qty-mini .v{width:36px;text-align:center;line-height:32px;font-family:var(--font-display);border-left:1px solid var(--line);border-right:1px solid var(--line);}
  .promo-card{margin-top:20px;background:var(--parch-50);border:1px dashed var(--moss);border-radius:var(--r-md);padding:20px 24px;display:flex;align-items:center;gap:16px;}
  .promo-card .icon{width:42px;height:42px;border-radius:50%;background:var(--moss-soft);color:var(--forest);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
  .promo-card h4{margin:0 0 2px;font-size:1rem;}
  .promo-card p{margin:0;color:var(--ink-mute);font-size:0.85rem;}
  .promo-card .open{margin-left:auto;font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.15em;font-size:0.74rem;color:var(--amber-deep);cursor:pointer;background:none;border:none;}
  .checkout-side{position:sticky;top:90px;align-self:start;}
  .checkout-card{background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);padding:28px;box-shadow:var(--shadow-soft);}
  .checkout-card h2{font-size:1.4rem;margin-bottom:6px;}
  .checkout-card .sub{color:var(--ink-mute);font-size:0.9rem;margin-bottom:22px;}
  .order-type{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:24px;}
  .order-type input{display:none;}
  .order-type label{padding:14px 8px;border:1.5px solid var(--line);border-radius:var(--r-sm);text-align:center;cursor:pointer;transition:all 0.2s;background:var(--parch-50);}
  .order-type label:hover{border-color:var(--amber);}
  .order-type input:checked + label{border-color:var(--amber-deep);background:var(--parch-100);box-shadow:0 0 0 3px rgba(184,118,58,0.18);}
  .order-type label .ico{font-size:1.4rem;display:block;margin-bottom:4px;}
  .order-type label .ttl{font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.12em;font-size:0.7rem;color:var(--ink);}
  .totals{margin-top:8px;border-top:1px solid var(--line);padding-top:22px;}
  .totals .row{display:flex;justify-content:space-between;align-items:baseline;padding:8px 0;color:var(--ink-soft);}
  .totals .row.total{border-top:1px dashed var(--line);padding-top:16px;margin-top:10px;font-family:var(--font-display);font-size:1.4rem;color:var(--ink);}
  .totals .row.total .amount{font-size:1.6rem;color:var(--amber-deep);}
  .empty-cart{text-align:center;padding:80px 20px;background:var(--parch-50);border:1px dashed var(--line);border-radius:var(--r-md);}
  .empty-cart .emoji{font-size:3rem;margin-bottom:14px;opacity:0.6;}
  .guest-auth{background:var(--parch-100);border:1px solid var(--line);border-radius:var(--r-md);padding:28px;text-align:center;}
  .addr-field{display:none;}
  @media(max-width:900px){.cart-grid{grid-template-columns:1fr;}.checkout-side{position:static;}.cart-row{grid-template-columns:90px 1fr;gap:14px;}.cart-row .ph{width:90px;height:80px;}.cart-row .price-sum,.cart-row .remove{grid-column:2;justify-self:end;}}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<section class="cart-hero">
  <div class="container">
    <div class="breadcrumbs" style="justify-content:center;padding:0 0 14px;"><a href="index.php">Главная</a><span class="sep">/</span><span>Корзина</span></div>
    <div class="eyebrow">Дорожный мешок</div>
    <h1>Твой заказ</h1>
    <p class="lede">Проверь, всё ли на месте, и выбери, как заберёшь.</p>
  </div>
</section>

<div class="container">
  <?php if (!empty($error)): ?><div class="error-message" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?><?php if (!$is_logged_in): ?><div style="display:flex;gap:12px;margin-top:12px;"><a href="login.php?return_to=cart" class="btn btn-primary btn-sm">Войти</a><a href="register.php?return_to=cart" class="btn btn-ghost btn-sm">Регистрация</a></div><?php endif; ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="success-message" style="margin-bottom:20px;"><?= $success ?></div><?php endif; ?>

  <?php if (empty($cart_items) && empty($success)): ?>
  <!-- Пустая корзина — по центру страницы -->
  <div style="max-width:500px;margin:40px auto 80px;text-align:center;">
    <div class="empty-cart" style="display:flex;flex-direction:column;align-items:center;">
      <div class="emoji">🧺</div>
      <h3 style="color:var(--ink-mute);">Корзина пуста</h3>
      <p class="muted" style="margin:8px 0 20px;max-width:340px;">Добавьте что-нибудь из нашего меню, добрый путник.</p>
      <a href="menu.php" class="btn btn-primary">Открыть меню</a>
      <?php if (!$is_logged_in): ?>
      <p style="margin-top:20px;font-size:0.9rem;color:var(--ink-mute);">
        Уже добавляли раньше? <a href="login.php?return_to=cart">Войдите</a> — корзина сохраняется.
      </p>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="cart-grid">
    <!-- Позиции -->
    <div>
      <?php if (empty($cart_items)): ?>
      <!-- этот блок не будет показан т.к. success показывает подтверждение -->
      <div></div>
      <?php else: ?>
      <div class="cart-list">
        <?php foreach ($cart_items as $item):
          $imgSrc = (!empty($item['image_path']) && file_exists(__DIR__.'/'.$item['image_path'])) ? htmlspecialchars($item['image_path']) : '';
        ?>
        <div class="cart-row">
          <div class="ph">
            <?php if ($imgSrc): ?><img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['item_name']) ?>"><?php else: ?><div class="placeholder-photo" style="height:100%;"></div><?php endif; ?>
          </div>
          <div>
            <div class="title"><?= htmlspecialchars($item['item_name']) ?></div>
            <?php if (!empty($item['item_desc'])): ?><p class="desc"><?= htmlspecialchars(mb_substr($item['item_desc'],0,60)) ?>…</p><?php endif; ?>
            <div class="price-each"><?= number_format($item['item_price'],0,'.',' ') ?> ₽ × <?= $item['quantity'] ?></div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;gap:8px;">
            <div class="qty-mini">
              <form method="POST" style="display:contents;"><input type="hidden" name="item_id" value="<?= $item['id'] ?>"><input type="hidden" name="quantity" value="<?= $item['quantity']-1 ?>"><button type="submit" name="update_quantity">−</button></form>
              <div class="v"><?= $item['quantity'] ?></div>
              <form method="POST" style="display:contents;"><input type="hidden" name="item_id" value="<?= $item['id'] ?>"><input type="hidden" name="quantity" value="<?= $item['quantity']+1 ?>"><button type="submit" name="update_quantity">+</button></form>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px;">
            <span class="price-sum"><?= number_format($item['item_price']*$item['quantity'],0,'.',' ') ?> ₽</span>
            <form method="POST"><input type="hidden" name="item_id" value="<?= $item['id'] ?>"><button type="submit" name="remove_item" class="remove" aria-label="Удалить"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Промокод -->
      <?php if ($applied_promo): ?>
      <div class="promo-card" style="border-style:solid;border-color:var(--moss);">
        <div class="icon" style="background:var(--moss);color:var(--parch-50);">✓</div>
        <div>
          <h4>Промокод «<?= htmlspecialchars($applied_promo['code']) ?>» применён</h4>
          <p>Скидка <?= htmlspecialchars(promo_label($applied_promo)) ?> · −<?= number_format($promo_discount,0,'.',' ') ?> ₽</p>
        </div>
        <form method="POST" style="margin-left:auto;"><?= csrf_field() ?>
          <button type="submit" name="remove_promo" class="open" style="color:var(--berry);">Убрать</button>
        </form>
      </div>
      <?php else: ?>
      <div class="promo-card">
        <div class="icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 14L5 10m0 0l4-4M5 10h14"/></svg></div>
        <div><h4>Промокод или сертификат</h4><p>Введите код — скидка применится к заказу</p></div>
        <button type="button" class="open" onclick="this.closest('.promo-card').style.display='none'; document.getElementById('promoInput').style.display='flex'">Ввести</button>
      </div>
      <form method="POST" id="promoInput" style="display:<?= $promo_error ? 'flex' : 'none' ?>;margin-top:12px;gap:8px;">
        <?= csrf_field() ?>
        <input type="text" name="promo_code" placeholder="Введи код" value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>" style="flex:1;padding:10px 14px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;text-transform:uppercase;">
        <button type="submit" name="apply_promo" class="btn btn-ghost btn-sm">Применить</button>
      </form>
      <?php endif; ?>
      <?php if ($promo_error): ?>
      <div class="error-message" style="margin-top:10px;"><?= htmlspecialchars($promo_error) ?></div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Оформление -->
    <?php if (!empty($cart_items)): ?>
    <aside class="checkout-side">
      <?php if ($is_logged_in): ?>
      <div class="checkout-card">
        <h2>Оформить</h2>
        <p class="sub">Выбери формат — перезвоним через 10 минут</p>
        <form method="POST" id="checkoutForm">
          <?= csrf_field() ?>
          <div class="order-type">
            <input type="radio" name="order_type" id="ot1" value="dine_in" checked>
            <label for="ot1"><span class="ico">🍽️</span><span class="ttl">В таверне</span></label>
            <input type="radio" name="order_type" id="ot2" value="takeaway">
            <label for="ot2"><span class="ico">🎁</span><span class="ttl">С собой</span></label>
            <input type="radio" name="order_type" id="ot3" value="delivery">
            <label for="ot3"><span class="ico">🛵</span><span class="ttl">Доставка</span></label>
          </div>

          <div class="field">
            <label>Телефон *</label>
            <input type="tel" name="customer_phone" value="" placeholder="+7 900 000-00-00" required>
          </div>
          <div class="field addr-field" id="addrField">
            <label>Адрес доставки *</label>
            <textarea name="delivery_address" rows="2" placeholder="Улица, дом, квартира"></textarea>
          </div>
          <div class="field">
            <label>Комментарий</label>
            <textarea name="notes" rows="2" placeholder="Аллергии, пожелания…"></textarea>
          </div>

          <div class="totals">
            <?php $delivery_fee = 0; ?>
            <div class="row"><span>Блюда (<?= count($cart_items) ?>)</span><span><?= number_format($total_price,0,'.',' ') ?> ₽</span></div>
            <?php if ($applied_promo): ?>
            <div class="row" style="color:var(--forest);"><span>Промокод «<?= htmlspecialchars($applied_promo['code']) ?>»</span><span>−<?= number_format($promo_discount,0,'.',' ') ?> ₽</span></div>
            <?php endif; ?>
            <div class="row" id="deliveryRow" style="display:none;"><span>Доставка</span><span>по запросу</span></div>
            <div class="row total"><span>Итого</span><span class="amount"><?= number_format($grand_total,0,'.',' ') ?> ₽</span></div>
          </div>

          <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:20px;">Оставить заявку</button>
          <p style="text-align:center;font-size:0.82rem;color:var(--ink-mute);margin:12px 0 0;">Подтвердим и свяжемся в течение 10 минут</p>
        </form>
      </div>
      <?php else: ?>
      <div class="guest-auth">
        <div class="eyebrow" style="margin-bottom:12px;">Требуется вход</div>
        <h3>Войди, чтобы оформить</h3>
        <p class="muted">Корзина сохранится после входа.</p>
        <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;justify-content:center;">
          <a href="login.php?return_to=cart" class="btn btn-primary">Войти</a>
          <a href="register.php?return_to=cart" class="btn btn-ghost">Регистрация</a>
        </div>
      </div>
      <?php endif; ?>
    </aside>
    <?php endif; ?>
  </div>
  <?php endif; // end empty cart check ?>
</div>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div><div class="brand" style="margin-bottom:14px;padding:0;"><img src="assets/brand-mark.svg" alt="" class="brand-mark"><span>Ширский уголок</span></div><p style="color:var(--parch-200);font-size:0.95rem;">Уютная таверна для добрых путников.</p></div>
      <div><h4>Разделы</h4><ul><li><a href="menu.php">Меню</a></li><li><a href="booking.php">Бронь стола</a></li><li><a href="gallery.php">Галерея</a></li><li><a href="reviews.php">Отзывы</a></li></ul></div>
      <div><h4>Контакты</h4><ul><li>пер. Зелёного Холма, 7</li><li><a href="tel:+78124567890">+7 (812) 456-78-90</a></li></ul></div>
      <div><h4>Меню</h4><a href="menu.php" class="btn btn-primary btn-sm">Выбрать блюда</a></div>
    </div>
    <div class="footer-bottom"><div>© 1893 — <?= date('Y') ?> Таверна «Ширский уголок»</div><div><a href="rules.php" style="color:var(--ink-faint);">Правила таверны</a></div></div>
  </div>
</footer>

<script src="atmosphere.js"></script>
<script src="assets/phone-validate.js"></script>
<script>
document.querySelectorAll('input[name="order_type"]').forEach(r => {
  r.addEventListener('change', () => {
    const isDelivery = r.value === 'delivery' && r.checked;
    const addr = document.getElementById('addrField');
    const addrTA = addr.querySelector('textarea');
    const deliveryRow = document.getElementById('deliveryRow');
    addr.style.display = isDelivery ? 'block' : 'none';
    addrTA.required = isDelivery;
    if (deliveryRow) deliveryRow.style.display = isDelivery ? 'flex' : 'none';
  });
});
</script>
</body>
</html>