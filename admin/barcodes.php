<?php
/** Barcodes — every barcode the system knows, and a setup mode for teaching many at once. */
require __DIR__.'/inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    if ($a === 'delete') {
        q("DELETE FROM barcodes WHERE id=?", [(int)$_POST['id']]);
        flash('Barcode removed. It will ask to be linked again the next time it is scanned.');
    }
    if ($a === 'add') {
        $art = resolve_article($_POST['article_id'] ?? 0, $_POST['article_name'] ?? '');
        try {
            if (!$art) throw new InvalidArgumentException('Pick the article this barcode belongs to.');
            $existing = find_barcode($_POST['barcode'] ?? '');
            link_barcode($_POST['barcode'] ?? '', $art['id'], $_POST['pack_qty'] ?? 1, $_POST['label'] ?? '');
            flash($existing && (int)$existing['article_id'] !== (int)$art['id']
                ? 'Barcode moved from '.$existing['name'].' to '.$art['name'].'.'
                : 'Barcode linked to '.$art['name'].'.');
        } catch (InvalidArgumentException $e) { flash($e->getMessage(), 'err'); }
    }
    redirect('barcodes.php'.(!empty($_GET['article']) ? '?article='.(int)$_GET['article'] : ''));
}

$filter = (int)($_GET['article'] ?? 0);
$rows = all("SELECT b.*, a.name, a.code, u.name AS unit, us.name AS who
             FROM barcodes b JOIN articles a ON a.id=b.article_id
             LEFT JOIN units u ON u.id=a.unit_id LEFT JOIN users us ON us.id=b.created_by".
            ($filter ? " WHERE b.article_id=?" : "")." ORDER BY a.name, b.pack_qty", $filter ? [$filter] : []);
$unlinked = (int) col("SELECT COUNT(*) FROM articles a WHERE a.active=1 AND NOT EXISTS
                       (SELECT 1 FROM barcodes b WHERE b.article_id=a.id)");
admin_header('Barcodes', 'barcodes');
?>
<div class="page-head">
  <div><h1 class="admin-title">Barcodes</h1>
    <p class="lede" style="margin:0">Each barcode points at one article. An article can have several — the single
      item and the outer case, for example, where one case scan counts as 24.</p></div>
  <?= scan_button('Start setup mode') ?>
</div>

<div class="kpis">
  <div class="kpi"><div class="l">Barcodes linked</div><div class="v"><?= (int)col("SELECT COUNT(*) FROM barcodes") ?></div></div>
  <div class="kpi"><div class="l">Articles with a barcode</div>
    <div class="v"><?= (int)col("SELECT COUNT(DISTINCT article_id) FROM barcodes") ?></div></div>
  <div class="kpi"><div class="l">Active articles without one</div>
    <div class="v <?= $unlinked ? 'neg' : '' ?>"><?= $unlinked ?></div><div class="h">They can still be counted by hand</div></div>
</div>

<section class="card pad">
  <h2 class="card-title">How setup works</h2>
  <p class="lede" style="margin:0">Press <strong>Start setup mode</strong> and scan products one after another with the camera or a
    handheld scanner. Anything new asks which article it is; anything already known just confirms. Supervisors can also
    teach a barcode on the spot from any count or waste sheet — it only has to be done once.</p>
</section>

<section class="card pad">
  <h2 class="card-title">Link a barcode by hand</h2>
  <form method="post" class="inline" style="margin-top:10px">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div style="flex:1 1 180px"><label for="bc">Barcode</label>
      <input id="bc" name="barcode" required inputmode="numeric" autocomplete="off"></div>
    <div style="flex:2 1 240px"><label for="art">Article</label>
      <input id="art" name="article_name" list="scan-articles" required autocomplete="off" data-article-input="aid">
      <input type="hidden" name="article_id" id="aid"></div>
    <div style="flex:0 1 120px"><label for="pq">Units per scan</label>
      <input id="pq" name="pack_qty" type="number" step="0.001" min="0.001" value="1"></div>
    <div style="flex:1 1 150px"><label for="lbl">Label (optional)</label>
      <input id="lbl" name="label" placeholder="Case of 24"></div>
    <div><button class="btn primary"><?= icon('link', 16) ?>Link barcode</button></div>
  </form>
</section>

<div class="toolbar"><input class="search" data-filter placeholder="Search barcodes and articles" aria-label="Search barcodes"></div>
<div class="card scroll">
  <table>
    <thead><tr><th>Barcode</th><th>Article</th><th class="num">Units per scan</th><th>Label</th><th>Linked by</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty">No barcodes yet. Start setup mode and scan your first product.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr data-row="<?= h($r['barcode'].' '.$r['name'].' '.$r['code']) ?>">
          <td><code><?= h($r['barcode']) ?></code></td>
          <td><?= h($r['name']) ?><div class="code"><?= h($r['code']) ?></div></td>
          <td class="num"><?= h(fmt_qty($r['pack_qty'], 3)) ?> <span class="faint"><?= h($r['unit'] ?: 'each') ?></span></td>
          <td class="muted"><?= h($r['label']) ?></td>
          <td class="muted"><?= h($r['who'] ?: '—') ?><div class="code"><?= $r['created_at'] ? h(date('d/m/Y', strtotime($r['created_at']))) : '' ?></div></td>
          <td class="num"><form method="post" class="inline-form" onsubmit="return confirm('Remove this barcode?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn sm danger">Remove</button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= scanner_ui('teach') ?>
<?php admin_footer();
