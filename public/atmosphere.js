/* Ширский уголок — общие скрипты атмосферы */

// --- Атмосфера: свет + декор (выбор посетителя) ---
const Atmosphere = {
  key: 'shire_atmo_v1',
  defaults: { mood: 'night', decor: 'rich' },
  fireflyFactor: { day: 0.4, dusk: 1, night: 1.7 },
  get() {
    try { return { ...this.defaults, ...JSON.parse(localStorage.getItem(this.key) || '{}') }; }
    catch (e) { return { ...this.defaults }; }
  },
  set(patch) {
    const next = { ...this.get(), ...patch };
    localStorage.setItem(this.key, JSON.stringify(next));
    this.apply(next);
    return next;
  },
  apply(state) {
    const s = state || this.get();
    document.documentElement.dataset.mood = s.mood;
    document.documentElement.dataset.decor = s.decor;
    if (document.body) this.respawnFireflies(s.mood);
  },
  respawnFireflies(mood) {
    const factor = this.fireflyFactor[mood] != null ? this.fireflyFactor[mood] : 1;
    document.querySelectorAll('.firefly-stage').forEach(stage => {
      const base = parseInt(stage.dataset.count) || 14;
      stage.innerHTML = '';
      spawnFireflies(stage, Math.max(3, Math.round(base * factor)));
    });
  }
};
Atmosphere.apply();

// --- Переключатель атмосферы ---
function buildAtmoSwitcher() {
  if (document.querySelector('.atmo-fab')) return;
  const state = Atmosphere.get();

  const fab = document.createElement('button');
  fab.className = 'atmo-fab';
  fab.title = 'Атмосфера таверны';
  fab.setAttribute('aria-label', 'Настроить атмосферу');
  fab.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
    <path d="M12 3a6 6 0 0 0 0 12 6 6 0 0 1 0 6 9 9 0 1 1 0-18z"/>
    <circle cx="12" cy="3" r="0.6" fill="currentColor"/></svg>`;

  const moods  = [['day','День'],['dusk','Закат'],['night','Ночь']];
  const decors = [['min','Скромно'],['cozy','Уютно'],['rich','Богато']];
  const seg = (group, opts, cur) => opts.map(([v,l]) =>
    `<button data-group="${group}" data-val="${v}" aria-pressed="${cur===v}">${l}</button>`).join('');

  const pop = document.createElement('div');
  pop.className = 'atmo-pop';
  pop.innerHTML = `
    <h4>Атмосфера таверны</h4>
    <p class="hint">Настрой свет и убранство под себя</p>
    <div class="atmo-grp">
      <div class="lbl">Свет за окном</div>
      <div class="atmo-seg" data-row="mood">${seg('mood', moods, state.mood)}</div>
    </div>
    <div class="atmo-grp">
      <div class="lbl">Убранство</div>
      <div class="atmo-seg" data-row="decor">${seg('decor', decors, state.decor)}</div>
    </div>
    <button class="reset">Сбросить</button>`;

  document.body.appendChild(fab);
  document.body.appendChild(pop);

  fab.addEventListener('click', (e) => { e.stopPropagation(); pop.classList.toggle('open'); });
  document.addEventListener('click', (e) => {
    if (!pop.contains(e.target) && e.target !== fab) pop.classList.remove('open');
  });
  pop.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-group]');
    if (btn) {
      Atmosphere.set({ [btn.dataset.group]: btn.dataset.val });
      pop.querySelectorAll(`[data-row="${btn.dataset.group}"] button`).forEach(b =>
        b.setAttribute('aria-pressed', b === btn));
      return;
    }
    if (e.target.closest('.reset')) {
      const d = Atmosphere.set({ ...Atmosphere.defaults });
      pop.querySelectorAll('[data-row] button').forEach(b => {
        const row = b.closest('[data-row]').dataset.row;
        b.setAttribute('aria-pressed', d[row] === b.dataset.val);
      });
    }
  });
}

// --- Светлячки ---
function spawnFireflies(stage, count = 14) {
  if (!stage) return;
  for (let i = 0; i < count; i++) {
    const fly = document.createElement('div');
    fly.className = 'firefly';
    fly.style.left = (Math.random() * 100) + '%';
    fly.style.top  = (60 + Math.random() * 40) + '%';
    fly.style.animationDelay = (-Math.random() * 12) + 's, ' + (-Math.random() * 3) + 's';
    fly.style.animationDuration = (10 + Math.random() * 8) + 's, ' + (2 + Math.random() * 3) + 's';
    fly.style.transform = 'scale(' + (0.6 + Math.random() * 1.1) + ')';
    stage.appendChild(fly);
  }
}

// --- Появление при скролле ---
function initReveal() {
  const items = document.querySelectorAll('.reveal');
  if (!items.length || !('IntersectionObserver' in window)) {
    items.forEach(el => el.classList.add('in')); return;
  }
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  items.forEach(el => io.observe(el));
}

// --- Параллакс для hero ---
function initParallax() {
  const layers = document.querySelectorAll('[data-parallax]');
  if (!layers.length) return;
  let rafId = null;
  function update() {
    const y = window.scrollY;
    layers.forEach(el => {
      el.style.transform = 'translate3d(0,' + (y * (parseFloat(el.dataset.parallax) || 0.3)) + 'px,0)';
    });
    rafId = null;
  }
  window.addEventListener('scroll', () => { if (!rafId) rafId = requestAnimationFrame(update); }, { passive: true });
  update();
}

// --- Просмотрщик фото (лайтбокс + карусель) ---
function initGalleryLightbox() {
  const items = Array.from(document.querySelectorAll('.mosaic-item')).filter(it => it.querySelector('img'));
  if (!items.length) return;

  const slides = items.map(it => {
    const img = it.querySelector('img');
    const cap = it.querySelector('.caption');
    return {
      src: img.getAttribute('src'),
      alt: img.getAttribute('alt') || '',
      caption: cap ? cap.textContent.trim() : ''
    };
  });

  const lb = document.createElement('div');
  lb.className = 'lightbox';
  lb.setAttribute('role', 'dialog');
  lb.setAttribute('aria-modal', 'true');
  lb.innerHTML =
    '<button class="lb-close" type="button" aria-label="Закрыть">&times;</button>' +
    '<button class="lb-nav lb-prev" type="button" aria-label="Предыдущее фото">&#10094;</button>' +
    '<figure class="lb-stage">' +
      '<img class="lb-img" alt="">' +
      '<figcaption class="lb-caption"></figcaption>' +
    '</figure>' +
    '<button class="lb-nav lb-next" type="button" aria-label="Следующее фото">&#10095;</button>' +
    '<div class="lb-counter"></div>';
  document.body.appendChild(lb);

  const imgEl = lb.querySelector('.lb-img');
  const capEl = lb.querySelector('.lb-caption');
  const counterEl = lb.querySelector('.lb-counter');
  const prevBtn = lb.querySelector('.lb-prev');
  const nextBtn = lb.querySelector('.lb-next');
  let idx = 0;

  if (slides.length < 2) {
    prevBtn.style.display = 'none';
    nextBtn.style.display = 'none';
    counterEl.style.display = 'none';
  }

  function render() {
    const s = slides[idx];
    imgEl.classList.remove('show');
    const pre = new Image();
    pre.onload = () => {
      if (slides[idx] !== s) return; // пользователь успел пролистать дальше
      imgEl.src = s.src;
      imgEl.alt = s.alt;
      requestAnimationFrame(() => imgEl.classList.add('show'));
    };
    pre.src = s.src;
    if (pre.complete) { imgEl.src = s.src; imgEl.alt = s.alt; imgEl.classList.add('show'); }
    capEl.textContent = s.caption;
    counterEl.textContent = (idx + 1) + ' / ' + slides.length;
  }
  function open(i) { idx = i; render(); lb.classList.add('open'); document.body.classList.add('lb-lock'); }
  function close() { lb.classList.remove('open'); document.body.classList.remove('lb-lock'); }
  function next() { idx = (idx + 1) % slides.length; render(); }
  function prev() { idx = (idx - 1 + slides.length) % slides.length; render(); }

  items.forEach((it, i) => it.addEventListener('click', () => open(i)));
  lb.querySelector('.lb-close').addEventListener('click', close);
  nextBtn.addEventListener('click', e => { e.stopPropagation(); next(); });
  prevBtn.addEventListener('click', e => { e.stopPropagation(); prev(); });
  lb.addEventListener('click', e => {
    // клик по фону (а не по фото/кнопкам) закрывает
    if (e.target === lb || e.target.classList.contains('lb-stage')) close();
  });
  document.addEventListener('keydown', e => {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
  });

  // свайп пальцем — листание как в соцсетях
  let sx = 0, sy = 0;
  lb.addEventListener('touchstart', e => { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
  lb.addEventListener('touchend', e => {
    if (slides.length < 2) return;
    const dx = e.changedTouches[0].clientX - sx;
    const dy = e.changedTouches[0].clientY - sy;
    if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) { dx < 0 ? next() : prev(); }
  }, { passive: true });
}

// --- Корзина (sync с сервером, badge из PHP) ---
function updateCartBadge(count) {
  document.querySelectorAll('[data-cart-badge]').forEach(el => {
    el.textContent = count;
    el.style.display = count > 0 ? '' : 'none';
  });
}

// --- Инициализация ---
document.addEventListener('DOMContentLoaded', () => {
  buildAtmoSwitcher();
  const mood   = Atmosphere.get().mood;
  const factor = Atmosphere.fireflyFactor[mood] != null ? Atmosphere.fireflyFactor[mood] : 1;
  document.querySelectorAll('.firefly-stage').forEach(s => {
    const base = parseInt(s.dataset.count) || 14;
    spawnFireflies(s, Math.max(3, Math.round(base * factor)));
  });
  initReveal();
  initParallax();
  initGalleryLightbox();

  // Кнопки "в корзину" — AJAX к add_to_cart.php
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-add-to-cart]');
    if (!btn) return;
    e.preventDefault();
    const label = btn.querySelector('.label');
    const oldText = label ? label.textContent : '';

    fetch('add_to_cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'menu_item_id=' + encodeURIComponent(btn.dataset.id)
    })
    .then(r => r.text())
    .then(() => {
      if (label) {
        label.textContent = 'В корзине ✓';
        btn.classList.add('added');
        setTimeout(() => { label.textContent = oldText; btn.classList.remove('added'); }, 1400);
      }
      // обновляем badge из заголовка
      const badgeEl = document.querySelector('.float-cart .badge, [data-cart-badge]');
      const floatCart = document.getElementById('floatCart') || document.querySelector('.float-cart');
      if (floatCart) floatCart.style.display = '';
      if (badgeEl) { const cur = parseInt(badgeEl.textContent)||0; badgeEl.textContent = cur+1; badgeEl.style.display=''; }
      document.dispatchEvent(new CustomEvent('cartUpdated', {detail:{count: badgeEl ? parseInt(badgeEl.textContent) : 1}}));
      showToast('Добавлено в корзину');
    })
    .catch(() => showToast('Ошибка добавления', 'error'));
  });
});

function showToast(message, type = 'success') {
  const t = document.createElement('div');
  t.className = 'toast-notification ' + type;
  t.textContent = message;
  document.body.appendChild(t);
  setTimeout(() => t.classList.add('show'), 50);
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 2800);
}

