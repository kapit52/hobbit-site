<?php
session_start();
include 'db.php';

// Определяем день недели и статус
$tz = new DateTimeZone('Europe/Moscow');
$now_dt = new DateTime('now', $tz);
$hour = (int)$now_dt->format('G');
$dow  = (int)$now_dt->format('N'); // 1=Mon, 7=Sun
$is_open = false;
if ($dow <= 4)      { $is_open = ($hour >= 12 && $hour < 23); }
elseif ($dow <= 6)  { $is_open = ($hour >= 12 || $hour < 2); }
else                { $is_open = ($hour >= 13 && $hour < 22); }
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Контакты · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .ct-hero{text-align:center;padding:50px 0 40px;background:linear-gradient(180deg,rgba(60,80,50,0.12),transparent),var(--parch-100);}
  .ct-hero h1{margin-bottom:10px;}
  .ct-hero .lede{max-width:600px;margin:0 auto;color:var(--ink-soft);font-style:italic;font-size:1.1rem;}
  .ct-map-wrap{margin-top:30px;border-radius:var(--r-md);overflow:hidden;border:1px solid var(--line);aspect-ratio:16/6;position:relative;background:#1a2a14;}
  .ct-map-wrap iframe{width:100%;height:100%;border:none;display:block;}
  .ct-map-wrap .placeholder-photo{background:repeating-linear-gradient(90deg,rgba(107,142,78,0.18) 0 1px,transparent 1px 60px),repeating-linear-gradient(0deg,rgba(107,142,78,0.18) 0 1px,transparent 1px 60px),radial-gradient(ellipse at 30% 40%,#2a3a1f 0%,#1a2a14 80%);}
  .ct-map-wrap .placeholder-photo::before{background:rgba(20,30,18,0.92);color:var(--parch-200);border-color:var(--parch-300);}
  .pin-big{position:absolute;left:50%;top:48%;transform:translate(-50%,-100%);z-index:3;}
  .pin-big .marker{width:56px;height:56px;background:var(--ember);clip-path:path('M 28 0 C 13 0 0 12 0 28 C 0 42 28 56 28 56 C 28 56 56 42 56 28 C 56 12 43 0 28 0 Z');box-shadow:0 8px 24px rgba(0,0,0,0.6);position:relative;display:flex;align-items:center;justify-content:center;padding-bottom:12px;font-size:18px;}
  .pin-big .ring{position:absolute;left:50%;top:100%;width:140px;height:50px;border-radius:50%;background:radial-gradient(ellipse,rgba(194,84,32,0.35),transparent 70%);transform:translate(-50%,-50%);animation:pin-pulse 2.2s ease-in-out infinite;}
  @keyframes pin-pulse{0%,100%{opacity:0.3;transform:translate(-50%,-50%) scale(0.7);}50%{opacity:1;transform:translate(-50%,-50%) scale(1.3);}}
  .ct-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:50px;padding:60px 0;}
  .ct-card{background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);padding:32px;box-shadow:var(--shadow-soft);margin-bottom:22px;}
  .ct-row{display:grid;grid-template-columns:50px 1fr auto;gap:18px;align-items:center;padding:18px 0;border-bottom:1px solid var(--line);}
  .ct-row:last-child{border-bottom:none;}
  .ct-row .ico{width:50px;height:50px;border-radius:50%;background:var(--parch-100);color:var(--amber-deep);display:flex;align-items:center;justify-content:center;border:1px solid var(--line);flex-shrink:0;}
  .ct-row .lbl{font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.15em;font-size:0.72rem;color:var(--ink-mute);margin-bottom:3px;}
  .ct-row .val{font-family:var(--font-display);font-size:1.1rem;color:var(--ink);}
  .ct-row .val a{color:var(--ink);border-bottom-color:var(--ink-faint);}
  .ct-row .action{font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.12em;font-size:0.72rem;color:var(--amber-deep);border:none;padding:8px 14px;background:var(--parch-100);border-radius:var(--r-sm);cursor:pointer;text-decoration:none;}
  .hours-table{width:100%;border-collapse:collapse;}
  .hours-table tr{border-bottom:1px dashed var(--line);}
  .hours-table tr:last-child{border:none;}
  .hours-table td{padding:12px 0;}
  .hours-table .day{font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.14em;font-size:0.82rem;color:var(--ink-soft);}
  .hours-table .time{text-align:right;font-family:var(--font-display);font-size:1rem;}
  .hours-table tr.today{background:rgba(184,134,11,0.08);}
  .hours-table tr.today .day{color:var(--amber-deep);padding-left:16px;position:relative;}
  .hours-table tr.today .day::before{content:"•";position:absolute;left:0;color:var(--amber-deep);font-size:1.4rem;line-height:1;top:50%;transform:translateY(-50%);}
  .ways{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
  .way{background:var(--parch-100);border:1px solid var(--line);border-radius:var(--r-sm);padding:18px;text-align:center;}
  .way .ico{font-size:1.8rem;margin-bottom:8px;}
  .way h4{font-size:0.85rem;text-transform:uppercase;letter-spacing:0.14em;margin-bottom:4px;}
  .way p{margin:0;font-size:0.85rem;color:var(--ink-mute);}
  .socials{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
  .soc{background:var(--parch-100);border:1px solid var(--line);border-radius:var(--r-sm);padding:18px 14px;text-align:center;text-decoration:none;transition:all 0.2s;display:block;}
  .soc:hover{background:var(--ink);color:var(--parch-50);border-color:var(--ink);transform:translateY(-2px);}
  .soc:hover .sname,.soc:hover .nick{color:var(--parch-50);}
  .soc .ico{font-size:1.6rem;margin-bottom:6px;}
  .soc .sname{font-family:var(--font-display);font-size:0.8rem;color:var(--ink);text-transform:uppercase;letter-spacing:0.12em;}
  .soc .nick{font-size:0.78rem;color:var(--ink-mute);margin-top:3px;}
  @media(max-width:900px){.ct-grid{grid-template-columns:1fr;}.ct-map-wrap{aspect-ratio:4/3;}.ways{grid-template-columns:1fr 1fr;}.socials{grid-template-columns:1fr 1fr;}}
  @media(max-width:640px){
    .ct-hero{padding:36px 0 24px;}
    .ct-map-wrap{aspect-ratio:1/1;}
    .ways{grid-template-columns:1fr;}
    .socials{grid-template-columns:1fr 1fr;}
    .ct-card{padding:18px;}
    .ct-row{grid-template-columns:38px 1fr;}
    .ct-row .action{display:none;}
  }
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<section class="ct-hero">
  <div class="container">
    <div class="breadcrumbs" style="justify-content:center;padding:0 0 14px;"><a href="index.php">Главная</a><span class="sep">/</span><span>Контакты</span></div>
    <div class="eyebrow">Где нас найти</div>
    <h1>Контакты</h1>
    <p class="lede">Третий поворот направо от площади, мимо мельницы и через мостик. Если потерялся — звони, выйдем встретить.</p>
  </div>
</section>

<div class="container">
  <!-- Карта на всю ширину -->
  <div class="ct-map-wrap">
    <!-- Яндекс карта: замените src на реальный iframe от Яндекс.Карт -->
    <!-- Чтобы получить iframe: откройте maps.yandex.ru, найдите ваш адрес, нажмите «Поделиться» → «Встроить» -->
    <iframe
      src="https://yandex.ru/map-widget/v1/?ll=30.352397%2C59.959005&z=16&pt=30.352397%2C59.959005,pm2rdm&mode=search&text=%D0%A1%D0%B0%D0%BD%D0%BA%D1%82-%D0%9F%D0%B5%D1%82%D0%B5%D1%80%D0%B1%D1%83%D1%80%D0%B3"
      title="Карта — Ширский уголок"
      allowfullscreen=""
      loading="lazy"
      referrerpolicy="no-referrer-when-downgrade"
      style="width:100%;height:100%;border:none;">
    </iframe>
    <div class="pin-big" style="display:none;"><div class="marker">🏠</div><div class="ring"></div></div>
  </div>

  <!-- Основная сетка -->
  <div class="ct-grid">
    <!-- Левая: контакты + часы + как добраться -->
    <div>
      <div class="ct-card">
        <div class="eyebrow" style="margin-bottom:20px;">Свяжитесь с нами</div>
        <div class="ct-row">
          <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="22" height="22"><path d="M12 2C8.1 2 5 5.1 5 9c0 5.3 7 13 7 13s7-7.7 7-13c0-3.9-3.1-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg></div>
          <div><div class="lbl">Адрес</div><div class="val">пер. Зелёного Холма, 7, Санкт-Петербург</div></div>
          <a href="https://yandex.ru/maps/" target="_blank" class="action">Маршрут</a>
        </div>
        <div class="ct-row">
          <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="22" height="22"><path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 3 4.2 2 2 0 0 1 5 2h3a2 2 0 0 1 2 1.7c.2 1.1.5 2.2 1 3.3A2 2 0 0 1 10.5 9l-1.3 1.3a16 16 0 0 0 6.6 6.6L17 15.5a2 2 0 0 1 2-.5c1 .5 2.1.8 3.3 1A2 2 0 0 1 22 16.9z"/></svg></div>
          <div><div class="lbl">Телефон</div><div class="val"><a href="tel:+78124567890">+7 (812) 456-78-90</a></div></div>
          <a href="tel:+78124567890" class="action">Позвонить</a>
        </div>
        <div class="ct-row">
          <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="22" height="22"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
          <div><div class="lbl">Email</div><div class="val"><a href="mailto:hello@shire-corner.ru">hello@shire-corner.ru</a></div></div>
          <a href="mailto:hello@shire-corner.ru" class="action">Написать</a>
        </div>
        <div class="ct-row">
          <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="22" height="22"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div>
            <div class="lbl">Статус</div>
            <div class="val" style="color:<?= $is_open ? '#4caf50' : 'var(--ember)' ?>;">
              <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:currentColor;margin-right:6px;vertical-align:middle;"></span>
              <?= $is_open ? 'Сейчас открыто' : 'Сейчас закрыто' ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Как добраться -->
      <div class="ct-card">
        <div class="eyebrow" style="margin-bottom:20px;">Как добраться</div>
        <div class="ways">
          <div class="way"><div class="ico">🚇</div><h4>Метро</h4><p>Ст. «Чкаловская», выход 2, 7 мин. пешком по ул. Зелёного Холма</p></div>
          <div class="way"><div class="ico">🚌</div><h4>Автобус</h4><p>Маршруты 10, 46, К-249. Остановка «Пер. Зелёного Холма»</p></div>
          <div class="way"><div class="ico">🚗</div><h4>Автомобиль</h4><p>Парковка перед таверной — 8 мест. В выходные — ул. Сенная</p></div>
        </div>
      </div>
    </div>

    <!-- Правая: часы + соцсети -->
    <div>
      <div class="ct-card">
        <div class="eyebrow" style="margin-bottom:20px;">Часы работы</div>
        <table class="hours-table">
          <tr class="<?= $dow <= 4 ? 'today' : '' ?>"><td class="day">Понедельник — Четверг</td><td class="time">12:00 — 23:00</td></tr>
          <tr class="<?= ($dow == 5 || $dow == 6) ? 'today' : '' ?>"><td class="day">Пятница — Суббота</td><td class="time">12:00 — 02:00</td></tr>
          <tr class="<?= $dow == 7 ? 'today' : '' ?>"><td class="day">Воскресенье</td><td class="time">13:00 — 22:00</td></tr>
        </table>
        <div style="margin-top:20px;padding-top:18px;border-top:1px dashed var(--line);font-size:0.9rem;color:var(--ink-mute);">
          🍳 Кухня закрывается за 30 минут до конца работы
        </div>
      </div>

      <div class="ct-card">
        <div class="eyebrow" style="margin-bottom:20px;">Мы в соцсетях</div>
        <div class="socials">
          <a href="https://vk.ru/club238868467" target="_blank" rel="noopener" class="soc"><div class="ico">🔵</div><div class="sname">ВКонтакте</div><div class="nick">@shire_corner</div></a>
        </div>
      </div>

      <div class="ct-card">
        <div class="eyebrow" style="margin-bottom:16px;">Реквизиты</div>
        <p style="font-size:0.88rem;color:var(--ink-mute);line-height:1.8;margin:0;">
          ИП Капитонова Е.И.<br>
          ИНН: 780112345678<br>
          ОГРНИП: 1237800123456<br>
          Юр. адрес: 197342, СПб, пер. Зелёного Холма, 7
        </p>
      </div>
    </div>
  </div>
</div>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div><div class="brand" style="margin-bottom:14px;padding:0;"><img src="assets/brand-mark.svg" alt="" class="brand-mark"><span>Ширский уголок</span></div><p style="color:var(--parch-200);font-size:0.95rem;">Уютная таверна для добрых путников.</p></div>
      <div><h4>Разделы</h4><ul><li><a href="menu.php">Меню</a></li><li><a href="booking.php">Бронь стола</a></li><li><a href="gallery.php">Галерея</a></li><li><a href="reviews.php">Отзывы</a></li></ul></div>
      <div><h4>Контакты</h4><ul><li>пер. Зелёного Холма, 7</li><li><a href="tel:+78124567890">+7 (812) 456-78-90</a></li><li><a href="mailto:hello@shire-corner.ru">hello@shire-corner.ru</a></li></ul></div>
      <div><h4>Бронь</h4><a href="booking.php" class="btn btn-primary btn-sm">Забронировать</a></div>
    </div>
    <div class="footer-bottom"><div>© 1893 — <?= date('Y') ?> Таверна «Ширский уголок»</div><div><a href="rules.php" style="color:var(--ink-faint);">Правила таверны</a></div></div>
  </div>
</footer>
<script src="atmosphere.js"></script>
</body>
</html>