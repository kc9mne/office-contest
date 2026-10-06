<?php
/** @var ?array $contest  @var string $siteUrl */
$logo = logo_url();
$mode = $contest['mode'] ?? 'general';
$noun = mode($mode)['noun'];
?><!doctype html>
<html lang="en" data-mode="<?= e($mode) ?>" style="<?= e(brand_style()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QR poster · <?= e($contest['title'] ?? company_name()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bowlby+One+SC&family=Figtree:wght@400;600;800&display=swap">
<link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>?v=8">
<style>
  /* Poster: always light, sized for US Letter / A4 portrait */
  :root { color-scheme: light; }
  body { background: #E9E4EE; color: #1A1020; }
  .toolbar { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; align-items: center; padding: 16px; font-size: 14px; }
  .sheet { width: min(8.5in, 100% - 32px); aspect-ratio: 8.5 / 11; margin: 0 auto 32px; background: #fff; border-radius: 6px; box-shadow: 0 10px 40px rgb(0 0 0 / .18);
    display: grid; grid-template-rows: auto 1fr auto; padding: 6% 7%; gap: 3%; text-align: center; overflow: hidden; container-type: inline-size; }
  .p-brand { display: flex; align-items: center; justify-content: center; gap: 2.5cqw; font-weight: 800; font-size: 3.2cqw; color: #4A3F55; }
  .p-brand .logo { width: 9cqw; height: 9cqw; border-radius: 1.6cqw; font-size: 3cqw; background: #1A1020; color: #fff; }
  .p-main { display: grid; align-content: center; justify-items: center; gap: 3cqw; }
  .p-eyebrow { font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: var(--accent); font-size: 3cqw; }
  .p-title { font-family: var(--font-display); font-weight: 400; font-size: 8.2cqw; line-height: 1; text-wrap: balance; margin: 0; }
  .p-qr { width: 52cqw; aspect-ratio: 1; padding: 0; border: 1.2cqw solid var(--accent); border-radius: 4cqw; background: #fff; }
  .p-qr svg { width: 100%; height: 100%; display: block; }
  .p-cta { font-family: var(--font-display); font-weight: 400; font-size: 6.4cqw; line-height: 1.05; margin: 0; }
  .p-sub { font-size: 3.2cqw; color: #4A3F55; margin: 0; max-width: 70cqw; }
  .p-foot { display: grid; gap: 1cqw; font-size: 2.8cqw; color: #4A3F55; }
  .p-foot b { color: #1A1020; }
  .p-url { font-family: var(--font-mono); font-size: 2.6cqw; color: #4A3F55; overflow-wrap: anywhere; }
  @media print {
    @page { size: letter portrait; margin: 0; }
    body { background: #fff; }
    .toolbar { display: none; }
    .sheet { width: 100%; height: 100vh; aspect-ratio: auto; margin: 0; border-radius: 0; box-shadow: none; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <a class="btn ghost small" href="<?= e(admin_url()) ?>">← Back to Admin</a>
    <button class="btn primary small" type="button" data-print>Print poster</button>
    <span class="muted">Prints on one Letter or A4 page. Pick "Portrait" and turn off headers and footers in the print dialog.</span>
  </div>
  <main class="sheet">
    <div class="p-brand">
      <span class="logo"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php else: ?><?= e(company_initials()) ?><?php endif; ?></span>
      <?= e(company_name()) ?>
    </div>
    <div class="p-main">
      <?php if ($contest): ?><div class="p-eyebrow"><?= e($contest['subtitle'] !== '' ? $contest['subtitle'] : mode($mode)['label']) ?></div><?php endif; ?>
      <h1 class="p-title"><?= e($contest['title'] ?? company_name() . ' contests') ?></h1>
      <div class="p-qr"><?= qr_svg($siteUrl) ?></div>
      <p class="p-cta">Scan to vote!</p>
      <p class="p-sub">Enter your <?= e($noun) ?>, vote for your favorites and see who's winning.</p>
    </div>
    <div class="p-foot">
      <?php if ($contest): ?>
        <div>Voting <b><?= e(utc_to_local($contest['starts_at'], 'D M j, g:i A')) ?></b> to <b><?= e(utc_to_local($contest['ends_at'], 'D M j, g:i A')) ?></b></div>
        <?php if ($contest['event_name'] !== ''): ?><div><?= e($contest['event_name']) ?><?= $contest['event_details'] !== '' ? ': <b>' . e($contest['event_details']) . '</b>' : '' ?></div><?php endif; ?>
      <?php endif; ?>
      <div class="p-url"><?= e(preg_replace('#^https?://#', '', $siteUrl)) ?></div>
    </div>
  </main>
  <script src="<?= e(url('/assets/app.js')) ?>?v=8"></script>
</body>
</html>
