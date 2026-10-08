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

  // Print buttons (QR poster).
  document.addEventListener('click', e => { if (e.target.closest('[data-print]')) window.print(); });

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

  // Join: take a photo with the camera right on the page, or choose a file.
  const joinForm = document.querySelector('[data-join-form]');
  if (joinForm) {
    const $id = id => document.getElementById(id);
    const fileInput = $id('photo'), dataInput = $id('photoData');
    const video = $id('camVideo'), preview = $id('shotPreview'), shot = $id('shot');
    const openBtn = joinForm.querySelector('[data-camera-open]'), flipBtn = joinForm.querySelector('[data-camera-flip]');
    const camError = $id('camError');
    let stream = null, facing = 'user';
    const canUseCamera = window.isSecureContext && !!navigator.mediaDevices?.getUserMedia;
    if (canUseCamera) openBtn.hidden = false;
    else $id('chooseLabel').textContent = 'Take or choose a photo';

    const show = state => { // 'empty' | 'camera' | 'photo'
      $id('shotEmpty').hidden = state !== 'empty';
      video.hidden = state !== 'camera';
      $id('camBar').hidden = state !== 'camera';
      preview.hidden = state !== 'photo';
      $id('doneBar').hidden = state !== 'photo';
      shot.classList.toggle('has', state === 'photo');
      shot.classList.toggle('live', state === 'camera');
    };
    const stop = () => { stream?.getTracks().forEach(t => t.stop()); stream = null; };
    const fieldError = msg => {
      const field = joinForm.querySelector('[data-photo-field]');
      field.classList.toggle('has-error', !!msg);
      camError.textContent = msg || '';
      camError.hidden = !msg;
    };

    async function startCamera() {
      fieldError('');
      stop();
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1920 }, height: { ideal: 1920 } }, audio: false });
      } catch (err) {
        show('empty');
        fieldError(err.name === 'NotAllowedError'
          ? 'The camera is blocked. Allow it for this site (camera icon in the address bar), or tap Choose a photo.'
          : 'The camera could not start. Tap Choose a photo instead.');
        return;
      }
      video.srcObject = stream;
      video.classList.toggle('mirror', facing === 'user');
      show('camera');
      await video.play().catch(() => {});
      const cams = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput');
      flipBtn.hidden = cams.length < 2;
    }

    function snap() {
      const vw = video.videoWidth, vh = video.videoHeight;
      if (!vw || !vh) return;
      // Same 4:5 crop the page shows, at most 1600px tall.
      let cw = vw, ch = vh;
      if (vw / vh > 4 / 5) cw = Math.round(vh * 4 / 5); else ch = Math.round(vw * 5 / 4);
      const scale = Math.min(1, 1600 / ch);
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(cw * scale); canvas.height = Math.round(ch * scale);
      const ctx = canvas.getContext('2d');
      if (facing === 'user') { ctx.translate(canvas.width, 0); ctx.scale(-1, 1); } // save the selfie the way it looked
      ctx.drawImage(video, (vw - cw) / 2, (vh - ch) / 2, cw, ch, 0, 0, canvas.width, canvas.height);
      dataInput.value = canvas.toDataURL('image/jpeg', 0.88);
      fileInput.value = '';
      preview.src = dataInput.value;
      stop();
      show('photo');
      fieldError('');
    }

    openBtn.addEventListener('click', startCamera);
    joinForm.querySelector('[data-camera-snap]').addEventListener('click', snap);
    joinForm.querySelector('[data-camera-cancel]').addEventListener('click', () => { stop(); show('empty'); });
    flipBtn.addEventListener('click', () => { facing = facing === 'user' ? 'environment' : 'user'; startCamera(); });
    joinForm.querySelector('[data-photo-clear]').addEventListener('click', () => {
      fileInput.value = ''; dataInput.value = ''; preview.removeAttribute('src'); show('empty');
    });
    fileInput.addEventListener('change', () => {
      const file = fileInput.files[0];
      if (!file) return;
      dataInput.value = '';
      preview.src = URL.createObjectURL(file);
      stop();
      show('photo');
      fieldError('');
    });

    joinForm.addEventListener('submit', e => {
      if (!fileInput.files[0] && !dataInput.value) {
        e.preventDefault();
        fieldError('Add a photo: take one or choose one.');
        shot.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      stop();
      const btn = joinForm.querySelector('[data-busy-text]');
      btn.disabled = true;
      btn.textContent = btn.dataset.busyText;
    });
    window.addEventListener('pagehide', stop);
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

  // ---------- videos ----------

  // YouTube: load the player only when someone taps play (no YouTube tracking until then).
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-youtube]');
    if (!btn) return;
    const frame = document.createElement('iframe');
    frame.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(btn.dataset.youtube)}?autoplay=1&rel=0&playsinline=1`;
    frame.title = btn.getAttribute('aria-label') || 'YouTube video';
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    frame.allowFullscreen = true;
    btn.replaceWith(frame);
  });

  // Pages waiting on a video conversion refresh themselves.
  const refresh = document.querySelector('[data-refresh-in]');
  if (refresh) setTimeout(() => { if (!document.activeElement?.matches('input, textarea')) location.reload(); }, +refresh.dataset.refreshIn * 1000);

  // Add a video: switch between upload and YouTube, and upload with a progress bar.
  const vform = document.querySelector('[data-video-form]');
  if (vform) {
    const kind = vform.querySelector('input[name="kind"]');
    document.querySelector('[data-video-tabs]')?.addEventListener('click', e => {
      const tab = e.target.closest('[data-kind]');
      if (!tab) return;
      kind.value = tab.dataset.kind;
      document.querySelectorAll('[data-video-tabs] [data-kind]').forEach(t => t.setAttribute('aria-selected', t === tab));
      vform.querySelectorAll('[data-kind-panel]').forEach(p => { p.hidden = p.dataset.kindPanel !== tab.dataset.kind; });
    });
    vform.addEventListener('submit', e => {
      if (kind.value !== 'upload') return;
      const file = vform.querySelector('#video').files[0];
      const field = vform.querySelector('[data-kind-panel="upload"]');
      const showError = msg => {
        field.classList.add('has-error');
        let el = field.querySelector('.error');
        if (!el) { el = document.createElement('span'); el.className = 'error'; el.setAttribute('role', 'alert'); field.append(el); }
        el.textContent = msg;
      };
      e.preventDefault();
      if (!file) return showError('Choose a video first.');
      if (file.size > 300 * 1024 * 1024) return showError(`That video is ${Math.round(file.size / 1048576)} MB. The limit is 300 MB. Trim it, or post it to YouTube and paste the link instead.`);
      const btn = vform.querySelector('button[type="submit"]');
      const box = vform.querySelector('[data-progress]');
      const bar = box.querySelector('.bar span');
      const text = box.querySelector('[data-progress-text]');
      btn.disabled = true; box.hidden = false;
      const xhr = new XMLHttpRequest();
      xhr.open('POST', vform.action);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.onprogress = ev => {
        if (!ev.lengthComputable) return;
        const pct = Math.round(ev.loaded / ev.total * 100);
        bar.style.width = pct + '%';
        text.textContent = pct < 100 ? `Uploading… ${pct}% (${Math.round(ev.loaded / 1048576)} of ${Math.round(ev.total / 1048576)} MB). Keep this page open.` : 'Upload done. Saving…';
      };
      xhr.onload = () => {
        let json = {};
        try { json = JSON.parse(xhr.responseText); } catch (_) { /* not JSON */ }
        if (xhr.status >= 200 && xhr.status < 300 && json.ok) { location.href = json.redirect || location.href; return; }
        btn.disabled = false; box.hidden = true;
        showError(json.csrfExpired ? 'This page was open too long. Reload it and try again.' : (json.error || 'The upload failed. Try again.'));
      };
      xhr.onerror = () => { btn.disabled = false; box.hidden = true; showError('The upload stopped. Check your connection and try again.'); };
      xhr.send(new FormData(vform));
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
