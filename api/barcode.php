<?php
/**
 * Barcode API — used by the scanner on every sheet.
 *   GET  ?code=…                     → what article is this?
 *   POST action=link (+ csrf)        → teach a new barcode (managers and admins only)
 */
require_once __DIR__.'/../lib.php';
ensure_schema();
if (!user()) json_out(['ok' => false, 'error' => 'Your session has ended. Sign in again.'], 401);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '')))
        json_out(['ok' => false, 'error' => 'Security check failed. Reload the page and try again.'], 400);
    if (!can_link_barcodes())
        json_out(['ok' => false, 'error' => 'Only a manager or admin can teach the system a new barcode.'], 403);
    try {
        link_barcode($_POST['code'] ?? '', (int)($_POST['article_id'] ?? 0), $_POST['pack_qty'] ?? 1, $_POST['label'] ?? '');
    } catch (InvalidArgumentException $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 422);
    }
    $hit = find_barcode($_POST['code']);
    json_out(['ok' => true, 'found' => true] + scan_payload($hit));
}

$code = normalise_barcode($_GET['code'] ?? '');
if ($code === '') json_out(['ok' => false, 'error' => 'No barcode read.'], 422);
$hit = find_barcode($code);
if (!$hit) json_out(['ok' => true, 'found' => false, 'code' => $code, 'can_link' => can_link_barcodes()]);
json_out(['ok' => true, 'found' => true] + scan_payload($hit));

function scan_payload($hit) {
    return [
        'code'       => $hit['barcode'],
        'article_id' => (int)$hit['article_id'],
        'name'       => $hit['name'],
        'sku'        => $hit['code'],
        'kind'       => $hit['kind'],
        'unit'       => $hit['unit'] ?: 'each',
        'pack_qty'   => (float)$hit['pack_qty'],
        'label'      => $hit['label'],
        'inactive'   => !$hit['active'] || $hit['blocked'],
    ];
}
