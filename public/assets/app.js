// Small progressive enhancements. Every page works without this file.
(() => {
  // Setup page: preselect the browser's time zone.
  const tz = document.querySelector('select[data-guess-tz="1"]');
  if (tz) {
    try {
      const guess = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (guess && [...tz.options].some(o => o.value === guess)) tz.value = guess;
    } catch (_) { /* keep server default */ }
  }

  // Copy buttons: data-copy="<id of element holding the text>"
  document.addEventListener('click', async e => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const el = document.getElementById(btn.dataset.copy);
    if (!el) return;
    try {
      await navigator.clipboard.writeText(el.textContent.trim());
      const old = btn.textContent;
      btn.textContent = 'Copied';
      setTimeout(() => { btn.textContent = old; }, 1600);
    } catch (_) {
      const range = document.createRange();
      range.selectNodeContents(el);
      const sel = getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
    }
  });

  // Forms that need a "are you sure?" first: <form data-confirm="Question">
  document.addEventListener('submit', e => {
    const msg = e.target.dataset?.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
    // Forms with several buttons where only Delete needs confirming.
    const del = e.target.dataset?.confirmDelete;
    if (del && e.submitter?.value === 'delete' && !window.confirm(del)) e.preventDefault();
  });

  // Logo preview before saving.
  document.addEventListener('change', e => {
    const input = e.target.closest('input[type="file"][data-preview]');
    if (!input || !input.files[0]) return;
    const box = document.getElementById(input.dataset.preview);
    const img = document.createElement('img');
    img.alt = 'New logo preview';
    img.src = URL.createObjectURL(input.files[0]);
    box.replaceChildren(img);
  });

  // ---------- public pages ----------

  // Countdown: <section data-countdown="epoch ms"> with <b data-unit="d|h|m|s">. Reloads when it hits zero.
  const clock = document.querySelector('[data-countdown]');
  if (clock) {
    const target = +clock.dataset.countdown;
    const units = Object.fromEntries([...clock.querySelectorAll('[data-unit]')].map(el => [el.dataset.unit, el]));
    const pad = n => String(n).padStart(2, '0');
    const tick = () => {
      const left = Math.max(0, Math.floor((target - Date.now()) / 1000));
      units.d && (units.d.textContent = Math.floor(left / 86400));
      units.h && (units.h.textContent = pad(Math.floor(left / 3600) % 24));
      units.m && (units.m.textContent = pad(Math.floor(left / 60) % 60));
      units.s && (units.s.textContent = pad(left % 60));
      if (left === 0) { clearInterval(timer); setTimeout(() => location.reload(), 1500); }
    };
    const timer = setInterval(tick, 1000);
    tick();
    // Keep the leaderboard fresh on screens left open (skipped while someone is typing).
    setInterval(() => { if (!document.hidden && !document.activeElement?.matches('input, textarea, select')) location.reload(); }, 60_000);
  }

  // Join: show the chosen photo, and stop double submits while it uploads.
  const photoInput = document.querySelector('[data-join-form] #photo');
  if (photoInput) {
    photoInput.addEventListener('change', () => {
      const file = photoInput.files[0];
      const img = document.getElementById('shotPreview');
      if (!file) return;
      img.src = URL.createObjectURL(file);
      img.hidden = false;
      document.getElementById('shot').classList.add('has');
    });
    document.querySelector('[data-join-form]').addEventListener('submit', e => {
      const btn = e.target.querySelector('[data-busy-text]');
      if (!photoInput.files[0]) {
        e.preventDefault();
        photoInput.closest('.field').classList.add('has-error');
        if (!photoInput.closest('.field').querySelector('.error')) {
          const msg = document.createElement('span');
          msg.className = 'error'; msg.setAttribute('role', 'alert'); msg.textContent = 'Add a photo.';
          photoInput.closest('.field').append(msg);
        }
        return;
      }
      btn.disabled = true;
      btn.textContent = btn.dataset.busyText;
    });
  }

  // Vote page: department filter chips.
  const deptFilter = document.querySelector('[data-dept-filter]');
  if (deptFilter) {
    deptFilter.addEventListener('click', e => {
      const btn = e.target.closest('[data-dept]');
      if (!btn) return;
      deptFilter.querySelectorAll('[data-dept]').forEach(b => b.setAttribute('aria-pressed', b === btn));
      document.querySelectorAll('[data-entry]').forEach(card => {
        card.hidden = btn.dataset.dept !== '' && card.dataset.dept !== btn.dataset.dept;
      });
    });
  }

  // Vote page: vote without reloading; falls back to a normal form post without JS.
  const grid = document.querySelector('[data-vote-grid]');
  const toastEl = document.getElementById('toast');
  let toastTimer;
  const toast = msg => {
    if (!toastEl) return;
    toastEl.textContent = msg; toastEl.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(() => { toastEl.hidden = true; }, 2800);
  };
  if (grid) {
    const catId = grid.dataset.category;
    const catName = (grid.dataset.categoryName || '').toLowerCase();
    const send = async (form, retry = true) => {
      const r = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const json = await r.json().catch(() => ({ error: 'Something went wrong. Reload the page and try again.' }));
      if (json.csrfExpired && retry) {
        const s = await fetch(document.baseURI.replace(/\/vote.*$/, '') + '/session', { headers: { Accept: 'application/json' } }).then(x => x.json());
        document.querySelectorAll('input[name="_csrf"]').forEach(i => { i.value = s.csrf; });
        return send(form, false);
      }
      return { ok: r.ok, json };
    };
    grid.addEventListener('submit', async e => {
      const form = e.target.closest('[data-vote-form]');
      if (!form) return;
      e.preventDefault();
      const btn = form.querySelector('button');
      if (btn.disabled) return;
      btn.disabled = true;
      try {
        const { ok, json } = await send(form);
        if (!ok) { toast(json.error || 'That vote could not be saved.'); return; }
        const picked = String(json.picks[catId]);
        grid.querySelectorAll('[data-entry]').forEach(card => {
          const mine = card.dataset.entry === picked;
          card.classList.toggle('mine', mine);
          card.querySelector('.vote-btn').textContent = mine ? `✓ Your ${catName} pick` : `Vote ${catName}`;
        });
        document.querySelector(`[data-cat-tab="${catId}"] .done`)?.removeAttribute('hidden');
        const count = document.getElementById('pickCount');
        if (count) count.textContent = `${json.done} of ${json.total}`;
        toast(json.done < json.total ? `${json.message} Pick the next category above.` : `${json.message} You've voted in every category!`);
      } catch (_) {
        toast('No connection. Check your signal and try again.');
      } finally {
        btn.disabled = false;
      }
    });
  }

  // Brand color: show the hex value next to the picker.
  const color = document.getElementById('brand_color');
  const hex = document.getElementById('brandHex');
  if (color && hex) color.addEventListener('input', () => { hex.textContent = color.value.toUpperCase(); });

  // Contest form: on a new contest, switching type fills in that type's defaults
  // unless the admin already changed those fields.
  const form = document.querySelector('form[data-contest-form]');
  if (form && form.dataset.new === '1') {
    const defaults = JSON.parse(form.dataset.modeDefaults || '{}');
    const title = form.querySelector('#title');
    const cats = form.querySelector('#categories');
    const eventName = form.querySelector('#event_name');
    const booth = form.querySelector('input[name="booth_enabled"]');
    let current = form.querySelector('input[name="mode"]:checked')?.value;
    const year = (form.querySelector('#starts_at').value || '').slice(0, 4);
    form.addEventListener('change', e => {
      if (e.target.name !== 'mode') return;
      const from = defaults[current] || {};
      const to = defaults[e.target.value] || {};
      if (title.value.trim() === `${from.title} ${year}`.trim() || title.value.trim() === '') title.value = `${to.title} ${year}`.trim();
      if (cats.value.trim() === (from.categories || '').trim() || cats.value.trim() === '') cats.value = to.categories || '';
      if (eventName.value.trim() === (from.event_name || '') || eventName.value.trim() === '') eventName.value = to.event_name || '';
      booth.checked = !!to.booth;
      // Swap photobooth looks if they still match the previous type's defaults.
      const fromStyles = from.styles || [];
      const toStyles = to.styles || [];
      form.querySelectorAll('[data-style-name]').forEach(nameEl => {
        const i = +nameEl.dataset.styleName;
        const promptEl = form.querySelector(`[data-style-prompt="${i}"]`);
        const was = fromStyles[i] || { name: '', prompt: '' };
        if (nameEl.value.trim() === was.name && promptEl.value.trim() === was.prompt) {
          nameEl.value = toStyles[i]?.name || '';
          promptEl.value = toStyles[i]?.prompt || '';
        }
      });
      document.documentElement.dataset.mode = e.target.value;
      current = e.target.value;
    });
  }
})();
