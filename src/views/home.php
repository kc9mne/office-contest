<?php if (!$contest): ?>
  <section class="card empty">
    <h1 class="page-title" style="font-size:28px">No contest running right now</h1>
    <p>Check back soon.</p>
  </section>
<?php else:
  $noun = mode($contest['mode'])['noun'];
  $target = $phase === 'before' ? $contest['starts_at'] : $contest['ends_at'];
  $overall = $standings['overall'];
  $top = array_values(array_filter($overall, fn($r) => $r['votes'] > 0));
  $entryCount = count($standings['entries']);
?>
  <section class="hero">
    <div class="pills">
      <span class="chip active <?= $phase === 'live' ? 'live' : '' ?>"><?= e(phase_label($phase)) ?></span>
      <?php if ($contest['subtitle'] !== ''): ?><span class="chip"><?= e($contest['subtitle']) ?></span><?php endif; ?>
    </div>
    <h1><?= e($contest['title']) ?></h1>
  </section>

  <section class="clock" <?= $phase !== 'closed' ? 'data-countdown="' . utc_timestamp($target) * 1000 . '"' : '' ?>>
    <?php if ($phase === 'closed'): ?>
      <div class="k">Final results</div>
      <div class="digits"><div><b><?= $standings['voters'] ?></b><span>voters</span></div><div><b><?= $entryCount ?></b><span><?= e($noun) ?><?= $entryCount === 1 ? '' : 's' ?></span></div></div>
      <div class="window">Voting ended <?= e(utc_to_local($contest['ends_at'])) ?></div>
    <?php else: ?>
      <div class="k"><?= $phase === 'before' ? 'Voting opens in' : 'Voting closes in' ?></div>
      <div class="digits" aria-live="off">
        <?php $left = max(0, utc_timestamp($target) - time()); ?>
        <div><b data-unit="d"><?= intdiv($left, 86400) ?></b><span>days</span></div>
        <div><b data-unit="h"><?= sprintf('%02d', intdiv($left, 3600) % 24) ?></b><span>hrs</span></div>
        <div><b data-unit="m"><?= sprintf('%02d', intdiv($left, 60) % 60) ?></b><span>min</span></div>
        <div><b data-unit="s"><?= sprintf('%02d', $left % 60) ?></b><span>sec</span></div>
      </div>
      <div class="window"><span>Opens <strong><?= e(utc_to_local($contest['starts_at'])) ?></strong></span><span>Closes <strong><?= e(utc_to_local($contest['ends_at'])) ?></strong></span></div>
    <?php endif; ?>
  </section>

  <div class="cta-row">
    <?php if ($phase === 'live'): ?><a class="btn primary" href="<?= e(url('/vote')) ?>">Vote now</a><?php endif; ?>
    <?php if ($phase !== 'closed'): ?><a class="btn <?= $phase === 'live' ? 'ghost' : 'primary' ?>" href="<?= e(url('/join')) ?>">Enter a <?= e($noun) ?></a><?php endif; ?>
    <?php if ($phase !== 'live'): ?><a class="btn ghost" href="<?= e(url('/vote')) ?>"><?= $phase === 'closed' ? 'See every ' . e($noun) : 'Browse ' . e($noun) . 's' ?></a><?php endif; ?>
  </div>

  <?php if ($phase === 'before'): ?>
    <section class="card">
      <h2><?= $entryCount ?> <?= e($noun) ?><?= $entryCount === 1 ? '' : 's' ?> entered so far</h2>
      <p class="muted" style="margin:0">The leaderboard appears when voting opens.</p>
    </section>
  <?php elseif (!$showResults): ?>
    <section class="card">
      <h2>The leaderboard is a secret</h2>
      <p class="muted" style="margin:0">Results are revealed when voting closes on <?= e(utc_to_local($contest['ends_at'])) ?>. <?= $standings['voters'] ?> <?= $standings['voters'] === 1 ? 'person has' : 'people have' ?> voted so far.</p>
    </section>
  <?php elseif (!$top): ?>
    <section class="card">
      <h2>No votes yet</h2>
      <p class="muted" style="margin:0"><?= $phase === 'live' ? 'Be the first to vote!' : 'Nobody voted in this contest.' ?></p>
    </section>
  <?php else: ?>
    <section class="stack" style="gap:10px">
      <h2 class="section-title"><?= $phase === 'closed' ? 'Overall winners' : 'Overall top 3' ?></h2>
      <div class="podium">
        <?php
        $slots = [1 => $top[1] ?? null, 0 => $top[0] ?? null, 2 => $top[2] ?? null];
        foreach ($slots as $i => $row):
          if (!$row) { echo '<div></div>'; continue; } ?>
          <div class="pod <?= ['first', 'second', 'third'][$i] ?>">
            <img src="<?= e(media_url($row['entry']['photo_path'])) ?>" alt="<?= e($row['entry']['name']) ?>" loading="lazy">
            <div class="medal"><?= e(place_label($row)) ?></div>
            <div class="nm"><?= e($row['entry']['name']) ?></div>
            <?php if ($row['entry']['title'] !== ''): ?><div class="cs"><?= e($row['entry']['title']) ?></div><?php endif; ?>
            <div class="vt"><?= $row['votes'] ?> vote<?= $row['votes'] === 1 ? '' : 's' ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="hint" style="margin:0">Overall is the total of each <?= e($noun) ?>'s votes across all categories.</p>
    </section>

    <section class="stack" style="gap:10px">
      <h2 class="section-title"><?= $phase === 'closed' ? 'Category winners' : 'Leading each category' ?></h2>
      <div class="leaders card" style="padding:4px 0">
        <?php foreach ($standings['categories'] as $cat):
          $leaders = ranked_leaders($cat['ranked']); ?>
          <a class="leader" href="<?= e(url('/vote?cat=' . $cat['category']['id'])) ?>">
            <?php if ($leaders): ?>
              <img src="<?= e(media_url($leaders[0]['entry']['photo_path'])) ?>" alt="" loading="lazy">
              <span><span class="c"><?= e($cat['category']['name']) ?><?= count($leaders) > 1 ? ' · Tie' : '' ?></span>
                <b><?= e(implode(' & ', array_map(fn($r) => $r['entry']['name'], $leaders))) ?></b></span>
              <span class="v"><?= $leaders[0]['votes'] ?></span>
            <?php else: ?>
              <span class="ph" aria-hidden="true"></span>
              <span><span class="c"><?= e($cat['category']['name']) ?></span><b class="muted">No votes yet</b></span>
              <span></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <nav class="home-links" aria-label="More">
    <a class="card home-link" href="<?= e(url('/gallery')) ?>">Gallery <span class="muted"><?= $galleryCount ? $galleryCount . ' photo' . ($galleryCount === 1 ? '' : 's') . ' →' : 'Coming soon →' ?></span></a>
    <?php if ($contest['event_name'] !== ''): ?>
      <a class="card home-link" href="<?= e(url('/videos')) ?>" style="display:grid; gap:2px"><span class="eyebrow"><?= e($contest['event_name']) ?></span><span><?= e($contest['event_details'] !== '' ? $contest['event_details'] : 'Details coming soon') ?></span><span class="muted"><?= $videoCount ? 'Watch ' . $videoCount . ' video' . ($videoCount === 1 ? '' : 's') . ' →' : 'Videos will be posted here →' ?></span></a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
