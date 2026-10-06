<?php
$flagged = array_values(array_filter($voters, fn($v) => $v['flag_level'] === 'high' || $v['flag_level'] === 'medium'));
$shown = $onlyFlagged ? $flagged : $voters;
$voided = count(array_filter($voters, fn($v) => $v['voided']));
$levelChip = ['high' => 'flag-high', 'medium' => 'flag-medium', 'info' => 'flag-info'];
?>
<div class="spread">
  <h1 class="page-title">Voters</h1>
  <?php if ($contest): ?>
    <div class="row">
      <a class="btn ghost small" href="<?= e(admin_url('contests/' . $contest['id'] . '/export/voters.csv')) ?>">Download voter list</a>
      <a class="btn ghost small" href="<?= e(admin_url('contests/' . $contest['id'] . '/export/votes.csv')) ?>">Download every vote</a>
      <a class="btn ghost small" href="<?= e(admin_url('contests/' . $contest['id'] . '/export/results.csv')) ?>">Download results</a>
    </div>
  <?php endif; ?>
</div>

<?php if (!$contest): ?>
  <section class="card empty"><p>Make a contest live to see its voters here.</p></section>
<?php elseif (!$voters): ?>
  <section class="card empty"><p><strong>Nobody has voted yet.</strong> Voters show up here as soon as they type their name on the Vote page.</p></section>
<?php else: ?>
  <section class="stats">
    <div class="card"><span class="eyebrow">Voters</span><b><?= count($voters) ?></b></div>
    <div class="card"><span class="eyebrow">Worth a look</span><b class="<?= $flagged ? 'warn-text' : '' ?>"><?= count($flagged) ?></b></div>
    <div class="card"><span class="eyebrow">Not counted</span><b><?= $voided ?></b></div>
  </section>

  <p class="hint" style="margin:0">Flags point out votes that might be from the same person. They're hints, not proof: coworkers can share a name, and everyone on the office Wi-Fi often shares one network address. Voiding a voter stops their votes counting; you can restore them any time.</p>

  <div class="row">
    <a class="btn small <?= $onlyFlagged ? 'ghost' : 'primary' ?>" href="<?= e(admin_url('voters')) ?>">Everyone (<?= count($voters) ?>)</a>
    <a class="btn small <?= $onlyFlagged ? 'primary' : 'ghost' ?>" href="<?= e(admin_url('voters?flagged=1')) ?>">Worth a look (<?= count($flagged) ?>)</a>
  </div>

  <?php if (!$shown): ?>
    <section class="card empty"><p>Nothing flagged right now.</p></section>
  <?php else: ?>
  <section class="card" style="padding:6px 8px">
    <div class="table-wrap">
      <table class="contests voters">
        <thead><tr><th>Voter</th><th>Device</th><th>Picks</th><th>Flags</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($shown as $v): ?>
          <tr id="voter-<?= (int) $v['id'] ?>" class="<?= $v['voided'] ? 'voided' : '' ?>">
            <td>
              <div class="title"><?= e($v['name']) ?></div>
              <div class="small muted"><?= $v['last_vote'] ? 'Last voted ' . e(utc_to_local($v['last_vote'], 'D g:i A')) : 'Hasn’t voted yet' ?></div>
            </td>
            <td class="small">
              <span class="mono"><?= e(device_label($v['device_id'])) ?></span> · <?= e(browser_label($v['user_agent'])) ?><br>
              <span class="muted mono"><?= e($v['ip']) ?></span>
            </td>
            <td class="mono"><?= (int) $v['picks'] ?></td>
            <td>
              <div class="flags">
                <?php if ($v['voided']): ?><span class="chip">Not counted</span><?php endif; ?>
                <?php foreach ($v['flags'] as [$level, $text]): ?><span class="chip <?= $levelChip[$level] ?>"><?= e($text) ?></span><?php endforeach; ?>
                <?php if (!$v['flags'] && !$v['voided']): ?><span class="muted small">—</span><?php endif; ?>
              </div>
            </td>
            <td>
              <form class="acts" method="post" action="<?= e(admin_url('voters' . ($onlyFlagged ? '?flagged=1' : ''))) ?>">
                <?= csrf_field() ?><input type="hidden" name="voter_id" value="<?= (int) $v['id'] ?>">
                <?php if ($v['voided']): ?>
                  <button class="btn small ghost" name="action" value="restore" type="submit">Count again</button>
                <?php else: ?>
                  <button class="btn small ghost danger" name="action" value="void" type="submit">Don't count</button>
                <?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <p class="hint">Device is a short code for each browser. A new code for the same person usually means they used private browsing, cleared their cookies, or switched phones.</p>
  <?php endif; ?>
<?php endif; ?>
