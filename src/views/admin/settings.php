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
        <span class="chip ok">AI connected</span><span class="muted">Model: <?= e(env('OPENAI_IMAGE_MODEL', 'gpt-image-1')) ?></span>
      <?php else: ?>
        <span class="chip">Demo mode</span><span class="muted">No AI key yet. Photos get a color filter instead. Add <code>OPENAI_API_KEY</code> to the server's <code>.env</code> file to turn on AI.</span>
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
