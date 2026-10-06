<!doctype html>
<html lang="en" data-mode="<?= e($mode ?? 'general') ?>" style="<?= e(brand_style()) ?>">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <?php $brandTitle = company_name(); $brandSub = null; require __DIR__ . '/partials/brand.php'; ?>
    <?php $here = request_path(); ?>
    <nav class="sitenav" aria-label="Site">
      <?php foreach (['/' => 'Home', '/vote' => 'Vote', '/join' => 'Enter', '/gallery' => 'Gallery'] as $p => $label): ?>
        <a href="<?= e(url($p)) ?>" <?= $here === $p || ($p === '/join' && $here === '/join/done') || ($p === '/gallery' && $here === '/videos') ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </header>
  <main class="stack">
    <?php if (!empty($flash)): ?><div class="flash" role="status"><?= e($flash) ?></div><?php endif; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
