<?php
/** Web app manifest — this is what makes the site installable as an app. */
require_once __DIR__.'/bootstrap.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$name = APP_NAME;
echo json_encode([
    'id'               => './index.php',
    'name'             => $name,
    'short_name'       => str_len($name) > 12 ? 'Mustr Stock' : $name,
    'description'      => 'Stock counts, waste, ordering and stock loss for your shops.',
    'start_url'        => './index.php?source=app',
    'scope'            => './',
    'display'          => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation'      => 'any',
    'background_color' => '#EEF1F6',
    'theme_color'      => '#01216C',
    'categories'       => ['business', 'productivity'],
    'icons' => [
        ['src' => 'assets/icons/icon-192.png',     'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icons/icon-512.png',     'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => 'Stock count',  'url' => './stock-count.php',  'icons' => [['src' => 'assets/icons/sc-count.png', 'sizes' => '96x96']]],
        ['name' => 'Product waste', 'url' => './product-waste.php', 'icons' => [['src' => 'assets/icons/sc-waste.png', 'sizes' => '96x96']]],
        ['name' => 'Ordering',     'url' => './orders.php',       'icons' => [['src' => 'assets/icons/sc-order.png', 'sizes' => '96x96']]],
        ['name' => 'Scan and look up', 'url' => './lookup.php',   'icons' => [['src' => 'assets/icons/sc-scan.png',  'sizes' => '96x96']]],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
