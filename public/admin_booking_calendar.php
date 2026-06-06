<?php
require_once 'includes/admin_auth.php';
require_once 'includes/booking_helpers.php';
require_once 'includes/order_helpers.php';
$admin_username = $_SESSION['admin_username'] ?? 'Администратор';
$admin_initials = mb_strtoupper(mb_substr($admin_username, 0, 2));
$counts = get_pending_counts($conn);

// Month navigation
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$prevYear  = $month === 1  ? $year - 1 : $year;
$prevMonth = $month === 1  ? 12 : $month - 1;
$nextYear  = $month === 12 ? $year + 1 : $year;
$nextMonth = $month === 12 ? 1  : $month + 1;

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth = (int)date('t', strtotime($monthStart));
$firstWeekday = (int)date('N', strtotime($monthStart)); // 1=Mon ... 7=Sun

// Fetch all bookings for the month
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
$stmt = $conn->prepare("
    SELECT b.*, rt.name AS table_name
    FROM bookings b
    LEFT JOIN restaurant_tables rt ON b.table_id = rt.id
    WHERE b.booking_date BETWEEN ? AND ?
    ORDER BY b.booking_time
");
$stmt->bind_param('ss', $monthStart, $monthEnd);
$stmt->execute();
$res = $stmt->get_result();
$allBookings = [];
while ($row = $res->fetch_assoc()) {
    $day = (int)date('j', strtotime($row['booking_date']));
    $allBookings[$day][] = $row;
}
$stmt->close();

$monthNames = ['','Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
$dayNames = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];

$selectedDay = isset($_GET['day']) ? (int)$_GET['day'] : 0;
$todayDay = (date('Y') == $year && date('n') == $month) ? (int)date('j') : 0;

function status_color(string $s): string {
    $map = ['pending'=>'#b8860b','confirmed'=>'#6b8e4e','completed'=>'#888','cancelled'=>'#8b1f3a','rejected'=>'#8b1f3a'];
    return $map[$s] ?? '#888';
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Календарь броней · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/admin.css">
<style>
  .cal-wrap { max-width:960px; margin:0 auto; }
  .cal-nav { display:flex; align-items:center; gap:16px; margin-bottom:24px; }
  .cal-nav h2 { flex:1; text-align:center; font-family:var(--font-display); font-size:1.4rem; letter-spacing:0.1em; margin:0; }
  .cal-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border:1px solid var(--line); border-radius:var(--r-sm); background:var(--parch-50); color:var(--ink); text-decoration:none; font-family:var(--font-display); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.1em; transition:border-color 0.15s; }
  .cal-btn:hover { border-color:var(--amber); color:var(--amber-deep); }
  .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; }
  .cal-header { text-align:center; font-family:var(--font-display); font-size:0.7rem; text-transform:uppercase; letter-spacing:0.14em; color:var(--ink-mute); padding:8px 0; }
  .cal-cell { min-height:80px; background:var(--parch-50); border:1px solid var(--line-soft); border-radius:var(--r-sm); padding:6px; cursor:pointer; transition:border-color 0.15s,background 0.15s; position:relative; }
  .cal-cell:hover { border-color:var(--amber); background:rgba(184,134,11,0.04); }
  .cal-cell.today { border-color:var(--amber); }
  .cal-cell.selected { border-color:var(--amber-deep); background:rgba(184,134,11,0.08); box-shadow:0 0 0 2px var(--amber); }
  .cal-cell.empty { background:transparent; border-color:transparent; cursor:default; }
  .cal-cell.empty:hover { background:transparent; border-color:transparent; }
  .day-num { font-family:var(--font-display); font-size:0.88rem; font-weight:600; color:var(--ink); margin-bottom:4px; }
  .cal-cell.today .day-num { color:var(--amber-deep); }
  .day-badge { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; background:var(--amber-deep); color:var(--parch-50); border-radius:50%; font-family:var(--font-display); font-size:0.68rem; font-weight:700; }
  .day-dots { display:flex; flex-wrap:wrap; gap:3px; margin-top:4px; }
  .day-dot { width:7px; height:7px; border-radius:50%; }
  /* Detail panel */
  .day-detail { margin-top:28px; background:var(--parch-50); border:1px solid var(--line); border-radius:var(--r-lg); padding:24px; }
  .day-detail h3 { font-family:var(--font-display); font-size:1rem; letter-spacing:0.1em; text-transform:uppercase; color:var(--ink-mute); margin:0 0 16px; }
  .booking-row { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--line-soft); flex-wrap:wrap; }
  .booking-row:last-child { border-bottom:none; }
  .b-time { font-family:var(--font-display); font-size:0.9rem; font-weight:600; color:var(--ink); min-width:48px; }
  .b-name { flex:1; font-size:0.95rem; }
  .b-meta { font-size:0.82rem; color:var(--ink-mute); }
  .b-pill { font-family:var(--font-display); text-transform:uppercase; letter-spacing:0.1em; font-size:0.66rem; padding:3px 10px; border-radius:12px; }
  .b-pill-pending   { background:rgba(184,134,11,0.12); color:var(--amber-deep); }
  .b-pill-confirmed { background:rgba(107,142,78,0.16); color:var(--forest); }
  .b-pill-completed { background:var(--parch-200); color:var(--ink-mute); }
  .b-pill-cancelled,.b-pill-rejected { background:rgba(139,31,58,0.1); color:var(--berry); }
</style>
</head>
<body class="admin-body">

<aside class="admin-side">
  <div class="admin-brand">
    <img src="assets/brand-mark.svg" alt="">
    <div>
      <div class="name">Ширский<br>уголок</div>
      <div class="sub">управление</div>
    </div>
  </div>

  <div class="admin-section-label">Обзор</div>
  <ul class="admin-nav">
    <li><a href="admin_panel.php?section=dashboard">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Дашборд
    </a></li>
  </ul>

  <div class="admin-section-label">Заявки</div>
  <ul class="admin-nav">
    <li><a href="admin_panel.php?section=orders">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h2l2.5 12h11l2.5-9H6"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
      Заказы
      <?php if ($counts['orders'] > 0): ?><span class="badge"><?= $counts['orders'] ?></span><?php endif; ?>
    </a></li>
    <li><a href="admin_panel.php?section=bookings">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Бронирования
      <?php if ($counts['bookings'] > 0): ?><span class="badge"><?= $counts['bookings'] ?></span><?php endif; ?>
    </a></li>
  </ul>

  <div class="admin-section-label">Управление</div>
  <ul class="admin-nav">
    <li><a href="admin_panel.php?section=tables">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/></svg>
      План зала / Столики
    </a></li>
    <li><a href="admin_panel.php?section=menu">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6h16M4 12h16M4 18h12"/></svg>
      Меню и блюда
    </a></li>
    <li><a href="admin_panel.php?section=reviews">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.5 8.5 0 0 1-7.6-3 8.5 8.5 0 0 1-1-7.7 8.4 8.4 0 0 1 8.4-5.6"/><path d="M16 8.7 11.5 13l-2.3-2.4"/></svg>
      Отзывы
    </a></li>
    <li><a href="admin_panel.php?section=gallery">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      Галерея
    </a></li>
  </ul>

  <div class="admin-section-label">Ссылки</div>
  <ul class="admin-nav">
    <li><a href="admin_booking_calendar.php" class="active">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Календарь броней
    </a></li>
    <li><a href="index.php" target="_blank">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      Открыть сайт
    </a></li>
  </ul>

  <div style="margin-top:auto;"></div>
  <div class="admin-user" style="margin-top:24px;">
    <div class="av"><?= htmlspecialchars($admin_initials) ?></div>
    <div>
      <div class="who"><?= htmlspecialchars($admin_username) ?></div>
      <div class="role">Администратор</div>
    </div>
    <a href="logout.php?type=admin" style="margin-left:auto;color:var(--ink-faint);border:none;font-size:0.8rem;">Выйти</a>
  </div>
</aside>

<div class="admin-main">
  <header class="admin-top">
    <h1>Календарь броней</h1>
    <div class="admin-quick"></div>
    <a href="logout.php?type=admin" class="btn btn-ghost btn-sm">Выйти</a>
  </header>

  <div class="admin-content">
    <div class="cal-wrap">
      <div class="cal-nav">
        <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="cal-btn">&#8592; <?= $monthNames[$prevMonth] ?></a>
        <h2><?= $monthNames[$month] ?> <?= $year ?></h2>
        <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="cal-btn"><?= $monthNames[$nextMonth] ?> &#8594;</a>
      </div>

      <div class="cal-grid">
        <?php foreach ($dayNames as $dn): ?>
        <div class="cal-header"><?= $dn ?></div>
        <?php endforeach; ?>

        <?php
        // Empty cells before first day
        for ($e = 1; $e < $firstWeekday; $e++): ?>
        <div class="cal-cell empty"></div>
        <?php endfor;

        for ($d = 1; $d <= $daysInMonth; $d++):
            $dayBookings = $allBookings[$d] ?? [];
            $cnt = count($dayBookings);
            $classes = 'cal-cell';
            if ($d === $todayDay) $classes .= ' today';
            if ($d === $selectedDay) $classes .= ' selected';
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
        ?>
        <div class="<?= $classes ?>" onclick="selectDay(<?= $d ?>)" data-day="<?= $d ?>">
          <div class="day-num"><?= $d ?></div>
          <?php if ($cnt > 0): ?>
          <div class="day-badge"><?= $cnt ?></div>
          <div class="day-dots">
            <?php foreach ($dayBookings as $db): ?>
            <div class="day-dot" style="background:<?= status_color($db['status']) ?>;" title="<?= htmlspecialchars($db['guest_name']) ?>"></div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endfor; ?>
      </div>

      <!-- Detail panel -->
      <div class="day-detail" id="dayDetail" style="<?= $selectedDay ? '' : 'display:none' ?>">
        <h3 id="detailTitle">Бронирования на <?= $selectedDay ? $selectedDay . ' ' . $monthNames[$month] : '' ?></h3>
        <div id="detailContent">
          <?php if ($selectedDay && isset($allBookings[$selectedDay])): ?>
          <?php foreach ($allBookings[$selectedDay] as $db): ?>
          <div class="booking-row">
            <div class="b-time"><?= substr($db['booking_time'], 0, 5) ?></div>
            <div class="b-name"><?= htmlspecialchars($db['guest_name']) ?></div>
            <div class="b-meta"><?= (int)$db['party_size'] ?> гост. &middot; <?= htmlspecialchars($db['table_name'] ?? 'без стола') ?></div>
            <span class="b-pill b-pill-<?= $db['status'] ?>"><?= booking_status_label($db['status']) ?></span>
            <a href="admin_panel.php?section=bookings" style="font-size:0.8rem;color:var(--ink-mute);">&#8594;</a>
          </div>
          <?php endforeach; ?>
          <?php elseif ($selectedDay): ?>
          <p style="color:var(--ink-mute);font-style:italic;">Нет броней на этот день.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
var bookings = <?= json_encode($allBookings, JSON_UNESCAPED_UNICODE) ?>;
var monthNames = <?= json_encode(array_values(['','Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь']), JSON_UNESCAPED_UNICODE) ?>;
var currentMonth = <?= $month ?>;
var statusLabels = {pending:'Ожидает',confirmed:'Подтверждена',completed:'Завершена',cancelled:'Отменена',rejected:'Отклонена'};
var pillColors = {pending:'b-pill-pending',confirmed:'b-pill-confirmed',completed:'b-pill-completed',cancelled:'b-pill-cancelled',rejected:'b-pill-rejected'};

function selectDay(d) {
  document.querySelectorAll('.cal-cell').forEach(function(c){ c.classList.remove('selected'); });
  var el = document.querySelector('.cal-cell[data-day="'+d+'"]');
  if (el) el.classList.add('selected');

  var detail = document.getElementById('dayDetail');
  var title = document.getElementById('detailTitle');
  var content = document.getElementById('detailContent');

  title.textContent = 'Бронирования на ' + d + ' ' + monthNames[currentMonth];
  detail.style.display = '';

  var dayData = bookings[d] || [];
  if (dayData.length === 0) {
    content.innerHTML = '<p style="color:var(--ink-mute);font-style:italic;">Нет броней на этот день.</p>';
    return;
  }

  var html = '';
  dayData.forEach(function(b) {
    var time = b.booking_time ? b.booking_time.substring(0,5) : '';
    var label = statusLabels[b.status] || b.status;
    var pill = pillColors[b.status] || '';
    html += '<div class="booking-row">';
    html += '<div class="b-time">'+time+'</div>';
    html += '<div class="b-name">'+escHtml(b.guest_name)+'</div>';
    html += '<div class="b-meta">'+parseInt(b.party_size)+' гост. &middot; '+escHtml(b.table_name || 'без стола')+'</div>';
    html += '<span class="b-pill '+pill+'">'+label+'</span>';
    html += '<a href="admin_panel.php?section=bookings" style="font-size:0.8rem;color:var(--ink-mute);">&#8594;</a>';
    html += '</div>';
  });
  content.innerHTML = html;

  history.replaceState(null,'','?year=<?= $year ?>&month=<?= $month ?>&day='+d);
}

function escHtml(s) {
  if (!s) return '';
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
