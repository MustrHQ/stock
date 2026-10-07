<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/inc/icons.php';

/* ---------- Auth ---------- */

function user() {
    if (empty($_SESSION['uid'])) return null;
    static $u; if ($u) return $u;
    if (isset($_SESSION['seen']) && time() - $_SESSION['seen'] > APP_IDLE_MINUTES * 60) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['expired'] = 1;
        return null;
    }
    $_SESSION['seen'] = time();
    $u = one("SELECT * FROM users WHERE id=? AND active=1", [$_SESSION['uid']]);
    return $u;
}
function require_login() {
    ensure_schema();
    if (!user()) redirect(base_url().'login.php');
}

/**
 * If the files are newer than the database (a zip update, or files copied up by FTP),
 * bring the database up to date. Only adds what is missing — never changes existing data.
 */
function ensure_schema() {
    static $done = false;
    if ($done) return; $done = true;
    try { $v = col("SELECT v FROM meta WHERE k='db_version'"); } catch (PDOException $e) { $v = null; }
    if ($v === MUSTR_VERSION) return;
    require_once MUSTR_ROOT.'/inc/schema.php';
    try { mustr_migrate(db()); } catch (PDOException $e) { error_log('MustrHQ Stock upgrade failed: '.$e->getMessage()); }
}

/** A sign-out button — sign-out is a POST so another site cannot log staff out mid-count. */
function logout_button() {
    return '<form method="post" action="'.h(base_url()).'logout.php" class="inline-form">'.csrf_field().
           '<button class="top-link" type="submit" title="Sign out">'.icon('logout', 17).'<span class="lbl">Sign out</span></button></form>';
}
function require_admin() {
    require_login();
    if (user()['role'] !== 'admin') { http_response_code(403); exit('Admins only.'); }
}
function is_admin()   { $u = user(); return $u && $u['role'] === 'admin'; }
function is_manager() { $u = user(); return $u && in_array($u['role'], ['admin','manager'], true); }

function base_url() {
    // works in a sub-folder and from /admin/
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if (substr($dir, -6) === '/admin') $dir = substr($dir, 0, -6);
    return ($dir === '' ? '' : $dir).'/';
}

/* ---------- Shop context ---------- */

function current_shop() {
    $u = user();
    if (!$u) return null;
    if (isset($_GET['shop']) && is_admin()) $_SESSION['shop_id'] = (int)$_GET['shop'];
    $id = $_SESSION['shop_id'] ?? $u['shop_id'];
    $s = one("SELECT * FROM shops WHERE id=? AND active=1", [$id]);
    if (!$s) $s = one("SELECT * FROM shops WHERE active=1 ORDER BY code LIMIT 1");
    if ($s) $_SESSION['shop_id'] = $s['id'];
    return $s;
}
function shop_label($s) { return $s ? $s['code'].' '.$s['name'].', '.$s['address'] : ''; }

/* ---------- Stock ledger ---------- */

const MV_DELIVERY  = 'delivery';
const MV_SALE      = 'sale';
const MV_TRANSFER  = 'transfer';
const MV_WASTE_ING = 'waste_ingredient';
const MV_WASTE_STA = 'waste_stales';
const MV_WASTE_DMG = 'waste_quality';
const MV_COUNT_ADJ = 'count_adj';

function move($shop, $article, $date, $type, $qty, $value, $ref = '') {
    q("INSERT INTO movements (shop_id,article_id,mv_date,mv_type,qty,mv_value,ref,created_at)
       VALUES (?,?,?,?,?,?,?,NOW())",
       [$shop, $article, $date, $type, $qty, $value, $ref]);
}

/** Stock on hand for one article, up to and including $upto (default: everything). */
function on_hand($shop, $article, $upto = null) {
    if ($upto) {
        return (float) col("SELECT COALESCE(SUM(qty),0) FROM movements
                            WHERE shop_id=? AND article_id=? AND mv_date<=?", [$shop, $article, $upto]);
    }
    return (float) col("SELECT COALESCE(SUM(qty),0) FROM movements
                        WHERE shop_id=? AND article_id=?", [$shop, $article]);
}

/** article_id => qty, for every article that has movement in this shop. */
function on_hand_map($shop, $upto = null) {
    $sql = "SELECT article_id, SUM(qty) AS q FROM movements WHERE shop_id=?";
    $p = [$shop];
    if ($upto) { $sql .= " AND mv_date<=?"; $p[] = $upto; }
    $sql .= " GROUP BY article_id";
    $m = [];
    foreach (all($sql, $p) as $r) $m[(int)$r['article_id']] = (float)$r['q'];
    return $m;
}

/** Articles sitting in negative stock — these get pulled into today's count automatically. */
function negative_stock_articles($shop, $upto = null) {
    $sql = "SELECT article_id FROM movements WHERE shop_id=?";
    $p = [$shop];
    if ($upto) { $sql .= " AND mv_date<=?"; $p[] = $upto; }
    $sql .= " GROUP BY article_id HAVING SUM(qty) < 0";
    return array_map(fn($r) => (int)$r['article_id'], all($sql, $p));
}

/* ---------- Count schedule ---------- */

/** Articles scheduled to be counted in this shop on $date (0 = Sunday … 6 = Saturday). */
function scheduled_article_ids($shop, $date) {
    $d = (int)date('w', strtotime($date));
    $rows = all("SELECT DISTINCT article_id FROM schedules
                 WHERE (shop_id IS NULL OR shop_id=?) AND d$d = 1", [$shop]);
    return array_map(fn($r) => (int)$r['article_id'], $rows);
}

/* ---------- Sessions (count / waste) ---------- */

function count_session($shop, $date, $create = true) {
    $s = one("SELECT * FROM count_sessions WHERE shop_id=? AND count_date=?", [$shop, $date]);
    if (!$s && $create) {
        q("INSERT INTO count_sessions (shop_id,count_date,status,created_by,created_at)
           VALUES (?,?,'open',?,NOW())", [$shop, $date, user()['id']]);
        $s = one("SELECT * FROM count_sessions WHERE shop_id=? AND count_date=?", [$shop, $date]);
    }
    return $s;
}

function waste_session($shop, $date, $type, $create = true) {
    $s = one("SELECT * FROM waste_sessions WHERE shop_id=? AND waste_date=? AND waste_type=?",
             [$shop, $date, $type]);
    if (!$s && $create) {
        q("INSERT INTO waste_sessions (shop_id,waste_date,waste_type,status,created_by,created_at)
           VALUES (?,?,?,'open',?,NOW())", [$shop, $date, $type, user()['id']]);
        $s = one("SELECT * FROM waste_sessions WHERE shop_id=? AND waste_date=? AND waste_type=?",
                 [$shop, $date, $type]);
    }
    return $s;
}

/** Tile status for the launchpad: open + has entries => TO DO, confirmed => COMPLETE. */
function tile_status($confirmed) { return $confirmed ? 'Confirmed' : 'To do'; }

function article($id) { return one("SELECT * FROM articles WHERE id=?", [$id]); }

/** Accept either the hidden id set by the picker, or whatever the person typed. */
function resolve_article($id, $name = '') {
    $id = (int)$id;
    if ($id && ($a = article($id))) return $a;
    $name = trim((string)$name);
    if ($name === '') return null;
    return one("SELECT * FROM articles WHERE active=1 AND (name=? OR code=?) LIMIT 1", [$name, $name]);
}

function net_sales($shop, $from, $to) {
    return (float) col("SELECT COALESCE(SUM(net_sales),0) FROM sales_days
                        WHERE shop_id=? AND sales_date BETWEEN ? AND ?", [$shop, $from, $to]);
}

/** Monday-based retail week for a date. */
function week_bounds($date) {
    $ts = strtotime($date);
    $mon = date('Y-m-d', strtotime('monday this week', $ts));
    $sun = date('Y-m-d', strtotime('sunday this week', $ts));
    return [$mon, $sun, date('o').'W'.date('W', $ts)];
}

/* ---------- Ordering ---------- */

const ORDER_STATES = ['draft' => 'Draft', 'sent' => 'Sent', 'received' => 'Received', 'cancelled' => 'Cancelled'];

/** Par level and pack size for one article in one shop, falling back to the article default. */
function supply_settings($shop, $article) {
    $p = one("SELECT * FROM par_levels WHERE shop_id=? AND article_id=?", [$shop, $article]);
    $a = article($article);
    return [
        'par'  => $p ? (float)$p['par_qty'] : 0,
        'pack' => max(1, (float)(($p && $p['pack_size'] > 0) ? $p['pack_size'] : ($a['pack_size'] ?? 1))),
    ];
}

/** Average units sold a day over the last $days days — used when no par level is set. */
function avg_daily_sales($shop, $article, $days = 28) {
    $from = date('Y-m-d', strtotime("-$days days"));
    $sold = (float) col("SELECT COALESCE(SUM(-qty),0) FROM movements
                         WHERE shop_id=? AND article_id=? AND mv_type=? AND mv_date>=?",
                        [$shop, $article, MV_SALE, $from]);
    return $days > 0 ? $sold / $days : 0;
}

/**
 * What this shop should order from one supplier.
 * Par level first; if none is set, cover a few days of recent sales instead.
 * Everything is rounded up to whole packs.
 */
function suggest_order($shop, $supplierId, $coverDays = null) {
    $sup = one("SELECT * FROM suppliers WHERE id=?", [$supplierId]);
    $cover = $coverDays !== null ? (float)$coverDays : max(1, (float)($sup['lead_days'] ?? 2) + 3);
    $rows = all("SELECT a.*, u.name AS unit FROM articles a
                 LEFT JOIN units u ON u.id=a.unit_id
                 WHERE a.active=1 AND a.blocked=0 AND a.supplier_id=? ORDER BY a.name", [$supplierId]);
    $out = [];
    foreach ($rows as $a) {
        $s    = supply_settings($shop, $a['id']);
        $hand = on_hand($shop, $a['id']);
        $rate = avg_daily_sales($shop, $a['id']);
        $need = $s['par'] > 0 ? $s['par'] - $hand : ($rate * $cover) - $hand;
        $qty  = $need > 0 ? ceil($need / $s['pack']) * $s['pack'] : 0;
        $out[] = $a + [
            'on_hand' => $hand, 'par' => $s['par'], 'pack' => $s['pack'],
            'rate' => $rate, 'suggested' => $qty,
            'unit_cost' => (float)($a['pack_cost'] > 0 ? $a['pack_cost'] / $s['pack'] : $a['cost_price']),
        ];
    }
    return $out;
}

/** Sequential per-shop order number, e.g. ORD-0101-0007. */
function next_order_no($shop) {
    $code = col("SELECT code FROM shops WHERE id=?", [$shop]) ?: 'SHOP';
    $n = (int) col("SELECT COUNT(*) FROM orders WHERE shop_id=?", [$shop]) + 1;
    do {
        $no = 'ORD-'.$code.'-'.str_pad((string)$n, 4, '0', STR_PAD_LEFT);
        $taken = col("SELECT id FROM orders WHERE order_no=?", [$no]);
        $n++;
    } while ($taken);
    return $no;
}

function order_total($orderId, $field = 'qty') {
    $field = $field === 'received_qty' ? 'received_qty' : 'qty';
    return (float) col("SELECT COALESCE(SUM($field * unit_cost),0) FROM order_lines WHERE order_id=?", [$orderId]);
}

/* ---------- Out of stock ---------- */

/**
 * Articles at zero or below in this shop. An article counts if it has ever been stocked here
 * (it has movements) or it has a par level — never-stocked catalogue lines are ignored.
 * Each row carries on_hand, on_order (open drafts + sent orders) and par.
 */
function out_of_stock($shop) {
    try {
        $hand = on_hand_map($shop);
        $par  = [];
        foreach (all("SELECT article_id, par_qty FROM par_levels WHERE shop_id=? AND par_qty>0", [$shop]) as $r)
            $par[(int)$r['article_id']] = (float)$r['par_qty'];
        $ids = [];
        foreach ($hand as $aid => $q) if ($q <= 0) $ids[$aid] = true;
        foreach ($par as $aid => $p) if (($hand[$aid] ?? 0) <= 0) $ids[$aid] = true;
        if (!$ids) return [];

        $onOrder = [];
        foreach (all("SELECT l.article_id, SUM(l.qty) AS q FROM order_lines l
                      JOIN orders o ON o.id=l.order_id
                      WHERE o.shop_id=? AND o.status IN ('draft','sent') GROUP BY l.article_id", [$shop]) as $r)
            $onOrder[(int)$r['article_id']] = (float)$r['q'];

        $in = implode(',', array_map('intval', array_keys($ids)));
        $rows = all("SELECT a.id, a.code, a.name, a.supplier_id, a.pack_size, s.name AS supplier, u.name AS unit
                     FROM articles a
                     LEFT JOIN suppliers s ON s.id=a.supplier_id
                     LEFT JOIN units u ON u.id=a.unit_id
                     WHERE a.id IN ($in) AND a.active=1 AND a.blocked=0
                     ORDER BY s.name IS NULL, s.name, a.name");
        foreach ($rows as &$r) {
            $r['on_hand']  = $hand[$r['id']] ?? 0;
            $r['on_order'] = $onOrder[$r['id']] ?? 0;
            $r['par']      = $par[$r['id']] ?? 0;
        }
        return $rows;
    } catch (PDOException $e) {
        return [];   // older database that has not been upgraded yet
    }
}

/** Out of stock and nobody has ordered it yet — these are the ones we nag about. */
function out_of_stock_alerts($shop) {
    return array_values(array_filter(out_of_stock($shop), fn($r) => $r['on_order'] <= 0));
}

/** The shop's open draft for a supplier, creating one if there is none. Returns the order id. */
function open_draft_order($shop, $supplierId) {
    $open = col("SELECT id FROM orders WHERE shop_id=? AND supplier_id=? AND status='draft' ORDER BY id DESC LIMIT 1",
                [$shop, $supplierId]);
    if ($open) return (int)$open;
    $lead = (int) col("SELECT lead_days FROM suppliers WHERE id=?", [$supplierId]);
    q("INSERT INTO orders (shop_id,supplier_id,order_no,order_date,delivery_date,status,created_by,created_at)
       VALUES (?,?,?,?,?,'draft',?,NOW())",
      [$shop, $supplierId, next_order_no($shop), date('Y-m-d'),
       date('Y-m-d', strtotime('+'.max(0, $lead).' days')), user()['id']]);
    return (int) db()->lastInsertId();
}

function fmt_qty($n, $dp = 2) { return rtrim(rtrim(number_format((float)$n, $dp, '.', ''), '0'), '.'); }

/* ---------- Barcodes ---------- */

/** Scanners disagree about leading zeros (UPC-A vs EAN-13), so we store one form and try both. */
function normalise_barcode($code) {
    return preg_replace('/[^0-9A-Za-z\-\.\/\+]/', '', trim((string)$code));
}
function barcode_variants($code) {
    $c = normalise_barcode($code);
    $v = [$c];
    if (ctype_digit($c) && strlen($c) === 12) $v[] = '0'.$c;               // UPC-A read as EAN-13
    if (ctype_digit($c) && strlen($c) === 13 && $c[0] === '0') $v[] = substr($c, 1);
    return array_values(array_unique(array_filter($v, 'strlen')));
}

/** Article + pack quantity for a scanned code, or null if nobody has taught it yet. */
function find_barcode($code) {
    $v = barcode_variants($code);
    if (!$v) return null;
    $in = implode(',', array_fill(0, count($v), '?'));
    return one("SELECT b.barcode, b.pack_qty, b.label, a.id AS article_id, a.code, a.name, a.kind,
                       a.active, a.blocked, u.name AS unit
                FROM barcodes b JOIN articles a ON a.id=b.article_id
                LEFT JOIN units u ON u.id=a.unit_id
                WHERE b.barcode IN ($in) LIMIT 1", $v);
}

/** Only supervisors (managers) and admins may teach the system a new barcode. */
function can_link_barcodes() { return is_manager(); }

function link_barcode($code, $articleId, $packQty = 1, $label = '') {
    $code = normalise_barcode($code);
    if ($code === '' || strlen($code) > 64) throw new InvalidArgumentException('That does not look like a barcode.');
    if (!article($articleId)) throw new InvalidArgumentException('Pick the article this barcode belongs to.');
    $packQty = max(0.001, (float)$packQty);
    q("INSERT INTO barcodes (barcode,article_id,pack_qty,label,created_by,created_at) VALUES (?,?,?,?,?,NOW())
       ON DUPLICATE KEY UPDATE article_id=VALUES(article_id), pack_qty=VALUES(pack_qty), label=VALUES(label),
                               created_by=VALUES(created_by), created_at=NOW()",
      [$code, (int)$articleId, $packQty, str_cut(trim($label), 60), user()['id'] ?? null]);
}

/** JSON response helper for the small API endpoints. */
function json_out($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** The scanner widget: include once per page that accepts scans. */
function scanner_assets() {
    $B = base_url();
    return '<script src="'.h($B).'assets/vendor/zxing.min.js" defer></script>'.
           '<script src="'.h($B).'assets/scan.js" defer></script>';
}
function scan_button($label = 'Scan barcode') {
    return '<button type="button" class="btn scan-btn" data-scan-open>'.icon('scan', 17).'<span>'.h($label).'</span></button>';
}

/**
 * Everything the scanner needs on a page: the root with its mode, the camera dialog,
 * the teach form, the article list for teaching, and the toast area.
 * $opts: name / id / qty (CSS selectors for pick mode), add_form (selector, tally mode).
 */
function scanner_ui($mode, $opts = []) {
    $B = base_url();
    $attrs = ' data-scan-mode="'.h($mode).'" data-scan-base="'.h($B).'" data-csrf="'.h(csrf()).'"';
    foreach (['name' => 'data-scan-name', 'id' => 'data-scan-id', 'qty' => 'data-scan-qty', 'add_form' => 'data-scan-add-form'] as $k => $a)
        if (!empty($opts[$k])) $attrs .= ' '.$a.'="'.h($opts[$k]).'"';
    ob_start(); ?>
<div id="scanner-root" hidden<?= $attrs ?>></div>
<dialog class="scanner" id="scanner" aria-labelledby="scannerTitle">
  <div class="scanner-head">
    <h2 id="scannerTitle"><?= icon('scan', 18) ?> Scan a barcode</h2>
    <button type="button" class="btn ghost sm" data-scan-close aria-label="Close scanner"><?= icon('x', 18) ?></button>
  </div>
  <div class="scanner-view">
    <video playsinline muted autoplay></video>
    <div class="scanner-frame" aria-hidden="true"></div>
    <p class="scanner-hint">Point the camera at the barcode</p>
  </div>
  <form class="scanner-manual">
    <label for="scanManual">Or type the barcode number</label>
    <div class="scanner-row">
      <input id="scanManual" name="code" inputmode="numeric" autocomplete="off" placeholder="The digits under the barcode">
      <button class="btn" type="submit">Find</button>
    </div>
  </form>
  <form class="scanner-teach" hidden>
    <div class="teach-head">
      <span class="teach-ic"><?= icon('link', 18) ?></span>
      <div><strong>New barcode</strong>
        <p>This is the first time <code data-teach-code></code> has been scanned. Tell the system what it is and
           it will recognise it from now on.</p></div>
    </div>
    <input type="hidden" name="code">
    <label for="teachArticle">Which article is it?</label>
    <input id="teachArticle" name="article_name" list="scan-articles" autocomplete="off" placeholder="Start typing the name or code" required>
    <input type="hidden" name="article_id">
    <label for="teachPack" style="margin-top:12px">How many units does one scan count as?</label>
    <input id="teachPack" name="pack_qty" type="number" step="0.001" min="0.001" value="1" inputmode="decimal">
    <p class="teach-note">Leave it at 1 for a single item. If this barcode is on an outer case, enter how many are in the case.</p>
    <p class="teach-err" role="alert"></p>
    <div class="scanner-row" style="justify-content:flex-end">
      <button type="button" class="btn" data-teach-cancel>Skip</button>
      <button type="submit" class="btn primary"><?= icon('link', 16) ?>Link barcode</button>
    </div>
  </form>
  <div class="scanner-log" aria-live="polite"></div>
</dialog>
<datalist id="scan-articles">
<?php foreach (all("SELECT id, code, name FROM articles WHERE active=1 ORDER BY name") as $a): ?>
  <option data-id="<?= (int)$a['id'] ?>" value="<?= h($a['name']) ?>"><?= h($a['code']) ?></option>
<?php endforeach; ?>
</datalist>
<div class="toasts" id="toasts" aria-live="polite"></div>
<?php
    return ob_get_clean().scanner_assets();
}

/** <head> tags that make every page part of the installable app. */
function app_head_tags() {
    $B = h(base_url());
    return '<link rel="manifest" href="'.$B.'manifest.php">'.
           '<link rel="icon" type="image/png" sizes="32x32" href="'.$B.'assets/icons/favicon-32.png">'.
           '<link rel="apple-touch-icon" href="'.$B.'assets/icons/apple-180.png">'.
           '<meta name="mobile-web-app-capable" content="yes">'.
           '<meta name="apple-mobile-web-app-capable" content="yes">'.
           '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">'.
           '<meta name="apple-mobile-web-app-title" content="Mustr Stock">';
}

/** Plain-English names for movement types and references shown in stock history. */
function movement_label($type) {
    return [
        MV_DELIVERY  => 'Delivery',        MV_SALE      => 'Sale',          MV_TRANSFER => 'Transfer',
        MV_WASTE_ING => 'Ingredient waste', MV_WASTE_STA => 'Product waste', MV_WASTE_DMG => 'Damaged stock',
        MV_COUNT_ADJ => 'Count adjustment',
    ][$type] ?? ucfirst(str_replace('_', ' ', $type));
}
function movement_ref($ref) {
    if (preg_match('/^(COUNT|WASTE|DAMAGED|ORDER)#(\d+)$/', (string)$ref, $m)) {
        if ($m[1] === 'ORDER') { $no = col("SELECT order_no FROM orders WHERE id=?", [$m[2]]); return $no ?: 'Order'; }
        return ['COUNT' => 'Stock count', 'WASTE' => 'Waste sheet', 'DAMAGED' => 'Damaged stock'][$m[1]].' #'.$m[2];
    }
    if (preg_match('/^[A-Z][A-Z ]+$/', (string)$ref)) return ucfirst(strtolower($ref));   // "SALES IMPORT" -> "Sales import"
    return (string)$ref;
}

/** Switch between the three waste sheets. */
function waste_switch($current) {
    $tabs = ['product-waste.php' => 'Product', 'ingredient-waste.php' => 'Ingredient', 'damaged-stock.php' => 'Damaged'];
    $out = '<nav class="btn-row no-print" aria-label="Waste sheets">';
    foreach ($tabs as $f => $l)
        $out .= '<a class="btn sm'.($f === $current ? ' on' : '').'" href="'.h($f).($f !== 'damaged-stock.php' && isset($_GET['d']) ? '?d='.h($_GET['d']) : '').'"'.
                ($f === $current ? ' aria-current="page"' : '').'>'.h($l).'</a>';
    return $out.'</nav>';
}
