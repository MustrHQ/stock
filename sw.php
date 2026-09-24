<?php
/**
 * Service worker. Served from PHP only so its cache name follows the release version —
 * after an update, phones pick up the new CSS and scripts on their own.
 *
 * Pages are never cached (they hold live stock figures and security tokens).
 * Only the app's own static files are, so the app opens fast and shows a proper
 * offline screen instead of the browser's error page.
 */
require_once __DIR__.'/version.php';
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');
header('Service-Worker-Allowed: ./');
$assets = ['assets/app.css', 'assets/app.js', 'assets/scan.js', 'assets/vendor/zxing.min.js',
           'assets/fonts/plex-sans-400.woff2', 'assets/fonts/plex-sans-500.woff2',
           'assets/fonts/plex-sans-600.woff2', 'assets/fonts/plex-sans-700.woff2',
           'assets/icons/icon-192.png', 'assets/icons/favicon-32.png', 'offline.html'];
?>
'use strict';
const CACHE = 'mustr-stock-<?= preg_replace('/[^0-9A-Za-z\.\-]/', '', MUSTR_VERSION) ?>';
const SHELL = <?= json_encode($assets) ?>;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('mustr-stock-') && k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;                       // forms always go to the server
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Pages: always live. If the network is gone, show the offline screen.
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }
  // The app's own static files: serve from cache, refresh in the background.
  if (url.pathname.includes('/assets/')) {
    e.respondWith(
      caches.open(CACHE).then((c) => c.match(req).then((hit) => {
        const live = fetch(req).then((res) => { if (res.ok) c.put(req, res.clone()); return res; }).catch(() => hit);
        return hit || live;
      }))
    );
  }
  // Everything else (API calls, downloads) goes straight to the network.
});
