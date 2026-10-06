<?php $total = count($photos) + count($entries); ?>
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

<?php if (!$contest): ?>
  <section class="card empty"><p><strong>No contest running right now.</strong></p></section>
<?php elseif (!$total): ?>
  <section class="card empty">
    <p><strong>No photos yet.</strong></p>
    <p>Enter the contest<?= $contest['booth_enabled'] ? ' or visit the photobooth' : '' ?> and your picture shows up here.</p>
  </section>
<?php else: ?>
  <?php if ($photos): ?>
  <section>
    <h2 class="eyebrow" style="margin-bottom:10px">Photobooth</h2>
    <div class="gallery">
      <?php foreach ($photos as $p): ?>
        <a class="gitem<?= $p['kind'] === 'group' ? ' wide' : '' ?>" href="<?= e(url('/p/' . $p['code'])) ?>">
          <img src="<?= e(media_url($p['result_path'])) ?>" alt="<?= e(($p['name'] !== '' ? $p['name'] . ' as ' : '') . $p['style']) ?>" loading="lazy">
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
