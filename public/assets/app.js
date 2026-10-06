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
