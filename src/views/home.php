<?php if (!$contest): ?>
  <section class="card empty">
    <h1 class="page-title" style="font-size:28px">No contest running right now</h1>
    <p>Check back soon.</p>
  </section>
<?php else: $phase = contest_phase($contest); ?>
  <section class="hero">
    <div class="pills">
      <span class="chip active <?= $phase === 'live' ? 'live' : '' ?>"><?= e(phase_label($phase)) ?></span>
      <?php if ($contest['subtitle'] !== ''): ?><span class="chip"><?= e($contest['subtitle']) ?></span><?php endif; ?>
    </div>
    <h1><?= e($contest['title']) ?></h1>
  </section>
  <section class="when">
    <div class="card"><span class="eyebrow">Voting opens</span><b><?= e(utc_to_local($contest['starts_at'])) ?></b></div>
    <div class="card"><span class="eyebrow">Voting closes</span><b><?= e(utc_to_local($contest['ends_at'])) ?></b></div>
    <?php if ($contest['event_name'] !== ''): ?>
      <div class="card"><span class="eyebrow"><?= e($contest['event_name']) ?></span><b><?= e($contest['event_details'] !== '' ? $contest['event_details'] : 'Details coming soon') ?></b></div>
    <?php endif; ?>
  </section>
  <nav class="home-links" aria-label="Contest pages">
    <a class="card home-link" href="<?= e(url('/gallery')) ?>">Gallery <span class="muted"><?= $galleryCount ? $galleryCount . ' photo' . ($galleryCount === 1 ? '' : 's') . ' →' : 'Coming soon →' ?></span></a>
  </nav>
  <section class="card">
    <span class="eyebrow">Categories</span>
    <div class="pills" style="margin-top:8px">
      <?php foreach ($categories as $cat): ?><span class="chip"><?= e($cat['name']) ?></span><?php endforeach; ?>
    </div>
  </section>
  <p class="hint">Entering and voting open in the next version of this site.</p>
<?php endif; ?>
