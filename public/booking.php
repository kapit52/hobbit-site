<?php
session_start();
include 'db.php';
require_once 'includes/csrf.php';
require_once 'includes/auth_user.php';
require_once 'includes/booking_helpers.php';
require_once 'includes/notification_helpers.php';
require_once 'includes/phone.php';

require_user_login('booking');

$userId = (int)$_SESSION['user_id'];
$prefill = ['guest_name' => '', 'guest_phone' => '', 'guest_email' => ''];
$stmt = $conn->prepare("SELECT full_name, phone, email, username FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($u) {
    $prefill['guest_name']  = $u['full_name'] ?: $u['username'];
    $prefill['guest_phone'] = $u['phone'] ?? '';
    $prefill['guest_email'] = $u['email'] ?? '';
}

$error   = '';
$success = '';
$timeSlots = booking_time_slots();
$minDate   = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ошибка безопасности. Обновите страницу.';
    } elseif (!is_user_logged_in()) {
        $error = 'Войдите в аккаунт, чтобы отправить заявку.';
    } else {
        $guestName  = trim($_POST['guest_name'] ?? '');
        [$guestPhone, $guestPhoneOk] = normalize_phone($_POST['guest_phone'] ?? '');
        $guestEmail = trim($_POST['guest_email'] ?? '');
        $bookingDate= $_POST['booking_date'] ?? '';
        $bookingTime= $_POST['booking_time'] ?? '';
        $partySize  = max(1, (int)($_POST['party_size'] ?? 2));
        $tableId    = (int)($_POST['table_id'] ?? 0) ?: null;
        $notes      = trim($_POST['notes'] ?? '');

        if ($guestName === '' || $guestPhone === '') {
            $error = !$guestPhoneOk ? 'Телефон должен быть в формате +7 и 10 цифр' : 'Укажите имя и телефон';
        } elseif ($tableId !== null && table_is_disabled($conn, $tableId)) {
            $error = 'Этот стол недоступен для брони. Выберите другой.';
        } elseif ($bookingDate < $minDate) {
            $error = 'Нельзя бронировать на прошедшую дату';
        } elseif (!in_array($bookingTime, $timeSlots, true)) {
            $error = 'Выберите корректное время';
        } elseif (!hall_has_capacity($conn, $bookingDate, $bookingTime, $partySize)) {
            $error = 'На выбранное время нет свободной вместимости. Выберите другой слот.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO bookings (user_id, guest_name, guest_phone, guest_email, booking_date, booking_time, party_size, table_id, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param('isssssiis', $userId, $guestName, $guestPhone, $guestEmail, $bookingDate, $bookingTime, $partySize, $tableId, $notes);
            if ($stmt->execute()) {
                $bookingId = $conn->insert_id;
                record_booking_status($conn, $bookingId, null, 'pending', 'customer', 'Заявка отправлена');
                create_user_notification($conn, $userId, 'booking', $bookingId, 'Бронь №' . $bookingId . ' отправлена и ожидает подтверждения');
                $success = 'Заявка на бронь №' . $bookingId . ' принята! Ожидайте подтверждения. Статус в <a href="profile.php">личном кабинете</a>.';
            } else {
                $error = 'Не удалось сохранить бронь. Попробуйте позже.';
            }
            $stmt->close();
        }
    }
}

// Занятые столы на сегодня
$bookedToday = [];
$todayRes = $conn->query("SELECT DISTINCT table_id FROM bookings WHERE booking_date='" . date('Y-m-d') . "' AND status IN ('pending','confirmed') AND table_id IS NOT NULL");
if ($todayRes) while ($r = $todayRes->fetch_assoc()) $bookedToday[] = (int)$r['table_id'];

// Выключенные админом столы — их нельзя выбирать
$disabledTables = get_disabled_table_ids($conn);

// Класс и подпись доступности стола на плане зала
function tbl_state_cls(int $id): string {
    global $bookedToday, $disabledTables;
    return (in_array($id, $disabledTables, true) || in_array($id, $bookedToday, true)) ? 'booked' : '';
}
function tbl_state_lbl(int $id): string {
    global $bookedToday, $disabledTables;
    if (in_array($id, $disabledTables, true)) return ' · недоступен';
    if (in_array($id, $bookedToday, true)) return ' · занят';
    return '';
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Бронирование стола · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Caveat:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .booking-hero{text-align:center;padding:50px 0 24px;background:linear-gradient(180deg,rgba(60,80,50,0.12),transparent),var(--parch-100);}
  .booking-hero h1{margin-bottom:10px;}
  .booking-hero .lede{max-width:560px;margin:0 auto;color:var(--ink-soft);font-style:italic;font-size:1.1rem;}
  .steps{display:flex;justify-content:center;gap:12px;padding:24px 0;flex-wrap:wrap;}
  .step{display:flex;align-items:center;gap:12px;font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.14em;font-size:0.78rem;color:var(--ink-faint);}
  .step .n{width:36px;height:36px;border-radius:50%;border:1.5px solid var(--ink-faint);display:inline-flex;align-items:center;justify-content:center;font-size:0.85rem;}
  .step.active{color:var(--amber-deep);}
  .step.active .n{background:var(--amber);color:var(--parch-50);border-color:var(--amber);box-shadow:0 0 0 4px rgba(184,118,58,0.18);}
  .step .line{width:60px;height:1px;background:var(--ink-faint);}
  .booking-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:40px;padding-bottom:60px;}
  .hall{background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);padding:28px;box-shadow:var(--shadow-soft);}
  .hall-zones{display:flex;gap:8px;margin-bottom:22px;flex-wrap:wrap;}
  .hall-zones button{padding:8px 16px;border:1px solid var(--line);background:var(--parch-100);border-radius:var(--r-sm);font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.12em;font-size:0.72rem;color:var(--ink-soft);cursor:pointer;transition:all 0.2s;}
  .hall-zones button:hover{border-color:var(--amber);}
  .hall-zones button.on{background:var(--ink);color:var(--parch-50);border-color:var(--ink);}
  .hall-map{position:relative;background:repeating-linear-gradient(90deg,rgba(139,90,43,0.06) 0 1px,transparent 1px 30px),repeating-linear-gradient(0deg,rgba(139,90,43,0.06) 0 1px,transparent 1px 30px),var(--parch-100);border:2px solid var(--ink-soft);border-radius:var(--r-md);aspect-ratio:4/3;overflow:hidden;}
  .wall{position:absolute;background:var(--ink-soft);z-index:1;}
  .fireplace{position:absolute;background:radial-gradient(ellipse at 50% 60%,#ff8a3c 0%,#c2541f 40%,#2c1810 80%),var(--ink);border:2px solid var(--amber-deep);border-radius:6px;display:flex;align-items:flex-end;justify-content:center;padding:4px;font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.1em;font-size:0.6rem;color:#ffd98a;z-index:2;}
  .bar-stand{position:absolute;background:var(--amber-deep);border:2px solid var(--ink);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;color:var(--parch-50);font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.12em;font-size:0.65rem;z-index:2;}
  .door-el{position:absolute;background:var(--moss);border:2px solid var(--forest);border-radius:50% 50% 0 0;display:flex;align-items:center;justify-content:center;color:var(--parch-50);font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.1em;font-size:0.6rem;z-index:2;}
  .table-spot{position:absolute;transform:translate(-50%,-50%);z-index:3;cursor:pointer;}
  .table-spot .tbl{width:56px;height:56px;border-radius:50%;background:var(--parch-50);border:2px solid var(--amber-deep);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:0.78rem;font-weight:700;color:var(--ink);transition:all 0.2s;position:relative;}
  .table-spot[data-shape="square"] .tbl{border-radius:6px;}
  .table-spot[data-seats="2"] .tbl{width:44px;height:44px;font-size:0.72rem;}
  .table-spot:hover .tbl{transform:scale(1.08);box-shadow:0 0 0 4px rgba(184,118,58,0.25);}
  .table-spot.booked{cursor:not-allowed;}
  .table-spot.booked .tbl{background:var(--parch-200);border-color:var(--ink-faint);color:var(--ink-faint);text-decoration:line-through;}
  .table-spot.selected .tbl{background:var(--moss);color:var(--parch-50);border-color:var(--forest);box-shadow:0 0 0 5px rgba(107,142,78,0.35),0 6px 20px rgba(60,80,50,0.5);transform:scale(1.12);}
  .table-spot.vip .tbl{border-color:var(--gold);background:radial-gradient(circle,var(--parch-50),#f1d9ae);}
  .table-spot.vip .tbl::after{content:"★";position:absolute;top:-8px;right:-8px;color:var(--gold);font-size:0.85rem;}
  .chair{position:absolute;width:8px;height:8px;border-radius:50%;background:var(--ink-soft);transform:translate(-50%,-50%);}
  .zone-label{position:absolute;font-family:var(--font-hand);color:var(--ink-mute);font-size:0.95rem;z-index:1;pointer-events:none;opacity:0.7;}
  .seats-label{position:absolute;bottom:-22px;left:50%;transform:translateX(-50%);font-family:var(--font-display);font-size:0.62rem;letter-spacing:0.1em;color:var(--ink-mute);white-space:nowrap;}
  .hall-legend{margin-top:20px;display:flex;gap:22px;font-size:0.85rem;color:var(--ink-soft);flex-wrap:wrap;}
  .hall-legend span{display:inline-flex;align-items:center;gap:8px;}
  .hall-legend .swatch{width:14px;height:14px;border-radius:50%;border:2px solid var(--amber-deep);background:var(--parch-50);}
  .hall-legend .swatch.booked{background:var(--parch-200);border-color:var(--ink-faint);}
  .hall-legend .swatch.selected{background:var(--moss);border-color:var(--forest);}
  .hall-legend .swatch.vip{border-color:var(--gold);}
  .booking-form{background:var(--parch-50);border:1px solid var(--line);border-radius:var(--r-md);padding:28px;box-shadow:var(--shadow-soft);position:sticky;top:90px;max-height:calc(100vh - 110px);overflow-y:auto;}
  .booking-form h2{font-size:1.5rem;margin-bottom:6px;}
  .booking-form .sub{color:var(--ink-mute);font-size:0.95rem;margin-bottom:22px;}
  .booked-table-card{background:var(--parch-100);border:1px solid var(--moss);border-radius:var(--r-sm);padding:16px;margin-bottom:22px;display:flex;align-items:center;gap:14px;}
  .booked-table-card .icon{width:50px;height:50px;border-radius:50%;background:var(--moss);color:var(--parch-50);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:700;font-size:1rem;flex-shrink:0;}
  .booked-table-card .name{font-family:var(--font-display);font-size:1.05rem;margin-bottom:2px;}
  .booked-table-card .meta{font-size:0.88rem;color:var(--ink-mute);}
  .table-empty-hint{padding:22px;border:1.5px dashed var(--line);border-radius:var(--r-sm);text-align:center;color:var(--ink-mute);font-style:italic;margin-bottom:22px;}
  .time-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:8px;}
  .time-grid button{padding:10px 6px;border:1px solid var(--line);background:var(--parch-50);border-radius:var(--r-sm);font-family:var(--font-display);font-size:0.85rem;color:var(--ink);cursor:pointer;transition:all 0.15s;}
  .time-grid button:hover{border-color:var(--amber);}
  .time-grid button.on{background:var(--ink);color:var(--parch-50);border-color:var(--ink);}
  .summary-box{background:var(--parch-100);border-top:1px solid var(--line);margin:22px -28px -28px;padding:20px 28px;border-radius:0 0 var(--r-md) var(--r-md);}
  .summary-box .row{display:flex;justify-content:space-between;padding:6px 0;font-size:0.95rem;}
  .summary-box .row.total{border-top:1px dashed var(--line);padding-top:14px;margin-top:8px;font-family:var(--font-display);font-size:1.1rem;}
  @media(max-width:1000px){.booking-grid{grid-template-columns:1fr;}.booking-form{position:static;max-height:none;}}
  @media(max-width:640px){
    .booking-hero{padding:36px 0 18px;}
    .steps{gap:6px;padding:16px 0;}
    .step .line{width:24px;}
    .step span:not(.n):not(.line){display:none;}
    .hall-zones{gap:6px;}
    .hall-zones button{padding:6px 10px;font-size:0.68rem;}
    .time-grid{grid-template-columns:repeat(3,1fr);gap:6px;}
    .time-grid button{padding:8px 4px;font-size:0.8rem;}
    .booking-form{padding:18px;}
    .field-row{grid-template-columns:1fr;}
  }
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<section class="booking-hero">
  <div class="container">
    <div class="breadcrumbs" style="justify-content:center;padding:0 0 14px;"><a href="index.php">Главная</a><span class="sep">/</span><span>Бронирование</span></div>
    <div class="eyebrow">Оставь место</div>
    <h1>Бронирование стола</h1>
    <p class="lede">Выбери дату, время и стол на плане зала. Подтвердим в течение 10 минут.</p>
    <div class="steps">
      <div class="step active"><span class="n">1</span><span>Выбор стола и даты</span></div>
      <span class="step"><span class="line"></span></span>
      <div class="step"><span class="n">2</span><span>Подтверждение</span></div>
    </div>
  </div>
</section>

<div class="container">
  <?php if ($success): ?>
    <div class="success-message" style="margin:20px 0;"><?= $success ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="error-message" style="margin:20px 0;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST" id="bookingForm">
    <?= csrf_field() ?>
    <input type="hidden" name="table_id" id="tableIdInput" value="">
    <input type="hidden" name="booking_time" id="timeInput" value="">

    <div class="booking-grid">
      <!-- Левая колонка: план зала -->
      <div class="hall">
        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:16px;">
          <h2 style="margin:0;font-size:1.6rem;">План зала</h2>
          <div class="muted small-caps">9 столов · 38 мест</div>
        </div>
        <div class="hall-zones">
          <button type="button" class="on" data-zone="all">Весь зал</button>
          <button type="button" data-zone="hall">Зал у очага</button>
          <button type="button" data-zone="terrace">Терраса</button>
          <button type="button" data-zone="vip">VIP-зал</button>
        </div>

        <div class="hall-map" id="hallMap">
          <!-- Перегородки -->
          <div class="wall" style="left:18%;right:35%;top:64%;height:3px;"></div>
          <div class="wall" style="left:65%;right:0;top:0;bottom:36%;width:3px;"></div>
          <div style="position:absolute;left:65%;right:24%;top:48%;border-top:3px dashed var(--ink-soft);opacity:0.6;z-index:1;"></div>
          <div style="position:absolute;left:88%;right:0;top:48%;border-top:3px dashed var(--ink-soft);opacity:0.6;z-index:1;"></div>
          <div class="door-el" style="left:77%;top:45.5%;width:64px;height:16px;font-size:0.52rem;">вход</div>
          <!-- Очаг -->
          <div class="fireplace" style="left:4%;top:4%;width:140px;height:50px;">очаг</div>
          <!-- Бар -->
          <div class="bar-stand" style="right:4%;top:84%;width:160px;height:36px;">барная стойка</div>
          <!-- Дверь -->
          <div class="door-el" style="left:50%;transform:translateX(-50%);bottom:0;width:90px;height:22px;">вход</div>
          <div class="door-el" style="left:2%;top:61%;width:66px;height:18px;font-size:0.55rem;">проход</div>
          <!-- Зоны -->
          <div class="zone-label" style="left:5%;top:18%;">Зал у очага</div>
          <div class="zone-label" style="right:5%;top:12%;">VIP-зал</div>
          <div class="zone-label" style="left:22%;bottom:18%;">Терраса</div>

          <!-- Столы: зал у очага -->
          <div class="table-spot <?= tbl_state_cls(1) ?>" data-id="1" data-seats="2" data-name="У очага" data-zone="hall" style="left:14%;top:30%;">
            <div class="tbl">1</div>
            <span class="chair" style="left:-8px;top:50%;"></span>
            <span class="chair" style="right:-8px;top:50%;transform:translate(50%,-50%);"></span>
            <span class="seats-label">2 места<?= tbl_state_lbl(1) ?></span>
          </div>
          <div class="table-spot <?= tbl_state_cls(2) ?>" data-id="2" data-seats="2" data-name="Тёплый угол" data-zone="hall" style="left:30%;top:25%;">
            <div class="tbl">2</div>
            <span class="chair" style="left:-8px;top:50%;"></span>
            <span class="chair" style="right:-8px;top:50%;transform:translate(50%,-50%);"></span>
            <span class="seats-label">2 места<?= tbl_state_lbl(2) ?></span>
          </div>
          <div class="table-spot <?= tbl_state_cls(3) ?>" data-id="3" data-seats="4" data-name="У окна" data-zone="hall" style="left:47%;top:50%;">
            <div class="tbl">3</div>
            <span class="chair" style="left:50%;top:-8px;transform:translate(-50%,-50%);"></span>
            <span class="chair" style="right:-8px;top:50%;transform:translate(50%,-50%);"></span>
            <span class="chair" style="left:50%;bottom:-8px;transform:translate(-50%,50%);"></span>
            <span class="chair" style="left:-8px;top:50%;"></span>
            <span class="seats-label">4 места<?= tbl_state_lbl(3) ?></span>
          </div>
          <div class="table-spot <?= tbl_state_cls(4) ?>" data-id="4" data-seats="6" data-name="Круглый стол" data-zone="hall" style="left:22%;top:48%;" data-shape="square">
            <div class="tbl">4</div>
            <span class="chair" style="left:50%;top:-8px;transform:translate(-50%,-50%);"></span>
            <span class="chair" style="right:-8px;top:30%;transform:translate(50%,-50%);"></span>
            <span class="chair" style="right:-8px;top:70%;transform:translate(50%,-50%);"></span>
            <span class="chair" style="left:50%;bottom:-8px;transform:translate(-50%,50%);"></span>
            <span class="chair" style="left:-8px;top:30%;"></span>
            <span class="chair" style="left:-8px;top:70%;"></span>
            <span class="seats-label">6 мест<?= tbl_state_lbl(4) ?></span>
          </div>
          <div class="table-spot <?= tbl_state_cls(8) ?>" data-id="8" data-seats="8" data-name="Большой стол" data-zone="hall" style="left:50%;top:22%;" data-shape="square">
            <div class="tbl" style="width:64px;height:64px;">8</div>
            <span class="chair" style="left:33%;top:-8px;transform:translate(-50%,-50%);"></span>
            <span class="chair" style="left:67%;top:-8px;transform:translate(-50%,-50%);"></span>
            <span class="chair" style="left:33%;bottom:-8px;transform:translate(-50%,50%);"></span>
            <span class="chair" style="left:67%;bottom:-8px;transform:translate(-50%,50%);"></span>
            <span class="chair" style="left:-8px;top:33%;"></span>
            <span class="chair" style="left:-8px;top:67%;"></span>
            <span class="chair" style="right:-8px;top:33%;transform:translate(50%,-50%);"></span>
            <span class="chair" style="right:-8px;top:67%;transform:translate(50%,-50%);"></span>
            <span class="seats-label">8 мест<?= tbl_state_lbl(8) ?></span>
          </div>
          <!-- VIP -->
          <div class="table-spot vip <?= tbl_state_cls(5) ?>" data-id="5" data-seats="4" data-name="VIP-стол" data-zone="vip" style="left:82%;top:25%;" data-shape="square">
            <div class="tbl">V</div>
            <span class="chair" style="left:50%;top:-8px;transform:translate(-50%,-50%);"></span>
            <span class="chair" style="right:-8px;top:50%;transform:translate(50%,-50%);"></span>
            <span class="chair" style="left:50%;bottom:-8px;transform:translate(-50%,50%);"></span>
            <span class="chair" style="left:-8px;top:50%;"></span>
            <span class="seats-label">VIP · 4 места<?= tbl_state_lbl(5) ?></span>
          </div>
          <!-- Терраса -->
          <div class="table-spot <?= tbl_state_cls(6) ?>" data-id="6" data-seats="4" data-name="Терраса №1" data-zone="terrace" style="left:14%;top:78%;">
            <div class="tbl">6</div>
            <span class="seats-label">4 места<?= tbl_state_lbl(6) ?></span>
          </div>
          <div class="table-spot <?= tbl_state_cls(7) ?>" data-id="7" data-seats="6" data-name="Терраса №2" data-zone="terrace" style="left:38%;top:80%;" data-shape="square">
            <div class="tbl">7</div>
            <span class="seats-label">6 мест<?= tbl_state_lbl(7) ?></span>
          </div>
          <div class="table-spot <?= tbl_state_cls(9) ?>" data-id="9" data-seats="2" data-name="Терраса №3" data-zone="terrace" style="left:56%;top:78%;">
            <div class="tbl">9</div>
            <span class="seats-label">2 места<?= tbl_state_lbl(9) ?></span>
          </div>

          <div class="firefly-stage" data-count="8"></div>
        </div>

        <div class="hall-legend">
          <span><span class="swatch"></span>Свободно</span>
          <span><span class="swatch selected"></span>Выбрано</span>
          <span><span class="swatch booked"></span>Занято</span>
          <span><span class="swatch vip"></span>VIP-стол</span>
        </div>
      </div>

      <!-- Правая колонка: форма -->
      <aside class="booking-form">
        <h2>Детали брони</h2>
        <p class="sub">Заполни — перезвоним в течение 10 минут.</p>

        <div class="field-row">
          <div class="field">
            <label>Дата</label>
            <input type="date" name="booking_date" id="bookDate" min="<?= $minDate ?>" value="<?= $minDate ?>" required>
          </div>
          <div class="field">
            <label>Гостей</label>
            <input type="number" name="party_size" id="partySize" min="1" max="20" value="2" required>
          </div>
        </div>

        <div class="field">
          <label>Время прихода</label>
          <div class="time-grid" id="timeGrid">
            <?php foreach ($timeSlots as $slot): ?>
              <button type="button" class="time-btn" data-time="<?= $slot ?>"><?= substr($slot, 0, 5) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label style="font-family:var(--font-display);text-transform:uppercase;letter-spacing:0.15em;font-size:0.72rem;color:var(--ink-soft);display:block;margin-bottom:8px;">Выбранный стол</label>
          <div class="table-empty-hint" id="emptySelection">Кликни на свободный кружок на плане ←</div>
          <div class="booked-table-card" id="selectedTableCard" style="display:none;">
            <div class="icon" id="sTableIcon">—</div>
            <div style="flex:1;">
              <div class="name" id="sTableName">—</div>
              <div class="meta" id="sTableMeta">—</div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="clearSelection()">Сменить</button>
          </div>
        </div>

        <div class="ornament" style="margin:22px 0;">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 L14 9 L21 12 L14 15 L12 22 L10 15 L3 12 L10 9 Z" opacity="0.5"/></svg>
        </div>

        <div class="field-row">
          <div class="field">
            <label>Имя *</label>
            <input type="text" name="guest_name" value="<?= htmlspecialchars($prefill['guest_name']) ?>" required>
          </div>
          <div class="field">
            <label>Телефон *</label>
            <input type="tel" name="guest_phone" value="" placeholder="+7 900 000-00-00" required>
          </div>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="guest_email" value="<?= htmlspecialchars($prefill['guest_email']) ?>">
        </div>
        <div class="field">
          <label>Особые пожелания</label>
          <textarea name="notes" rows="3" placeholder="Аллергии, торт, детский стульчик…"></textarea>
        </div>

        <div class="summary-box">
          <div class="row"><span>Дата</span><strong id="sumDate">—</strong></div>
          <div class="row"><span>Время</span><strong id="sumTime">не выбрано</strong></div>
          <div class="row"><span>Гости</span><strong id="sumGuests">2</strong></div>
          <div class="row"><span>Стол</span><strong id="sumTable">не выбран</strong></div>
          <div class="row total"><span>Бронь</span><strong>бесплатно</strong></div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:22px;">Подтвердить бронирование</button>
        <p style="text-align:center;font-size:0.82rem;color:var(--ink-mute);margin:14px 0 0;">Нажимая кнопку, ты соглашаешься с <a href="rules.php" target="_blank">правилами таверны</a></p>
      </aside>
    </div>
  </form>
  <?php endif; ?>
</div>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div><div class="brand" style="margin-bottom:14px;padding:0;"><img src="assets/brand-mark.svg" alt="" class="brand-mark"><span>Ширский уголок</span></div><p style="color:var(--parch-200);font-size:0.95rem;">Уютная таверна для добрых путников.</p></div>
      <div><h4>Разделы</h4><ul><li><a href="menu.php">Меню</a></li><li><a href="booking.php">Бронь стола</a></li><li><a href="gallery.php">Галерея</a></li><li><a href="reviews.php">Отзывы</a></li></ul></div>
      <div><h4>Контакты</h4><ul><li>пер. Зелёного Холма, 7</li><li><a href="tel:+78124567890">+7 (812) 456-78-90</a></li></ul></div>
      <div><h4>Телефон</h4><p style="font-size:0.9rem;color:var(--parch-200);">Принимаем звонки с 12:00 до 22:00</p><a href="tel:+78124567890" class="btn btn-ghost-light btn-sm" style="margin-top:8px;">Позвонить</a></div>
    </div>
    <div class="footer-bottom"><div>© 1893 — <?= date('Y') ?> Таверна «Ширский уголок»</div><div><a href="rules.php" style="color:var(--ink-faint);">Правила таверны</a></div></div>
  </div>
</footer>

<script src="atmosphere.js"></script>
<script src="assets/phone-validate.js"></script>
<script>
// Выбор стола
document.querySelectorAll('.table-spot:not(.booked)').forEach(spot => {
  spot.addEventListener('click', () => {
    document.querySelectorAll('.table-spot').forEach(s => s.classList.remove('selected'));
    spot.classList.add('selected');
    const id = spot.dataset.id, name = spot.dataset.name, seats = spot.dataset.seats;
    document.getElementById('tableIdInput').value = id;
    document.getElementById('emptySelection').style.display = 'none';
    document.getElementById('selectedTableCard').style.display = 'flex';
    document.getElementById('sTableIcon').textContent = id;
    document.getElementById('sTableName').textContent = name;
    document.getElementById('sTableMeta').textContent = seats + ' мест';
    document.getElementById('sumTable').textContent = name + ' (№' + id + ')';
  });
});
function clearSelection() {
  document.querySelectorAll('.table-spot').forEach(s => s.classList.remove('selected'));
  document.getElementById('tableIdInput').value = '';
  document.getElementById('emptySelection').style.display = '';
  document.getElementById('selectedTableCard').style.display = 'none';
  document.getElementById('sumTable').textContent = 'не выбран';
}
// Фильтр зон
document.querySelectorAll('.hall-zones button').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('.hall-zones button').forEach(x => x.classList.remove('on'));
    b.classList.add('on');
    const z = b.dataset.zone;
    document.querySelectorAll('.table-spot').forEach(s => {
      s.style.opacity = (z==='all'||s.dataset.zone===z) ? '' : '0.25';
      s.style.pointerEvents = (z==='all'||s.dataset.zone===z) ? '' : 'none';
    });
  });
});
// Время
document.querySelectorAll('.time-btn').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('.time-btn').forEach(x => x.classList.remove('on'));
    b.classList.add('on');
    document.getElementById('timeInput').value = b.dataset.time;
    document.getElementById('sumTime').textContent = b.textContent;
  });
});
// Синхронизация сводки
const bookDate  = document.getElementById('bookDate');
const partySize = document.getElementById('partySize');
if (bookDate)  bookDate.addEventListener('input',  () => document.getElementById('sumDate').textContent   = bookDate.value);
if (partySize) partySize.addEventListener('input', () => document.getElementById('sumGuests').textContent = partySize.value);
if (bookDate) document.getElementById('sumDate').textContent = bookDate.value;
</script>
</body>
</html>
