<?php
session_start();
include 'db.php';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Правила таверны · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .rules-hero{text-align:center;padding:50px 0 30px;background:linear-gradient(180deg,rgba(60,80,50,0.12),transparent),var(--parch-100);}
  .rules-hero h1{margin-bottom:10px;}
  .rules-hero .lede{max-width:620px;margin:0 auto;color:var(--ink-soft);font-style:italic;font-size:1.1rem;}
  .rules-wrap{max-width:860px;margin:0 auto;padding:50px 0 70px;}
  .rule-card{background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);padding:30px 34px;margin-bottom:22px;box-shadow:var(--shadow-soft);}
  .rule-card h2{display:flex;align-items:center;gap:14px;font-size:1.4rem;margin:0 0 14px;}
  .rule-card h2 .n{flex-shrink:0;width:38px;height:38px;border-radius:50%;background:var(--moss-soft);color:var(--forest);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1rem;}
  .rule-card ul{margin:0;padding-left:22px;}
  .rule-card li{padding:5px 0;color:var(--ink-soft);line-height:1.6;}
  .rule-card p{color:var(--ink-soft);line-height:1.7;margin:0;}
  .rules-foot-note{text-align:center;color:var(--ink-mute);font-style:italic;margin-top:10px;}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<section class="rules-hero">
  <div class="container">
    <div class="breadcrumbs" style="justify-content:center;padding:0 0 14px;"><a href="index.php">Главная</a><span class="sep">/</span><span>Правила таверны</span></div>
    <div class="eyebrow">Добрые порядки</div>
    <h1>Правила таверны</h1>
    <p class="lede">У нас уютно и по-домашнему. Несколько простых правил помогают, чтобы каждому путнику было тепло и хорошо.</p>
  </div>
</section>

<div class="container">
  <div class="rules-wrap">

    <div class="rule-card">
      <h2><span class="n">1</span>Бронирование стола</h2>
      <ul>
        <li>Бронь подтверждается после нашего звонка или сообщения — обычно в течение 10 минут.</li>
        <li>Стол держим 15 минут после назначенного времени, дальше можем предложить его другим гостям.</li>
        <li>Отменить или перенести бронь можно в личном кабинете или по телефону — лучше заранее.</li>
        <li>Для больших компаний (от 8 человек) бронируйте, пожалуйста, заблаговременно.</li>
      </ul>
    </div>

    <div class="rule-card">
      <h2><span class="n">2</span>Заказы и оплата</h2>
      <ul>
        <li>Заказы оформляются через корзину; статус всегда виден в личном кабинете.</li>
        <li>Оплата — наличными или картой при получении/в зале.</li>
        <li>Промокоды применяются в корзине к сумме блюд и не суммируются между собой.</li>
        <li>Если что-то пошло не так — сразу скажите нам, исправим.</li>
      </ul>
    </div>

    <div class="rule-card">
      <h2><span class="n">3</span>Гостям и атмосфера</h2>
      <ul>
        <li>Мы рады гостям любого возраста; для малышей найдётся детский стульчик — укажите в пожеланиях.</li>
        <li>Берегите уют: громкая музыка и шумные игры — повод выйти на террасу.</li>
        <li>О любых аллергиях предупреждайте заранее — шеф подберёт безопасное блюдо.</li>
        <li>Курение — только в отведённых местах на улице.</li>
      </ul>
    </div>

    <div class="rule-card">
      <h2><span class="n">4</span>Отзывы и личные данные</h2>
      <ul>
        <li>Отзывы оставляют зарегистрированные гости; мы публикуем их без правок.</li>
        <li>Прикладывая фото к отзыву, вы разрешаете показывать его другим гостям на сайте.</li>
        <li>Ваши контакты используются только для связи по броням и заказам и не передаются третьим лицам.</li>
      </ul>
    </div>

    <p class="rules-foot-note">Если остались вопросы —<a href="contacts.php">напишите или позвоните нам</a>. Двери таверны всегда открыты.</p>
  </div>
</div>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div><div class="brand" style="margin-bottom:14px;padding:0;"><img src="assets/brand-mark.svg" alt="" class="brand-mark"><span>Ширский уголок</span></div><p style="color:var(--parch-200);font-size:0.95rem;">Уютная таверна для добрых путников.</p></div>
      <div><h4>Разделы</h4><ul><li><a href="menu.php">Меню</a></li><li><a href="booking.php">Бронь стола</a></li><li><a href="gallery.php">Галерея</a></li><li><a href="reviews.php">Отзывы</a></li></ul></div>
      <div><h4>Контакты</h4><ul><li>пер. Зелёного Холма, 7</li><li><a href="tel:+78124567890">+7 (812) 456-78-90</a></li></ul></div>
      <div><h4>Бронь</h4><a href="booking.php" class="btn btn-primary btn-sm">Забронировать</a></div>
    </div>
    <div class="footer-bottom"><div>© 1893 — <?= date('Y') ?> Таверна «Ширский уголок»</div><div><a href="rules.php" style="color:var(--ink-faint);">Правила таверны</a></div></div>
  </div>
</footer>
<script src="atmosphere.js"></script>
</body>
</html>
