<?php
session_start();
include 'db.php';
require_once 'includes/auth_user.php';

$error = '';
$return_to = htmlspecialchars($_GET['return_to'] ?? $_POST['return_to'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) {
        $error = 'Заполните почту и пароль';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $role = $user['role'] ?? 'user';
                set_user_session((int)$user['id'], $user['username'], $role);
                $returnTo = $_POST['return_to'] ?? '';
                // Админа без явного return_to ведём сразу в управление.
                if ($role === 'admin' && $returnTo === '') {
                    header('Location: admin_panel.php');
                    exit;
                }
                redirect_after_auth($returnTo);
            } else {
                $error = 'Неверный пароль';
            }
        } else {
            $error = 'Пользователь не найден';
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Вход · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/account.css">
</head>
<body>

<div class="auth-wrap">
  <!-- Атмосферная сторона -->
  <aside class="auth-scene">
    <div class="scene-photo">
      <img src="images/h.jpg" alt="" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;">
      <div style="position:absolute;inset:0;background:linear-gradient(160deg,rgba(20,30,18,0.6),rgba(20,30,18,0.85));z-index:1;"></div>
    </div>
    <div class="firefly-stage" data-count="14"></div>

    <a href="index.php" class="brand" style="position:relative;z-index:2;">
      <img src="assets/brand-mark.svg" alt="">
      <span>Ширский уголок</span>
    </a>

    <div class="scene-copy" style="position:relative;z-index:2;">
      <div class="eyebrow" style="color:#ffd98a;">Двери всегда открыты</div>
      <h2>С возвращением, добрый путник</h2>
      <p>Войди в свой уголок: здесь хранятся твои брони у камина, любимые блюда и память обо всех тёплых вечерах.</p>
    </div>

    <div class="scene-foot" style="position:relative;z-index:2;">«у нас всегда найдётся место за столом»</div>
  </aside>

  <!-- Форма -->
  <main class="auth-panel">
    <div class="auth-card">
      <div class="auth-tabs">
        <button class="on" onclick="window.location='login.php<?= $return_to ? '?return_to='.urlencode($return_to) : '' ?>'">Войти</button>
        <button onclick="window.location='register.php<?= $return_to ? '?return_to='.urlencode($return_to) : '' ?>'">Завести уголок</button>
      </div>

      <form class="auth-form on" method="POST" action="">
        <?php if ($return_to): ?><input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to) ?>"><?php endif; ?>
        <div class="eyebrow">Свои да придут</div>
        <h1>Вход в кабинет</h1>
        <p class="lede">Рады видеть снова. Назовись — и проходи к огню.</p>

        <?php if ($error): ?><div class="error-message" style="margin-bottom:16px;"><?= $error ?></div><?php endif; ?>

        <div class="field">
          <label>Почта</label>
          <input type="email" name="username" placeholder="putnik@pochta.ru" required autocomplete="email">
        </div>
        <div class="field">
          <label>Пароль</label>
          <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin:-4px 0 20px;gap:12px;flex-wrap:wrap;">
          <label style="display:inline-flex;align-items:center;gap:8px;font-size:0.88rem;color:var(--ink-soft);cursor:pointer;">
            <input type="checkbox" name="remember" style="width:16px;height:16px;accent-color:var(--amber);"> Запомнить меня
          </label>
          <a href="#" style="font-size:0.85rem;color:var(--amber-deep);border-bottom-color:transparent;">Забыл пароль?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Войти в уголок</button>

        <div class="auth-divider">или</div>
        <div class="auth-socials">
          <a href="https://vk.ru/club238868467" target="_blank" rel="noopener" style="flex:1;padding:12px;border:1px solid #e0e0ff;background:var(--parch-50);border-radius:var(--r-sm);font-family:var(--font-display);letter-spacing:0.08em;font-size:0.82rem;color:var(--ink-soft);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:9px;transition:all 0.18s;text-decoration:none;" onmouseover="this.style.borderColor='#0077ff'" onmouseout="this.style.borderColor='#e0e0ff'">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="#0077ff"><path d="M13 18c-6 0-9-4-9-12h3c0 5 2 8 4 8V6h3v3c2-1 4-3 4-3l2 1c-1 2-3 4-5 5 2 1 4 3 5 5l-2 1c0 0-2-2-4-3v3h-1z"/></svg>
            Сообщество ВК
          </a>
        </div>

        <a href="index.php" class="auth-back">← Вернуться на сайт</a>
      </form>
    </div>
  </main>
</div>

<script src="atmosphere.js"></script>
</body>
</html>