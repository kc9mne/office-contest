<section class="card stack" style="text-align:center; justify-items:center">
  <img src="<?= e(media_url($entry['photo_path'])) ?>" alt="Your entry" style="width:min(240px, 70%); aspect-ratio: 4/5; object-fit:cover; border-radius:14px">
  <?php if ($entry['status'] === 'pending'): ?>
    <h1 class="page-title" style="font-size:30px">Entry sent!</h1>
    <p class="muted" style="margin:0">An organizer will approve it shortly. It shows up on the voting page once it's approved.</p>
  <?php else: ?>
    <h1 class="page-title" style="font-size:30px">You're in!</h1>
    <p class="muted" style="margin:0"><?= e($entry['name']) ?><?= $entry['title'] !== '' ? ' as ' . e($entry['title']) : '' ?> is now on the voting page.</p>
  <?php endif; ?>
  <div class="cta-row" style="width:100%">
    <?php if ($phase === 'live'): ?><a class="btn primary" href="<?= e(url('/vote')) ?>">Go vote</a><?php endif; ?>
    <a class="btn ghost" href="<?= e(url('/')) ?>">Back to home</a>
  </div>
</section>
