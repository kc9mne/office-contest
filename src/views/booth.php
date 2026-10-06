<?php
/** @var ?array $contest  @var array $config  @var string $state */
$config['qr'] = url('/qr');
$title = 'Photobooth';
$mode = $contest['mode'] ?? 'general';
?><!doctype html>
<html lang="en" data-mode="<?= e($mode) ?>" style="<?= e(brand_style()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Photobooth · <?= e(company_name()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bowlby+One+SC&family=Figtree:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>?v=2">
<link rel="stylesheet" href="<?= e(url('/assets/booth.css')) ?>?v=2">
<script src="<?= e(url('/assets/booth.js')) ?>?v=2" defer></script>
</head>
<body class="booth-body">
<div class="kiosk" id="kiosk" data-state="<?= e($state) ?>" data-config="<?= e(json_encode($config)) ?>">
  <header class="khead">
    <?php $brandTitle = $contest['title'] ?? company_name(); $brandSub = 'Photobooth · ' . company_name(); require APP_ROOT . '/src/views/partials/brand.php'; ?>
    <div class="ktools">
      <?php if ($state === 'ready' && $config['demo']): ?><span class="kdemo" title="Add OPENAI_API_KEY to the server's .env file to use real AI">Demo mode · no AI key</span><?php endif; ?>
      <button type="button" class="kicon" id="camBtn" aria-label="Camera settings" <?= $state !== 'ready' ? 'hidden' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
      </button>
      <button type="button" class="kicon" id="fsBtn" aria-label="Full screen">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
      </button>
    </div>
  </header>

  <?php if ($state !== 'ready'): ?>
    <main class="koff">
      <?php if ($state === 'no_contest'): ?>
        <b>No contest is live right now.</b>
        <span>Make a contest live in Admin, then reload this page.</span>
      <?php else: ?>
        <b>The photobooth is off for this contest.</b>
        <span>Turn on Photobooth in the contest's settings in Admin, then reload this page.</span>
      <?php endif; ?>
    </main>
  <?php else: ?>
    <main class="kbody">
      <div class="camwrap">
        <div class="cam" id="cam">
          <video id="video" autoplay playsinline muted></video>
          <img id="still" alt="Your photo" hidden>
          <span class="kchip rec" id="camChip">Live camera</span>
          <div class="count" id="count" aria-live="assertive" hidden></div>
          <div class="shimmer" id="shimmer" hidden></div>
          <div class="flash" id="flash" hidden></div>
          <div class="camerr" id="camErr" hidden>
            <b id="camErrTitle">Camera not available</b>
            <span id="camErrText"></span>
            <button type="button" class="kbtn ghost" id="camRetry">Try again</button>
          </div>
        </div>
        <div class="split" id="split" hidden>
          <figure><img id="beforeImg" alt="Before"><figcaption>Before</figcaption></figure>
          <figure><img id="afterImg" alt="After"><figcaption id="afterCap">After</figcaption></figure>
        </div>
      </div>
      <div class="kside" id="side" aria-live="polite"></div>
    </main>

    <dialog class="kdialog" id="camDialog">
      <form method="dialog" class="stack">
        <h2>Camera</h2>
        <div class="field">
          <label for="camSelect">Use this camera</label>
          <select id="camSelect"></select>
          <span class="hint">Saved on this device.</span>
        </div>
        <button class="kbtn primary" value="close">Done</button>
      </form>
    </dialog>
  <?php endif; ?>
</div>
</body>
</html>
