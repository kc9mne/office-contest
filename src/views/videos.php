<?php
$err = fn($k) => isset($errors[$k]) ? '<span class="error" role="alert">' . e($errors[$k]) . '</span>' : '';
$eventName = $contest['event_name'] !== '' ? $contest['event_name'] : 'Videos';
?>
<div>
  <a class="small" href="<?= e(url('/')) ?>">← <?= e($contest['title']) ?></a>
  <h1 class="page-title">Gallery</h1>
</div>
<nav class="subtabs" aria-label="Gallery sections">
  <a href="<?= e(url('/gallery')) ?>">Photos</a>
  <a href="<?= e(url('/videos')) ?>" aria-current="page">Videos<?= $videos ? ' (' . count($videos) . ')' : '' ?></a>
</nav>

<?php if ($contest['event_name'] !== ''): ?>
  <div class="alert"><strong><?= e($eventName) ?></strong><?= $contest['event_details'] !== '' ? ' · ' . e($contest['event_details']) : '' ?></div>
<?php endif; ?>

<?php if ($processing): ?>
  <div class="flash" role="status">Your video is being prepared. It shows up here in a few minutes; this page refreshes itself.</div>
  <span data-refresh-in="20" hidden></span>
<?php endif; ?>

<?php if (!$videos): ?>
  <section class="card empty"><p><strong>No videos yet.</strong></p><p><?= $canPost ? 'Add the first one below.' : 'Organizers will post recordings here.' ?></p></section>
<?php else: ?>
  <div class="videos">
    <?php foreach ($videos as $v): ?>
      <article class="video card">
        <?php if ($v['kind'] === 'youtube'): ?>
          <button type="button" class="yt" data-youtube="<?= e($v['youtube_id']) ?>" aria-label="Play <?= e($v['title'] ?: 'video') ?> from YouTube">
            <img src="https://i.ytimg.com/vi/<?= e($v['youtube_id']) ?>/hqdefault.jpg" alt="" loading="lazy">
            <span class="play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
          </button>
        <?php else: ?>
          <video controls playsinline preload="metadata" <?= $v['poster_path'] ? 'poster="' . e(media_url($v['poster_path'])) . '"' : '' ?>>
            <source src="<?= e(media_url($v['video_path'])) ?>" type="video/mp4">
          </video>
        <?php endif; ?>
        <div class="video-meta">
          <span class="src-chip"><?= $v['kind'] === 'youtube' ? 'YouTube' : 'Uploaded' ?><?= $v['duration_sec'] ? ' · ' . e(format_duration((int) $v['duration_sec'])) : '' ?></span>
          <b><?= e($v['title'] !== '' ? $v['title'] : $eventName) ?></b>
          <?php if ($v['posted_by'] !== ''): ?><span class="muted small">Posted by <?= e($v['posted_by']) ?></span><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($canPost): ?>
<section class="card" id="add">
  <h2>Add a video</h2>
  <?php if (isset($errors['form'])): ?><div class="alert" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
  <div class="segbar" role="tablist" data-video-tabs>
    <button type="button" role="tab" data-kind="upload" aria-selected="<?= $form['kind'] === 'upload' ? 'true' : 'false' ?>">Upload from phone</button>
    <button type="button" role="tab" data-kind="youtube" aria-selected="<?= $form['kind'] === 'youtube' ? 'true' : 'false' ?>">YouTube link</button>
  </div>
  <form class="stack" method="post" action="<?= e(url('/videos')) ?>" enctype="multipart/form-data" novalidate data-video-form>
    <?= csrf_field() ?>
    <input type="hidden" name="kind" value="<?= e($form['kind']) ?>">
    <div class="field <?= isset($errors['video']) ? 'has-error' : '' ?>" data-kind-panel="upload" <?= $form['kind'] === 'upload' ? '' : 'hidden' ?>>
      <label for="video">Video file</label>
      <input id="video" name="video" type="file" accept="video/*">
      <span class="hint">Up to <?= VIDEO_MAX_MB ?> MB, about 5 minutes of phone video. Bigger? Post it to YouTube and paste the link instead.</span>
      <?= $err('video') ?>
    </div>
    <div class="field <?= isset($errors['youtube_url']) ? 'has-error' : '' ?>" data-kind-panel="youtube" <?= $form['kind'] === 'youtube' ? '' : 'hidden' ?>>
      <label for="youtube_url">YouTube link</label>
      <input id="youtube_url" name="youtube_url" value="<?= e($form['youtube_url']) ?>" inputmode="url" placeholder="https://youtu.be/…">
      <span class="hint">Public or unlisted videos work. Private ones won't play.</span>
      <?= $err('youtube_url') ?>
    </div>
    <div class="grid2">
      <div class="field"><label for="title">Caption <span class="hint">(optional)</span></label><input id="title" name="title" value="<?= e($form['title']) ?>" maxlength="120" placeholder="e.g. Sales department walk-on"></div>
      <div class="field"><label for="posted_by">Your name <span class="hint">(optional)</span></label><input id="posted_by" name="posted_by" value="<?= e($form['posted_by']) ?>" maxlength="80" autocomplete="name"></div>
    </div>
    <div class="progress" hidden data-progress><div class="bar"><span></span></div><span class="small muted" data-progress-text>Uploading…</span></div>
    <div><button class="btn primary" type="submit">Add video</button></div>
  </form>
</section>
<?php endif; ?>
