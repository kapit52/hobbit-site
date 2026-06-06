<?php 
session_start();
include 'db.php';

// фон секции (title = 'Фон секции')
$section_bg = 'images/l.png';
$stmt = $conn->prepare("SELECT image_path FROM menu_items WHERE category = 'decor' AND title = 'Фон секции' LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['image_path'])) $section_bg = $row['image_path'];
    }
    $stmt->close();
}

// шеф (category = 'chef')
$chef_img = 'images/photo.jpg';
$stmt = $conn->prepare("SELECT image_path FROM menu_items WHERE category = 'chef' LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['image_path'])) $chef_img = $row['image_path'];
    }
    $stmt->close();
}

$is_admin = isset($_SESSION['admin']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>О ресторане — Ширский Уголок</title>
  <link href="https://fonts.googleapis.com/css2?family=Ruslan+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- ставим фон секции через inline style -->
<section class="intro-text" style="background-image: url('<?= htmlspecialchars($section_bg) ?>');">
  <div class="text-container">
      <h2>Добро пожаловать в Ширский Уголок!</h2>
      <p>Милости просим, путник, в наш уголок, где каждый найдет угощение по душе. В нашем уютном ресторане мы собрали лучшие блюда со всего света, чтобы ты мог насладиться вкусами, которые согреют душу и порадуют сердце.</p>
      <p>Садись у камина, расслабься и позволь нам угостить тебя лучшими яствами. Пусть каждый прием пищи станет для тебя настоящим путешествием, полным волшебства и тепла.</p>
  </div>

  <div class="chef-container">
      <h3>Наш шеф-повар</h3>
      <div class="chef-photo">
          <img src="<?= htmlspecialchars($chef_img) ?>" alt="Фото шеф-повара">
          <p>Капитонова Елизавета Ильинична</p>
      </div>
  </div>
</section>

</body>
</html>
