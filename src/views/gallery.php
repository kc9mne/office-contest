<?php
$shared = array_values(array_filter($photos, fn($p) => $p['kind'] === 'upload'));
$photos = array_values(array_filter($photos, fn($p) => $p['kind'] !== 'upload'));
$total = count($photos) + count($shared) + count($entries);
?>
<div class="spread">
  <div>
    <a class="small" href="<?= e(url('/')) ?>">← <?= e($contest['title'] ?? 'Home') ?></a>
    <h1 class="page-title">Gallery</h1>
  </div>
  <?php if ($total): ?><span class="chip"><?= $total ?> photo<?= $total === 1 ? '' : 's' ?></span><?php endif; ?>
</div>
<?php if ($contest): ?>
<nav class="subtabs" aria-label="Gallery sections">
  <a href="<?= e(url('/gallery')) ?>" aria-current="page">Photos</a>
  <a href="<?= e(url('/videos')) ?>">Videos<?= $videoCount ? ' (' . $videoCount . ')' : '' ?></a>
</nav>
<?php endif; ?>

<?php if ($contest): ?>
<details class="card add-photos" id="add">
  <summary><b>+ Add your photos</b> <span class="muted small">Share your pictures from the party</span></summary>
  <form method="post" action="<?= e(url('/gallery/add')) ?>" enctype="multipart/form-data" class="stack" style="gap:12px; margin-top:12px">
    <?= csrf_field() ?>
    <div class="field">
      <label for="g_photos">Photos <span class="hint">(up to <?= GALLERY_UPLOADS_PER_POST ?> at a time)</span></label>
      <input id="g_photos" name="photos[]" type="file" accept="image/*" multiple required>
    </div>
    <div class="field">
      <label for="g_name">Who's in them? <span class="hint">(optional)</span></label>
      <input id="g_name" name="name" maxlength="80" autocomplete="off">
    </div>
    <label class="check"><input type="checkbox" name="consent" value="1" required> Everyone in these photos is OK with them being on the office site and screens.</label>
    <button class="btn primary block" type="submit" data-busy-text="Uploading…">Add to gallery</button>
  </form>
</details>
<?php endif; ?>

<?php if (!$contest): ?>
  <section class="card empty"><p><strong>No contest running right now.</strong></p></section>
<?php elseif (!$total): ?>
  <section class="card empty">
    <p><strong>No photos yet.</strong></p>
    <p>Add your photos above, enter the contest<?= $contest['booth_enabled'] ? ' or visit the photobooth' : '' ?>, and your pictures show up here.</p>
  </section>
<?php else: ?>
  <?php if ($shared): ?>
  <section>
    <h2 class="eyebrow" style="margin-bottom:10px">Shared by everyone</h2>
    <div class="gallery">
      <?php foreach ($shared as $p): ?>
        <a class="gitem" href="<?= e(url('/p/' . $p['code'])) ?>">
          <?php require APP_ROOT . '/src/views/partials/booth_media.php'; ?>
          <?php if ($p['name'] !== ''): ?><span class="gcap"><?= e($p['name']) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
  <?php if ($photos): ?>
  <section>
    <h2 class="eyebrow" style="margin-bottom:10px">Photobooth</h2>
    <div class="gallery">
      <?php foreach ($photos as $p): ?>
        <a class="gitem<?= $p['kind'] === 'group' ? ' wide' : '' ?><?= $p['kind'] === 'strip' ? ' strip' : '' ?>" href="<?= e(url('/p/' . $p['code'])) ?>">
          <?php require APP_ROOT . '/src/views/partials/booth_media.php'; ?>
          <span class="gcap"><?= e($p['name'] !== '' ? $p['name'] : 'Photobooth') ?><small><?= e($p['style']) ?></small></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
  <?php if ($entries): ?>
  <section>
    <h2 class="eyebrow" style="margin-bottom:10px">Contest entries</h2>
    <div class="gallery">
      <?php foreach ($entries as $en): ?>
        <a class="gitem" href="<?= e(url('/vote#entry-' . $en['id'])) ?>">
          <img src="<?= e(media_url($en['photo_path'])) ?>" alt="<?= e($en['name']) ?>" loading="lazy">
          <span class="gcap"><?= e($en['name']) ?><?php if ($en['title'] !== '' || $en['department'] !== ''): ?><small><?= e($en['title'] !== '' ? $en['title'] : $en['department']) ?></small><?php endif; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
<?php endif; ?>
