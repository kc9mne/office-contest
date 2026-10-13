<?php
/** @var array $p  booth_photos row. Optional $mediaAttrs (string) for extra attributes. */
$alt = ($p['name'] !== '' ? $p['name'] . ' as ' : '') . $p['style'];
if (booth_photo_is_video($p)): ?>
<video src="<?= e(media_url($p['result_path'])) ?>" poster="<?= e(media_url($p['original_path'])) ?>" autoplay muted loop playsinline preload="metadata" aria-label="<?= e($alt) ?>" <?= $mediaAttrs ?? '' ?>></video>
<?php else: ?>
<img src="<?= e(media_url($p['result_path'])) ?>" alt="<?= e($alt) ?>" loading="lazy" <?= $mediaAttrs ?? '' ?>>
<?php endif;
