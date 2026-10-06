<div class="spread">
  <div>
    <a class="small" href="<?= e(url('/')) ?>">← <?= e($contest['title'] ?? 'Home') ?></a>
    <h1 class="page-title">Gallery</h1>
  </div>
  <?php if ($photos): ?><span class="chip"><?= count($photos) ?> photo<?= count($photos) === 1 ? '' : 's' ?></span><?php endif; ?>
</div>

<?php if (!$contest): ?>
  <section class="card empty"><p><strong>No contest running right now.</strong></p></section>
<?php elseif (!$photos): ?>
  <section class="card empty">
    <p><strong>No photos yet.</strong></p>
    <p><?= $contest['booth_enabled'] ? 'Visit the photobooth, pick a look and tap "Add to gallery". Your picture shows up here.' : 'Photos will appear here once the contest gets going.' ?></p>
  </section>
<?php else: ?>
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
