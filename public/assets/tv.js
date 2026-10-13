// TV display: renders from JSON, refreshes every 30 s, rotates the slideshow, keeps the screen awake.
(() => {
  const tv = document.getElementById('tv');
  const SLIDE_MS = 8000, REFRESH_MS = 30000;
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const $ = sel => tv.querySelector(sel);
  let data = JSON.parse(tv.dataset.initial || '{}');

  // ---------- header: title and countdown ----------
  function renderHeader() {
    const c = data.contest;
    $('[data-field="title"]').textContent = c ? c.title : 'No contest running';
    $('[data-field="subtitle"]').textContent = c?.subtitle || '';
    $('[data-field="event"]').textContent = c?.event || '';
    const clock = $('[data-clock]');
    clock.hidden = !c || data.phase === 'closed';
    $('[data-field="clockLabel"]').textContent = data.phase === 'before' ? 'Voting opens in' : 'Voting closes in';
  }
  function tick() {
    if (!data.contest || data.phase === 'closed') return;
    const left = Math.max(0, Math.floor((data.target - Date.now()) / 1000));
    const set = (u, v) => { const el = $(`[data-unit="${u}"]`); if (el) el.textContent = v; };
    const pad = n => String(n).padStart(2, '0');
    set('d', Math.floor(left / 86400)); set('h', pad(Math.floor(left / 3600) % 24)); set('m', pad(Math.floor(left / 60) % 60)); set('s', pad(left % 60));
    if (left === 0) refresh();
  }

  // ---------- leaderboard ----------
  function renderBoard() {
    const b = $('[data-board]');
    const c = data.contest;
    if (!c) { b.innerHTML = '<div class="tv-note"><h2>No contest running right now</h2><p>Check back soon.</p></div>'; return; }
    if (data.phase === 'before') {
      b.innerHTML = `<div class="tv-note"><h2>Voting opens ${esc(c.starts)}</h2>
        <div class="tv-big">${data.entries}</div><p>${esc(c.noun)}${data.entries === 1 ? '' : 's'} entered so far. Scan the code to enter yours!</p></div>`;
      return;
    }
    if (!data.showResults) {
      b.innerHTML = `<div class="tv-note"><h2>The leaderboard is a secret</h2>
        <div class="tv-big">${data.voters}</div><p>${data.voters === 1 ? 'person has' : 'people have'} voted. Results are revealed when voting closes at ${esc(c.ends)}.</p></div>`;
      return;
    }
    if (!data.top.length) {
      b.innerHTML = `<div class="tv-note"><h2>${data.phase === 'closed' ? 'Voting has closed' : 'No votes yet'}</h2><p>${data.phase === 'closed' ? 'Nobody voted in this contest.' : 'Scan the code and be the first to vote!'}</p></div>`;
      return;
    }
    const slots = [[data.top[1], 'second'], [data.top[0], 'first'], [data.top[2], 'third']];
    const podium = slots.map(([r, cls]) => r
      ? `<div class="tv-pod ${cls}"><img src="${esc(r.photo)}" alt=""><div class="m">${esc(r.place)}</div><div class="n">${esc(r.name)}</div><div class="v">${r.votes} vote${r.votes === 1 ? '' : 's'}</div></div>`
      : '<div></div>').join('');
    const leaders = data.leaders.map(l => `<div class="tv-leader">
        ${l.photo ? `<img src="${esc(l.photo)}" alt="">` : '<span class="ph"></span>'}
        <div><div class="c">${esc(l.category)}${l.tie ? ' · Tie' : ''}</div><b>${esc(l.names || 'No votes yet')}</b></div>
        <span class="v">${l.votes || ''}</span></div>`).join('');
    b.innerHTML = `<h2 class="tv-h">${data.phase === 'closed' ? 'Winners!' : 'Overall top 3'}</h2>
      <div class="tv-podium">${podium}</div>
      <div class="tv-leaders">${leaders}</div>`;
  }

  // ---------- slideshow ----------
  const figs = [...tv.querySelectorAll('.tv-slide')];
  let slideIndex = -1, front = 0, slideTimer = null;
  function nextSlide() {
    const slides = data.slides || [];
    const empty = $('[data-show-empty]');
    if (!slides.length) {
      figs.forEach(f => f.classList.remove('on'));
      empty.hidden = false;
      empty.innerHTML = `<b>Photos show up here</b><span>Enter the contest${data.contest ? ' or visit the photobooth' : ''} and your picture joins the slideshow.</span>`;
      return;
    }
    empty.hidden = true;
    slideIndex = (slideIndex + 1) % slides.length;
    const s = slides[slideIndex];
    const back = figs[1 - front];
    const img = back.querySelector('img');
    const vid = back.querySelector('video');
    const swap = () => {
      back.querySelector('figcaption').innerHTML = `${esc(s.name)}${s.caption ? `<small>${esc(s.caption)}</small>` : ''}`;
      // Boomerangs: show the poster at once, then play the loop over it.
      if (s.video) { vid.src = s.video; vid.hidden = false; vid.play().catch(() => {}); }
      else { vid.pause(); vid.removeAttribute('src'); vid.hidden = true; }
      back.classList.add('on');
      const old = figs[front];
      old.classList.remove('on');
      setTimeout(() => { if (!old.classList.contains('on')) old.querySelector('video').pause(); }, 1300);
      front = 1 - front;
    };
    img.onload = swap;
    img.onerror = () => { clearTimeout(slideTimer); slideTimer = setTimeout(nextSlide, 500); };
    img.src = s.img;
    if (img.complete && img.naturalWidth) swap();
  }
  function startSlides() { clearInterval(slideTimer); nextSlide(); slideTimer = setInterval(nextSlide, SLIDE_MS); }

  // ---------- refresh ----------
  async function refresh() {
    try {
      const r = await fetch(tv.dataset.src, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!r.ok) return;
      const fresh = await r.json();
      const hadSlides = (data.slides || []).length;
      // New photos go to the front of the queue so they're seen soon.
      const known = new Set((data.slides || []).map(s => s.img));
      const added = (fresh.slides || []).filter(s => !known.has(s.img));
      data = fresh;
      if (added.length && hadSlides) slideIndex = -1;
      renderHeader(); renderBoard(); tick();
      if (!hadSlides && data.slides?.length) startSlides();
    } catch (_) { /* keep showing what we have; try again next time */ }
  }

  // ---------- full screen, hidden cursor, screen awake ----------
  const fs = tv.querySelector('[data-fullscreen]');
  fs.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>';
  fs.addEventListener('click', () => {
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
    else document.documentElement.requestFullscreen?.().catch(() => {});
  });
  let idleTimer;
  const wake = () => { document.body.classList.remove('idle'); clearTimeout(idleTimer); idleTimer = setTimeout(() => document.body.classList.add('idle'), 4000); };
  document.addEventListener('mousemove', wake); wake();
  let lock = null;
  const keepAwake = async () => { try { if (!lock && document.visibilityState === 'visible') { lock = await navigator.wakeLock?.request('screen'); lock?.addEventListener('release', () => { lock = null; }); } } catch (_) { /* not allowed here; fine */ } };
  document.addEventListener('visibilitychange', keepAwake);
  document.addEventListener('click', keepAwake);
  keepAwake();

  renderHeader(); renderBoard(); tick(); startSlides();
  setInterval(tick, 1000);
  setInterval(refresh, REFRESH_MS);
})();
