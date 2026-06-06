<?php
session_start();
include 'db.php';

// Загруженные фото галереи (категория gallery, кроме именованных слотов главной)
$atmo_imgs = [];
try {
    $res = $conn->query("SELECT image_path, label FROM gallery_images WHERE category='gallery' AND image_path<>'' AND slot_key NOT IN ('hero-main','legend-hostess') ORDER BY sort_order ASC, id ASC");
    if ($res) while ($r = $res->fetch_assoc()) {
        if (file_exists(__DIR__.'/'.$r['image_path'])) $atmo_imgs[] = $r;
    }
} catch (mysqli_sql_exception $e) {
    // Таблица ещё не создана — покажем заглушки
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Атмосфера · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .gallery-hero{padding:60px 0 30px;text-align:center;background:var(--ink);color:var(--parch-100);position:relative;overflow:hidden;}
  .gallery-hero h1,.gallery-hero .eyebrow{color:var(--parch-50);}
  .gallery-hero .eyebrow{color:#ffd98a;}
  .gallery-hero p{color:var(--parch-200);max-width:560px;margin:0 auto;font-style:italic;}
  .mosaic{display:grid;grid-template-columns:repeat(4,1fr);grid-auto-rows:200px;grid-auto-flow:dense;gap:14px;padding:40px 0 80px;}
  .mosaic-item{border-radius:var(--r-md);overflow:hidden;border:1px solid rgba(184,134,11,0.25);position:relative;cursor:pointer;transition:transform 0.3s,box-shadow 0.3s;}
  .mosaic-item:hover{transform:translateY(-4px);box-shadow:var(--shadow-card);z-index:3;}
  /* хаотичная мозаика — фото то высокое, то широкое, то крупное; dense + равные ряды убирают «сжатые» ячейки */
  .mosaic-item:nth-child(6n+1){grid-row:span 2;}
  .mosaic-item:nth-child(6n+4){grid-column:span 2;}
  .mosaic-item:nth-child(12n+6){grid-row:span 2;grid-column:span 2;}
  .mosaic-item img{width:100%;height:100%;object-fit:cover;display:block;position:absolute;inset:0;transition:transform 0.5s;}
  .mosaic-item:hover img{transform:scale(1.05);}
  .mosaic-item .ph{background:repeating-linear-gradient(45deg,#3a2817 0,#3a2817 12px,#2c1810 12px,#2c1810 24px);position:absolute;inset:0;display:flex;align-items:center;justify-content:center;}
  .mosaic-item .caption{position:absolute;bottom:0;left:0;right:0;padding:14px;background:linear-gradient(transparent,rgba(20,12,6,0.7));color:var(--parch-50);font-family:var(--font-hand);font-size:1rem;opacity:0;transition:opacity 0.3s;z-index:2;pointer-events:none;}
  .mosaic-item:hover .caption{opacity:1;}
  @media(max-width:900px){.mosaic{grid-template-columns:repeat(2,1fr);grid-auto-rows:175px;}}
  @media(max-width:640px){.mosaic{gap:10px;grid-auto-rows:150px;}}

  /* Просмотрщик фото — лайтбокс с каруселью (как в соцсетях) */
  .lightbox{position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;padding:40px;background:rgba(12,8,4,0.94);backdrop-filter:blur(6px);opacity:0;visibility:hidden;transition:opacity 0.3s ease;}
  .lightbox.open{opacity:1;visibility:visible;}
  body.lb-lock{overflow:hidden;}
  .lb-stage{margin:0;display:flex;flex-direction:column;align-items:center;gap:16px;}
  .lb-img{max-width:90vw;max-height:78vh;width:auto;height:auto;border-radius:var(--r-md);border:1px solid rgba(184,134,11,0.35);box-shadow:0 24px 70px rgba(0,0,0,0.6);opacity:0;transform:scale(0.97);transition:opacity 0.28s,transform 0.28s;}
  .lb-img.show{opacity:1;transform:scale(1);}
  .lb-caption{color:var(--parch-50);font-family:var(--font-hand);font-size:1.35rem;text-align:center;max-width:80vw;min-height:1.2em;}
  .lb-close{position:absolute;top:18px;right:22px;width:46px;height:46px;border-radius:50%;border:1px solid rgba(184,134,11,0.4);background:rgba(20,12,6,0.6);color:var(--parch-50);font-size:1.7rem;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.2s,transform 0.3s;}
  .lb-close:hover{background:rgba(184,134,11,0.3);transform:rotate(90deg);}
  .lb-nav{position:absolute;top:50%;transform:translateY(-50%);width:54px;height:54px;border-radius:50%;border:1px solid rgba(184,134,11,0.4);background:rgba(20,12,6,0.55);color:var(--parch-50);font-size:1.4rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.2s,transform 0.2s;}
  .lb-nav:hover{background:rgba(184,134,11,0.35);}
  .lb-prev{left:20px;} .lb-next{right:20px;}
  .lb-prev:hover{transform:translateY(-50%) translateX(-3px);}
  .lb-next:hover{transform:translateY(-50%) translateX(3px);}
  .lb-counter{position:absolute;bottom:22px;left:50%;transform:translateX(-50%);color:var(--parch-200);letter-spacing:0.05em;font-size:0.95rem;}
  @media(max-width:640px){.lightbox{padding:14px;}.lb-nav{width:44px;height:44px;}.lb-prev{left:8px;}.lb-next{right:8px;}.lb-img{max-height:72vh;}.lb-close{top:10px;right:12px;}}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<section class="gallery-hero">
  <div class="firefly-stage" data-count="20"></div>
  <div class="container" style="position:relative;z-index:2;">
    <div class="breadcrumbs" style="justify-content:center;padding:0 0 20px;color:var(--parch-300);"><a href="index.php" style="color:var(--parch-300);">Главная</a><span class="sep">/</span><span>Атмосфера</span></div>
    <div class="eyebrow">Загляни внутрь</div>
    <h1 style="margin-bottom:12px;">Атмосфера уголка</h1>
    <p>Низкие балки, светлячки в банках, скрипучие половицы и медный чайник на печке — у нас всё устроено для долгих разговоров.</p>
  </div>
</section>

<div class="container">
  <?php if (empty($atmo_imgs)): ?>
  <p style="text-align:center;color:var(--ink-mute);font-style:italic;padding:20px 0 0;">Скоро здесь появятся фотографии нашей таверны</p>
  <?php endif; ?>
  <div class="mosaic">
    <?php if (!empty($atmo_imgs)): ?>
      <?php foreach ($atmo_imgs as $img): ?>
      <div class="mosaic-item">
        <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="<?= htmlspecialchars($img['label'] ?? '') ?>">
        <?php if (!empty($img['label'])): ?><div class="caption"><?= htmlspecialchars($img['label']) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <?php
      $atmo_labels = [
        'Зал у очага · вечер',
        'Барная стойка · медовый эль',
        'Очаг · живой огонь',
        'Терраса · летний вечер',
        'VIP-зал у камина',
        'Детали интерьера',
        'Скрипач у очага · пятница',
        'Кухня таверны',
        'Круглая дверь',
        'Дубовые балки',
        'Свечи и фонари',
        'Зимний сад',
      ];
      foreach ($atmo_labels as $label): ?>
      <div class="mosaic-item">
        <div class="ph"><div class="placeholder-photo" data-label="<?= htmlspecialchars($label) ?>" style="height:100%;background:repeating-linear-gradient(45deg,#3a2817 0,#3a2817 12px,#2c1810 12px,#2c1810 24px);"></div></div>
        <div class="caption"><?= htmlspecialchars($label) ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<section class="section" style="background:var(--parch-50);border-top:1px solid var(--line);">
  <div class="container">
    <div class="section-heading reveal">
      <div class="eyebrow">Вечера таверны</div>
      <h2>Особые события</h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:28px;">
      <?php
      $events = [
        ['Пятничный скрипач','Каждую пятницу с 19:00 — живая скрипка у очага. Особый уют, особое меню.'],
        ['Осенние ужины','По субботам в октябре — тематическое меню по мотивам лесных преданий.'],
        ['Детское утро','Воскресенье 11:00–14:00 — завтрак для маленьких путников и их родителей.'],
      ];
      foreach ($events as $e): ?>
      <div class="card reveal">
        <div class="eyebrow" style="margin-bottom:8px;">Событие</div>
        <h3><?= $e[0] ?></h3>
        <p class="muted"><?= $e[1] ?></p>
        <a href="booking.php" class="btn btn-primary btn-sm" style="margin-top:12px;">Забронировать место</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

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
<script src="atmosphere.js?v=<?= @filemtime(__DIR__.'/atmosphere.js') ?>"></script>
</body>
</html>
