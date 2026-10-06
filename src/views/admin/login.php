<section class="stack">
  <?php $brandTitle = company_name(); $brandSub = 'Contest admin'; require APP_ROOT . '/src/views/partials/brand.php'; ?>
  <form class="card stack" method="post" novalidate>
    <?= csrf_field() ?>
    <h1 style="font-size:22px">Enter your admin PIN</h1>
    <div class="field <?= $error ? 'has-error' : '' ?>">
      <label for="pin">PIN</label>
      <input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="current-password" autofocus required>
      <?php if ($error): ?><span class="error" role="alert"><?= e($error) ?></span><?php endif; ?>
    </div>
    <button class="btn primary block" type="submit">Sign in</button>
  </form>
</section>
