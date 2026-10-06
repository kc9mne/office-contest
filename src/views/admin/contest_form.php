<?php
$err = fn($k) => isset($errors[$k]) ? '<span class="error">' . e($errors[$k]) . '</span>' : '';
$cls = fn($k) => isset($errors[$k]) ? 'field has-error' : 'field';
$modeDefaults = [];
foreach (modes() as $key => $m) {
    $modeDefaults[$key] = ['title' => $m['title'], 'categories' => implode("\n", $m['categories']), 'event_name' => $m['event_name'], 'booth' => $m['booth'],
        'styles' => array_map(fn($s) => ['name' => $s['name'], 'prompt' => $s['prompt']], $m['booth_styles'])];
}
$styleRows = array_pad(array_values($form['styles'] ?? []), BOOTH_MAX_STYLES, ['name' => '', 'prompt' => '']);
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
    </fieldset>
  </section>

  <section class="card">
    <fieldset>
      <legend>Photobooth</legend>
      <label class="toggle"><span>Photobooth for this contest<small>A full-screen camera page for a tablet or webcam PC. Get its link in Site settings.</small></span><input class="switch" type="checkbox" name="booth_enabled" value="1" <?= !empty($form['booth_enabled']) ? 'checked' : '' ?>></label>
      <div class="<?= $cls('booth_daily_limit') ?>" style="max-width:240px">
        <label for="booth_daily_limit">Daily AI photo limit</label>
        <input id="booth_daily_limit" name="booth_daily_limit" type="number" min="0" max="99999" value="<?= e((string) ($form['booth_daily_limit'] ?? 200)) ?>">
        <span class="hint">Caps your AI bill. 0 means no limit. Resets at midnight.</span>
        <?= $err('booth_daily_limit') ?>
      </div>
      <div class="stack" style="gap:6px">
        <span class="label">Looks</span>
        <span class="hint">Up to <?= BOOTH_MAX_STYLES ?>. The instructions are sent to the AI along with the photo. Leave a row empty to skip it. Write them for one person; for group photos the booth tells the AI to apply the look to everyone. Every request also tells the AI to keep people recognizable and office-appropriate.</span>
        <?= $err('booth_styles') ?>
      </div>
      <?php foreach ($styleRows as $i => $s): ?>
        <div class="grid2" style="grid-template-columns: minmax(140px, 1fr) minmax(220px, 3fr)">
          <div class="field">
            <label for="style_name_<?= $i ?>">Look <?= $i + 1 ?> name</label>
            <input id="style_name_<?= $i ?>" name="style_name[]" value="<?= e($s['name']) ?>" maxlength="60" data-style-name="<?= $i ?>">
          </div>
          <div class="field">
            <label for="style_prompt_<?= $i ?>">Instructions for the AI</label>
            <textarea id="style_prompt_<?= $i ?>" name="style_prompt[]" rows="2" style="min-height:64px" maxlength="1000" data-style-prompt="<?= $i ?>"><?= e($s['prompt']) ?></textarea>
          </div>
        </div>
      <?php endforeach; ?>
    </fieldset>
  </section>

  <div class="row">
    <button class="btn primary" type="submit"><?= $contest ? 'Save changes' : 'Create contest' ?></button>
    <a class="btn ghost" href="<?= e(admin_url()) ?>">Cancel</a>
  </div>
</form>
