<section class="stack" style="max-width:560px; margin:0 auto; width:100%">
  <div>
    <div class="eyebrow"><?= e($contest['title'] ?? 'Photobooth') ?></div>
    <?php
      $who = $photo['name'];
      $heading = match ($photo['kind']) {
          'boomerang' => ($who !== '' ? "{$who} · " : '') . 'Boomerang',
          'strip' => ($who !== '' ? "{$who}'s photo strip" : 'Photo strip') . ($photo['style'] !== 'No AI' ? " · {$photo['style']}" : ''),
          default => ($who !== '' ? "{$who} as " : '') . $photo['style'],
      };
    ?>
    <h1 class="page-title" style="font-size:28px"><?= e($heading) ?></h1>
  </div>
  <?php $p = $photo; $mediaAttrs = 'style="width:100%; border-radius:16px; display:block"'; require APP_ROOT . '/src/views/partials/booth_media.php'; $isVideo = booth_photo_is_video($photo); ?>
  <a class="btn primary block" href="<?= e(media_url($photo['result_path'])) ?>" download="photobooth-<?= e(strtolower($photo['code'])) ?>.<?= $isVideo ? 'mp4' : 'jpg' ?>"><?= $isVideo ? 'Save video' : 'Save photo' ?></a>
  <p class="hint">On a phone you can also press and hold the <?= $isVideo ? 'video' : 'picture' ?>, then choose Save.</p>
</section>
