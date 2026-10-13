<?php
/** @var ?array $contest  @var array $data */
$mode = $contest['mode'] ?? 'general';
$pageBg = contest_background_url($contest);
$siteUrl = site_origin() . url('/');
$logo = logo_url();
?><!doctype html>
<html lang="en" data-mode="<?= e($mode) ?>" class="<?= $pageBg ? 'has-bg' : '' ?>" style="<?= e(brand_style() . ($pageBg ? "--page-bg:url('{$pageBg}');" : '')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>TV · <?= e($contest['title'] ?? company_name()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bowlby+One+SC&family=Figtree:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>?v=12">
<link rel="stylesheet" href="<?= e(url('/assets/tv.css')) ?>?v=2">
<script src="<?= e(url('/assets/tv.js')) ?>?v=1" defer></script>
</head>
<body class="tv-body">
<div class="tv" id="tv" data-src="<?= e(url('/tv/data')) ?>" data-initial="<?= e(json_encode($data)) ?>">
  <header class="tv-head">
    <div class="tv-brand">
      <span class="logo"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php else: ?><?= e(company_initials()) ?><?php endif; ?></span>
      <div>
        <div class="tv-sub"><?= e(company_name()) ?><span data-field="subtitle"></span></div>
        <h1 class="tv-title" data-field="title"><?= e($contest['title'] ?? 'No contest running') ?></h1>
      </div>
    </div>
    <div class="tv-clock" data-clock hidden>
      <div class="tv-k" data-field="clockLabel"></div>
      <div class="tv-digits"><b data-unit="d">0</b><span>d</span><b data-unit="h">00</b><span>h</span><b data-unit="m">00</b><span>m</span><b data-unit="s">00</b><span>s</span></div>
    </div>
  </header>

  <main class="tv-main">
    <section class="tv-board" data-board></section>
    <section class="tv-show" data-show>
      <figure class="tv-slide"><img alt=""><figcaption></figcaption></figure>
      <figure class="tv-slide"><img alt=""><figcaption></figcaption></figure>
      <div class="tv-empty" data-show-empty hidden></div>
    </section>
  </main>

  <footer class="tv-foot">
    <div class="tv-qr"><?= qr_svg($siteUrl) ?></div>
    <div class="tv-cta">
      <b>Scan to vote<?= $contest ? ' and enter your ' . e(mode($mode)['noun']) : '' ?>!</b>
      <span class="tv-url"><?= e(preg_replace('#^https?://#', '', rtrim($siteUrl, '/'))) ?></span>
    </div>
    <div class="tv-event" data-field="event"></div>
    <button type="button" class="tv-fs" data-fullscreen aria-label="Full screen"></button>
  </footer>
</div>
</body>
</html>
