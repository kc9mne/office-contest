<?php if ($justInstalled): ?>
<section class="alert stack" style="gap:8px">
  <div><strong>Setup is done. Bookmark your admin link now.</strong> You need this link plus your PIN to get back in. Anyone without the link just sees a "page not found".</div>
  <div class="copy"><code id="adminLink"><?= e($adminLink) ?></code><button class="btn small ghost" type="button" data-copy="adminLink">Copy</button></div>
</section>
<?php endif; ?>

<div class="spread">
  <h1 class="page-title">Contests</h1>
  <div class="row">
    <a class="btn ghost" href="<?= e(admin_url('poster')) ?>">Print QR poster</a>
    <a class="btn primary" href="<?= e(admin_url('contests/new')) ?>">+ New contest</a>
  </div>
</div>

<?php if (!$contests): ?>
  <section class="card empty">
    <p><strong>No contests yet.</strong></p>
    <p>Create one, pick Halloween, Holiday party or General, and make it live when you're ready.</p>
    <a class="btn primary" href="<?= e(admin_url('contests/new')) ?>">Create your first contest</a>
  </section>
<?php else: ?>
  <section class="card" style="padding:6px 8px">
    <div class="table-wrap">
      <table class="contests">
        <thead><tr><th>Contest</th><th>Voting</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($contests as $c): $phase = contest_phase($c); ?>
          <tr>
            <td>
              <div class="title"><?= e($c['title']) ?></div>
              <div class="small muted"><?= e(mode($c['mode'])['label']) ?></div>
            </td>
            <td class="small">
              <?= e(utc_to_local($c['starts_at'])) ?><br>
              <span class="muted">to <?= e(utc_to_local($c['ends_at'])) ?></span>
            </td>
            <td>
              <?php if ($c['status'] === 'active'): ?>
                <span class="chip active <?= $phase === 'live' ? 'live' : '' ?>">On home page · <?= e(phase_label($phase)) ?></span>
              <?php elseif ($c['status'] === 'archived'): ?>
                <span class="chip">Archived</span>
              <?php else: ?>
                <span class="chip">Draft</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="acts">
                <a class="btn small ghost" href="<?= e(admin_url('contests/' . $c['id'])) ?>">Edit</a>
                <?php if ($c['status'] !== 'active'): ?>
                  <form class="inline" method="post" action="<?= e(admin_url('contests/' . $c['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="active"><button class="btn small primary" type="submit">Make live</button></form>
                <?php else: ?>
                  <form class="inline" method="post" action="<?= e(admin_url('contests/' . $c['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="draft"><button class="btn small ghost" type="submit">Take off home page</button></form>
                <?php endif; ?>
                <?php if ($c['status'] !== 'archived'): ?>
                  <form class="inline" method="post" action="<?= e(admin_url('contests/' . $c['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="archived"><button class="btn small ghost" type="submit">Archive</button></form>
                <?php else: ?>
                  <form class="inline" method="post" action="<?= e(admin_url('contests/' . $c['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="draft"><button class="btn small ghost" type="submit">Restore</button></form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <p class="hint">Only one contest is on the home page at a time. Making a contest live takes the previous one off.</p>
<?php endif; ?>
