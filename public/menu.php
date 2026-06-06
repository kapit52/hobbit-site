<?php
session_start();
include 'db.php';

$sections = [
    'Горячие угощения'  => ['icon' => '🍖', 'desc' => 'Блюда из печи и с открытого огня — мясо, птица, рагу'],
    'Яства и ломтики'   => ['icon' => '🥗', 'desc' => 'Закуски, салаты и лёгкие тарелки для доброго начала'],
    'Ласковые лакомства'=> ['icon' => '🍰', 'desc' => 'Пироги, кисели и сладкие угощения от нашего кондитера'],
    'Чарующие напитки'  => ['icon' => '🍺', 'desc' => 'Эли, настойки, морсы и горячие напитки со своей кухни'],
];

// Проверяем наличие новых колонок
$_menu_has_extra = false;
$_cx = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'description'");
if ($_cx && $_cx->num_rows > 0) $_menu_has_extra = true;
$_menu_cols = $_menu_has_extra
    ? "id, title, description, price, weight, badge, image_path"
    : "id, title, NULL AS description, price, NULL AS weight, NULL AS badge, image_path";

$all_items = [];
foreach (array_keys($sections) as $cat) {
    $stmt = $conn->prepare("SELECT $_menu_cols FROM menu_items WHERE category = ? ORDER BY id");
    $stmt->bind_param('s', $cat);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $all_items[$cat][] = $row;
    $stmt->close();
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Меню · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .menu-hero {
    position: relative; padding: 80px 0 40px;
    background: linear-gradient(180deg,rgba(60,80,50,0.16),transparent),var(--parch-100);
    overflow: hidden;
  }
  .menu-hero::before,.menu-hero::after {
    content:""; position:absolute; width:320px; height:320px;
    background:url('assets/vine-corner.svg') no-repeat; background-size:contain;
    pointer-events:none; opacity:0.5;
  }
  .menu-hero::before{top:0;left:0;} .menu-hero::after{top:0;right:0;transform:scaleX(-1);}
  .menu-hero .container{position:relative;z-index:2;text-align:center;}
  .menu-cats {
    position:sticky; top:67px; z-index:50;
    background:var(--parch-100); border-bottom:1px solid var(--line); box-shadow:var(--shadow-soft);
  }
  .menu-cats-inner {
    max-width:var(--max-w); margin:0 auto; padding:14px 28px;
    display:flex; gap:8px; overflow-x:auto; align-items:center;
  }
  .menu-cats a {
    flex-shrink:0; padding:10px 18px; border-radius:var(--r-sm); border:1px solid transparent;
    font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.14em; font-size:0.74rem;
    color:var(--ink-soft); text-decoration:none; transition:all 0.2s;
  }
  .menu-cats a:hover{background:var(--parch-200);color:var(--ink);}
  .menu-cats a.active{background:var(--ink);color:var(--parch-50);}
  .cat-section { padding:70px 0 30px; scroll-margin-top:140px; }
  .cat-header { display:flex; align-items:flex-end; justify-content:space-between; gap:30px; margin-bottom:36px; padding-bottom:18px; border-bottom:1px solid var(--line); }
  .cat-header h2 { margin:0; display:flex; align-items:baseline; gap:18px; }
  .cat-header h2 .ico { font-size:1.3rem; color:var(--moss); }
  .cat-header .descr { max-width:380px; color:var(--ink-mute); font-style:italic; font-size:0.98rem; text-align:right; margin:0; }
  .menu-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:28px; }
  .mcard {
    background:var(--parch-50); border:1px solid var(--line); border-radius:var(--r-md);
    overflow:hidden; box-shadow:var(--shadow-soft); display:flex; flex-direction:column;
    transition:all 0.35s; position:relative;
  }
  .mcard:hover{transform:translateY(-4px);box-shadow:var(--shadow-card);border-color:var(--amber);}
  .mcard .ph { aspect-ratio:5/4; position:relative; overflow:hidden; }
  .mcard .ph img { width:100%; height:100%; object-fit:cover; transition:transform 0.6s; }
  .mcard:hover .ph img{transform:scale(1.05);}
  .mcard .body { padding:20px 22px 22px; display:flex; flex-direction:column; gap:12px; flex:1; }
  .mcard h3 { font-size:1.3rem; margin:0; }
  .mcard .desc { color:var(--ink-mute); font-size:0.95rem; margin:0; flex:1; }
  .mcard .foot { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-top:auto; }
  .mcard .weight-lbl { font-family:var(--font-display); font-size:0.68rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--ink-faint); }
  .empty-cat { padding:40px; text-align:center; color:var(--ink-mute); border:1.5px dashed var(--line); border-radius:var(--r-md); font-style:italic; }
  @media(max-width:900px){ .menu-grid{grid-template-columns:repeat(2,1fr);} }
  @media(max-width:600px){ .menu-grid{grid-template-columns:1fr;} .cat-header{flex-direction:column;align-items:flex-start;} .cat-header .descr{text-align:left;} }
  @media(max-width:480px){
    .menu-hero{padding:50px 0 24px;}
    .menu-cats-inner{padding:10px 12px;gap:6px;}
    .menu-cats a{padding:8px 12px;font-size:0.68rem;letter-spacing:0.1em;}
    .cat-section{padding:40px 0 20px;scroll-margin-top:110px;}
  }
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<section class="menu-hero">
  <div class="container">
    <div class="breadcrumbs" style="justify-content:center;padding:0 0 20px;"><a href="index.php">Главная</a><span class="sep">/</span><span>Меню</span></div>
    <div class="eyebrow">Блюда для путников</div>
    <h1 style="margin-bottom:12px;">Меню таверны</h1>
    <p style="max-width:600px;margin:0 auto;color:var(--ink-soft);font-size:1.1rem;font-style:italic;">Всё готовится на нашей кухне — из того, что утром выбрал сам шеф на ярмарке.</p>
  </div>
</section>

<nav class="menu-cats" id="menu-cats">
  <div class="menu-cats-inner">
    <?php foreach ($sections as $cat => $info): ?>
      <a href="#cat-<?= urlencode($cat) ?>"><?= $info['icon'] ?> <?= htmlspecialchars($cat) ?></a>
    <?php endforeach; ?>
  </div>
</nav>

<div class="container">
  <?php foreach ($sections as $cat => $info):
    $items = $all_items[$cat] ?? [];
  ?>
  <section class="cat-section" id="cat-<?= urlencode($cat) ?>">
    <div class="cat-header">
      <h2><span class="ico"><?= $info['icon'] ?></span><?= htmlspecialchars($cat) ?></h2>
      <p class="descr"><?= htmlspecialchars($info['desc']) ?></p>
    </div>
    <?php if (empty($items)): ?>
      <div class="empty-cat">Блюда в этой категории пока не добавлены.</div>
    <?php else: ?>
    <div class="menu-grid">
      <?php foreach ($items as $item):
        $imgSrc = (!empty($item['image_path']) && file_exists(__DIR__.'/'.$item['image_path'])) ? htmlspecialchars($item['image_path']) : '';
      ?>
      <article class="mcard reveal" data-title="<?= htmlspecialchars(strtolower($item['title'] . ' ' . ($item['description'] ?? ''))) ?>">
        <div class="ph">
          <?php if (!empty($item['badge'])): ?>
            <span class="tag tag-amber" style="position:absolute;top:14px;left:14px;z-index:2;"><?= htmlspecialchars($item['badge']) ?></span>
          <?php endif; ?>
          <?php if ($imgSrc): ?>
            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['title']) ?>">
          <?php else: ?>
            <div class="placeholder-photo" data-label="<?= htmlspecialchars($item['title']) ?>"></div>
          <?php endif; ?>
        </div>
        <div class="body">
          <h3><?= htmlspecialchars($item['title']) ?></h3>
          <?php if (!empty($item['description'])): ?>
            <p class="desc"><?= htmlspecialchars($item['description']) ?></p>
          <?php endif; ?>
          <div class="foot">
            <div>
              <?php if (!empty($item['weight'])): ?><div class="weight-lbl"><?= htmlspecialchars($item['weight']) ?></div><?php endif; ?>
              <span class="price"><?= htmlspecialchars($item['price']) ?><span class="currency"> ₽</span></span>
            </div>
            <button class="btn btn-primary btn-sm" data-add-to-cart
              data-id="<?= $item['id'] ?>"
              data-name="<?= htmlspecialchars($item['title']) ?>"
              data-price="<?= htmlspecialchars(preg_replace('/[^\d.]/', '', $item['price'])) ?>">
              <span class="label">В корзину</span>
            </button>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
  <div style="height:60px;"></div>
</div>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="brand" style="margin-bottom:14px;padding:0;"><img src="assets/brand-mark.svg" alt="" class="brand-mark"><span>Ширский уголок</span></div>
        <p style="color:var(--parch-200);font-size:0.95rem;">Уютная таверна для добрых путников.</p>
      </div>
      <div><h4>Разделы</h4><ul><li><a href="menu.php">Меню</a></li><li><a href="booking.php">Бронь стола</a></li><li><a href="gallery.php">Галерея</a></li><li><a href="reviews.php">Отзывы</a></li></ul></div>
      <div><h4>Контакты</h4><ul><li>пер. Зелёного Холма, 7</li><li><a href="tel:+78124567890">+7 (812) 456-78-90</a></li></ul></div>
      <div><h4>Бронь</h4><p style="font-size:0.9rem;color:var(--parch-200);margin-bottom:14px;">Позвоните или забронируйте онлайн.</p><a href="booking.php" class="btn btn-primary btn-sm">Забронировать</a></div>
    </div>
    <div class="footer-bottom"><div>© 1893 — <?= date('Y') ?> Таверна «Ширский уголок»</div><div><a href="rules.php" style="color:var(--ink-faint);">Правила таверны</a></div></div>
  </div>
</footer>

<script src="atmosphere.js"></script>
<script>
// Подсветка активной категории при скролле
const catLinks = document.querySelectorAll('.menu-cats a');
const catSections = document.querySelectorAll('.cat-section');
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      catLinks.forEach(l => l.classList.remove('active'));
      const active = document.querySelector('.menu-cats a[href="#' + e.target.id + '"]');
      if (active) active.classList.add('active');
    }
  });
}, { rootMargin: '-30% 0px -60% 0px' });
catSections.forEach(s => observer.observe(s));
</script>
</body>
</html>
