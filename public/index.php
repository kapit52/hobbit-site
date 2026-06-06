<?php
session_start();
include 'db.php';
require_once 'includes/site_images.php';

// Динамические часы работы (московское время)
$tz = new DateTimeZone('Europe/Moscow');
$now_dt = new DateTime('now', $tz);
$hour = (int)$now_dt->format('G');
$dow  = (int)$now_dt->format('N'); // 1=Mon, 7=Sun
$is_open = false;
$status_text = '';
if ($dow <= 4) {
    $is_open = ($hour >= 12 && $hour < 23);
    $kitchen_text = 'кухня до 22:30';
    $close_text = '23:00';
} elseif ($dow <= 6) {
    $is_open = ($hour >= 12 || $hour < 2);
    $kitchen_text = 'кухня до 01:30';
    $close_text = '02:00';
} else {
    $is_open = ($hour >= 13 && $hour < 22);
    $kitchen_text = 'кухня до 21:30';
    $close_text = '22:00';
}
$status_text = $is_open ? 'Сейчас открыто · ' . $kitchen_text : 'Сейчас закрыто · открываемся с 12:00';
$status_color = $is_open ? '#4caf50' : '#c25420';

// Проверяем наличие новых колонок (совместимость с не-мигрированной БД)
$_has_new_cols = false;
$_col_check = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'is_special'");
if ($_col_check && $_col_check->num_rows > 0) $_has_new_cols = true;

// Блюда дня (is_special=1 или первые 3 из горячих)
$specials = [];
if ($_has_new_cols) {
    $stmt = $conn->prepare("SELECT id, title, description, price, weight, badge, image_path FROM menu_items WHERE is_special = 1 AND category != 'decor' LIMIT 3");
    if ($stmt && $stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $specials[] = $r;
        $stmt->close();
    }
}
if (empty($specials)) {
    $cols = $_has_new_cols ? "id, title, description, price, weight, badge, image_path" : "id, title, NULL AS description, price, NULL AS weight, NULL AS badge, image_path";
    $stmt = $conn->prepare("SELECT $cols FROM menu_items WHERE category != 'decor' LIMIT 3");
    if ($stmt && $stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $specials[] = $r;
        $stmt->close();
    }
}

// Последние отзывы
$reviews = [];
$_has_rating = false;
$_rc = $conn->query("SHOW COLUMNS FROM reviews LIKE 'rating'");
if ($_rc && $_rc->num_rows > 0) $_has_rating = true;

if ($_has_rating) {
    $res = $conn->query("SELECT r.id, r.name, r.review, COALESCE(r.rating,5) AS rating,
        DATE_FORMAT(COALESCE(r.created_at, NOW()), '%e %M') AS dt
        FROM reviews r ORDER BY r.id DESC LIMIT 3");
} else {
    $res = $conn->query("SELECT r.id, r.name, r.review, 5 AS rating,
        DATE_FORMAT(NOW(), '%e %M') AS dt
        FROM reviews r ORDER BY r.id DESC LIMIT 3");
}
if ($res) while ($r = $res->fetch_assoc()) $reviews[] = $r;

$months_ru = ['January'=>'января','February'=>'февраля','March'=>'марта','April'=>'апреля',
    'May'=>'мая','June'=>'июня','July'=>'июля','August'=>'августа',
    'September'=>'сентября','October'=>'октября','November'=>'ноября','December'=>'декабря'];

function ru_date($dt) {
    global $months_ru;
    foreach ($months_ru as $en => $ru) $dt = str_replace($en, $ru, $dt);
    return $dt;
}

// Всего отзывов
$total_reviews = 0;
$r2 = $conn->query("SELECT COUNT(*) AS c FROM reviews");
if ($r2) $total_reviews = (int)$r2->fetch_assoc()['c'];

// Изображения для галереи (из gallery_images, категория gallery)
$gallery_imgs = [];
try {
    $res = $conn->query("SELECT image_path, label AS title FROM gallery_images WHERE category = 'gallery' AND image_path != '' AND slot_key NOT IN ('hero-main','legend-hostess') ORDER BY sort_order ASC, id ASC LIMIT 6");
    if ($res) while ($r = $res->fetch_assoc()) $gallery_imgs[] = $r;
} catch (mysqli_sql_exception $e) {
    // Таблица ещё не создана (например, до первого визита в админку) — показываем плейсхолдеры
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Ширский уголок — таверна для добрых путников</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .hero {
    position: relative; min-height: 92vh; overflow: hidden;
    color: var(--parch-50); isolation: isolate;
  }
  .hero-bg {
    position: absolute; inset: -10% 0 0; z-index: -2;
  }
  /* Затемняющий слой вынесен в ::after, чтобы его можно было плавно убирать кнопкой-глазом */
  .hero-bg::after {
    content: ""; position: absolute; inset: 0;
    background-image: linear-gradient(180deg, rgba(10,18,10,0.70) 0%, rgba(10,18,10,0.75) 50%, rgba(10,18,10,0.45) 75%, var(--parch-100) 100%);
    transition: opacity 0.7s ease;
  }
  .hero-bg .hero-img {
    position: absolute; inset: 0; z-index: -3;
    background: repeating-linear-gradient(135deg,#3a4d2e 0,#3a4d2e 14px,#2e3d24 14px,#2e3d24 28px);
  }
  .hero-bg .hero-img img { width:100%; height:100%; object-fit:cover; opacity:0.7; }
  .hero-content {
    position: relative; z-index: 3; max-width: var(--max-w); margin: 0 auto;
    padding: 140px 28px 80px;
    display: grid; grid-template-columns: 1.4fr 1fr; gap: 60px; align-items: center;
  }
  .hero-title { font-size: clamp(3rem,7.5vw,6.5rem); line-height: 0.95; letter-spacing: 0.01em; margin-bottom: 24px; text-shadow: 0 4px 30px rgba(0,0,0,0.45); color: var(--parch-50); }
  .hero-title em { font-family: 'Caveat',cursive; font-style: normal; color: #ffd98a; font-size: 0.7em; display: block; letter-spacing: 0; font-weight: 500; }
  .hero-eyebrow { color: #ffd98a; margin-bottom: 22px; }
  .hero-lede { font-size: 1.2rem; line-height: 1.6; color: var(--parch-200); max-width: 540px; margin-bottom: 38px; font-style: italic; text-shadow: 0 2px 20px rgba(0,0,0,0.8), 0 1px 4px rgba(0,0,0,0.9); }
  .hero-ctas { display: flex; gap: 14px; flex-wrap: wrap; }
  .hero-card { background: rgba(244,236,216,0.96); color: var(--ink); border: 1px solid var(--gold); border-radius: var(--r-md); padding: 32px; box-shadow: 0 30px 60px -20px rgba(0,0,0,0.6); backdrop-filter: blur(6px); position: relative; }
  .hero-card::before { content:""; position:absolute; inset:6px; border:1px solid var(--line); border-radius:14px; pointer-events:none; }
  .hours-list { list-style:none; padding:0; margin:16px 0 0; font-size:0.95rem; }
  .hours-list li { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed var(--line); }
  .hours-list li:last-child { border-bottom:none; }
  .hours-list .day { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.12em; font-size:0.78rem; color:var(--ink-soft); }
  .hero-meta { margin-top:22px; padding-top:18px; border-top:1px solid var(--line); display:flex; align-items:center; gap:12px; font-size:0.92rem; }
  .hero-meta .open { width:8px;height:8px;border-radius:50%;background:#4caf50;box-shadow:0 0 8px #4caf50; }

  .legend { position:relative; overflow:hidden; }
  .legend-grid { display:grid; grid-template-columns:1fr 1fr; gap:80px; align-items:center; }
  .legend-text { position:relative; z-index:2; }
  .legend .drop { float:left; font-family:var(--font-display); font-size:4.5rem; line-height:0.9; color:var(--amber-deep); padding-right:14px; padding-top:6px; }
  .legend-art { aspect-ratio:4/5; position:relative; border-radius:var(--r-md); overflow:hidden; border:1px solid var(--line); box-shadow:var(--shadow-card); }
  .legend-art::before { content:""; position:absolute; inset:0; background:linear-gradient(180deg,transparent 50%,rgba(44,24,16,0.4) 100%); z-index:2; }
  .legend-art .caption { position:absolute; bottom:22px; left:22px; right:22px; color:var(--parch-50); font-family:var(--font-hand); font-size:1.25rem; z-index:3; text-shadow:0 2px 8px rgba(0,0,0,0.6); }
  .legend-art img { width:100%; height:100%; object-fit:cover; }

  .specials { background:linear-gradient(180deg,rgba(107,142,78,0.08),rgba(107,142,78,0.16) 100%),var(--parch-100); position:relative; overflow:hidden; }
  .specials::before,.specials::after { content:""; position:absolute; width:280px; height:280px; background:url('assets/vine-corner.svg') no-repeat; pointer-events:none; opacity:0.5; }
  .specials::before { top:0; left:0; }
  .specials::after  { bottom:0; right:0; transform:scale(-1,-1); }
  .specials-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:28px; position:relative; z-index:2; }
  .dish-card { background:var(--parch-50); border:1px solid var(--line); border-radius:var(--r-md); overflow:hidden; box-shadow:var(--shadow-soft); display:flex; flex-direction:column; transition:transform 0.35s,box-shadow 0.35s; position:relative; }
  .dish-card:hover { transform:translateY(-6px); box-shadow:var(--shadow-card); }
  .dish-card .dish-photo { aspect-ratio:4/3; position:relative; overflow:hidden; }
  .dish-card .dish-photo img { width:100%; height:100%; object-fit:cover; transition:transform 0.5s; }
  .dish-card:hover .dish-photo img { transform:scale(1.05); }
  .dish-card .dish-tag { position:absolute; top:14px; left:14px; z-index:2; }
  .dish-card .dish-body { padding:22px 24px 24px; display:flex; flex-direction:column; flex:1; }
  .dish-card h3 { font-size:1.4rem; margin-bottom:8px; }
  .dish-card .desc { color:var(--ink-mute); font-size:0.98rem; margin-bottom:20px; flex:1; }
  .dish-card .dish-foot { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-top:auto; }
  .dish-card .price-row { display:flex; flex-direction:column; gap:2px; }
  .dish-card .weight { font-family:var(--font-display); font-size:0.7rem; letter-spacing:0.15em; color:var(--ink-mute); text-transform:uppercase; }

  .atmosphere { background:var(--ink); color:var(--parch-100); position:relative; }
  .atmosphere h2,.atmosphere .eyebrow { color:var(--parch-50); }
  .atmosphere .eyebrow { color:#ffd98a; }
  .gallery-mosaic { display:grid; grid-template-columns:repeat(4,1fr); grid-template-rows:240px 240px; gap:14px; }
  .gallery-mosaic > div { border-radius:var(--r-md); overflow:hidden; position:relative; border:1px solid rgba(184,134,11,0.3); }
  .gallery-mosaic > div:nth-child(1) { grid-row:span 2; }
  .gallery-mosaic > div:nth-child(4) { grid-row:span 2; grid-column:4; }
  .gallery-mosaic img,.gallery-mosaic .placeholder-photo { width:100%; height:100%; object-fit:cover; }

  .team-grid { display:grid; grid-template-columns:1.4fr 1fr 1fr 1fr; grid-auto-rows:1fr; gap:26px; align-items:stretch; }
  .chef-card { grid-row:span 2; background:var(--parch-50); border:1px solid var(--line); border-radius:var(--r-md); overflow:hidden; box-shadow:var(--shadow-card); display:flex; flex-direction:column; }
  .chef-card .photo { aspect-ratio:1; position:relative; overflow:hidden; }
  .chef-card .photo img { width:100%; height:100%; object-fit:cover; }
  .chef-card .body { padding:22px 24px 26px; }
  .chef-card .role { font-family:var(--font-display); font-size:0.72rem; letter-spacing:0.18em; text-transform:uppercase; color:var(--moss); margin-bottom:8px; }
  .person { background:var(--parch-50); border:1px solid var(--line); border-radius:var(--r-md); overflow:hidden; text-align:center; padding-bottom:22px; height:auto; }
  .person .photo { aspect-ratio:4/3; overflow:hidden; margin-bottom:16px; }
  .person .photo img { width:100%; height:100%; object-fit:cover; }
  .person .role { font-family:var(--font-display); font-size:0.68rem; letter-spacing:0.18em; text-transform:uppercase; color:var(--moss); margin:2px 0 8px; }
  .person h4 { margin-bottom:4px; font-size:1.05rem; }

  .cta-book { position:relative; overflow:hidden; background:linear-gradient(135deg,#2a3a1f 0%,#3d5a2a 60%,#4a6b3a 100%); color:var(--parch-50); border-radius:var(--r-lg); padding:60px; margin:0 28px; max-width:calc(var(--max-w) - 56px); margin-left:auto; margin-right:auto; isolation:isolate; }
  .cta-book::before { content:""; position:absolute; inset:0; background:url('assets/vine-corner.svg') no-repeat; background-size:320px; opacity:0.35; z-index:-1; }
  .cta-book::after  { content:""; position:absolute; inset:0; background:url('assets/vine-corner.svg') no-repeat top right; background-size:320px; transform:scaleX(-1); opacity:0.35; z-index:-1; }
  .cta-book h2 { color:var(--parch-50); }
  .cta-book .row { display:flex; align-items:center; justify-content:space-between; gap:40px; flex-wrap:wrap; }
  .cta-book p { color:var(--parch-200); font-size:1.1rem; margin:0; }

  .reviews-preview { background:var(--parch-50); }
  .reviews-row { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
  .review-card { background:var(--parch-100); border:1px solid var(--line); border-radius:var(--r-md); padding:34px 28px 28px; position:relative; }
  .review-card::before { content:"\201C"; position:absolute; top:-8px; left:16px; font-family:var(--font-display); font-size:3.4rem; line-height:1; color:var(--amber); opacity:0.4; z-index:0; pointer-events:none; }
  .review-card > * { position:relative; z-index:1; }
  .review-card .text { font-style:italic; font-size:1.05rem; color:var(--ink-soft); margin-bottom:22px; }
  .review-card .author { display:flex; align-items:center; gap:12px; }
  .review-card .avatar { width:42px; height:42px; border-radius:50%; background:var(--moss-soft); display:flex; align-items:center; justify-content:center; font-family:var(--font-display); color:var(--forest); font-weight:600; }
  .review-card .name  { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.1em; font-size:0.82rem; color:var(--ink); }
  .review-card .stars { color:var(--gold); font-size:0.95rem; letter-spacing:0.15em; }
  .review-card .date  { font-size:0.8rem; color:var(--ink-mute); }

  .find { background:var(--ink); color:var(--parch-100); }
  .find h2,.find .eyebrow { color:var(--parch-50); }
  .find .eyebrow { color:#ffd98a; }
  .find-grid { display:grid; grid-template-columns:1fr 1.3fr; gap:50px; align-items:stretch; }
  .find-info .row { padding:22px 0; border-bottom:1px solid rgba(201,184,147,0.18); display:grid; grid-template-columns:130px 1fr; gap:16px; align-items:baseline; }
  .find-info .row:last-child { border:none; }
  .find-info .lbl { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.18em; font-size:0.72rem; color:var(--gold); }
  .find-info .val { color:var(--parch-100); font-size:1.05rem; }
  .find-info .val a { color:var(--parch-50); border-bottom-color:rgba(244,236,216,0.3); }
  .find-map { background:#1a1d14; border:1px solid rgba(184,134,11,0.3); border-radius:var(--r-md); aspect-ratio:4/3; position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center; }
  .find-map .placeholder-photo { background:repeating-linear-gradient(90deg,#243018 0,#243018 1px,transparent 1px,transparent 60px),repeating-linear-gradient(0deg,#243018 0,#243018 1px,transparent 1px,transparent 60px),linear-gradient(135deg,#1a2a14,#0e1a08); }
  .pin { position:absolute; left:50%; top:50%; transform:translate(-50%,-100%); width:38px; height:38px; z-index:3; }
  .pin::before { content:""; position:absolute; inset:0; background:var(--ember); clip-path:path('M 19 0 C 8.5 0 0 8.5 0 19 C 0 30 19 38 19 38 C 19 38 38 30 38 19 C 38 8.5 29.5 0 19 0 Z'); box-shadow:0 6px 20px rgba(0,0,0,0.5); }
  .pin::after { content:""; position:absolute; left:50%; top:14px; width:12px; height:12px; background:var(--parch-50); border-radius:50%; transform:translateX(-50%); }
  .pin-ring { position:absolute; left:50%; top:100%; width:90px; height:30px; border-radius:50%; background:radial-gradient(ellipse,rgba(194,84,32,0.3),transparent 70%); transform:translate(-50%,-50%); animation:pin-pulse 2s ease-in-out infinite; }
  @keyframes pin-pulse { 0%,100%{opacity:0.3;transform:translate(-50%,-50%) scale(0.8);} 50%{opacity:1;transform:translate(-50%,-50%) scale(1.2);} }

  /* ===== Плавное появление блоков и надписей героя при загрузке ===== */
  @keyframes hero-rise {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .hero-anim { opacity: 0; animation: hero-rise 1.1s cubic-bezier(0.22,0.68,0.28,1) both; }
  .hero-d1 { animation-delay: 0.25s; }
  .hero-d2 { animation-delay: 0.45s; }
  .hero-d3 { animation-delay: 0.65s; }
  .hero-d4 { animation-delay: 0.85s; }
  .hero-d5 { animation-delay: 0.55s; }

  /* ===== Кнопка-глаз: убрать всё оформление и рассмотреть фото ===== */
  .hero-peek {
    position: absolute; top: 18px; right: 20px; z-index: 5;
    width: 42px; height: 42px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    background: rgba(16,24,14,0.25); color: rgba(244,236,216,0.6);
    border: 1px solid rgba(244,236,216,0.2); cursor: pointer; opacity: 0.4;
    -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
    transition: opacity 0.4s ease, color 0.4s ease, background 0.4s ease, transform 0.2s ease;
  }
  .hero-peek:hover { opacity: 1; color: #ffd98a; background: rgba(16,24,14,0.5); }
  .hero-peek:active { transform: scale(0.92); }
  .hero-peek:focus-visible { outline: 2px solid #ffd98a; outline-offset: 2px; opacity: 1; }
  .hero-peek svg { width: 21px; height: 21px; display: block; }
  .hero-peek .icon-off { display: none; }
  .hero.peek .hero-peek { opacity: 0.75; }
  .hero.peek .hero-peek .icon-on  { display: none; }
  .hero.peek .hero-peek .icon-off { display: block; }

  /* Режим «посмотреть на фото»: прячем оформление и осветляем снимок */
  .hero-content, .firefly-stage { transition: opacity 0.6s ease, transform 0.6s ease; }
  .hero.peek .hero-content { opacity: 0; transform: translateY(14px); pointer-events: none; }
  .hero.peek .firefly-stage { opacity: 0; }
  .hero-bg .hero-img img { transition: opacity 0.7s ease; }
  .hero.peek .hero-bg::after { opacity: 0; }
  .hero.peek .hero-bg .hero-img img { opacity: 1; }

  @media (prefers-reduced-motion: reduce) {
    .hero-anim { animation: none; opacity: 1; }
    .hero-content, .firefly-stage, .hero-bg::after, .hero-bg .hero-img img { transition: none; }
  }

  @media (max-width:900px) {
    .hero-content,.legend-grid,.specials-grid,.reviews-row,.find-grid,.team-grid { grid-template-columns:1fr; }
    .team-grid .chef-card { grid-row:auto; }
    .gallery-mosaic { grid-template-columns:1fr 1fr; grid-template-rows:repeat(3,200px); }
    .gallery-mosaic>div:nth-child(1){grid-row:auto;} .gallery-mosaic>div:nth-child(4){grid-row:auto;grid-column:auto;}
    .cta-book { padding:36px 28px; margin:0 18px; }
  }
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- ===== Hero ===== -->
<section class="hero">
  <div class="hero-bg" data-parallax="0.25">
    <div class="hero-img">
      <?php $heroImg = site_image($conn, 'hero-main') ?: (file_exists(__DIR__.'/images/h.jpg') ? 'images/h.jpg' : ''); ?>
      <?php if ($heroImg): ?>
        <img src="<?= htmlspecialchars($heroImg) ?>" alt="Таверна Ширский уголок">
      <?php else: ?>
        <div class="placeholder-photo" data-label="фасад таверны · 1920×1080"></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="firefly-stage" data-count="24"></div>
  <button class="hero-peek" id="heroPeek" type="button" aria-pressed="false" aria-label="Скрыть оформление и рассмотреть фото" title="Рассмотреть фото">
    <svg class="icon-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
      <path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>
    <svg class="icon-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
      <path d="M3 3l18 18"/>
      <path d="M10.6 6.1A9.6 9.6 0 0 1 12 6c7 0 10.5 7 10.5 7a17.6 17.6 0 0 1-3.3 3.9M6.2 7.3A17.4 17.4 0 0 0 1.5 12S5 19 12 19a9.4 9.4 0 0 0 4-0.9"/>
      <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
    </svg>
  </button>
  <div class="hero-content">
    <div class="hero-intro">
      <div class="eyebrow hero-eyebrow hero-anim hero-d1" style="text-shadow: 0 1px 8px rgba(0,0,0,0.7);">Таверна доброго пути · с 1893 года</div>
      <h1 class="hero-title hero-anim hero-d2">
        Ширский<br>уголок
        <em>добро пожаловать, путник</em>
      </h1>
      <p class="hero-lede hero-anim hero-d3">
        За зелёной дверью под старым дубом — тёплый очаг, медовый эль
        и яства, что согреют душу после долгой дороги. Заходи, у нас
        всегда найдётся место за столом.
      </p>
      <div class="hero-ctas hero-anim hero-d4">
        <a href="booking.php" class="btn btn-primary btn-lg">Забронировать стол</a>
        <a href="menu.php" class="btn btn-ghost-light btn-lg">Заглянуть в меню</a>
      </div>
    </div>
    <aside class="hero-card hero-anim hero-d5">
      <div class="eyebrow" style="color:var(--moss);justify-content:center;">часы и место</div>
      <h3 class="center">Огонь в очаге</h3>
      <p class="center muted" style="margin:0 0 6px;">пер. Зелёного Холма, 7</p>
      <ul class="hours-list">
        <li><span class="day">Пн — Чт</span><span>12:00 — 23:00</span></li>
        <li><span class="day">Пт — Сб</span><span>12:00 — 02:00</span></li>
        <li><span class="day">Воскресенье</span><span>13:00 — 22:00</span></li>
      </ul>
      <div class="hero-meta">
        <span class="open" id="statusDot" style="background:<?= $status_color ?>; box-shadow:0 0 8px <?= $status_color ?>;"></span>
        <span id="statusText"><?= htmlspecialchars($status_text) ?></span>
      </div>
    </aside>
  </div>
</section>

<!-- ===== Легенда таверны ===== -->
<section class="section legend">
  <img src="assets/vine-corner.svg" alt="" class="vine-corner tl" style="width:260px;">
  <img src="assets/vine-corner.svg" alt="" class="vine-corner br" style="width:260px;">
  <div class="container">
    <div class="legend-grid">
      <div class="legend-text reveal">
        <div class="eyebrow">Легенда заведения</div>
        <h2>Где живёт хорошая история, там сытно ужинают</h2>
        <p><span class="drop">К</span>огда-то давно, как рассказывает старая хозяйка, по этой дороге шёл усталый путник. У него остался последний кусок хлеба и пустая фляга, но впереди — ещё три дня пути. И тут он увидел, как из-под зелёного склона тянется дымок и пахнет тушёным мясом с травами.</p>
        <p>Дверь открылась прежде, чем он постучал. Внутри потрескивал очаг, играла скрипка, а на длинном столе уже остывала миска супа — будто кто-то знал, что он придёт. С того вечера каждый, кто переступает наш порог, — желанный гость. <em>Просто так у нас заведено.</em></p>
        <a href="contacts.php" class="btn btn-ghost" style="margin-top:16px;">Узнать больше о нас</a>
      </div>
      <div class="legend-art reveal">
        <?php $legendImg = site_image($conn, 'legend-hostess'); ?>
        <?php if ($legendImg): ?>
          <img src="<?= htmlspecialchars($legendImg) ?>" alt="Хозяйка у очага" style="width:100%;height:auto;display:block;border-radius:var(--r-md);">
        <?php else: ?>
          <div class="placeholder-photo" data-label="старая хозяйка у очага · 720×900"></div>
        <?php endif; ?>
        <div class="caption">«у нас всегда хватит на ещё одну тарелку»</div>
      </div>
    </div>
  </div>
</section>

<!-- ===== Особые блюда дня ===== -->
<section class="section specials">
  <div class="container">
    <div class="section-heading reveal">
      <div class="eyebrow">Сезонное меню</div>
      <h2>Сегодня на печи</h2>
      <p class="muted" style="max-width:580px;margin:0 auto;">Каждый день шеф выбирает на ярмарке самые свежие продукты и готовит из них блюда, которых не найдёшь в большом меню.</p>
    </div>
    <div class="specials-grid">
    <?php foreach ($specials as $i => $dish):
      $tags = [['Блюдо дня','tag-amber'],['Новинка','tag-berry'],['Любимое','']];
      [$tagLabel,$tagClass] = $tags[$i % 3];
      $imgSrc = (!empty($dish['image_path']) && file_exists(__DIR__.'/'.$dish['image_path'])) ? htmlspecialchars($dish['image_path']) : '';
      $badgeLabel = $dish['badge'] ?: $tagLabel;
    ?>
      <article class="dish-card reveal">
        <div class="dish-photo">
          <span class="tag <?= $tagClass ?> dish-tag"><?= htmlspecialchars($badgeLabel) ?></span>
          <?php if ($imgSrc): ?>
            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($dish['title']) ?>">
          <?php else: ?>
            <div class="placeholder-photo" data-label="<?= htmlspecialchars($dish['title']) ?>"></div>
          <?php endif; ?>
        </div>
        <div class="dish-body">
          <h3><?= htmlspecialchars($dish['title']) ?></h3>
          <p class="desc"><?= htmlspecialchars($dish['description'] ?? '') ?></p>
          <div class="dish-foot">
            <div class="price-row">
              <?php if (!empty($dish['weight'])): ?><span class="weight"><?= htmlspecialchars($dish['weight']) ?></span><?php endif; ?>
              <span class="price"><?= htmlspecialchars($dish['price']) ?><span class="currency"> ₽</span></span>
            </div>
            <button class="btn btn-primary btn-sm" data-add-to-cart data-id="<?= $dish['id'] ?>" data-name="<?= htmlspecialchars($dish['title']) ?>" data-price="<?= htmlspecialchars($dish['price']) ?>">
              <span class="label">В корзину</span>
            </button>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
    <div class="ornament">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 L14 9 L21 12 L14 15 L12 22 L10 15 L3 12 L10 9 Z" opacity="0.6"/></svg>
    </div>
    <div class="center">
      <a href="menu.php" class="btn btn-ghost">Всё меню таверны →</a>
    </div>
  </div>
</section>

<!-- ===== Атмосфера / Галерея ===== -->
<section class="section atmosphere">
  <div class="firefly-stage" data-count="16"></div>
  <div class="container">
    <div class="section-heading reveal">
      <div class="eyebrow">Загляни внутрь</div>
      <h2>Атмосфера уголка</h2>
      <p style="max-width:580px;margin:0 auto;color:var(--parch-200);">Низкие балки, светлячки в банках, скрипучие половицы и медный чайник на печке — у нас всё устроено для долгих разговоров.</p>
    </div>
    <div class="gallery-mosaic reveal">
      <?php
      $g_labels = ['зал с очагом','барная стойка','блюдо крупно','терраса вечером','круглая дверь','vip-зал у камина'];
      for ($gi = 0; $gi < 6; $gi++):
        $gimg = $gallery_imgs[$gi] ?? null;
      ?>
      <div>
        <?php if ($gimg && file_exists(__DIR__.'/'.$gimg['image_path'])): ?>
          <img src="<?= htmlspecialchars($gimg['image_path']) ?>" alt="<?= htmlspecialchars($gimg['title']) ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <div class="placeholder-photo" data-label="<?= htmlspecialchars($g_labels[$gi]) ?>" style="background:repeating-linear-gradient(45deg,#3a2817 0,#3a2817 12px,#2c1810 12px,#2c1810 24px);"></div>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
    </div>
    <div class="center" style="margin-top:36px;">
      <a href="gallery.php" class="btn btn-ghost-light">Открыть всю галерею</a>
    </div>
  </div>
</section>

<!-- ===== Команда таверны ===== -->
<section class="section">
  <div class="container">
    <div class="section-heading reveal">
      <div class="eyebrow">Те, кто кормит</div>
      <h2>Команда таверны</h2>
    </div>
    <div class="team-grid">
      <article class="chef-card reveal">
        <div class="photo">
          <?php $chefImg = site_image($conn, 'team-chef'); ?>
          <?php if ($chefImg): ?>
            <img src="<?= htmlspecialchars($chefImg) ?>" alt="Капитонова Елизавета Ильинична">
          <?php else: ?>
            <div class="placeholder-photo" data-label="шеф-повар · портрет"></div>
          <?php endif; ?>
        </div>
        <div class="body">
          <div class="role">Шеф-повар</div>
          <h3>Капитонова Елизавета Ильинична</h3>
          <p>Елизавета пришла к нам пятнадцать лет назад с десятком рецептов от бабушки и упрямством северянки. Сегодня её томлёная утка — повод приехать через полгорода.</p>
          <p class="muted" style="font-size:0.92rem;font-style:italic;">«Хорошее блюдо — это терпение, тепло и пара секретов.»</p>
        </div>
      </article>
      <?php
      $team = [
        ['Кондитер','Беляев Тимофей','Знает 36 видов пирогов и ни одного не повторит.','team-pastry'],
        ['Бариста','Соколова Анна','Варит кофе так, будто шепчет ему сказку.','team-barista'],
        ['Хостес','Дрозд Михаил','Помнит имена и любимые столики всех гостей.','team-host'],
        ['Сомелье','Хмельницкая Вера','Подскажет, чем запить грибной суп в дождливый вечер.','team-sommelier'],
        ['Музыкант','Стрижевский Олег','По пятницам играет на скрипке у очага.','team-musician'],
        ['Садовник','Иволгин Пётр','Растит травы и считает улиток по именам.','team-gardener'],
      ];
      foreach ($team as $member):
        $memberImg = site_image($conn, $member[3]); ?>
      <article class="person reveal">
        <div class="photo">
          <?php if ($memberImg): ?>
            <img src="<?= htmlspecialchars($memberImg) ?>" alt="<?= htmlspecialchars($member[1]) ?>">
          <?php else: ?>
            <div class="placeholder-photo" data-label="<?= htmlspecialchars($member[0]) ?>"></div>
          <?php endif; ?>
        </div>
        <div class="role"><?= htmlspecialchars($member[0]) ?></div>
        <h4><?= htmlspecialchars($member[1]) ?></h4>
        <p class="muted" style="font-size:0.9rem;padding:0 18px;"><?= htmlspecialchars($member[2]) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===== CTA Бронирование ===== -->
<section class="section-tight">
  <div class="cta-book reveal">
    <div class="firefly-stage" data-count="10" style="z-index:0;"></div>
    <div class="row" style="position:relative;z-index:2;">
      <div class="col-text" style="max-width:600px;">
        <div class="eyebrow" style="color:#ffd98a;">Лучше прийти подготовленным</div>
        <h2>Оставь место за длинным столом</h2>
        <p style="color:var(--parch-200);font-size:1.1rem;margin:0;">Особенно по пятницам и выходным — у нас бывает многолюдно, а места у камина разбирают первыми.</p>
      </div>
      <a href="booking.php" class="btn btn-primary btn-lg">Забронировать стол</a>
    </div>
  </div>
</section>

<!-- ===== Отзывы превью ===== -->
<section class="section reviews-preview">
  <div class="container">
    <div class="section-heading reveal">
      <div class="eyebrow">Слово гостей</div>
      <h2>Что говорят путники</h2>
    </div>
    <div class="reviews-row">
    <?php foreach ($reviews as $rev):
      $initials = mb_strtoupper(mb_substr($rev['name'], 0, 1));
      $stars = str_repeat('★', max(1, min(5, (int)$rev['rating']))) . str_repeat('☆', 5 - max(1, min(5, (int)$rev['rating'])));
    ?>
      <article class="review-card reveal">
        <p class="text"><?= nl2br(htmlspecialchars(mb_substr($rev['review'], 0, 200))) ?><?= mb_strlen($rev['review']) > 200 ? '…' : '' ?></p>
        <div class="author">
          <div class="avatar"><?= htmlspecialchars($initials) ?></div>
          <div>
            <div class="name"><?= htmlspecialchars($rev['name']) ?></div>
            <div class="stars"><?= $stars ?></div>
            <div class="date"><?= ru_date($rev['dt']) ?></div>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (empty($reviews)): ?>
      <div class="review-card reveal" style="grid-column:span 3;">
        <p class="text" style="text-align:center;color:var(--ink-mute);">Будь первым, кто оставит отзыв о нашей таверне!</p>
        <div class="center"><a href="reviews.php" class="btn btn-ghost btn-sm" style="margin-top:12px;">Написать отзыв</a></div>
      </div>
    <?php endif; ?>
    </div>
    <?php if ($total_reviews > 0): ?>
    <div class="center" style="margin-top:40px;">
      <a href="reviews.php" class="btn btn-ghost">Все <?= $total_reviews ?> отзыв<?= $total_reviews % 10 == 1 && $total_reviews % 100 != 11 ? 'а' : ($total_reviews % 10 >= 2 && $total_reviews % 10 <= 4 && ($total_reviews % 100 < 10 || $total_reviews % 100 >= 20) ? 'а' : 'ов') ?> →</a>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ===== Контакты + карта ===== -->
<section class="section find">
  <div class="container">
    <div class="find-grid">
      <div class="find-info reveal">
        <div class="eyebrow">Как нас найти</div>
        <h2>Под старым дубом, у поворота на холм</h2>
        <p style="color:var(--parch-200);margin-bottom:30px;">Третий поворот направо от площади, мимо мельницы и через мостик. Если потерялся — звони, мы выйдем встретить.</p>
        <div class="row"><div class="lbl">Адрес</div><div class="val">пер. Зелёного Холма, 7<br>Санкт-Петербург, 197342</div></div>
        <div class="row"><div class="lbl">Телефон</div><div class="val"><a href="tel:+78124567890">+7 (812) 456-78-90</a></div></div>
        <div class="row"><div class="lbl">Часы</div><div class="val">Пн—Чт 12:00–23:00 · Пт—Сб 12:00–02:00 · Вс 13:00–22:00</div></div>
        <div class="row"><div class="lbl">Соцсети</div><div class="val"><a href="https://vk.ru/club238868467" target="_blank" rel="noopener">ВКонтакте</a></div></div>
      </div>
      <div class="find-map reveal" style="overflow:hidden;border-radius:var(--r-md);">
        <iframe
          src="https://yandex.ru/map-widget/v1/?ll=30.352397%2C59.959005&z=16&pt=30.352397%2C59.959005,pm2rdm&mode=search&text=%D0%A1%D0%B0%D0%BD%D0%BA%D1%82-%D0%9F%D0%B5%D1%82%D0%B5%D1%80%D0%B1%D1%83%D1%80%D0%B3"
          title="Карта — Ширский уголок"
          allowfullscreen=""
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          style="width:100%;height:100%;border:none;display:block;position:absolute;inset:0;">
        </iframe>
      </div>
    </div>
  </div>
</section>

<!-- ===== Подвал ===== -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="brand" style="margin-bottom:14px;padding:0;">
          <img src="assets/brand-mark.svg" alt="" class="brand-mark">
          <span>Ширский уголок</span>
        </div>
        <p style="color:var(--parch-200);font-size:0.95rem;max-width:320px;">Уютная таверна для добрых путников. Очаг, музыка, мёд и яства — приходи как есть.</p>
      </div>
      <div>
        <h4>Разделы</h4>
        <ul>
          <li><a href="menu.php">Меню</a></li>
          <li><a href="booking.php">Бронь стола</a></li>
          <li><a href="gallery.php">Галерея</a></li>
          <li><a href="reviews.php">Отзывы</a></li>
        </ul>
      </div>
      <div>
        <h4>Контакты</h4>
        <ul>
          <li>пер. Зелёного Холма, 7</li>
          <li><a href="tel:+78124567890">+7 (812) 456-78-90</a></li>
          <li><a href="mailto:hello@shire-corner.ru">hello@shire-corner.ru</a></li>
          <li>Пн–Вс 12:00 — 23:00</li>
        </ul>
      </div>
      <div>
        <h4>Будь на связи</h4>
        <p style="font-size:0.9rem;color:var(--parch-200);margin-bottom:14px;">Сезонные ужины, авторские вечера — сообщим первыми.</p>
        <form onsubmit="event.preventDefault();this.querySelector('button').textContent='Записали ✓'">
          <div style="display:flex;gap:8px;">
            <input type="email" placeholder="твоя почта" style="flex:1;padding:10px 12px;border-radius:var(--r-sm);border:1px solid rgba(201,184,147,0.3);background:rgba(244,236,216,0.08);color:var(--parch-100);">
            <button class="btn btn-primary btn-sm" type="submit">→</button>
          </div>
        </form>
      </div>
    </div>
    <div class="footer-bottom">
      <div>© 1893 — <?= date('Y') ?> Таверна «Ширский уголок». Все права на хорошее настроение защищены.</div>
      <div><a href="rules.php" style="color:var(--ink-faint);">Правила таверны</a></div>
    </div>
  </div>
</footer>

<script src="atmosphere.js"></script>
<script>
// Кнопка-глаз: убрать все блоки и надписи, чтобы рассмотреть фотографию героя
(function(){
  var hero = document.querySelector('.hero');
  var btn  = document.getElementById('heroPeek');
  if (!hero || !btn) return;
  btn.addEventListener('click', function(){
    var on = hero.classList.toggle('peek');
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn.title = on ? 'Вернуть оформление' : 'Рассмотреть фото';
    btn.setAttribute('aria-label', on ? 'Вернуть оформление' : 'Скрыть оформление и рассмотреть фото');
  });
})();
</script>
<script>
// Живой статус работы таверны — пересчёт по московскому времени без перезагрузки
(function(){
  function moscowParts(){
    var fmt = new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Moscow',hour:'2-digit',minute:'2-digit',weekday:'short',hour12:false});
    var h=0,wd='';
    fmt.formatToParts(new Date()).forEach(function(p){
      if(p.type==='hour') h=parseInt(p.value,10);
      if(p.type==='weekday') wd=p.value;
    });
    if(h===24) h=0; // некоторые движки отдают «24» для полуночи
    var map={Mon:1,Tue:2,Wed:3,Thu:4,Fri:5,Sat:6,Sun:7};
    return {hour:h, dow:map[wd]||1};
  }
  function status(){
    var p=moscowParts(), open=false, kitchen='';
    if(p.dow<=4){ open=(p.hour>=12&&p.hour<23); kitchen='кухня до 22:30'; }
    else if(p.dow<=6){ open=(p.hour>=12||p.hour<2); kitchen='кухня до 01:30'; }
    else { open=(p.hour>=13&&p.hour<22); kitchen='кухня до 21:30'; }
    return {open:open, text: open ? ('Сейчас открыто · '+kitchen) : 'Сейчас закрыто · открываемся с 12:00', color: open ? '#4caf50' : '#c25420'};
  }
  function update(){
    var s=status(), dot=document.getElementById('statusDot'), txt=document.getElementById('statusText');
    if(dot){ dot.style.background=s.color; dot.style.boxShadow='0 0 8px '+s.color; }
    if(txt){ txt.textContent=s.text; }
  }
  update();
  setInterval(update, 30000);
})();
</script>
</body>
</html>
