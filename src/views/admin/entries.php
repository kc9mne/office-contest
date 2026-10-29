<?php
$pending = $entries ? count(array_filter($entries, fn($e) => $e['status'] === 'pending')) : 0;
$totals = [];
if ($standings) {
    foreach ($standings['overall'] as $row) {
        $totals[(int) $row['entry']['id']] = $row['votes'];
    }
}
?>
<div class="spread">
  <h1 class="page-title">Entries</h1>
  <?php if ($contest): ?><a class="btn ghost" href="<?= e(url('/vote')) ?>" target="_blank" rel="noopener">View voting page ↗</a><?php endif; ?>
</div>

<?php if (!$contest): ?>
  <section class="card empty"><p>Make a contest live to see its entries here.</p></section>
<?php else: ?>

  <?php if ($standings && $standings['totalVotes'] > 0): ?>
  <section class="card stack" style="gap:12px">
    <div class="spread"><h2>Results so far</h2><span class="muted small"><?= $standings['voters'] ?> voter<?= $standings['voters'] === 1 ? '' : 's' ?> · <?= $standings['totalVotes'] ?> votes</span></div>
    <div class="table-wrap">
      <table class="contests">
        <thead><tr><th>Category</th><th>Leading</th><th>Votes</th><th>Runner-up</th></tr></thead>
        <tbody>
          <?php $overallLeaders = ranked_leaders($standings['overall']); $second = array_values(array_filter($standings['overall'], fn($r) => $r['votes'] > 0 && $r['place'] > 1))[0] ?? null; ?>
          <tr>
            <td><strong>Overall</strong></td>
            <td><?= e(implode(' & ', array_map(fn($r) => $r['entry']['name'], $overallLeaders)) ?: '—') ?><?= count($overallLeaders) > 1 ? ' <span class="chip">Tie</span>' : '' ?></td>
            <td class="mono"><?= $overallLeaders[0]['votes'] ?? 0 ?></td>
            <td class="small muted"><?= $second ? e($second['entry']['name']) . ' (' . $second['votes'] . ')' : '—' ?></td>
          </tr>
          <?php foreach ($standings['categories'] as $cat):
            $leaders = ranked_leaders($cat['ranked']);
            $next = array_values(array_filter($cat['ranked'], fn($r) => $r['votes'] > 0 && $r['place'] > 1))[0] ?? null; ?>
            <tr>
              <td><?= e($cat['category']['name']) ?></td>
              <td><?= e(implode(' & ', array_map(fn($r) => $r['entry']['name'], $leaders)) ?: '—') ?><?= count($leaders) > 1 ? ' <span class="chip">Tie</span>' : '' ?></td>
              <td class="mono"><?= $leaders[0]['votes'] ?? 0 ?></td>
              <td class="small muted"><?= $next ? e($next['entry']['name']) . ' (' . $next['votes'] . ')' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($pending): ?><div class="alert"><strong><?= $pending ?> entr<?= $pending === 1 ? 'y is' : 'ies are' ?> waiting for approval.</strong> They're at the top of the list.</div><?php endif; ?>

  <?php if (!$entries): ?>
    <section class="card empty"><p><strong>No entries yet.</strong> People enter from the Enter page on the site.</p></section>
  <?php else: ?>
    <section class="card" style="padding:6px 8px">
      <div class="table-wrap">
        <table class="contests">
          <thead><tr><th>Entry</th><th>Status</th><th>Votes</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
          <?php foreach ($entries as $en): ?>
            <tr>
              <td>
                <div class="row" style="flex-wrap:nowrap; gap:12px">
                  <a href="<?= e(media_url($en['photo_path'])) ?>" target="_blank" rel="noopener"><img src="<?= e(media_url($en['photo_path'])) ?>" alt="" style="width:52px; height:64px; object-fit:cover; border-radius:8px; display:block"></a>
                  <div style="min-width:0">
                    <div class="title"><?= e($en['name']) ?></div>
                    <div class="small muted"><?= e(implode(' · ', array_filter([$en['title'], $en['department'], utc_to_local($en['created_at'], 'D g:i A')]))) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="chip <?= $en['status'] === 'approved' ? 'ok' : ($en['status'] === 'pending' ? 'active' : '') ?>"><?= e(['approved' => 'Showing', 'pending' => 'Waiting', 'rejected' => 'Hidden'][$en['status']] ?? $en['status']) ?></span></td>
              <td class="mono votes-cell"><?php $bl = $ballots[(int) $en['id']] ?? []; $n = $totals[(int) $en['id']] ?? 0; ?>
                <?php if (!$bl): ?><?= $n ?><?php else: ?>
                  <details class="vote-list">
                    <summary title="See who voted"><?= $n ?><?= count(array_filter($bl, fn($b) => in_array($b['voter']['flag_level'], ['high', 'medium'], true))) ? ' <span class="chip flag-medium">check</span>' : '' ?></summary>
                    <div class="vote-pop">
                      <div class="spread"><b><?= e($en['name']) ?>: <?= count($bl) ?> vote<?= count($bl) === 1 ? '' : 's' ?></b><button type="button" class="btn small ghost" data-close-details>Close</button></div>
                      <ul>
                        <?php foreach ($bl as $b): $vt = $b['voter']; ?>
                          <li class="<?= $vt['voided'] ? 'voided' : '' ?>">
                            <a href="<?= e(admin_url('voters') . (function_exists('admin_cq') ? admin_cq($contest) : '')) ?>#voter-<?= (int) $vt['id'] ?>"><?= e($vt['name']) ?></a>
                            <span class="muted"><?= e($b['category']) ?> · <?= e(utc_to_local($b['at'], 'g:i A')) ?></span>
                            <?php if ($vt['voided']): ?><span class="chip">Not counted</span><?php endif; ?>
                            <?php foreach ($vt['flags'] as [$level, $text]): if ($level === 'info') continue; ?><span class="chip flag-<?= e($level) ?>"><?= e($text) ?></span><?php endforeach; ?>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                      <span class="small muted">Tap a name to see that voter. Flags come from the Voters page.</span>
                    </div>
                  </details>
                <?php endif; ?>
              </td>
              <td>
                <form class="acts" method="post" data-confirm-delete="Delete <?= e($en['name']) ?>'s entry and all its votes? This can't be undone.">
                  <?= csrf_field() ?><input type="hidden" name="entry_id" value="<?= (int) $en['id'] ?>">
                  <?php if ($en['status'] !== 'approved'): ?><button class="btn small primary" name="action" value="approve" type="submit"><?= $en['status'] === 'pending' ? 'Approve' : 'Show' ?></button><?php endif; ?>
                  <?php if ($en['status'] !== 'rejected'): ?><button class="btn small ghost" name="action" value="reject" type="submit">Hide</button><?php endif; ?>
                  <button class="btn small ghost danger" name="action" value="delete" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <p class="hint">Hiding keeps the entry and its votes but takes it off the voting page and gallery. Deleting removes both for good.</p>
  <?php endif; ?>
<?php endif; ?>
