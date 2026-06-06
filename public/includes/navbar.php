<?php
if (!function_exists('is_admin_logged_in')) {
    require_once __DIR__ . '/auth_admin.php';
}
if (!function_exists('is_user_logged_in')) {
    require_once __DIR__ . '/auth_user.php';
}

$_nav_pending_badge = '';
$_nav_user_notif_badge = '';

if (is_admin_logged_in() && isset($conn)) {
    require_once __DIR__ . '/order_helpers.php';
    $counts = get_pending_counts($conn);
    $total = $counts['orders'] + $counts['bookings'];
    if ($total > 0) {
        $_nav_pending_badge = ' <span class="pending-badge">' . $total . '</span>';
    }
}

if (is_user_logged_in() && isset($conn)) {
    require_once __DIR__ . '/notification_helpers.php';
    $unread = get_unread_notification_count($conn, (int)$_SESSION['user_id']);
    if ($unread > 0) {
        $_nav_user_notif_badge = ' <span class="pending-badge">' . $unread . '</span>';
    }
}

$_nav_cart_count = 0;
if (isset($_SESSION['order_id']) && isset($conn)) {
    $cart_order_id = (int)$_SESSION['order_id'];
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS c FROM order_items WHERE order_id = ?");
    $stmt->bind_param('i', $cart_order_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $_nav_cart_count = (int)($row['c'] ?? 0);
}

// Determine active page
$_nav_current = basename($_SERVER['PHP_SELF']);
if (!function_exists('_nav_active')) {
    function _nav_active($page) {
        global $_nav_current;
        return $_nav_current === $page ? ' class="active"' : '';
    }
}
?>
<!-- ===== Шапка ===== -->
<header class="site-header">
  <div class="bar">
    <a href="index.php" class="brand">
      <img src="assets/brand-mark.svg" alt="" class="brand-mark">
      <span>Ширский уголок</span>
    </a>
    <button class="burger" id="burger" aria-label="Меню" onclick="document.querySelector('.nav').classList.toggle('open');this.classList.toggle('open');">
      <span></span><span></span><span></span>
    </button>
    <nav class="nav" id="mainNav">
      <a href="index.php"<?= _nav_active('index.php') ?>>Главная</a>
      <a href="menu.php"<?= _nav_active('menu.php') ?>>Меню</a>
      <a href="booking.php"<?= _nav_active('booking.php') ?>>Бронь стола</a>
      <a href="gallery.php"<?= _nav_active('gallery.php') ?>>Атмосфера</a>
      <a href="reviews.php"<?= _nav_active('reviews.php') ?>>Отзывы</a>
      <a href="contacts.php"<?= _nav_active('contacts.php') ?>>Контакты</a>
      <span class="nav-divider"></span>
      <?php if (is_admin_logged_in()): ?>
        <a href="admin_panel.php" class="nav-profile-btn" title="Управление">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3c-1.7 0-3 1.3-3 3s1.3 3 3 3 3-1.3 3-3-1.3-3-3-3zM12 12c-5.3 0-8 2.7-8 4v1h16v-1c0-1.3-2.7-4-8-4z" fill="currentColor" stroke="none"/></svg>
          Управление<?= $_nav_pending_badge ?>
        </a>
      <?php elseif (is_user_logged_in()): ?>
        <a href="profile.php" class="nav-profile-btn <?= $_nav_current === 'profile.php' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="none"><path d="M12 3c-1.7 0-3 1.3-3 3s1.3 3 3 3 3-1.3 3-3-1.3-3-3-3zM12 12c-5.3 0-8 2.7-8 4v1h16v-1c0-1.3-2.7-4-8-4z"/></svg>
          Мой уголок<?= $_nav_user_notif_badge ?>
        </a>
      <?php else: ?>
        <a href="login.php" class="nav-profile-btn">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
          Войти
        </a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<script>
// Close mobile nav on outside click
document.addEventListener('click', function(e) {
  var nav = document.getElementById('mainNav');
  var btn = document.getElementById('burger');
  if (nav && btn && !nav.contains(e.target) && !btn.contains(e.target)) {
    nav.classList.remove('open');
    btn.classList.remove('open');
  }
});
// Close on link click
document.addEventListener('DOMContentLoaded', function() {
  var nav = document.getElementById('mainNav');
  var btn = document.getElementById('burger');
  if (nav) nav.querySelectorAll('a').forEach(function(a) {
    a.addEventListener('click', function() {
      nav.classList.remove('open');
      if (btn) btn.classList.remove('open');
    });
  });
});
</script>

<!-- Плавающая корзина (только когда не пустая) -->
<?php if ($_nav_cart_count > 0): ?>
<a href="cart.php" class="float-cart" title="Корзина (<?= $_nav_cart_count ?>)">
  <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6">
    <path d="M3 4h2l2.5 12h11l2.5-9H6"/>
    <circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>
  </svg>
  <span class="badge" data-cart-badge><?= $_nav_cart_count ?></span>
</a>
<?php else: ?>
<!-- Пустая корзина: badge обновляется JS при добавлении -->
<a href="cart.php" class="float-cart" title="Корзина" id="floatCart" style="display:none;">
  <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6">
    <path d="M3 4h2l2.5 12h11l2.5-9H6"/>
    <circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>
  </svg>
  <span class="badge" data-cart-badge>0</span>
</a>
<script>
// Показываем корзину как только в неё добавили
(function(){
  const btn = document.getElementById('floatCart');
  if (!btn) return;
  const orig = atmosphere && atmosphere.add ? atmosphere.add : null;
  document.addEventListener('cartUpdated', function(e) {
    if (e.detail && e.detail.count > 0) btn.style.display = '';
  });
})();
</script>
<?php endif; ?>
