<?php
$err = fn($k) => isset($errors[$k]) ? '<span class="error" role="alert">' . e($errors[$k]) . '</span>' : '';
$cls = fn($k) => isset($errors[$k]) ? 'field has-error' : 'field';
$Noun = ucfirst($noun);
?>
<div>
  <a class="small" href="<?= e(url('/')) ?>">← <?= e($contest['title']) ?></a>
  <h1 class="page-title">Enter a <?= e($noun) ?></h1>
</div>

<?php if ($phase === 'closed'): ?>
  <section class="card empty"><p><strong>Entries are closed.</strong> Voting has ended for this contest.</p><a class="btn ghost" href="<?= e(url('/')) ?>">See the results</a></section>
<?php else: ?>
<form class="stack" method="post" enctype="multipart/form-data" novalidate data-join-form>
  <?= csrf_field() ?>
  <div class="<?= $cls('photo') ?>">
    <label class="shot" for="photo" id="shot">
      <input class="sr-only" type="file" id="photo" name="photo" accept="image/*" required>
      <span class="shot-empty">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
        <b>Take a photo</b>
        <span>or choose one from your phone</span>
      </span>
      <img id="shotPreview" alt="Your photo" hidden>
    </label>
    <span class="hint"><?= $contest['mode'] === 'general' ? 'A photo of your entry.' : "A photo of you in your {$noun}." ?></span>
    <?= $err('photo') ?>
  </div>

  <div class="<?= $cls('name') ?>">
    <label for="name">Your name</label>
    <input id="name" name="name" value="<?= e($form['name']) ?>" maxlength="100" autocomplete="name" required>
    <span class="hint">For a group, put everyone's names.</span>
    <?= $err('name') ?>
  </div>

  <div class="<?= $cls('department') ?>">
    <label for="department">Department</label>
    <?php if ($departments): ?>
      <select id="department" name="department" required>
        <option value="">Choose your department</option>
        <?php foreach ($departments as $d): ?><option <?= $form['department'] === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
      </select>
    <?php else: ?>
      <input id="department" name="department" value="<?= e($form['department']) ?>" maxlength="80" required>
    <?php endif; ?>
    <?= $err('department') ?>
  </div>

  <div class="field">
    <label for="title"><?= e($Noun) ?> name <span class="hint">(optional)</span></label>
    <input id="title" name="title" value="<?= e($form['title']) ?>" maxlength="100" placeholder="<?= e(['halloween' => 'e.g. Haunted Printer', 'holiday' => "e.g. Rudolph's Revenge", 'general' => 'e.g. Five-Alarm Turkey Chili'][$contest['mode']] ?? '') ?>">
  </div>

  <div class="<?= $cls('consent') ?>">
    <label class="check"><input type="checkbox" name="consent" value="1" <?= $form['consent'] ? 'checked' : '' ?>> I'm OK with my photo being shown in the gallery and on the office screens.</label>
    <?= $err('consent') ?>
  </div>

  <button class="btn primary block" type="submit" data-busy-text="Sending your photo…">Submit entry</button>
  <p class="hint" style="margin:0; text-align:center">
    <?= $contest['require_approval'] ? 'An organizer approves entries before they appear.' : 'Your entry appears in the gallery and on the voting page right away.' ?>
  </p>
</form>
<?php endif; ?>
