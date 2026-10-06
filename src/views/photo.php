<section class="stack" style="max-width:560px; margin:0 auto; width:100%">
  <div>
    <div class="eyebrow"><?= e($contest['title'] ?? 'Photobooth') ?></div>
    <h1 class="page-title" style="font-size:28px"><?= $photo['name'] !== '' ? e($photo['name']) . ' as ' : '' ?><?= e($photo['style']) ?></h1>
  </div>
  <img src="<?= e(media_url($photo['result_path'])) ?>" alt="Photobooth picture: <?= e($photo['style']) ?>" style="width:100%; border-radius:16px; display:block">
  <a class="btn primary block" href="<?= e(media_url($photo['result_path'])) ?>" download="photobooth-<?= e(strtolower($photo['code'])) ?>.jpg">Save photo</a>
  <p class="hint">On a phone you can also press and hold the picture, then choose Save.</p>
</section>
