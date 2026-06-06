<?php
/**
 * ТЕХНИЧЕСКАЯ АДМИНКА «Ширский уголок».
 * Вкладки: Дашборд метрик · API (Swagger) · Логи (реальное время) · Логеры ·
 *          Статус систем · Система.
 * Доступ — только администратор (SHIREADMIN). Данные тянутся из admin_tech_api.php.
 */
require_once __DIR__ . '/includes/admin_auth_lite.php';

$admin_username = get_admin_username();
$admin_initials = mb_strtoupper(mb_substr($admin_username, 0, 1));
$csrf = csrf_token();
$tabs = [
    'dashboard' => 'Дашборд',
    'api'       => 'API (Swagger)',
    'logs'      => 'Логи',
    'loggers'   => 'Логеры',
    'status'    => 'Статус систем',
    'system'    => 'Система',
];
$active = $_GET['tab'] ?? 'dashboard';
if (!isset($tabs[$active])) $active = 'dashboard';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Тех. панель · Ширский уголок</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/admin.css">
<link rel="stylesheet" href="assets/admin-tech.css?v=<?= @filemtime(__DIR__ . '/assets/admin-tech.css') ?>">
<link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
</head>
<body class="admin-body tech">

<aside class="admin-side">
  <div class="admin-brand">
    <img src="assets/brand-mark.svg" alt="">
    <div>
      <div class="name">Ширский<br>уголок</div>
      <div class="sub">тех. панель</div>
    </div>
  </div>

  <div class="admin-section-label">Мониторинг</div>
  <ul class="admin-nav">
    <li><a href="?tab=dashboard" data-tab="dashboard" class="<?= $active==='dashboard'?'active':'' ?>" onclick="showTab('dashboard');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 12h4l2 7 4-14 2 7h6"/></svg>
      Дашборд метрик
    </a></li>
    <li><a href="?tab=status" data-tab="status" class="<?= $active==='status'?'active':'' ?>" onclick="showTab('status');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
      Статус систем
    </a></li>
  </ul>

  <div class="admin-section-label">Наблюдаемость</div>
  <ul class="admin-nav">
    <li><a href="?tab=logs" data-tab="logs" class="<?= $active==='logs'?'active':'' ?>" onclick="showTab('logs');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6h16M4 12h10M4 18h7"/></svg>
      Логи · live
    </a></li>
    <li><a href="?tab=loggers" data-tab="loggers" class="<?= $active==='loggers'?'active':'' ?>" onclick="showTab('loggers');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.6-2-3.4-2.4 1a7 7 0 0 0-1.7-1L16.5 2h-4l-.3 2.6a7 7 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 1.7 1l.3 2.6h4l.3-2.6a7 7 0 0 0 1.7-1l2.4 1 2-3.4-2-1.6a7 7 0 0 0 .1-1z"/></svg>
      Логеры
    </a></li>
  </ul>

  <div class="admin-section-label">Интерфейсы</div>
  <ul class="admin-nav">
    <li><a href="?tab=api" data-tab="api" class="<?= $active==='api'?'active':'' ?>" onclick="showTab('api');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/></svg>
      API · Swagger
    </a></li>
    <li><a href="?tab=system" data-tab="system" class="<?= $active==='system'?'active':'' ?>" onclick="showTab('system');return false;">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>
      Система
    </a></li>
  </ul>

  <div class="admin-section-label">Ссылки</div>
  <ul class="admin-nav">
    <li><a href="admin_panel.php">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      ← В обычную админку
    </a></li>
    <li><a href="admin_tech_openapi.php" target="_blank">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14 3h7v7M21 3l-9 9M5 5h6M5 5v14h14v-6"/></svg>
      OpenAPI JSON
    </a></li>
  </ul>

  <div style="margin-top:auto;"></div>
  <div class="admin-user" style="margin-top:24px;">
    <div class="av"><?= htmlspecialchars($admin_initials) ?></div>
    <div>
      <div class="who"><?= htmlspecialchars($admin_username) ?></div>
      <div class="role">Инженер</div>
    </div>
    <a href="logout.php?type=admin" style="margin-left:auto;color:var(--ink-faint);border:none;font-size:0.8rem;">Выйти</a>
  </div>
</aside>

<div class="admin-main">
  <header class="admin-top">
    <h1 id="techTitle"><?= htmlspecialchars($tabs[$active]) ?><span class="tech-tag">tech</span></h1>
    <div class="admin-quick">
      <span id="globalHealth" class="status-pill ok live">проверка…</span>
    </div>
    <span class="tech-refresh-note" id="lastUpdate">—</span>
  </header>

  <div class="admin-content">

    <!-- ===== ДАШБОРД ===== -->
    <div id="tab-dashboard" class="admin-section-content <?= $active==='dashboard'?'active':'' ?>">
      <div class="tech-grid cols-4" id="metricTiles">
        <div class="metric"><span class="m-label">Загрузка…</span><span class="m-value">·</span></div>
        <div class="metric"><span class="m-label">Загрузка…</span><span class="m-value">·</span></div>
        <div class="metric"><span class="m-label">Загрузка…</span><span class="m-value">·</span></div>
        <div class="metric"><span class="m-label">Загрузка…</span><span class="m-value">·</span></div>
      </div>

      <div class="tech-grid cols-2" style="margin-top:18px;">
        <div class="dash-card">
          <h3>Бизнес-показатели</h3>
          <div class="tech-grid cols-2" id="bizTiles"></div>
        </div>
        <div class="dash-card">
          <h3>Активность ошибок · 24 ч</h3>
          <svg id="errChart" viewBox="0 0 400 120" preserveAspectRatio="none" style="width:100%;height:120px;"></svg>
          <div id="lastErrorBox" style="margin-top:10px;font-size:0.82rem;color:var(--ink-mute);"></div>
        </div>
      </div>

      <div class="dash-card" style="margin-top:18px;">
        <h3>Таблицы базы данных</h3>
        <div id="dbTables"><p class="muted">Загрузка…</p></div>
      </div>
    </div>

    <!-- ===== СТАТУС СИСТЕМ ===== -->
    <div id="tab-status" class="admin-section-content <?= $active==='status'?'active':'' ?>">
      <p class="swagger-frame-note">Опрос каждые 5 секунд. <span id="statusStamp" class="tech-refresh-note"></span></p>
      <div class="tech-grid cols-3" id="statusCards">
        <div class="status-card"><div class="sc-head"><span class="sc-title">Фронтенд</span><span class="status-pill">…</span></div></div>
        <div class="status-card"><div class="sc-head"><span class="sc-title">Бэкенд</span><span class="status-pill">…</span></div></div>
        <div class="status-card"><div class="sc-head"><span class="sc-title">База данных</span><span class="status-pill">…</span></div></div>
      </div>
    </div>

    <!-- ===== ЛОГИ ===== -->
    <div id="tab-logs" class="admin-section-content <?= $active==='logs'?'active':'' ?>">
      <div class="log-toolbar">
        <label class="muted" style="font-size:0.8rem;">Уровень</label>
        <select id="logLevel">
          <option value="">все</option>
          <option value="debug">debug+</option>
          <option value="info">info+</option>
          <option value="warning">warning+</option>
          <option value="error">error+</option>
        </select>
        <label class="muted" style="font-size:0.8rem;">Канал</label>
        <select id="logChannel"><option value="">все</option></select>
        <span id="liveDot" class="status-pill ok live" style="font-size:0.62rem;">live</span>
        <div class="spacer"></div>
        <button class="btn-small" id="logPause" type="button">⏸ Пауза</button>
        <button class="btn-small" id="logTest" type="button">+ Тест</button>
        <button class="btn-small btn-danger" id="logClear" type="button">Очистить</button>
      </div>
      <div class="log-console" id="logConsole"><div class="log-empty">Ожидание записей…</div></div>
      <p class="tech-refresh-note" style="margin-top:8px;">Файл: logs/app.log (вне веб-корня) · <span id="logMeta">—</span></p>
    </div>

    <!-- ===== ЛОГЕРЫ ===== -->
    <div id="tab-loggers" class="admin-section-content <?= $active==='loggers'?'active':'' ?>">
      <p class="swagger-frame-note">Каналы логирования: включение и минимальный уровень. Настройки сохраняются в logs/loggers.json и применяются сразу.</p>
      <div id="loggerList"><p class="muted">Загрузка…</p></div>
    </div>

    <!-- ===== API / SWAGGER ===== -->
    <div id="tab-api" class="admin-section-content <?= $active==='api'?'active':'' ?>">
      <p class="swagger-frame-note">Интерактивная схема HTTP-API сайта. Источник: <code>admin_tech_openapi.php</code>.</p>
      <div id="swaggerUI"></div>
    </div>

    <!-- ===== СИСТЕМА ===== -->
    <div id="tab-system" class="admin-section-content <?= $active==='system'?'active':'' ?>">
      <div class="tech-grid cols-2">
        <div class="dash-card"><h3>PHP / Сервер</h3><ul class="kv-list" id="sysPhp"></ul></div>
        <div class="dash-card"><h3>Конфигурация PHP</h3><ul class="kv-list" id="sysIni"></ul></div>
      </div>
      <div class="dash-card" style="margin-top:18px;"><h3>Диск</h3><ul class="kv-list" id="sysDisk"></ul></div>
      <div class="dash-card" style="margin-top:18px;"><h3>Загруженные расширения PHP</h3><div class="ext-chips" id="sysExt"></div></div>
    </div>

  </div>
</div>

<script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
<script>
const CSRF = <?= json_encode($csrf) ?>;
const API = 'admin_tech_api.php';

/* ---------- утилиты ---------- */
function api(action, params = {}) {
  const q = new URLSearchParams({ action, ...params });
  return fetch(API + '?' + q.toString(), { credentials: 'same-origin' }).then(r => r.json());
}
function apiPost(action, data = {}) {
  const body = new URLSearchParams({ action, csrf_token: CSRF, ...data });
  return fetch(API, { method: 'POST', credentials: 'same-origin', body }).then(r => r.json());
}
function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function fmtTime(ts) { const d = new Date(ts * 1000); return d.toLocaleTimeString('ru-RU', {hour12:false}) + '.' + String(d.getMilliseconds()).padStart(3,'0'); }
function num(n) { return n == null ? '—' : Number(n).toLocaleString('ru-RU'); }
function stamp() { document.getElementById('lastUpdate').textContent = 'обновлено ' + new Date().toLocaleTimeString('ru-RU', {hour12:false}); }

/* ---------- навигация по вкладкам ---------- */
const tabTitles = <?= json_encode($tabs, JSON_UNESCAPED_UNICODE) ?>;
let currentTab = <?= json_encode($active) ?>;
const timers = {};
function clearTimers() { Object.values(timers).forEach(clearInterval); for (const k in timers) delete timers[k]; }

function showTab(name) {
  if (!tabTitles[name]) name = 'dashboard';
  currentTab = name;
  document.querySelectorAll('.admin-section-content').forEach(s => s.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  document.querySelectorAll('.admin-nav a[data-tab]').forEach(a => a.classList.toggle('active', a.dataset.tab === name));
  document.getElementById('techTitle').innerHTML = esc(tabTitles[name]) + '<span class="tech-tag">tech</span>';
  history.replaceState(null, '', '?tab=' + name);
  clearTimers();
  if (name === 'dashboard') { loadMetrics(); timers.m = setInterval(loadMetrics, 10000); }
  if (name === 'status')    { loadStatus();  timers.s = setInterval(loadStatus, 5000); }
  if (name === 'logs')      { startLogs(); }
  if (name === 'loggers')   { loadLoggers(); }
  if (name === 'system')    { loadSystem(); }
  if (name === 'api')       { initSwagger(); }
}

/* ---------- глобальный индикатор здоровья (всегда опрашиваем) ---------- */
function refreshGlobalHealth() {
  api('status').then(d => {
    const order = { down: 0, degraded: 1, ok: 2 };
    const worst = [d.front, d.back, d.db].reduce((w, s) => order[s.status] < order[w] ? s.status : w, 'ok');
    const el = document.getElementById('globalHealth');
    el.className = 'status-pill live ' + worst;
    el.textContent = worst === 'ok' ? 'все системы в норме' : (worst === 'down' ? 'есть сбой' : 'есть замечания');
  }).catch(() => {});
}

/* ---------- ДАШБОРД ---------- */
function tile(label, value, sub, cls, spark) {
  return `<div class="metric"><span class="m-label">${esc(label)}</span>`
       + `<span class="m-value ${cls||''}">${value}</span>`
       + (sub ? `<span class="m-sub">${esc(sub)}</span>` : '')
       + (spark || '') + `</div>`;
}
function loadMetrics() {
  api('metrics').then(d => {
    const L = d.logs, DB = d.db, B = d.business, R = d.runtime;
    const errCls = L.errors_24h > 0 ? 'bad' : 'good';
    const dbCls = DB.up ? 'good' : 'bad';
    document.getElementById('metricTiles').innerHTML =
        tile('Ошибок за 24 ч', num(L.errors_24h), (L.warnings_24h||0) + ' предупреждений', errCls)
      + tile('Записей в логе', num(L.total), 'за последний срез', '')
      + tile('Запросов (лог)', num(L.requests), R.php ? ('avg ' + (L.req_avg_ms==null?'—':L.req_avg_ms+' мс')) : '', '')
      + tile('Состояние БД', DB.up ? 'ONLINE' : 'OFFLINE', DB.up ? (DB.latency_ms + ' мс · ' + (DB.size_mb||'—') + ' МБ') : (DB.error||''), dbCls);

    const biz = [
      ['Заказов сегодня', B.orders_today], ['Заказов в ожидании', B.orders_pending],
      ['Броней в ожидании', B.bookings_pending], ['Пользователей', B.users],
      ['Отзывов', B.reviews], ['Позиций меню', B.menu_items],
    ];
    document.getElementById('bizTiles').innerHTML = biz.map(([k,v]) => tile(k, num(v), '', '')).join('');

    // график ошибок 24ч
    drawSeries('errChart', L.series_24h || []);
    const le = L.last_error;
    document.getElementById('lastErrorBox').innerHTML = le
      ? `<strong style="color:var(--berry)">Последняя ошибка:</strong> [${esc(le.channel)}] ${esc(le.msg)} <span class="tech-refresh-note">${fmtTime(le.ts)}</span>`
      : '<span class="muted">Ошибок не зафиксировано 👍</span>';

    // таблицы БД
    if (DB.up && DB.tables && DB.tables.length) {
      let h = '<table class="tech-table"><thead><tr><th>Таблица</th><th class="num">Строк</th><th class="num">Размер, КБ</th></tr></thead><tbody>';
      DB.tables.forEach(t => h += `<tr><td>${esc(t.name)}</td><td class="num">${num(t.rows)}</td><td class="num">${num(t.size_kb)}</td></tr>`);
      h += '</tbody></table>';
      document.getElementById('dbTables').innerHTML = h;
    } else {
      document.getElementById('dbTables').innerHTML = '<p class="muted">' + (DB.up ? 'Нет данных.' : 'БД недоступна: ' + esc(DB.error||'')) + '</p>';
    }
    stamp();
  }).catch(() => {});
}
function drawSeries(id, series) {
  const svg = document.getElementById(id);
  const W = 400, H = 120, n = series.length || 24;
  const max = Math.max(1, ...series);
  const bw = W / n;
  let bars = '';
  series.forEach((v, i) => {
    const bh = Math.round((v / max) * (H - 16));
    const color = v > 0 ? '#8b1f3a' : '#c9b893';
    bars += `<rect x="${(i*bw+1).toFixed(1)}" y="${H-bh}" width="${(bw-2).toFixed(1)}" height="${bh}" rx="2" fill="${color}" opacity="${v>0?0.85:0.4}"><title>${v} за час -${n-1-i}</title></rect>`;
  });
  svg.innerHTML = bars;
}

/* ---------- СТАТУС ---------- */
function ckIcon(ok) {
  return ok
    ? '<svg class="ck ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg>'
    : '<svg class="ck no" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M18 6L6 18M6 6l12 12"/></svg>';
}
function statusCard(title, s) {
  const lbl = { ok: 'В норме', degraded: 'Замечания', down: 'Сбой' }[s.status] || s.status;
  let checks = (s.checks||[]).map(c =>
    `<li>${ckIcon(c.ok)}<span class="ck-name">${esc(c.name)}</span><span class="ck-detail">${esc(c.detail)}</span></li>`).join('');
  return `<div class="status-card ${s.status}">
    <div class="sc-head"><span class="sc-title">${esc(title)}</span><span class="status-pill ${s.status}">${esc(lbl)}</span></div>
    <ul class="sc-checks">${checks}</ul></div>`;
}
function loadStatus() {
  api('status').then(d => {
    document.getElementById('statusCards').innerHTML =
        statusCard('Фронтенд', d.front) + statusCard('Бэкенд', d.back) + statusCard('База данных', d.db);
    document.getElementById('statusStamp').textContent = 'обновлено ' + new Date().toLocaleTimeString('ru-RU',{hour12:false});
    stamp();
  }).catch(() => {});
}

/* ---------- ЛОГИ (реальное время) ---------- */
let logOffset = null, logPaused = false, logTimer = null, channelsSeen = new Set();
function logLineHTML(e) {
  const ctx = e.ctx ? ' ' + esc(JSON.stringify(e.ctx)) : '';
  return `<div class="log-line lv-${esc(e.level)}"><span class="lt">${fmtTime(e.ts)}</span>`
       + `<span class="ll">${esc(e.level)}</span><span class="lc">${esc(e.channel)}</span>`
       + `<span class="lm">${esc(e.msg)}<span class="lx">${ctx}</span></span></div>`;
}
function pollLogs() {
  if (logPaused) { logTimer = setTimeout(pollLogs, 1500); return; }
  const params = { limit: 400 };
  if (logOffset !== null) params.offset = logOffset;
  const lvl = document.getElementById('logLevel').value;
  const ch = document.getElementById('logChannel').value;
  if (lvl) params.level = lvl;
  if (ch) params.channel = ch;
  api('logs', params).then(d => {
    const cons = document.getElementById('logConsole');
    const atBottom = cons.scrollHeight - cons.scrollTop - cons.clientHeight < 40;
    if (logOffset === null) cons.innerHTML = '';
    if (d.entries && d.entries.length) {
      const empty = cons.querySelector('.log-empty'); if (empty) empty.remove();
      d.entries.forEach(e => { cons.insertAdjacentHTML('beforeend', logLineHTML(e));
        if (e.channel) channelsSeen.add(e.channel); });
      syncChannels();
      // ограничим DOM до 1500 строк
      while (cons.children.length > 1500) cons.removeChild(cons.firstChild);
      if (atBottom) cons.scrollTop = cons.scrollHeight;
    }
    if (logOffset === null && (!d.entries || !d.entries.length) && !cons.querySelector('.log-line')) {
      cons.innerHTML = '<div class="log-empty">Записей нет. Нажмите «+ Тест» или подождите событий.</div>';
    }
    logOffset = d.offset;
    document.getElementById('logMeta').textContent = (d.size!=null ? (Math.round(d.size/1024*10)/10 + ' КБ') : '') + ' · offset ' + logOffset;
  }).catch(() => {}).finally(() => { logTimer = setTimeout(pollLogs, 1800); });
}
function syncChannels() {
  const sel = document.getElementById('logChannel');
  channelsSeen.forEach(ch => {
    if (![...sel.options].some(o => o.value === ch)) {
      const o = document.createElement('option'); o.value = ch; o.textContent = ch; sel.appendChild(o);
    }
  });
}
function resetLogStream() {
  logOffset = null;
  document.getElementById('logConsole').innerHTML = '<div class="log-empty">Загрузка…</div>';
}
function startLogs() {
  if (logTimer) clearTimeout(logTimer);
  resetLogStream();
  pollLogs();
}
document.getElementById('logLevel').addEventListener('change', resetLogStream);
document.getElementById('logChannel').addEventListener('change', resetLogStream);
document.getElementById('logPause').addEventListener('click', e => {
  logPaused = !logPaused;
  e.target.textContent = logPaused ? '▶ Продолжить' : '⏸ Пауза';
  document.getElementById('liveDot').style.opacity = logPaused ? '0.3' : '1';
});
document.getElementById('logTest').addEventListener('click', () => apiPost('log_test'));
document.getElementById('logClear').addEventListener('click', () => {
  if (!confirm('Очистить весь журнал?')) return;
  apiPost('log_clear').then(() => { channelsSeen = new Set(); resetLogStream(); });
});

/* ---------- ЛОГЕРЫ ---------- */
let logLevelsList = [];
function loadLoggers() {
  api('loggers_get').then(d => {
    logLevelsList = d.levels || [];
    const cfg = d.config || {};
    let h = '';
    for (const key in cfg) {
      const c = cfg[key];
      const opts = logLevelsList.map(l => `<option value="${l}" ${c.min_level===l?'selected':''}>${l}</option>`).join('');
      h += `<div class="logger-row">
        <label class="tech-switch"><input type="checkbox" data-ch="${esc(key)}" ${c.enabled?'checked':''}><span class="sl"></span></label>
        <div><div class="lg-name">${esc(c.label||key)}</div><div class="lg-key">${esc(key)}</div></div>
        <div class="lg-spacer"></div>
        <label class="muted" style="font-size:0.8rem;">мин. уровень</label>
        <select data-ch-level="${esc(key)}">${opts}</select>
      </div>`;
    }
    const box = document.getElementById('loggerList');
    box.innerHTML = h;
    box.querySelectorAll('input[data-ch]').forEach(inp =>
      inp.addEventListener('change', () => apiPost('loggers_set', { channel: inp.dataset.ch, enabled: inp.checked ? 1 : 0 })));
    box.querySelectorAll('select[data-ch-level]').forEach(sel =>
      sel.addEventListener('change', () => apiPost('loggers_set', { channel: sel.dataset.chLevel, min_level: sel.value })));
  });
}

/* ---------- СИСТЕМА ---------- */
function kv(k, v) { return `<li><span class="k">${esc(k)}</span><span class="v">${esc(v)}</span></li>`; }
function loadSystem() {
  api('sysinfo').then(d => {
    const p = d.php, ini = d.ini, disk = d.disk;
    document.getElementById('sysPhp').innerHTML =
      kv('PHP', p.version) + kv('SAPI', p.sapi) + kv('ОС', p.os) + kv('Сервер', p.server)
      + kv('Часовой пояс', p.timezone) + kv('Время сервера', p.time);
    document.getElementById('sysIni').innerHTML =
      kv('memory_limit', ini.memory_limit) + kv('upload_max_filesize', ini.upload_max_filesize)
      + kv('post_max_size', ini.post_max_size) + kv('max_execution_time', ini.max_execution_time)
      + kv('display_errors', ini.display_errors) + kv('error_reporting', ini.error_reporting);
    document.getElementById('sysDisk').innerHTML =
      kv('Свободно', (disk.free_gb??'—') + ' ГБ') + kv('Всего', (disk.total_gb??'—') + ' ГБ')
      + kv('Занято', (disk.used_pct??'—') + ' %');
    document.getElementById('sysExt').innerHTML = (d.extensions||[]).sort().map(e => `<span>${esc(e)}</span>`).join('');
  });
}

/* ---------- SWAGGER ---------- */
let swaggerReady = false;
function initSwagger() {
  if (swaggerReady) return;
  swaggerReady = true;
  if (typeof SwaggerUIBundle === 'undefined') {
    document.getElementById('swaggerUI').innerHTML = '<p class="muted" style="padding:20px;">Не удалось загрузить Swagger UI (нет доступа к CDN).</p>';
    return;
  }
  SwaggerUIBundle({
    url: 'admin_tech_openapi.php',
    dom_id: '#swaggerUI',
    deepLinking: true,
    docExpansion: 'list',
    defaultModelsExpandDepth: -1,
    presets: [SwaggerUIBundle.presets.apis],
  });
}

/* ---------- старт ---------- */
showTab(currentTab);
refreshGlobalHealth();
setInterval(refreshGlobalHealth, 15000);
</script>
</body>
</html>
