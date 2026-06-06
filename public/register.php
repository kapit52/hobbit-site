<?php
session_start();
include 'db.php';
require_once 'includes/auth_user.php';
require_once 'includes/phone.php';

$error = '';
$return_to = htmlspecialchars($_GET['return_to'] ?? $_POST['return_to'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    [$phone, $phoneOk] = normalize_phone($_POST['phone'] ?? '');
    $username  = $email; // авторизация по почте — логин совпадает с email

    if (empty($email) || empty($password)) {
        $error = 'Заполните обязательные поля';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный адрес почты';
    } elseif (!$phoneOk) {
        $error = 'Телефон должен быть в формате +7 и 10 цифр';
    } elseif (mb_strlen($password) < 6) {
        $error = 'Пароль должен быть не короче 6 символов';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Пользователь с такой почтой уже существует';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $stmt->bind_param('sssss', $username, $email, $hash, $full_name, $phone);
            if ($stmt->execute()) {
                set_user_session($conn->insert_id, $username);
                $_SESSION['register_success'] = true;
                redirect_after_auth($_POST['return_to'] ?? '');
            } else {
                $error = 'Ошибка при регистрации: ' . $conn->error;
            }
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Регистрация · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/account.css">
</head>
<body>

<div class="auth-wrap">
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
      <div class="eyebrow" style="color:#ffd98a;">Новому гостю</div>
      <h2>Добро пожаловать в таверну</h2>
      <p>Заведи свой уголок — и таверна запомнит тебя навсегда. В подарок новому гостю — 200 медовых монет.</p>
    </div>
    <div class="scene-foot" style="position:relative;z-index:2;">«пара строк — и у тебя здесь будет свой угол»</div>
  </aside>

  <main class="auth-panel">
    <div class="auth-card">
      <div class="auth-tabs">
        <button onclick="window.location='login.php<?= $return_to ? '?return_to='.urlencode($return_to) : '' ?>'">Войти</button>
        <button class="on" onclick="window.location='register.php<?= $return_to ? '?return_to='.urlencode($return_to) : '' ?>'">Завести уголок</button>
      </div>

      <form class="auth-form on" method="POST" action="">
        <?php if ($return_to): ?><input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to) ?>"><?php endif; ?>
        <div class="eyebrow">Новому гостю</div>
        <h1>Заведи свой уголок</h1>
        <p class="lede">Пара строк — и таверна запомнит тебя навсегда.</p>

        <?php if ($error): ?><div class="error-message" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="field">
          <label>Как тебя звать *</label>
          <input type="text" name="full_name" placeholder="Имя путника" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="field-row">
          <div class="field">
            <label>Почта *</label>
            <input type="email" name="email" placeholder="putnik@pochta.ru" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email">
          </div>
          <div class="field">
            <label>Телефон</label>
            <input type="tel" name="phone" placeholder="+7 900 000-00-00" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>
        <div class="field">
          <label>Придумай пароль *</label>
          <input type="password" name="password" placeholder="не меньше 6 знаков" required autocomplete="new-password">
        </div>

        <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:20px;padding:14px;background:var(--parch-100);border-radius:var(--r-sm);border:1px solid var(--line);">
          <input type="checkbox" name="agree" required style="width:18px;height:18px;margin-top:2px;flex-shrink:0;accent-color:var(--amber);">
          <span style="font-size:0.88rem;color:var(--ink-soft);line-height:1.5;">
            Согласен с <a href="rules.php" target="_blank" style="color:var(--amber-deep);">правилами таверны</a> и не против получать вести о сезонных ужинах и авторских вечерах.
          </span>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Открыть уголок</button>

        <p class="muted" style="font-size:0.86rem;text-align:center;margin:18px 0 0;font-style:italic;">
          В подарок новому гостю — <strong style="color:var(--amber-deep);">200 медовых монет</strong> на первый ужин.
        </p>

        <a href="index.php" class="auth-back">← Вернуться на сайт</a>
      </form>
    </div>
  </main>
</div>

<script src="atmosphere.js"></script>
<script src="assets/phone-validate.js"></script>
</body>
</html>