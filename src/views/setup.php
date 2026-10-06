<?php $err = fn($k) => isset($errors[$k]) ? '<span class="error">' . e($errors[$k]) . '</span>' : ''; ?>
<section class="stack">
  <div>
    <div class="eyebrow">First-time setup</div>
    <h1 class="page-title">Office Contest</h1>
    <p class="muted">Set your company name, time zone and an admin PIN. You can change all of these later.</p>
  </div>
  <form class="card stack" method="post" action="<?= e(url('/setup')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field <?= isset($errors['company_name']) ? 'has-error' : '' ?>">
      <label for="company_name">Company name</label>
      <input id="company_name" name="company_name" value="<?= e($form['company_name']) ?>" autocomplete="organization" required>
      <?= $err('company_name') ?>
    </div>
    <div class="field <?= isset($errors['timezone']) ? 'has-error' : '' ?>">
      <label for="timezone">Time zone</label>
      <select id="timezone" name="timezone" data-guess-tz="<?= is_post() ? '0' : '1' ?>">
        <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
          <option value="<?= e($tz) ?>" <?= $tz === $form['timezone'] ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $tz)) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="hint">Contest start and end times use this zone.</span>
      <?= $err('timezone') ?>
    </div>
    <div class="grid2">
      <div class="field <?= isset($errors['pin']) ? 'has-error' : '' ?>">
        <label for="pin">Admin PIN</label>
        <input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="new-password" required>
        <span class="hint">4 to 12 digits</span>
        <?= $err('pin') ?>
      </div>
      <div class="field <?= isset($errors['pin_confirm']) ? 'has-error' : '' ?>">
        <label for="pin_confirm">Type the PIN again</label>
        <input id="pin_confirm" name="pin_confirm" type="password" inputmode="numeric" autocomplete="new-password" required>
        <?= $err('pin_confirm') ?>
      </div>
    </div>
    <button class="btn primary block" type="submit">Finish setup</button>
  </form>
  <p class="hint">After setup you get a private admin link. Bookmark it: you need the link and the PIN to manage contests.</p>
</section>
