<?php
$err = fn($k) => isset($errors[$k]) ? '<span class="error">' . e($errors[$k]) . '</span>' : '';
$cls = fn($k) => isset($errors[$k]) ? 'field has-error' : 'field';
$logo = logo_url();
$currentTz = site_tz()->getName();
?>
<h1 class="page-title">Site settings</h1>
<?php if ($errors): ?><div class="alert" role="alert"><strong>Check the highlighted fields.</strong></div><?php endif; ?>

<form class="card" method="post" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="branding">
  <fieldset>
    <legend>Branding</legend>
    <div class="row" style="gap:16px; align-items:flex-start">
      <span class="logo lg" id="logoPreview"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt="Current logo"><?php else: ?><?= e(company_initials()) ?><?php endif; ?></span>
      <div class="<?= $cls('logo') ?>" style="flex:1; min-width:200px">
        <label for="logo">Logo</label>
        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-preview="logoPreview">
        <span class="hint">PNG with a transparent background looks best. Shown in the header of every page.</span>
        <?php if ($logo): ?><label class="row small"><input type="checkbox" name="remove_logo" value="1"> Remove the current logo</label><?php endif; ?>
        <?= $err('logo') ?>
      </div>
    </div>
    <div class="<?= $cls('company_name') ?>">
      <label for="company_name">Company name</label>
      <input id="company_name" name="company_name" value="<?= e(company_name()) ?>" maxlength="80">
      <?= $err('company_name') ?>
    </div>
    <div class="<?= $cls('brand_color') ?>">
      <span class="label">Brand color</span>
      <div class="colorrow">
        <input type="color" id="brand_color" name="brand_color" value="<?= e(strtolower((string) setting('brand_color', '#2563EB'))) ?>" aria-label="Brand color">
        <span class="mono" id="brandHex"><?= e(setting('brand_color', '#2563EB')) ?></span>
      </div>
      <?= $err('brand_color') ?>
    </div>
    <label class="toggle"><span>Use the brand color instead of the mode colors<small>Off: Halloween is orange, Holiday party is red, General is teal</small></span><input class="switch" type="checkbox" name="use_brand_color" value="1" <?= setting('use_brand_color') === '1' ? 'checked' : '' ?>></label>
    <div class="<?= $cls('timezone') ?>">
      <label for="timezone">Time zone</label>
      <select id="timezone" name="timezone">
        <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
          <option value="<?= e($tz) ?>" <?= $tz === $currentTz ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $tz)) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $err('timezone') ?>
    </div>
    <div><button class="btn primary" type="submit">Save branding</button></div>
  </fieldset>
</form>

<form class="card" method="post" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="new_booth_link">
  <fieldset>
    <legend>Photobooth</legend>
    <p class="hint" style="margin:0">Open this link on the booth tablet or PC, allow the camera, and tap the full-screen button. It always shows the contest that's live on the home page.</p>
    <div class="copy"><code id="boothLink"><?= e($boothLink) ?></code><button class="btn small ghost" type="button" data-copy="boothLink">Copy</button><a class="btn small ghost" href="<?= e($boothLink) ?>" target="_blank" rel="noopener">Open</a></div>
    <div class="row small">
      <?php if (booth_ai_configured()): ?>
        <span class="chip ok">AI connected</span>
      <?php else: ?>
        <span class="chip">Demo mode</span><span class="muted">No AI key yet, so photos get a color filter. <a href="#ai">Add a key below.</a></span>
      <?php endif; ?>
    </div>
    <div class="row small muted">
      AI photos today: <strong style="color:var(--fg)"><?= (int) $boothRunsToday ?></strong>
      <?php if ($activeContest && (int) $activeContest['booth_daily_limit'] > 0): ?>of <?= (int) $activeContest['booth_daily_limit'] ?> allowed<?php endif; ?>
      <?php if ($activeContest && !$activeContest['booth_enabled']): ?> · The photobooth is turned off for the live contest.<?php endif; ?>
    </div>
    <div><button class="btn ghost danger" type="submit">Make a new photobooth link</button></div>
  </fieldset>
</form>

<?php $keySource = openai_key_source(); $savedKey = openai_saved_key(); $brokenKey = setting('openai_api_key_enc') && $savedKey === null; ?>
<section class="card stack" id="ai" style="gap:14px">
  <h2 style="font-size:18px">Photobooth AI</h2>
  <p class="hint" style="margin:0">The photobooth uses OpenAI to restyle photos. Get a key at platform.openai.com under API keys. It's stored encrypted and never shown in full again.</p>

  <div class="row small">
    <?php if ($keySource === 'env'): ?>
      <span class="chip ok">Key set on the server</span><span class="muted">It comes from the server's <code>.env</code> file, which takes priority over a key saved here.</span>
    <?php elseif ($keySource === 'admin'): ?>
      <span class="chip ok">Key saved</span><span class="mono"><?= e(mask_secret($savedKey)) ?></span>
    <?php else: ?>
      <span class="chip">No key</span><span class="muted">The photobooth runs in demo mode.</span>
    <?php endif; ?>
  </div>
  <?php if ($brokenKey): ?><div class="alert"><strong>The saved key can't be read.</strong> The server's encryption file (storage/app.key) changed. Paste the key again.</div><?php endif; ?>

  <?php if ($keySource !== 'env'): ?>
  <form class="stack" method="post" novalidate style="gap:10px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="ai_key_save">
    <div class="<?= $cls('openai_key') ?>">
      <label for="openai_key"><?= $keySource === 'admin' ? 'Replace the key' : 'OpenAI API key' ?></label>
      <input id="openai_key" name="openai_key" type="password" autocomplete="off" spellcheck="false" placeholder="sk-...">
      <span class="hint">It's checked with OpenAI (free) before it's saved.</span>
      <?= $err('openai_key') ?>
    </div>
    <div><button class="btn primary" type="submit">Save key</button></div>
  </form>
  <?php elseif (isset($errors['openai_key'])): ?><?= $err('openai_key') ?><?php endif; ?>

  <?php if ($keySource !== null): ?>
  <div class="row">
    <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ai_key_test"><button class="btn ghost small" type="submit">Test the key</button></form>
    <?php if ($keySource === 'admin'): ?>
      <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ai_key_remove"><button class="btn ghost small danger" type="submit">Remove the key</button></form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <form class="stack" method="post" novalidate style="gap:10px; border-top:1px solid var(--line); padding-top:14px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="ai_options">
    <div class="grid2">
      <div class="<?= $cls('openai_model') ?>">
        <label for="openai_model">Image model</label>
        <input id="openai_model" name="openai_model" value="<?= e(openai_model()) ?>" list="modelList" maxlength="60" spellcheck="false">
        <datalist id="modelList"><option value="gpt-image-1"><option value="gpt-image-1-mini"></datalist>
        <span class="hint">gpt-image-1 keeps faces most recognizable.</span>
        <?= $err('openai_model') ?>
      </div>
      <div class="<?= $cls('openai_quality') ?>">
        <label for="openai_quality">Quality</label>
        <select id="openai_quality" name="openai_quality">
          <?php foreach (['low' => 'Low (cheapest, fastest)', 'medium' => 'Medium (recommended)', 'high' => 'High (costs the most)'] as $q => $label): ?>
            <option value="<?= $q ?>" <?= openai_quality() === $q ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= $err('openai_quality') ?>
      </div>
    </div>
    <div><button class="btn ghost" type="submit">Save AI options</button></div>
  </form>
</section>

<form class="card" method="post" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="pin">
  <fieldset>
    <legend>Change admin PIN</legend>
    <div class="grid2">
      <div class="<?= $cls('current_pin') ?>">
        <label for="current_pin">Current PIN</label>
        <input id="current_pin" name="current_pin" type="password" inputmode="numeric" autocomplete="current-password">
        <?= $err('current_pin') ?>
      </div>
      <div class="<?= $cls('new_pin') ?>">
        <label for="new_pin">New PIN</label>
        <input id="new_pin" name="new_pin" type="password" inputmode="numeric" autocomplete="new-password">
        <span class="hint">4 to 12 digits</span>
        <?= $err('new_pin') ?>
      </div>
      <div class="<?= $cls('new_pin_confirm') ?>">
        <label for="new_pin_confirm">New PIN again</label>
        <input id="new_pin_confirm" name="new_pin_confirm" type="password" inputmode="numeric" autocomplete="new-password">
        <?= $err('new_pin_confirm') ?>
      </div>
    </div>
    <div><button class="btn ghost" type="submit">Change PIN</button></div>
  </fieldset>
</form>

<form class="card" method="post" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="new_link">
  <fieldset>
    <legend>Admin link</legend>
    <div class="copy"><code id="adminLink"><?= e($adminLink) ?></code><button class="btn small ghost" type="button" data-copy="adminLink">Copy</button></div>
    <p class="hint" style="margin:0">If the link was shared with someone who shouldn't have it, make a new one. The old link stops working right away.</p>
    <label class="row small <?= isset($errors['confirm_new_link']) ? 'error' : '' ?>"><input type="checkbox" name="confirm_new_link" value="1"> I understand the old admin link will stop working</label>
    <?= $err('confirm_new_link') ?>
    <div><button class="btn ghost danger" type="submit">Make a new admin link</button></div>
  </fieldset>
</form>
