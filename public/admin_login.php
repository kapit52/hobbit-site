<?php
// Отдельная cookie-сессия для админки (см. includes/admin_auth.php)
session_name('SHIREADMIN');
session_start();
require_once 'includes/auth_admin.php';
require_once 'db.php';
if (is_admin_logged_in()) { header('Location: admin_panel.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Введите почту (или логин) и пароль администратора';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE (username = ? OR email = ?) AND role = 'admin'");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($user && password_verify($password, $user['password'])) {
            set_admin_session((int)$user['id'], $user['username']);
            header('Location: admin_panel.php');
            exit;
        }
        $error = 'Неверный логин или пароль, либо нет прав администратора';
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Вход · Управление таверной</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/account.css">
<style>
  .auth-scene { background: linear-gradient(160deg, #1f1109, #2c1810); }
  .auth-scene .scene-photo .placeholder-photo {
    background: repeating-linear-gradient(135deg, #2c1810 0, #2c1810 14px, #1f1109 14px, #1f1109 28px);
  }
</style>
</head>
<body>
<div class="auth-wrap">
  <aside class="auth-scene">
    <div class="scene-photo">
      <img src="images/h.jpg" alt="" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;opacity:0.4;">
      <div style="position:absolute;inset:0;background:linear-gradient(160deg,rgba(20,10,5,0.75),rgba(20,10,5,0.9));z-index:1;"></div>
    </div>
    <div class="firefly-stage" data-count="10"></div>
    <a href="index.php" class="brand" style="position:relative;z-index:2;">
      <img src="assets/brand-mark.svg" alt="">
      <span>Ширский уголок</span>
    </a>
    <div class="scene-copy" style="position:relative;z-index:2;">
      <div class="eyebrow" style="color:#ffd98a;">Управление таверной</div>
      <h2>Панель хозяина</h2>
      <p>Только для сотрудников таверны. Обычные гости входят через <a href="login.php" style="color:#ffd98a;">страницу входа</a>.</p>
    </div>
    <div class="scene-foot" style="position:relative;z-index:2;">«добрый хозяин знает всё о своей таверне»</div>
  </aside>

  <main class="auth-panel">
    <div class="auth-card" style="max-width:400px;">
      <div class="eyebrow" style="margin-bottom:12px;">Только администраторы</div>
      <h1 style="font-size:1.8rem;margin-bottom:6px;">Вход в управление</h1>
      <p class="lede">Роль «Администратор» обязательна.</p>

      <?php if ($error): ?><div class="error-message" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST" class="auth-form on">
        <div class="field">
          <label>Почта или логин администратора</label>
          <input type="text" name="username" required autofocus autocomplete="username" placeholder="admin@shire-corner.local или admin">
        </div>
        <div class="field">
          <label>Пароль</label>
          <input type="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:8px;">Войти в управление</button>
      </form>

      <a href="index.php" class="auth-back" style="margin-top:24px;">← Вернуться на сайт</a>
    </div>
  </main>
</div>
<script src="atmosphere.js"></script>
</body>
</html>