<?php
$current = request_path();
$nav = [
    admin_base() => 'Contests',
    admin_base() . '/entries' => 'Entries',
    admin_base() . '/voters' => 'Voters',
    admin_base() . '/gallery' => 'Gallery',
    admin_base() . '/settings' => 'Site settings',
];
?><!doctype html>
<html lang="en" data-mode="admin" style="<?= e(brand_style()) ?>">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<div class="wrap">
  <header class="adminbar">
    <?php $brandTitle = company_name(); $brandSub = 'Contest admin'; require __DIR__ . '/partials/brand.php'; ?>
    <nav class="adminnav" aria-label="Admin">
      <?php foreach ($nav as $path => $label): ?>
        <a href="<?= e(url($path)) ?>" <?= ($current === $path || ($path === admin_base() && str_starts_with($current, admin_base() . '/contests'))) ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View site ↗</a>
      <form class="inline" method="post" action="<?= e(admin_url('logout')) ?>"><?= csrf_field() ?><button class="btn link" type="submit" style="padding:6px 10px">Sign out</button></form>
    </nav>
  </header>
  <main class="stack">
    <?php if (!empty($flash)): ?><div class="flash" role="status"><?= e($flash) ?></div><?php endif; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
