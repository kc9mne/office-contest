<?php
/** Logo (or initials) plus company name. Optional $sub line under the name. */
$logo = logo_url();
?>
<a class="brandrow" href="<?= e(url('/')) ?>">
  <span class="logo"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt="<?= e(company_name()) ?> logo"><?php else: ?><?= e(company_initials()) ?><?php endif; ?></span>
  <span class="brand-name"><?= e($brandTitle ?? company_name()) ?><?php if (!empty($brandSub)): ?><small><?= e($brandSub) ?></small><?php endif; ?></span>
</a>
