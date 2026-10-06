<?php
$noun = mode($contest['mode'])['noun'];
$depts = array_values(array_unique(array_filter(array_column($entries, 'department'))));
sort($depts);
$canVote = $phase === 'live' && $voter && !$voter['voided'];
$showGate = $phase === 'live' && (!$voter || $changingName);
?>
<div>
  <a class="small" href="<?= e(url('/')) ?>">← <?= e($contest['title']) ?></a>
  <h1 class="page-title">Vote</h1>
</div>

<?php if ($phase === 'before'): ?>
  <div class="alert">Voting opens <strong><?= e(utc_to_local($contest['starts_at'])) ?></strong>. Browse the <?= e($noun) ?>s in the meantime.</div>
<?php elseif ($phase === 'closed'): ?>
  <div class="alert">Voting has closed. <a href="<?= e(url('/')) ?>">See the results</a>.</div>
<?php elseif ($showGate): ?>
  <form class="namegate card stack" method="post" action="<?= e(url('/vote' . ($current ? '?cat=' . $current['id'] : ''))) ?>" novalidate style="gap:8px">
    <?= csrf_field() ?>
    <label for="voter_name"><b><?= $voter ? 'Change your name' : 'Enter your name to vote' ?></b></label>
    <span class="hint">So organizers can spot duplicate votes. Your name isn't shown publicly.</span>
    <div class="row" style="flex-wrap:nowrap">
      <input id="voter_name" name="voter_name" value="<?= e($voter['name'] ?? '') ?>" maxlength="80" autocomplete="name" placeholder="First and last name" required style="flex:1; min-width:0">
      <button class="btn primary" type="submit"><?= $voter ? 'Save' : 'Start voting' ?></button>
    </div>
    <?php if ($nameError): ?><span class="error" role="alert"><?= e($nameError) ?></span><?php endif; ?>
  </form>
<?php elseif ($voter && $voter['voided']): ?>
  <div class="alert">Votes from this phone aren't being counted. If that's a mistake, talk to an organizer.</div>
<?php else: ?>
  <div class="who card">
    <span>Voting as <b><?= e($voter['name']) ?></b> · <span id="pickCount"><?= count($picks) ?> of <?= count($categories) ?></span> picked</span>
    <a class="small" href="<?= e(url('/vote?name=1' . ($current ? '&cat=' . $current['id'] : ''))) ?>">Not you?</a>
  </div>
<?php endif; ?>

<?php if (!$entries): ?>
  <section class="card empty">
    <p><strong>No <?= e($noun) ?>s yet.</strong></p>
    <?php if ($phase !== 'closed'): ?><a class="btn primary" href="<?= e(url('/join')) ?>">Be the first to enter</a><?php endif; ?>
  </section>
<?php else: ?>
  <nav class="cats" aria-label="Categories">
    <?php foreach ($categories as $c): $done = isset($picks[(int) $c['id']]); ?>
      <a href="<?= e(url('/vote?cat=' . $c['id'])) ?>" <?= $current && (int) $current['id'] === (int) $c['id'] ? 'aria-current="page"' : '' ?> data-cat-tab="<?= (int) $c['id'] ?>">
        <span class="done" <?= $done ? '' : 'hidden' ?>>✓</span><?= e($c['name']) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if (count($depts) > 1): ?>
    <div class="filters" role="group" aria-label="Filter by department" data-dept-filter>
      <button type="button" data-dept="" aria-pressed="true">All</button>
      <?php foreach ($depts as $d): ?><button type="button" data-dept="<?= e($d) ?>" aria-pressed="false"><?= e($d) ?></button><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="entries" data-vote-grid data-category="<?= (int) ($current['id'] ?? 0) ?>" data-category-name="<?= e($current['name'] ?? '') ?>">
    <?php foreach ($entries as $en):
      $mine = $current && ($picks[(int) $current['id']] ?? 0) === (int) $en['id']; ?>
      <article class="entry<?= $mine ? ' mine' : '' ?>" id="entry-<?= (int) $en['id'] ?>" data-entry="<?= (int) $en['id'] ?>" data-dept="<?= e($en['department']) ?>">
        <a class="entry-photo" href="<?= e(media_url($en['photo_path'])) ?>" target="_blank" rel="noopener">
          <img src="<?= e(media_url($en['photo_path'])) ?>" alt="<?= e($en['name']) ?>" loading="lazy">
          <?php if ($en['department'] !== ''): ?><span class="tag"><?= e($en['department']) ?></span><?php endif; ?>
        </a>
        <div class="entry-meta">
          <div><div class="nm"><?= e($en['name']) ?></div><?php if ($en['title'] !== ''): ?><div class="cs"><?= e($en['title']) ?></div><?php endif; ?></div>
          <?php if ($current): ?>
          <form method="post" action="<?= e(url('/vote')) ?>" data-vote-form>
            <?= csrf_field() ?>
            <input type="hidden" name="category_id" value="<?= (int) $current['id'] ?>">
            <input type="hidden" name="entry_id" value="<?= (int) $en['id'] ?>">
            <button class="vote-btn" type="submit" <?= $canVote ? '' : 'disabled' ?>><?= $mine ? '✓ Your ' . e(mb_strtolower($current['name'])) . ' pick' : 'Vote ' . e(mb_strtolower($current['name'])) ?></button>
          </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<div class="toast" id="toast" role="status" hidden></div>
