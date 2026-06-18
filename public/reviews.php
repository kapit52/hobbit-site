<?php
session_start();
include 'db.php';
require_once 'includes/csrf.php';
require_once 'includes/auth_user.php';

$reviews = [];
$error = '';
$success = '';
$can_write = is_user_logged_in();

// Совместимость с не-мигрированной БД
$_rv_has_rating = false;
$_rvc = $conn->query("SHOW COLUMNS FROM reviews LIKE 'rating'");
if ($_rvc && $_rvc->num_rows > 0) $_rv_has_rating = true;

// Колонка для фото к отзыву (создаём при необходимости)
$_rv_has_photo = false;
$_rvp = $conn->query("SHOW COLUMNS FROM reviews LIKE 'photo_path'");
if ($_rvp && $_rvp->num_rows > 0) {
    $_rv_has_photo = true;
} elseif ($conn->query("ALTER TABLE reviews ADD COLUMN photo_path VARCHAR(500) NULL AFTER review")) {
    $_rv_has_photo = true;
}

// Фильтр по рейтингу
$filter_rating = (int)($_GET['stars'] ?? 0);
$sort = $_GET['sort'] ?? 'new';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_write) {
        $error = 'Оставлять отзывы могут только авторизованные пользователи. <a href="login.php?return_to=reviews">Войти</a>';
    } elseif (!csrf_verify()) {
        $error = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $reviewText = trim($_POST['review'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $userId = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $displayName = trim($u['full_name'] ?? '') ?: ($u['username'] ?? 'Гость');

        // Загрузка фото к отзыву
        $photoPath = null;
        if ($_rv_has_photo && !empty($_FILES['photo']['name'])) {
            $uerr = $_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($uerr === UPLOAD_ERR_INI_SIZE || $uerr === UPLOAD_ERR_FORM_SIZE) {
                $error = 'Фото слишком большое (максимум ' . ini_get('upload_max_filesize') . '). Выберите файл поменьше.';
            } elseif ($uerr !== UPLOAD_ERR_OK) {
                $error = 'Не удалось загрузить фото. Попробуйте ещё раз.';
            } else {
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
                $mime = '';
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
                    finfo_close($finfo);
                } else {
                    $info = @getimagesize($_FILES['photo']['tmp_name']);
                    $mime = $info['mime'] ?? '';
                }
                if (!isset($allowed[$mime])) {
                    $error = 'Фото должно быть изображением (jpg, png, webp или gif)';
                } else {
                    if (!is_dir(__DIR__ . '/uploads')) @mkdir(__DIR__ . '/uploads', 0775, true);
                    $fname = 'review_' . time() . '_' . mt_rand(1000, 9999) . '.' . $allowed[$mime];
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/uploads/' . $fname)) {
                        $photoPath = 'uploads/' . $fname;
                    } else {
                        $error = 'Не удалось сохранить фото. Попробуйте ещё раз.';
                    }
                }
            }
        }

        if ($reviewText === '') {
            $error = 'Напишите текст отзыва';
        }

        if (!$error) {
            $cols  = ['user_id', 'name', 'review'];
            $types = 'iss';
            $vals  = [$userId, $displayName, $reviewText];
            if ($_rv_has_rating) { $cols[] = 'rating';     $types .= 'i'; $vals[] = $rating; }
            if ($_rv_has_photo)  { $cols[] = 'photo_path';  $types .= 's'; $vals[] = $photoPath; }
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $stmt = $conn->prepare("INSERT INTO reviews (" . implode(',', $cols) . ") VALUES ($placeholders)");
            $stmt->bind_param($types, ...$vals);
            if ($stmt->execute()) {
                $stmt->close();
                $_SESSION['success_message'] = 'Отзыв успешно оставлен!';
                header('Location: reviews.php');
                exit;
            }
            $stmt->close();
            $error = 'Не удалось сохранить отзыв';
        }
    }
}
if (isset($_SESSION['success_message'])) { $success = $_SESSION['success_message']; unset($_SESSION['success_message']); }

// Загрузка отзывов с фильтром
$where = $filter_rating > 0 && $_rv_has_rating ? "WHERE r.rating = $filter_rating" : '';
$order = ($sort === 'top' && $_rv_has_rating) ? "r.rating DESC, r.id DESC" : "r.id DESC";
if ($_rv_has_rating) {
    $result = $conn->query("SELECT r.*, COALESCE(r.rating,5) AS rating, DATE_FORMAT(COALESCE(r.created_at,NOW()),'%e %M %Y') AS dt FROM reviews r LEFT JOIN users u ON r.user_id = u.id $where ORDER BY $order");
} else {
    $result = $conn->query("SELECT r.*, 5 AS rating, DATE_FORMAT(NOW(),'%e %M %Y') AS dt FROM reviews r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.id DESC");
}
if ($result) while ($row = $result->fetch_assoc()) $reviews[] = $row;

// Статистика
$total = count($reviews);
$avg = $total > 0 ? round(array_sum(array_column($reviews, 'rating')) / $total, 1) : 0;
$dist = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
foreach ($reviews as $rv) { $r = max(1,min(5,(int)$rv['rating'])); $dist[$r]++; }

$months_ru = ['January'=>'января','February'=>'февраля','March'=>'марта','April'=>'апреля','May'=>'мая','June'=>'июня','July'=>'июля','August'=>'августа','September'=>'сентября','October'=>'октября','November'=>'ноября','December'=>'декабря'];
function ru_date($dt) { global $months_ru; foreach ($months_ru as $en=>$ru) $dt=str_replace($en,$ru,$dt); return $dt; }
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Отзывы · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .rev-hero{text-align:center;padding:50px 0 30px;background:linear-gradient(180deg,rgba(60,80,50,0.12),transparent),var(--parch-100);}
  .rev-summary{display:grid;grid-template-columns:1fr 2fr 1fr;gap:40px;align-items:center;background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);padding:36px 40px;margin:30px 0;box-shadow:var(--shadow-soft);}
  .rev-score{text-align:center;}
  .rev-score .num{font-family:var(--font-display);font-size:4rem;line-height:1;color:var(--amber-deep);}
  .rev-score .stars{color:var(--gold);font-size:1.2rem;letter-spacing:0.15em;margin:6px 0;}
  .rev-score .of{font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.16em;font-size:0.78rem;color:var(--ink-mute);}
  .rev-bars{padding:0 12px;}
  .rev-bar{display:grid;grid-template-columns:50px 1fr 50px;gap:14px;align-items:center;padding:5px 0;font-family:var(--font-display);font-size:0.85rem;}
  .rev-bar .label{color:var(--ink-soft);}
  .rev-bar .pct{color:var(--ink-mute);text-align:right;}
  .rev-bar .track{height:8px;background:var(--parch-200);border-radius:4px;overflow:hidden;}
  .rev-bar .fill{height:100%;background:linear-gradient(90deg,var(--moss),var(--amber));border-radius:4px;}
  .rev-cta{display:flex;flex-direction:column;gap:10px;align-items:center;}
  .rev-cta .count{font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.16em;font-size:0.74rem;color:var(--ink-mute);}
  .rev-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:30px 0 20px;}
  .rev-filters .chip{padding:8px 16px;border-radius:20px;border:1px solid var(--line);background:var(--parch-50);font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.12em;font-size:0.72rem;color:var(--ink-soft);cursor:pointer;transition:all 0.2s;text-decoration:none;}
  .rev-filters .chip:hover{border-color:var(--amber);color:var(--amber-deep);}
  .rev-filters .chip.on{background:var(--ink);color:var(--parch-50);border-color:var(--ink);}
  .rev-filters .spacer{flex:1;}
  .rev-filters .sort{background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-sm);padding:8px 14px;font:inherit;font-size:0.9rem;color:var(--ink);}
  .rev-list{display:grid;grid-template-columns:1fr 1fr;gap:24px;padding-bottom:30px;}
  .rev-item{background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);padding:28px;box-shadow:var(--shadow-soft);display:flex;flex-direction:column;gap:14px;}
  .rev-item .head{display:flex;align-items:center;gap:14px;}
  .rev-item .avatar{width:50px;height:50px;border-radius:50%;background:var(--moss-soft);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);color:var(--forest);font-weight:600;flex-shrink:0;font-size:1rem;}
  .rev-item .author-info{flex:1;}
  .rev-item .name{font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.1em;font-size:0.88rem;color:var(--ink);margin-bottom:3px;}
  .rev-item .visit{font-size:0.82rem;color:var(--ink-mute);}
  .rev-item .stars{color:var(--gold);font-size:1.05rem;letter-spacing:0.12em;}
  .rev-item .text{font-size:1rem;line-height:1.6;color:var(--ink-soft);margin:0;font-style:italic;flex:1;}
  .rev-item .rev-photo{display:block;max-width:240px;border-radius:var(--r-sm);overflow:hidden;border:1px solid var(--line);}
  .rev-item .rev-photo img{display:block;width:100%;height:auto;object-fit:cover;transition:transform 0.4s;}
  .rev-item .rev-photo:hover img{transform:scale(1.04);}
  .rev-item .foot{display:flex;justify-content:space-between;align-items:center;padding-top:10px;border-top:1px dashed var(--line);font-size:0.85rem;color:var(--ink-mute);}
  .rev-reply{background:rgba(107,142,78,0.07);border-left:3px solid var(--forest);border-radius:0 var(--r-sm) var(--r-sm) 0;padding:12px 16px;margin-top:2px;}
  .rev-reply .rr-head{display:flex;align-items:center;gap:8px;font-family:var(--font-display);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.14em;color:var(--forest);margin-bottom:6px;}
  .rev-reply .rr-head .rr-icon{width:22px;height:22px;background:var(--forest);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.75rem;}
  .rev-reply p{margin:0;font-size:0.95rem;line-height:1.55;color:var(--ink-soft);font-style:italic;}
  .write-review{background:linear-gradient(135deg,rgba(184,134,11,0.05),rgba(107,142,78,0.08)),var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);padding:40px;margin:30px 0 60px;position:relative;overflow:hidden;}
  .write-review::before{content:"";position:absolute;top:0;left:0;width:200px;height:200px;background:url('assets/vine-corner.svg') no-repeat;background-size:contain;opacity:0.3;pointer-events:none;}
  .write-review h2{margin-bottom:8px;}
  .star-rating{display:inline-flex;gap:12px;font-size:4.2rem;margin-bottom:18px;}
  .star-rating label{font-size:inherit;color:var(--parch-300);cursor:pointer;transition:color 0.15s;line-height:1;}
  .star-rating input{display:none;}
  .star-rating input:checked ~ label,.star-rating label:hover,.star-rating label:hover ~ label{color:var(--gold);}
  .star-rating{flex-direction:row-reverse;}
  .guest-prompt{background:var(--parch-100);border:1px solid var(--line);border-radius:var(--r-md);padding:36px;text-align:center;margin:30px 0 60px;}
  .pagination{display:flex;justify-content:center;gap:6px;padding:30px 0;}
  .pagination a{width:40px;height:40px;border-radius:var(--r-sm);border:1px solid var(--line);background:var(--parch-50);font-family:var(--font-display);font-size:0.9rem;color:var(--ink-soft);display:inline-flex;align-items:center;justify-content:center;text-decoration:none;}
  .pagination a.on{background:var(--ink);color:var(--parch-50);border-color:var(--ink);}
  @media(max-width:900px){.rev-summary{grid-template-columns:1fr;gap:24px;padding:28px;}.rev-list{grid-template-columns:1fr;}}
  @media(max-width:640px){
    .rev-hero{padding:36px 0 20px;}
    .rev-filters{gap:6px;margin:20px 0 14px;}
    .rev-filters .chip{padding:6px 12px;font-size:0.68rem;}
    .write-review{padding:20px 16px;}
    .rev-item{padding:20px 18px;}
    .star-rating{font-size:3.4rem;gap:10px;}
  }
  /* Пустой список отзывов */
  .rev-empty{text-align:center;padding:60px 24px 66px;border:1px dashed var(--line);border-radius:var(--r-lg);background:var(--parch-50);margin:10px 0 48px;}
  .rev-empty .quill{width:50px;height:50px;color:var(--amber);opacity:0.85;margin-bottom:6px;}
  .rev-empty .empty-stars{font-size:1.8rem;letter-spacing:0.18em;color:var(--gold);margin:8px 0 16px;}
  .rev-empty .empty-stars .dim{color:var(--line);}
  .rev-empty h3{font-family:var(--font-display);font-size:1.45rem;color:var(--ink);margin:0 0 10px;}
  .rev-empty p{color:var(--ink-mute);font-style:italic;max-width:430px;margin:0 auto 22px;line-height:1.6;}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<section class="rev-hero">
  <div class="container">
    <div class="breadcrumbs" style="justify-content:center;padding:0 0 14px;"><a href="index.php">Главная</a><span class="sep">/</span><span>Отзывы</span></div>
    <div class="eyebrow">Слово гостей</div>
    <h1>Что говорят путники</h1>
    <p style="max-width:580px;margin:0 auto;color:var(--ink-soft);font-style:italic;font-size:1.05rem;">Здесь — настоящие истории тех, кто заглянул на огонёк. Без правок и редактуры.</p>
  </div>
</section>

<div class="container">
  <?php if (!empty($success)): ?><div class="success-message"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="error-message"><?= $error ?></div><?php endif; ?>

<?php if (!empty($success)): ?>
<div id="reviewToast" role="status" style="position:fixed;top:84px;left:50%;transform:translateX(-50%) translateY(-20px);z-index:9999;background:var(--forest,#3d5a2a);color:#fff;padding:14px 28px;border-radius:12px;box-shadow:0 12px 34px rgba(0,0,0,0.28);font-family:var(--font-display);letter-spacing:0.04em;font-size:0.95rem;opacity:0;transition:opacity .4s, transform .4s;">✓ <?= htmlspecialchars($success) ?></div>
<script>
window.addEventListener('load', function () {
  var t = document.getElementById('reviewToast');
  if (!t) return;
  requestAnimationFrame(function () { t.style.opacity = '1'; t.style.transform = 'translateX(-50%) translateY(0)'; });
  setTimeout(function () { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(-20px)'; }, 3800);
});
</script>
<?php endif; ?>

  <!-- Сводка рейтингов -->
  <?php if ($total > 0): ?>
  <div class="rev-summary">
    <div class="rev-score">
      <div class="num"><?= $avg ?></div>
      <div class="stars"><?= str_repeat('★', round($avg)) . str_repeat('☆', 5 - round($avg)) ?></div>
      <div class="of"><?= $total ?> отзыв<?= $total%10==1&&$total%100!=11?'':'ов' ?></div>
    </div>
    <div class="rev-bars">
      <?php for ($s = 5; $s >= 1; $s--):
        $pct = $total > 0 ? round($dist[$s] / $total * 100) : 0;
      ?>
      <div class="rev-bar">
        <div class="label"><?= $s ?> ★</div>
        <div class="track"><div class="fill" style="width:<?= $pct ?>%"></div></div>
        <div class="pct"><?= $pct ?>%</div>
      </div>
      <?php endfor; ?>
    </div>
    <div class="rev-cta">
      <div class="count"><?= $total ?> <?= $total%10==1&&$total%100!=11?'отзыв':($total%10>=2&&$total%10<=4&&($total%100<10||$total%100>=20)?'отзыва':'отзывов') ?></div>
      <?php if ($can_write): ?>
        <a href="#write" class="btn btn-primary btn-sm">Написать отзыв</a>
      <?php else: ?>
        <a href="login.php?return_to=reviews" class="btn btn-primary btn-sm">Войти и написать</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Фильтры -->
  <div class="rev-filters">
    <a href="reviews.php" class="chip <?= !$filter_rating ? 'on' : '' ?>">Все</a>
    <?php for ($s = 5; $s >= 1; $s--): ?>
      <a href="?stars=<?= $s ?>&sort=<?= htmlspecialchars($sort) ?>" class="chip <?= $filter_rating === $s ? 'on' : '' ?>"><?= str_repeat('★', $s) ?></a>
    <?php endfor; ?>
    <span class="spacer"></span>
    <select class="sort" onchange="location.href='?stars=<?= $filter_rating ?>&sort='+this.value">
      <option value="new" <?= $sort==='new'?'selected':'' ?>>Сначала новые</option>
      <option value="top" <?= $sort==='top'?'selected':'' ?>>Сначала лучшие</option>
    </select>
  </div>

  <!-- Список -->
  <?php if (empty($reviews)): ?>
  <div class="rev-empty">
    <svg class="quill" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 4C9 5 4 13 4 20c4-2 6-2 9-3M20 4c0 6-3 11-9 13M20 4l-8 8"/>
    </svg>
    <?php if ($filter_rating): ?>
      <div class="empty-stars"><?= str_repeat('★', $filter_rating) ?><span class="dim"><?= str_repeat('☆', 5 - $filter_rating) ?></span></div>
      <h3>Пока нет отзывов на <?= $filter_rating ?> из 5</h3>
      <p>С такой оценкой ещё никто не делился впечатлениями. Посмотрите отзывы с другими оценками — или станьте первым.</p>
      <a href="reviews.php" class="btn btn-ghost btn-sm">← Все отзывы</a>
    <?php else: ?>
      <h3>Пока нет отзывов</h3>
      <p>Здесь появятся впечатления гостей о таверне. Будьте первым, кто оставит отзыв!</p>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="rev-list">
    <?php foreach ($reviews as $rev):
      $initials = mb_strtoupper(mb_substr($rev['name'], 0, 1));
      $rating = max(1, min(5, (int)$rev['rating']));
      $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    ?>
    <article class="rev-item reveal">
      <div class="head">
        <div class="avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="author-info">
          <div class="name"><?= htmlspecialchars($rev['name']) ?></div>
          <div class="visit"><?= ru_date($rev['dt']) ?></div>
        </div>
        <div class="stars"><?= $stars ?></div>
      </div>
      <p class="text"><?= nl2br(htmlspecialchars($rev['review'])) ?></p>
      <?php $rvPhoto = $rev['photo_path'] ?? ''; if (!empty($rvPhoto) && file_exists(__DIR__.'/'.$rvPhoto)): ?>
      <a class="rev-photo" href="<?= htmlspecialchars($rvPhoto) ?>" target="_blank" rel="noopener">
        <img src="<?= htmlspecialchars($rvPhoto) ?>" alt="Фото к отзыву" loading="lazy">
      </a>
      <?php endif; ?>
      <?php if (!empty($rev['admin_reply'])): ?>
      <div class="rev-reply">
        <div class="rr-head">
          <span class="rr-icon">&#9812;</span>
          Ответ хозяйки таверны
        </div>
        <p><?= nl2br(htmlspecialchars($rev['admin_reply'])) ?></p>
      </div>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Форма -->
  <?php if ($can_write): ?>
  <div class="write-review" id="write">
    <div class="eyebrow" style="margin-bottom:12px;">Твой отзыв</div>
    <h2>Расскажи о своём вечере</h2>
    <p style="color:var(--ink-mute);font-style:italic;margin-bottom:24px;">Ты пишешь как <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
    <form method="POST" action="" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field" style="margin-bottom:22px;">
        <label>Оценка</label>
        <div class="star-rating" id="starRating">
          <?php for ($s = 5; $s >= 1; $s--): ?>
            <input type="radio" name="rating" id="sr<?= $s ?>" value="<?= $s ?>" <?= $s==5?'checked':'' ?>>
            <label for="sr<?= $s ?>" title="<?= $s ?> звёзд">★</label>
          <?php endfor; ?>
        </div>
      </div>
      <div class="field">
        <label>Что запомнилось?</label>
        <textarea name="review" rows="5" placeholder="Любимое блюдо, атмосфера, музыкант у очага, повод для следующего визита..." required style="resize:vertical;"></textarea>
      </div>
      <div class="field" style="margin-top:4px;">
        <label>Фото к отзыву <span style="text-transform:none;letter-spacing:0;color:var(--ink-mute);font-size:0.82rem;">(необязательно, до 5 МБ)</span></label>
        <input type="file" name="photo" accept="image/*" id="reviewPhoto">
        <div id="reviewPhotoPreview" style="margin-top:12px;display:none;">
          <img src="" alt="" style="max-width:180px;max-height:180px;border-radius:var(--r-sm);border:1px solid var(--line);">
        </div>
      </div>
      <div style="display:flex;gap:14px;align-items:center;margin-top:18px;">
        <button type="submit" class="btn btn-primary">Оставить отзыв</button>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="guest-prompt" id="write">
    <div class="eyebrow" style="margin-bottom:12px;">Оставить отзыв</div>
    <h3>Войди, чтобы написать</h3>
    <p class="muted">Отзывы могут оставлять зарегистрированные гости таверны.</p>
    <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;justify-content:center;">
      <a href="login.php?return_to=reviews" class="btn btn-primary">Войти</a>
      <a href="register.php?return_to=reviews" class="btn btn-ghost">Регистрация</a>
    </div>
  </div>
  <?php endif; ?>
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
<script>
// Превью выбранного фото перед отправкой отзыва
(function(){
  var input = document.getElementById('reviewPhoto');
  if (!input) return;
  var prev = document.getElementById('reviewPhotoPreview');
  var img = prev ? prev.querySelector('img') : null;
  input.addEventListener('change', function(){
    var file = input.files && input.files[0];
    if (file && img) {
      img.src = URL.createObjectURL(file);
      prev.style.display = '';
    } else if (prev) {
      prev.style.display = 'none';
    }
  });
})();
</script>
</body>
</html>