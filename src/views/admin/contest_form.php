<?php
$err = fn($k) => isset($errors[$k]) ? '<span class="error">' . e($errors[$k]) . '</span>' : '';
$cls = fn($k) => isset($errors[$k]) ? 'field has-error' : 'field';
$modeDefaults = [];
foreach (modes() as $key => $m) {
    $modeDefaults[$key] = ['title' => $m['title'], 'categories' => implode("\n", $m['categories']), 'event_name' => $m['event_name'], 'booth' => $m['booth']];
}
?>
<div class="spread">
  <div>
    <a class="small" href="<?= e(admin_url()) ?>">← All contests</a>
    <h1 class="page-title"><?= $contest ? e($contest['title']) : 'New contest' ?></h1>
  </div>
  <?php if ($contest): ?><span class="chip <?= $contest['status'] === 'active' ? 'active' : '' ?>"><?= e(['active' => 'On home page', 'draft' => 'Draft', 'archived' => 'Archived'][$contest['status']] ?? $contest['status']) ?></span><?php endif; ?>
</div>

<?php if ($errors): ?><div class="alert" role="alert"><strong>Check the highlighted fields.</strong></div><?php endif; ?>

<form class="stack" method="post" novalidate data-contest-form data-new="<?= $contest ? '0' : '1' ?>" data-mode-defaults="<?= e(json_encode($modeDefaults)) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <fieldset>
      <legend>Contest type</legend>
      <div class="modes" role="radiogroup">
        <?php foreach (modes() as $key => $m): ?>
          <label><input type="radio" name="mode" value="<?= e($key) ?>" <?= $form['mode'] === $key ? 'checked' : '' ?>> <?= e($m['label']) ?></label>
        <?php endforeach; ?>
      </div>
      <span class="hint">Sets the colors, wording and photobooth looks. Changing it on a new contest also fills in starter categories.</span>
      <?= $err('mode') ?>
    </fieldset>
  </section>

  <section class="card">
    <fieldset>
      <legend>Details</legend>
      <div class="<?= $cls('title') ?>">
        <label for="title">Title</label>
        <input id="title" name="title" value="<?= e($form['title']) ?>" maxlength="120" required>
        <?= $err('title') ?>
      </div>
      <div class="field">
        <label for="subtitle">Subtitle <span class="hint">(optional)</span></label>
        <input id="subtitle" name="subtitle" value="<?= e($form['subtitle']) ?>" maxlength="120" placeholder="e.g. Halloween 2026">
      </div>
      <div class="grid2">
        <div class="<?= $cls('starts_at') ?>">
          <label for="starts_at">Voting opens</label>
          <input id="starts_at" name="starts_at" type="datetime-local" value="<?= e($form['starts_at']) ?>" required>
          <?= $err('starts_at') ?>
        </div>
        <div class="<?= $cls('ends_at') ?>">
          <label for="ends_at">Voting closes</label>
          <input id="ends_at" name="ends_at" type="datetime-local" value="<?= e($form['ends_at']) ?>" required>
          <?= $err('ends_at') ?>
        </div>
      </div>
      <span class="hint">Times are in <?= e(str_replace('_', ' ', site_tz()->getName())) ?>. Change the time zone in Site settings.</span>
    </fieldset>
  </section>

  <section class="card">
    <fieldset>
      <legend>Categories</legend>
      <div class="<?= $cls('categories') ?>">
        <label for="categories">One per line</label>
        <textarea id="categories" name="categories" rows="5"><?= e($form['categories']) ?></textarea>
        <span class="hint">Up to 10. The overall winner is worked out from the total of all category votes, so you don't need an "Overall" category.</span>
        <?= $err('categories') ?>
      </div>
    </fieldset>
  </section>

  <section class="card">
    <fieldset>
      <legend>Event</legend>
      <div class="grid2">
        <div class="field">
          <label for="event_name">Event name</label>
          <input id="event_name" name="event_name" value="<?= e($form['event_name']) ?>" maxlength="80" placeholder="e.g. Costume parade">
        </div>
        <div class="field">
          <label for="event_details">When and where</label>
          <input id="event_details" name="event_details" value="<?= e($form['event_details']) ?>" maxlength="160" placeholder="e.g. Fri Oct 30, 12:30 PM · Main lobby">
        </div>
      </div>
    </fieldset>
  </section>

  <section class="card">
    <fieldset>
      <legend>Options</legend>
      <label class="toggle"><span>Approve entries before they appear<small>Off: new entries show up right away</small></span><input class="switch" type="checkbox" name="require_approval" value="1" <?= !empty($form['require_approval']) ? 'checked' : '' ?>></label>
      <label class="toggle"><span>Show live vote counts<small>Off: counts stay hidden until voting closes</small></span><input class="switch" type="checkbox" name="show_counts" value="1" <?= !empty($form['show_counts']) ? 'checked' : '' ?>></label>
      <label class="toggle"><span>Photobooth<small>AI photobooth page for a tablet or webcam (coming in a later step)</small></span><input class="switch" type="checkbox" name="booth_enabled" value="1" <?= !empty($form['booth_enabled']) ? 'checked' : '' ?>></label>
    </fieldset>
  </section>

  <div class="row">
    <button class="btn primary" type="submit"><?= $contest ? 'Save changes' : 'Create contest' ?></button>
    <a class="btn ghost" href="<?= e(admin_url()) ?>">Cancel</a>
  </div>
</form>
