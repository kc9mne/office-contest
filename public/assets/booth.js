// Photobooth kiosk. Flow: attract -> countdown -> preview -> style -> working -> result -> done.
(() => {
  const kiosk = document.getElementById('kiosk');
  const cfg = JSON.parse(kiosk.dataset.config || '{}');
  const $ = id => document.getElementById(id);

  // Full screen works in every state.
  $('fsBtn').addEventListener('click', () => {
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
    else document.documentElement.requestFullscreen?.().catch(() => {});
  });
  if (kiosk.dataset.state !== 'ready') return;

  const video = $('video'), still = $('still'), side = $('side');
  const IDLE_RESET_MS = 90_000;
  const DONE_RESET_S = 45;

  const st = { stage: 'attract', kind: 'single', blob: null, blobUrl: null, photo: null, style: null, name: '', error: '' };
  let stream = null, timer = null, idleTimer = null, busy = false;

  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const store = {
    get: k => { try { return localStorage.getItem(k); } catch (_) { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch (_) { /* private mode */ } },
  };

  // ---------- camera ----------
  async function startCamera() {
    $('camErr').hidden = true;
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
      return camError('This page needs HTTPS', 'Browsers only allow the camera on secure (https://) pages.');
    }
    stopCamera();
    const deviceId = store.get('booth.camera');
    const video_ = { width: { ideal: 1920 }, height: { ideal: 1080 } };
    if (deviceId) video_.deviceId = { exact: deviceId };
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: video_, audio: false });
    } catch (err) {
      if (deviceId && err.name === 'OverconstrainedError') {
        store.set('booth.camera', '');
        return startCamera();
      }
      const msg = err.name === 'NotAllowedError'
        ? 'Allow camera access for this site in the browser (look for the camera icon in the address bar), then tap Try again.'
        : err.name === 'NotFoundError' ? 'No camera was found. Plug in a webcam, then tap Try again.'
        : 'The camera could not start (' + err.name + '). Close other apps using it, then tap Try again.';
      return camError('Camera not available', msg);
    }
    video.srcObject = stream;
    await video.play().catch(() => {});
    fillCameraList();
  }
  function stopCamera() { stream?.getTracks().forEach(t => t.stop()); stream = null; }
  function camError(title, text) {
    $('camErrTitle').textContent = title;
    $('camErrText').textContent = text;
    $('camErr').hidden = false;
  }
  async function fillCameraList() {
    const sel = $('camSelect');
    const devices = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput');
    const current = stream?.getVideoTracks()[0]?.getSettings().deviceId;
    sel.replaceChildren(...devices.map((d, i) => new Option(d.label || `Camera ${i + 1}`, d.deviceId, false, d.deviceId === current)));
  }
  $('camRetry').addEventListener('click', startCamera);
  $('camBtn').addEventListener('click', () => $('camDialog').showModal());
  $('camSelect').addEventListener('change', e => { store.set('booth.camera', e.target.value); startCamera(); });

  const isGroup = () => st.kind === 'group';

  /** Grab the centre of the video in the frame's shape: 2:3 portrait, or 3:2 for groups. Same area the screen shows. */
  function capture() {
    const vw = video.videoWidth, vh = video.videoHeight;
    if (!vw || !vh) return Promise.resolve(null);
    const ratio = isGroup() ? 3 / 2 : 2 / 3;
    let cw = vw, ch = vh;
    if (vw / vh > ratio) cw = Math.round(vh * ratio); else ch = Math.round(vw / ratio);
    const scale = Math.min(1, 1536 / Math.max(cw, ch));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(cw * scale);
    canvas.height = Math.round(ch * scale);
    canvas.getContext('2d').drawImage(video, (vw - cw) / 2, (vh - ch) / 2, cw, ch, 0, 0, canvas.width, canvas.height);
    return new Promise(res => canvas.toBlob(res, 'image/jpeg', 0.92));
  }

  // ---------- server ----------
  async function freshToken() {
    const r = await fetch(cfg.base + '/session', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    cfg.csrf = (await r.json()).csrf;
  }
  async function post(path, data, { timeoutMs = 30_000, retry = true } = {}) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    fd.append('_csrf', cfg.csrf);
    const ctl = new AbortController();
    const t = setTimeout(() => ctl.abort(), timeoutMs);
    try {
      const r = await fetch(cfg.base + path, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: ctl.signal });
      const json = await r.json().catch(() => ({ error: 'The server sent an unexpected reply.' }));
      if (json.csrfExpired && retry) { await freshToken(); return post(path, data, { timeoutMs, retry: false }); }
      return { ok: r.ok, status: r.status, json };
    } finally { clearTimeout(t); }
  }
  async function getPhoto(id) {
    const r = await fetch(`${cfg.base}/photos/${id}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    return r.json();
  }

  // ---------- flow ----------
  function go(stage, extra = {}) {
    Object.assign(st, extra, { stage });
    touchIdle();
    render();
  }

  /** Restart the "nobody's here, start over" timer. */
  function touchIdle() {
    clearTimeout(idleTimer);
    if (!['attract', 'countdown', 'working'].includes(st.stage)) idleTimer = setTimeout(reset, IDLE_RESET_MS);
  }

  function reset() {
    clearInterval(timer);
    if (st.blobUrl) URL.revokeObjectURL(st.blobUrl);
    Object.assign(st, { kind: 'single', blob: null, blobUrl: null, photo: null, style: null, name: '', error: '' });
    go('attract');
  }

  function countdown() {
    let n = isGroup() ? 5 : 3;
    go('countdown');
    const count = $('count');
    const show = () => { count.textContent = n; count.classList.remove('tick'); void count.offsetWidth; count.classList.add('tick'); };
    count.hidden = false;
    show();
    timer = setInterval(async () => {
      n--;
      if (n > 0) return show();
      clearInterval(timer);
      count.hidden = true;
      const blob = await capture();
      const flash = $('flash');
      flash.hidden = false;
      flash.style.animation = 'none'; void flash.offsetWidth; flash.style.animation = '';
      setTimeout(() => { flash.hidden = true; }, 700);
      if (!blob) return go('attract', { error: 'The camera did not give a picture. Try again.' });
      if (st.blobUrl) URL.revokeObjectURL(st.blobUrl);
      go('preview', { blob, blobUrl: URL.createObjectURL(blob), photo: null, error: '' });
    }, 1000);
  }

  async function makeIt() {
    if (busy || st.style == null) return;
    busy = true;
    const style = cfg.styles[st.style].name;
    go('working', { error: '' });
    try {
      await freshToken();
      if (!st.photo) {
        const up = await post('/photos', { photo: new File([st.blob], 'photo.jpg', { type: 'image/jpeg' }), name: st.name, kind: st.kind });
        if (!up.ok) throw new Error(up.json.error || 'The photo could not be uploaded.');
        st.photo = up.json;
      }
      let res;
      try {
        res = await post(`/photos/${st.photo.id}/process`, { style }, { timeoutMs: 240_000 });
      } catch (_) {
        res = null; // connection dropped or timed out: the server keeps working, so check in
      }
      let photo = res?.json;
      if (!res || res.status === 202) photo = await waitForPhoto(st.photo.id);
      if (!photo || photo.status !== 'done') throw new Error(photo?.error || 'The AI could not finish this photo.');
      st.photo = photo;
      go('result');
    } catch (err) {
      go('failed', { error: err.message });
    } finally {
      busy = false;
    }
  }

  async function waitForPhoto(id) {
    for (let i = 0; i < 80; i++) {
      await new Promise(r => setTimeout(r, 3000));
      const p = await getPhoto(id).catch(() => null);
      if (p && p.status !== 'processing') return p;
    }
    return null;
  }

  async function addToGallery() {
    if (busy) return;
    busy = true;
    try {
      await freshToken();
      const res = await post(`/photos/${st.photo.id}/gallery`, { name: st.name });
      if (!res.ok) throw new Error(res.json.error || 'Could not add the photo to the gallery.');
      st.photo = res.json;
      go('done');
      let left = DONE_RESET_S;
      clearInterval(timer);
      timer = setInterval(() => {
        left--;
        const el = $('resetIn');
        if (el) el.textContent = left;
        if (left <= 0) reset();
      }, 1000);
    } catch (err) {
      go('failed', { error: err.message });
    } finally {
      busy = false;
    }
  }

  // ---------- rendering ----------
  function render() {
    const s = st.stage;
    const showSplit = s === 'result' || s === 'done';
    $('cam').hidden = showSplit;
    $('split').hidden = !showSplit;
    $('cam').classList.toggle('group', isGroup());
    $('split').classList.toggle('group', isGroup());
    still.hidden = !['preview', 'style', 'working', 'failed'].includes(s);
    if (!still.hidden) still.src = st.blobUrl;
    $('camChip').textContent = still.hidden ? 'Live camera' : 'Your photo';
    $('camChip').classList.toggle('rec', still.hidden);
    $('shimmer').hidden = s !== 'working';
    if (showSplit) {
      $('beforeImg').src = st.blobUrl;
      $('afterImg').src = st.photo.result;
      $('afterCap').textContent = 'After · ' + st.photo.style;
    }
    side.innerHTML = views[s]();
    side.querySelector('[data-autofocus]')?.focus();
  }

  const fine = cfg.demo
    ? '<p class="kfine">Demo mode: photos get a color filter instead of AI until an API key is added.</p>'
    : '<p class="kfine">Your photo is restyled by an AI service. You choose whether it goes in the contest gallery.</p>';

  const views = {
    attract: () => `
      <h2>Strike a pose!</h2>
      <p>Stand on the mark and face the camera. Tap when you're ready for the countdown.</p>
      ${st.error ? `<div class="kerr">${esc(st.error)}</div>` : ''}
      <div class="kacts">
        <button type="button" class="kbtn primary huge" data-act="single" data-autofocus>Just me</button>
        <button type="button" class="kbtn primary huge" data-act="group">Group photo</button>
      </div>
      ${fine}`,
    countdown: () => isGroup() ? `<h2>Squeeze in!</h2><p>Make sure everyone's face is in the frame.</p>` : `<h2>Get ready…</h2><p>Look at the camera and hold still.</p>`,
    preview: () => `
      <h2>How's that?</h2><p>Keep it or take another.</p>
      <div class="kacts"><button type="button" class="kbtn ghost" data-act="retake">Retake</button><button type="button" class="kbtn primary" data-act="keep" data-autofocus>Looks good</button></div>
      <button type="button" class="kbtn link" data-act="reset">← Go back to switch between Just me and Group photo</button>`,
    style: () => `
      <h2>Pick your look</h2>
      <div class="kstyles">${cfg.styles.map((x, i) => `<button type="button" class="kstyle" data-style="${i}" aria-pressed="${st.style === i}"><span class="sw" style="background:linear-gradient(160deg,${esc(x.from)},${esc(x.to)})"></span>${esc(x.name)}</button>`).join('')}</div>
      <div class="field"><label for="kName">${isGroup() ? 'Names' : 'Your name'} <span class="hint">(optional, shown in the gallery)</span></label><input id="kName" maxlength="80" value="${esc(st.name)}" autocomplete="off"></div>
      <button type="button" class="kbtn primary" data-act="make" ${st.style == null ? 'disabled' : ''}>${esc(cfg.verb)}</button>
      <button type="button" class="kbtn link" data-act="retake">Retake photo</button>`,
    working: () => `
      <h2>Working on it…</h2>
      <p>Turning ${isGroup() ? 'your group' : 'you'} into: <b>${esc(cfg.styles[st.style]?.name)}</b>. This usually takes 15–60 seconds.</p>
      <div class="spin" role="status" aria-label="Working"></div>`,
    failed: () => `
      <h2>That didn't work</h2>
      <div class="kerr">${esc(st.error)}</div>
      <div class="kacts col">
        ${st.blob ? '<button type="button" class="kbtn primary" data-act="restyle">Try again</button>' : ''}
        <button type="button" class="kbtn ghost" data-act="reset">Start over</button>
      </div>`,
    result: () => `
      <h2>Ta-da!</h2><p>Here's ${isGroup() ? 'your group' : 'you'} as <b>${esc(st.photo.style)}</b>.</p>
      <div class="kacts col">
        <button type="button" class="kbtn primary" data-act="gallery" data-autofocus>Add to gallery</button>
        <button type="button" class="kbtn ghost" data-act="restyle">Try another look</button>
        <button type="button" class="kbtn link" data-act="reset">Start over</button>
      </div>`,
    done: () => `
      <h2>You're in the gallery!</h2>
      <div class="kqr"><img src="${esc(cfg.qr)}?u=${encodeURIComponent(st.photo.shareUrl)}" alt="QR code linking to your photo">
        <span>Scan to save your photo to your phone.<code>${esc(st.photo.shareUrl)}</code></span></div>
      <button type="button" class="kbtn primary" data-act="reset" data-autofocus>Next person</button>
      <p class="kfine">Resetting in <span id="resetIn">${DONE_RESET_S}</span> seconds.</p>`,
  };

  side.addEventListener('click', e => {
    const styleBtn = e.target.closest('[data-style]');
    if (styleBtn) {
      st.style = +styleBtn.dataset.style;
      side.querySelectorAll('[data-style]').forEach(b => b.setAttribute('aria-pressed', b === styleBtn));
      side.querySelector('[data-act="make"]').disabled = false;
      return;
    }
    const act = e.target.closest('[data-act]')?.dataset.act;
    if (!act) return;
    if (act === 'single' || act === 'group') { st.kind = act; render(); countdown(); }
    else if (act === 'retake') countdown();
    else if (act === 'keep' || act === 'restyle') go('style');
    else if (act === 'make') makeIt();
    else if (act === 'gallery') addToGallery();
    else if (act === 'reset') reset();
  });
  side.addEventListener('input', e => { if (e.target.id === 'kName') st.name = e.target.value; });

  // Keyboard or presenter clicker.
  document.addEventListener('keydown', e => {
    // Space / Enter = just me, G = group photo.
    if (st.stage !== 'attract' || $('camDialog').open) return;
    if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); st.kind = 'single'; render(); countdown(); }
    else if (e.key === 'g' || e.key === 'G') { e.preventDefault(); st.kind = 'group'; render(); countdown(); }
  });
  // Any touch keeps the session from resetting mid-use.
  document.addEventListener('pointerdown', touchIdle, true);
  document.addEventListener('keydown', touchIdle, true);

  render();
  startCamera();
})();
