<!doctype html>
<html lang="en" data-mode="<?= e($mode ?? 'admin') ?>" style="<?= e(brand_style()) ?>">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<div class="wrap narrow">
  <main class="stack">
    <?php if (!empty($flash)): ?><div class="flash" role="status"><?= e($flash) ?></div><?php endif; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
