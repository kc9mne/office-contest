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

<?php if ($contest): ?>
<section class="stack" id="videos" style="gap:12px">
  <div class="spread">
    <h2 class="section-title">Videos</h2>
    <a class="btn primary small" href="<?= e(url('/videos#add')) ?>" target="_blank" rel="noopener">Add a video</a>
  </div>
  <?php if (!$videos): ?>
    <section class="card empty"><p>No videos yet. Admins can add them from the Videos page while signed in.</p></section>
  <?php else: ?>
  <section class="card" style="padding:6px 8px">
    <div class="table-wrap">
      <table class="contests">
        <thead><tr><th>Video</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($videos as $v): ?>
          <tr>
            <td>
              <div class="row" style="flex-wrap:nowrap; gap:12px">
                <?php if ($v['kind'] === 'youtube'): ?>
                  <img src="https://i.ytimg.com/vi/<?= e($v['youtube_id']) ?>/default.jpg" alt="" style="width:80px; aspect-ratio:16/9; object-fit:cover; border-radius:6px">
                <?php elseif ($v['poster_path']): ?>
                  <img src="<?= e(media_url($v['poster_path'])) ?>" alt="" style="width:80px; aspect-ratio:16/9; object-fit:cover; border-radius:6px">
                <?php else: ?>
                  <span style="width:80px; aspect-ratio:16/9; border-radius:6px; background:var(--surface-2); display:block"></span>
                <?php endif; ?>
                <div style="min-width:0">
                  <div class="title"><?= e($v['title'] !== '' ? $v['title'] : 'Untitled video') ?></div>
                  <div class="small muted"><?= e(implode(' · ', array_filter([$v['kind'] === 'youtube' ? 'YouTube' : 'Upload, ' . round($v['size_bytes'] / 1048576) . ' MB', format_duration($v['duration_sec'] !== null ? (int) $v['duration_sec'] : null), $v['posted_by'] !== '' ? 'by ' . $v['posted_by'] : '', utc_to_local($v['created_at'], 'D g:i A')]))) ?></div>
                  <?php if ($v['status'] === 'failed'): ?><div class="error"><?= e($v['error']) ?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <?php if ($v['status'] === 'processing'): ?><span class="chip active live">Converting</span>
              <?php elseif ($v['status'] === 'failed'): ?><span class="chip flag-high">Failed</span>
              <?php elseif ($v['visible']): ?><span class="chip ok">Showing</span>
              <?php else: ?><span class="chip">Hidden</span><?php endif; ?>
            </td>
            <td>
              <form class="acts" method="post" data-confirm-delete="Delete this video for good?">
                <?= csrf_field() ?><input type="hidden" name="video_id" value="<?= (int) $v['id'] ?>">
                <?php if ($v['status'] === 'ready'): ?>
                  <?php if ($v['visible']): ?><button class="btn small ghost" name="action" value="hide" type="submit">Hide</button>
                  <?php else: ?><button class="btn small primary" name="action" value="show" type="submit">Show</button><?php endif; ?>
                <?php endif; ?>
                <button class="btn small ghost danger" name="action" value="delete" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>
</section>
<?php endif; ?>
