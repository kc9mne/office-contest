// Photobooth kiosk. Flow: attract -> countdown -> preview -> style -> working -> result -> done.
// Modes: single / group photo (AI look), photo strip (4 real shots), boomerang (short clip). Strips and boomerangs don't use the AI.
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

  const st = { stage: 'attract', kind: 'single', flow: 'booth', frames: [], shot: 0, clip: null, clipUrl: null, entry: { name: '', department: '', title: '', consent: true }, blob: null, blobUrl: null, photo: null, style: null, name: '', error: '' };
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
  const isWide = () => st.kind === 'group' || st.kind === 'strip';
  const canBoomerang = cfg.boomerang && typeof window.MediaRecorder === 'function';
  const LOOK_NONE = -1; // real photo, no AI (photo strips always; single/group via "No changes")
  const asIs = () => st.style === LOOK_NONE && st.kind !== 'strip';
  /** Looks for this kind of photo: everyone's, plus just-me-only or group-only ones. Keeps each look's index. */
  const looksForKind = () => cfg.styles.map((x, i) => ({ ...x, i })).filter(x => !x.for || x.for === 'all' || x.for === (isGroup() ? 'group' : 'single'));

  /** Grab the centre of the video in the frame's shape: 2:3 portrait, or 3:2 for groups and strips. Same area the screen shows. */
  function capture() {
    const vw = video.videoWidth, vh = video.videoHeight;
    if (!vw || !vh) return Promise.resolve(null);
    const ratio = isWide() ? 3 / 2 : 2 / 3;
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
  const OFFLINE = 'No connection to the server. Check the Wi-Fi and try again.';
  async function freshToken() {
    try {
      const r = await fetch(cfg.base + '/session', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      cfg.csrf = (await r.json()).csrf;
      if (!cfg.csrf) throw new Error();
    } catch (_) { throw new Error(OFFLINE); }
  }
  async function post(path, data, { timeoutMs = 30_000, retry = true } = {}) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => Array.isArray(v) ? v.forEach(x => fd.append(k + '[]', x)) : fd.append(k, v));
    fd.append('_csrf', cfg.csrf);
    const ctl = new AbortController();
    const t = setTimeout(() => ctl.abort(), timeoutMs);
    try {
      let r;
      try { r = await fetch(cfg.base + path, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: ctl.signal }); }
      catch (err) { if (err.name === 'AbortError') throw err; throw new Error(OFFLINE); }
      const json = await r.json().catch(() => ({ error: r.ok ? 'The server sent an unexpected reply.' : OFFLINE }));
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
    if (st.clipUrl) URL.revokeObjectURL(st.clipUrl);
    Object.assign(st, { kind: 'single', flow: 'booth', frames: [], shot: 0, clip: null, clipUrl: null, entry: { name: '', department: '', title: '', consent: true }, blob: null, blobUrl: null, photo: null, style: null, name: '', error: '' });
    go('attract');
  }

  /** Show n, n-1 … 1 over the camera, then resolve. */
  function runCountdown(n) {
    const count = $('count');
    const show = () => { count.textContent = n; count.classList.remove('tick'); void count.offsetWidth; count.classList.add('tick'); };
    count.hidden = false;
    show();
    return new Promise(resolve => {
      clearInterval(timer);
      timer = setInterval(() => {
        n--;
        if (n > 0) return show();
        clearInterval(timer);
        count.hidden = true;
        resolve();
      }, 1000);
    });
  }
  function flashFx() {
    const flash = $('flash');
    flash.hidden = false;
    flash.style.animation = 'none'; void flash.offsetWidth; flash.style.animation = '';
    setTimeout(() => { flash.hidden = true; }, 700);
  }
  const wait = ms => new Promise(r => setTimeout(r, ms));

  async function countdown() {
    if (st.kind === 'strip') return stripShots();
    if (st.kind === 'boomerang') return recordBoomerang();
    go('countdown');
    await runCountdown(isGroup() ? 5 : 3);
    const blob = await capture();
    flashFx();
    if (!blob) return go('attract', { error: 'The camera did not give a picture. Try again.' });
    if (st.blobUrl) URL.revokeObjectURL(st.blobUrl);
    go('preview', { blob, blobUrl: URL.createObjectURL(blob), photo: null, error: '' });
  }

  /** Photo strip: four shots, then a 2×2 preview. */
  async function stripShots() {
    st.frames = [];
    st.photo = null;
    for (let i = 0; i < 4; i++) {
      go('countdown', { shot: i + 1 });
      await runCountdown(i === 0 ? 3 : 2);
      const blob = await capture();
      flashFx();
      if (!blob) return go('attract', { error: 'The camera did not give a picture. Try again.' });
      st.frames.push(blob);
      if (i < 3) await wait(500);
    }
    const preview = await stripPreview(st.frames);
    if (st.blobUrl) URL.revokeObjectURL(st.blobUrl);
    go('preview', { blob: preview, blobUrl: URL.createObjectURL(preview), error: '' });
  }
  async function stripPreview(blobs) {
    const imgs = await Promise.all(blobs.map(b => createImageBitmap(b)));
    const w = imgs[0].width, h = imgs[0].height, gap = Math.round(w * 0.02);
    const c = document.createElement('canvas');
    c.width = w * 2 + gap; c.height = h * 2 + gap;
    const x = c.getContext('2d');
    x.fillStyle = '#120D18'; x.fillRect(0, 0, c.width, c.height);
    imgs.forEach((im, i) => x.drawImage(im, (i % 2) * (w + gap), Math.floor(i / 2) * (h + gap), w, h));
    return new Promise(res => c.toBlob(res, 'image/jpeg', 0.85));
  }

  /** Boomerang: record about two seconds straight from the camera. */
  async function recordBoomerang() {
    st.photo = null;
    go('countdown', { shot: 0 });
    await runCountdown(3);
    const type = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm', 'video/mp4'].find(t => MediaRecorder.isTypeSupported(t)) || '';
    let rec;
    try { rec = new MediaRecorder(stream, type ? { mimeType: type, videoBitsPerSecond: 6_000_000 } : undefined); }
    catch (_) { return go('attract', { error: "This browser can't record video, so boomerangs won't work here." }); }
    const chunks = [];
    rec.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
    const stopped = new Promise(r => { rec.onstop = r; });
    go('recording');
    rec.start();
    await wait(2200);
    rec.stop();
    await stopped;
    const clip = new Blob(chunks, { type: rec.mimeType || type || 'video/webm' });
    if (!clip.size) return go('attract', { error: 'The camera did not record anything. Try again.' });
    if (st.clipUrl) URL.revokeObjectURL(st.clipUrl);
    go('preview', { clip, clipUrl: URL.createObjectURL(clip), error: '' });
  }

  async function makeBoomerang() {
    if (busy || !st.clip) return;
    busy = true;
    go('working', { error: '' });
    try {
      await freshToken();
      const ext = (st.clip.type || '').includes('mp4') ? 'mp4' : 'webm';
      const res = await post('/boomerang', { clip: new File([st.clip], 'clip.' + ext, { type: st.clip.type || 'video/webm' }), name: st.name }, { timeoutMs: 90_000 });
      if (!res.ok) throw new Error(res.json.error || 'The boomerang could not be made.');
      st.photo = res.json;
      go('result');
    } catch (err) {
      go('failed', { error: err.name === 'AbortError' ? 'The server took too long. Try again.' : err.message });
    } finally {
      busy = false;
    }
  }

  async function makeIt() {
    if (busy || st.style == null) return;
    busy = true;
    const style = st.style === LOOK_NONE ? '' : cfg.styles[st.style].name;
    go('working', { error: '' });
    try {
      await freshToken();
      if (!st.photo && st.kind === 'strip') {
        const frames = st.frames.map((b, i) => new File([b], `shot${i + 1}.jpg`, { type: 'image/jpeg' }));
        const up = await post('/strip', { frames, name: st.name }, { timeoutMs: 60_000 });
        if (!up.ok) throw new Error(up.json.error || 'The photos could not be uploaded.');
        st.photo = up.json;
      } else if (!st.photo) {
        const up = await post('/photos', { photo: new File([st.blob], 'photo.jpg', { type: 'image/jpeg' }), name: st.name, kind: st.kind });
        if (!up.ok) throw new Error(up.json.error || 'The photo could not be uploaded.');
        st.photo = up.json;
      }
      let res;
      try {
        res = await post(`/photos/${st.photo.id}/process`, asIs() ? { style, as_is: '1' } : { style }, { timeoutMs: 240_000 });
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

  async function submitEntry() {
    if (busy) return;
    const e = st.entry;
    const problem = !e.name.trim() ? 'Enter your name.' : !e.department.trim() ? 'Choose your department.'
      : !e.consent ? 'Tick the box so your photo can be shown in the gallery and on screens.' : '';
    if (problem) { st.error = problem; render(); return; }
    busy = true;
    const btn = side.querySelector('[data-act="submitEntry"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }
    try {
      await freshToken();
      const res = await post('/entry', {
        photo: new File([st.blob], 'entry.jpg', { type: 'image/jpeg' }),
        name: e.name.trim(), department: e.department.trim(), title: e.title.trim(), consent: e.consent ? '1' : '',
      });
      if (!res.ok) { st.error = res.json.error || 'The entry could not be sent. Try again.'; render(); return; }
      go('entryDone', { error: '', entryStatus: res.json.status });
      let left = DONE_RESET_S;
      clearInterval(timer);
      timer = setInterval(() => {
        left--;
        const el = $('resetIn');
        if (el) el.textContent = left;
        if (left <= 0) reset();
      }, 1000);
    } catch (_) {
      st.error = 'No connection to the server. Check the Wi-Fi and try again.';
      render();
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
    const boom = st.kind === 'boomerang';
    $('cam').classList.toggle('group', isWide());
    $('split').classList.toggle('group', isGroup());
    $('split').classList.toggle('strip', st.kind === 'strip');
    const showingTake = ['preview', 'style', 'working', 'failed', 'entryForm', 'entryDone'].includes(s);
    // The take: a still (or the strip's 2×2 preview), or the boomerang clip looping.
    const clipVid = $('clipVid');
    still.hidden = !showingTake || boom;
    if (!still.hidden) still.src = st.blobUrl;
    still.classList.toggle('unmirror', st.kind === 'strip'); // the 2×2 preview reads left to right
    clipVid.hidden = !showingTake || !boom || !st.clipUrl;
    if (!clipVid.hidden) { if (clipVid.src !== st.clipUrl) clipVid.src = st.clipUrl; clipVid.play().catch(() => {}); } else clipVid.pause();
    const live = !showingTake;
    $('camChip').textContent = s === 'recording' ? 'Recording' : live ? (s === 'countdown' && st.shot ? `Shot ${st.shot} of 4` : 'Live camera') : (boom ? 'Your clip' : st.kind === 'strip' ? 'Your shots' : 'Your photo');
    $('camChip').classList.toggle('rec', live);
    $('shimmer').hidden = s !== 'working';
    if (showSplit) {
      const afterVid = $('afterVid'), afterImg = $('afterImg');
      afterImg.hidden = !!st.photo.isVideo;
      afterVid.hidden = !st.photo.isVideo;
      if (st.photo.isVideo) { if (afterVid.getAttribute('src') !== st.photo.result) afterVid.src = st.photo.result; afterVid.play().catch(() => {}); }
      else { afterVid.pause(); afterImg.src = st.photo.result; }
      $('afterCap').textContent = boom ? 'Boomerang' : st.kind === 'strip' ? '' : st.photo.style;
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
        <button type="button" class="kbtn ghost mode" data-act="strip"><b>Photo strip</b><span>4 shots, classic strip</span></button>
        ${canBoomerang ? '<button type="button" class="kbtn ghost mode" data-act="boomerang"><b>Boomerang</b><span>2-second loop</span></button>' : ''}
        ${cfg.entries ? `<button type="button" class="kbtn ghost wide" data-act="enter">Enter the ${esc(cfg.noun)} contest</button>` : ''}
      </div>
      ${fine}`,
    frame: () => `
      <h2>Get everyone in the frame</h2>
      <p>Squeeze in until every face is on the screen. Take your time. Tap when you're all ready and you'll get a 5-second countdown.</p>
      <div class="kacts col">
        <button type="button" class="kbtn primary huge" data-act="ready" data-autofocus>We're ready!</button>
        <button type="button" class="kbtn link" data-act="reset">← Go back to the start</button>
      </div>`,
    recording: () => `<h2>Go go go!</h2><p>Wave, spin, jump: it plays forward and backward on a loop.</p>`,
    countdown: () => st.kind === 'strip' ? `<h2>Shot ${st.shot} of 4</h2><p>${['Big smile!', 'Now something silly!', 'Spooky face!', 'Last one: anything goes!'][st.shot - 1] || ''}</p>`
      : st.kind === 'boomerang' ? `<h2>Get ready to move!</h2><p>When it says go, do something fun for two seconds.</p>`
      : st.flow === 'entry' ? `<h2>Show off that ${esc(cfg.noun)}!</h2><p>Stand back so the whole ${esc(cfg.noun)} is in the frame.</p>` : isGroup() ? `<h2>Squeeze in!</h2><p>Make sure everyone's face is in the frame.</p>` : `<h2>Get ready…</h2><p>Look at the camera and hold still.</p>`,
    preview: () => `
      <h2>How's that?</h2><p>${st.kind === 'strip' ? 'Keep these four or take them again.' : st.kind === 'boomerang' ? 'This is the raw clip. Keep it and we\'ll make it loop.' : 'Keep it or take another.'}</p>
      <div class="kacts"><button type="button" class="kbtn ghost" data-act="retake">Retake</button><button type="button" class="kbtn primary" data-act="keep" data-autofocus>Looks good</button></div>
      <button type="button" class="kbtn link" data-act="reset">← Go back to the start</button>`,
    style: () => `
      <h2>Pick your look</h2>
      <div class="kstyles${looksForKind().length > 3 ? ' many' : ''}"><button type="button" class="kstyle" data-style="${LOOK_NONE}" aria-pressed="${st.style === LOOK_NONE}"><span class="sw" style="background:linear-gradient(160deg,#c9ccd3,#6b7280)"></span>No changes</button>${looksForKind().map(x => `<button type="button" class="kstyle" data-style="${x.i}" aria-pressed="${st.style === x.i}"><span class="sw" style="background:linear-gradient(160deg,${esc(x.from)},${esc(x.to)})"></span>${esc(x.name)}</button>`).join('')}</div>
      <div class="field"><label for="kName">${isGroup() ? 'Names' : 'Your name'} <span class="hint">(optional, shown in the gallery)</span></label><input id="kName" maxlength="80" value="${esc(st.name)}" autocomplete="off"></div>
      <button type="button" class="kbtn primary" data-act="make" ${st.style == null ? 'disabled' : ''}>${asIs() ? 'Use this photo' : esc(cfg.verb)}</button>
      <button type="button" class="kbtn link" data-act="retake">Retake photo</button>`,
    working: () => `
      <h2>Working on it…</h2>
      <p>${st.kind === 'boomerang' ? 'Making your boomerang. Just a few seconds.'
        : st.kind === 'strip' ? 'Putting your strip together. Just a second.'
        : asIs() ? 'Saving your photo. Just a second.'
        : `Turning ${isGroup() ? 'your group' : 'you'} into: <b>${esc(cfg.styles[st.style]?.name)}</b>. This usually takes 15–60 seconds.`}</p>
      <div class="spin" role="status" aria-label="Working"></div>`,
    failed: () => `
      <h2>That didn't work</h2>
      <div class="kerr">${esc(st.error)}</div>
      <div class="kacts col">
        ${(st.kind === 'boomerang' && st.clip) || (st.kind === 'strip' && st.frames.length === 4) ? '<button type="button" class="kbtn primary" data-act="keep">Try again</button>' : st.blob ? '<button type="button" class="kbtn primary" data-act="restyle">Try again</button>' : ''}
        <button type="button" class="kbtn ghost" data-act="reset">Start over</button>
      </div>`,
    result: () => `
      <h2>Ta-da!</h2><p>${st.kind === 'boomerang' ? "Here's your boomerang!" : st.kind === 'strip' ? "Here's your photo strip!" : asIs() ? "Here's your photo!" : `Here's ${isGroup() ? 'your group' : 'you'} as <b>${esc(st.photo.style)}</b>.`}</p>
      <div class="kacts col">
        <button type="button" class="kbtn primary" data-act="gallery" data-autofocus>Add to gallery</button>
        ${st.kind === 'boomerang' || st.kind === 'strip' ? '<button type="button" class="kbtn ghost" data-act="retake">Do another one</button>' : '<button type="button" class="kbtn ghost" data-act="restyle">Try another look</button>'}
        <button type="button" class="kbtn link" data-act="reset">Start over</button>
      </div>`,
    entryForm: () => {
      const e = st.entry;
      const dept = cfg.departments.length
        ? `<select id="eDept"><option value="">Choose your department</option>${cfg.departments.map(d => `<option ${d === e.department ? 'selected' : ''}>${esc(d)}</option>`).join('')}</select>`
        : `<input id="eDept" maxlength="80" value="${esc(e.department)}" autocomplete="off">`;
      return `
      <h2>Enter the contest</h2>
      ${st.error ? `<div class="kerr" role="alert">${esc(st.error)}</div>` : ''}
      <div class="field"><label for="eName">Your name</label><input id="eName" maxlength="100" value="${esc(e.name)}" autocomplete="off" data-autofocus></div>
      <div class="field"><label for="eDept">Department</label>${dept}</div>
      <div class="field"><label for="eTitle">${esc(cfg.noun.charAt(0).toUpperCase() + cfg.noun.slice(1))} name <span class="hint">(optional)</span></label><input id="eTitle" maxlength="100" value="${esc(e.title)}" autocomplete="off"></div>
      <label class="kcheck"><input type="checkbox" id="eConsent" ${e.consent ? 'checked' : ''}> I'm OK with my photo being shown in the gallery and on the office screens.</label>
      <button type="button" class="kbtn primary" data-act="submitEntry">Submit entry</button>
      <button type="button" class="kbtn link" data-act="retake">Retake photo</button>`;
    },
    entryDone: () => `
      <h2>${st.entryStatus === 'pending' ? 'Entry sent!' : "You're in!"}</h2>
      <p>${st.entryStatus === 'pending' ? 'An organizer will approve it shortly.' : `${esc(st.entry.name)} is now on the voting page.`}</p>
      <div class="kqr"><img src="${esc(cfg.qr)}?u=${encodeURIComponent(cfg.voteUrl)}" alt="QR code for the voting page">
        <span>Scan to vote from your phone.<code>${esc(cfg.voteUrl)}</code></span></div>
      <button type="button" class="kbtn primary" data-act="reset" data-autofocus>Next person</button>
      <p class="kfine">Resetting in <span id="resetIn">${DONE_RESET_S}</span> seconds.</p>`,
    done: () => `
      <h2>You're in the gallery!</h2>
      <div class="kqr"><img src="${esc(cfg.qr)}?u=${encodeURIComponent(st.photo.shareUrl)}" alt="QR code linking to your photo">
        <span>Scan to save your ${st.kind === 'boomerang' ? 'boomerang' : st.kind === 'strip' ? 'strip' : 'photo'} to your phone.<code>${esc(st.photo.shareUrl)}</code></span></div>
      <button type="button" class="kbtn primary" data-act="reset" data-autofocus>Next person</button>
      <p class="kfine">Resetting in <span id="resetIn">${DONE_RESET_S}</span> seconds.</p>`,
  };

  side.addEventListener('click', e => {
    const styleBtn = e.target.closest('[data-style]');
    if (styleBtn) {
      st.style = +styleBtn.dataset.style;
      side.querySelectorAll('[data-style]').forEach(b => b.setAttribute('aria-pressed', b === styleBtn));
      const make = side.querySelector('[data-act="make"]');
      make.disabled = false;
      make.textContent = asIs() ? 'Use this photo' : cfg.verb;
      return;
    }
    const act = e.target.closest('[data-act]')?.dataset.act;
    if (!act) return;
    if (act === 'group') { st.kind = 'group'; st.flow = 'booth'; st.style = null; go('frame'); }
    else if (act === 'ready') countdown();
    else if (['single', 'strip', 'boomerang'].includes(act)) { st.kind = act; st.flow = 'booth'; st.style = null; render(); countdown(); }
    else if (act === 'enter') { st.kind = 'single'; st.flow = 'entry'; render(); countdown(); }
    else if (act === 'keep' && st.flow === 'entry') go('entryForm', { error: '' });
    else if (act === 'submitEntry') submitEntry();
    else if (act === 'retake') isGroup() && st.flow === 'booth' ? go('frame') : countdown();
    else if (act === 'keep' && st.kind === 'boomerang') makeBoomerang();
    else if (act === 'keep' && st.kind === 'strip') { st.style = LOOK_NONE; makeIt(); }
    else if (act === 'keep' || act === 'restyle') go('style');
    else if (act === 'make') makeIt();
    else if (act === 'gallery') addToGallery();
    else if (act === 'reset') reset();
  });
  side.addEventListener('input', e => {
    const t = e.target;
    if (t.id === 'kName') st.name = t.value;
    if (t.id === 'eName') st.entry.name = t.value;
    if (t.id === 'eDept') st.entry.department = t.value;
    if (t.id === 'eTitle') st.entry.title = t.value;
    if (t.id === 'eConsent') st.entry.consent = t.checked;
  });
  side.addEventListener('change', e => {
    if (e.target.id === 'eDept') st.entry.department = e.target.value;
    if (e.target.id === 'eConsent') st.entry.consent = e.target.checked;
  });

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
