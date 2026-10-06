<div class="spread">
  <h1 class="page-title">Gallery</h1>
  <a class="btn ghost" href="<?= e(url('/gallery')) ?>" target="_blank" rel="noopener">View public gallery ↗</a>
</div>

<?php if (!$contest): ?>
  <section class="card empty"><p>Make a contest live to see its photos here.</p></section>
<?php elseif (!$photos): ?>
  <section class="card empty"><p><strong>No photobooth pictures yet</strong> for <?= e($contest['title']) ?>.</p></section>
<?php else:
  $shown = count(array_filter($photos, fn($p) => $p['in_gallery'])); ?>
  <p class="muted" style="margin:0"><?= e($contest['title']) ?>: <?= count($photos) ?> photobooth picture<?= count($photos) === 1 ? '' : 's' ?>, <?= $shown ?> in the public gallery. Pictures people didn't add to the gallery show faded; only you can see them here.</p>
  <div class="gallery admin-gallery">
    <?php foreach ($photos as $p): ?>
      <div class="gadmin<?= $p['kind'] === 'group' ? ' wide' : '' ?>" style="<?= $p['kind'] === 'group' ? 'grid-column: span 2' : '' ?>">
        <a class="gitem<?= $p['kind'] === 'group' ? ' wide' : '' ?><?= $p['in_gallery'] ? '' : ' hidden-photo' ?>" href="<?= e(media_url($p['result_path'])) ?>" target="_blank" rel="noopener">
          <img src="<?= e(media_url($p['result_path'])) ?>" alt="<?= e($p['style']) ?>" loading="lazy">
          <span class="gcap"><?= e($p['name'] !== '' ? $p['name'] : 'No name') ?><small><?= e($p['style']) ?> · <?= e(utc_to_local($p['created_at'], 'D g:i A')) ?></small></span>
        </a>
        <div class="acts">
          <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int) $p['id'] ?>">
            <?php if ($p['in_gallery']): ?>
              <button class="btn small ghost" name="action" value="hide" type="submit">Remove from gallery</button>
            <?php else: ?>
              <button class="btn small ghost" name="action" value="show" type="submit">Add to gallery</button>
            <?php endif; ?>
          </form>
          <form class="inline" method="post" data-confirm="Delete this photo for good? Its share link will stop working."><?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int) $p['id'] ?>">
            <button class="btn small ghost danger" name="action" value="delete" type="submit">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
