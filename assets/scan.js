/*
 * MustrHQ Stock — barcode scanning.
 *
 * Three ways in, all ending in handle(code):
 *   1. Phone or tablet camera   — native BarcodeDetector where the browser has it, ZXing otherwise.
 *   2. Handheld scanner         — USB/Bluetooth scanners "type" the digits and press Enter very fast.
 *                                 We spot that burst anywhere on the page, even inside a quantity box.
 *   3. Typing the number        — the box under the camera view.
 *
 * What a scan does depends on the page (data-scan-mode on #scanner-root):
 *   tally  — add the barcode's pack quantity to that article's line   (count and waste sheets)
 *   pick   — select the article in the form and fill the quantity     (damaged stock, goods in, orders)
 *   lookup — open the article                                           (lookup stock)
 *   teach  — link barcodes one after another                            (admin → barcodes)
 *
 * A barcode nobody has seen before opens the "teach" panel for managers and admins.
 */
(function () {
  'use strict';
  var root = document.getElementById('scanner-root');
  if (!root) return;

  var mode   = root.getAttribute('data-scan-mode') || 'lookup';
  var base   = root.getAttribute('data-scan-base') || '';
  var csrf   = root.getAttribute('data-csrf') || '';
  var dlg    = document.getElementById('scanner');
  var video  = dlg.querySelector('video');
  var hint   = dlg.querySelector('.scanner-hint');
  var logEl  = dlg.querySelector('.scanner-log');
  var teach  = dlg.querySelector('.scanner-teach');
  var manual = dlg.querySelector('.scanner-manual');
  var toasts = document.getElementById('toasts');

  var stream = null, zxReader = null, detectTimer = null;
  var lastCode = '', lastAt = 0, busy = false;
  var seen = {};            // camera: code -> when it was last in view

  /* A camera sees the same barcode several times a second. It only counts again once the
     barcode has been out of view for a moment — hold a product there and it counts once. */
  var AWAY_MS = 1500;
  function fromCamera(raw) {
    var code = String(raw || '').replace(/[^0-9A-Za-z\-\.\/\+]/g, '');
    var now = Date.now(), prev = seen[code] || 0;
    seen[code] = Math.max(now, prev);
    if (now - prev < AWAY_MS) return;
    handle(code, true);
  }

  /* ---------------- helpers ---------------- */
  function $(sel) { return sel ? document.querySelector(sel) : null; }
  function round(n) { return Math.round(n * 1000) / 1000; }
  function fmt(n) { return String(round(n)); }

  function toast(msg, kind, action) {
    var t = document.createElement('div');
    t.className = 'toast ' + (kind || 'ok');
    t.setAttribute('role', 'status');
    var span = document.createElement('span');
    span.textContent = msg;
    t.appendChild(span);
    if (action) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'toast-act'; b.textContent = action.label;
      b.addEventListener('click', function () { t.remove(); action.run(); });
      t.appendChild(b);
    }
    toasts.appendChild(t);
    setTimeout(function () { t.classList.add('out'); }, action ? 7000 : 3200);
    setTimeout(function () { t.remove(); }, action ? 7400 : 3600);
    if (dlg.open) log(msg, kind);
  }
  function log(msg, kind) {
    var row = document.createElement('div');
    row.className = 'log-row ' + (kind || 'ok');
    row.textContent = msg;
    logEl.insertBefore(row, logEl.firstChild);
    while (logEl.children.length > 6) logEl.removeChild(logEl.lastChild);
  }

  var audioCtx = null;
  function beep(ok) {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      var o = audioCtx.createOscillator(), g = audioCtx.createGain();
      o.frequency.value = ok ? 1320 : 330;
      g.gain.value = 0.06;
      o.connect(g); g.connect(audioCtx.destination);
      o.start(); o.stop(audioCtx.currentTime + (ok ? 0.08 : 0.22));
    } catch (e) {}
    if (navigator.vibrate) navigator.vibrate(ok ? 40 : [60, 40, 60]);
  }

  /* ---------------- the scan itself ---------------- */
  function handle(raw, camera) {
    var code = String(raw || '').replace(/[^0-9A-Za-z\-\.\/\+]/g, '');
    if (code.length < 3) return;
    var now = Date.now();
    if (!camera && code === lastCode && now - lastAt < 350) return;   // an accidental double Enter
    lastCode = code; lastAt = now;
    if (busy) return;
    busy = true;

    fetch(base + 'api/barcode.php?code=' + encodeURIComponent(code), { credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
      .then(function (res) {
        busy = false;
        var j = res.body;
        if (res.status === 401) { toast(j.error, 'err'); return; }
        if (!j.ok) { beep(false); toast(j.error || 'That scan could not be read.', 'err'); return; }
        if (!j.found) {
          beep(false);
          if (j.can_link) { seen[code] = Date.now() + 60000; openTeach(code); }
          else toast('Barcode ' + code + ' is not linked to an article yet. Ask a manager to link it.', 'warn');
          return;
        }
        beep(true);
        apply(j);
      })
      .catch(function () {
        busy = false; beep(false);
        toast('No connection. Type the quantity in by hand for now.', 'err');
      });
  }

  function apply(item) {
    if (item.inactive) toast(item.name + ' is hidden or blocked in the catalogue.', 'warn');

    if (mode === 'tally') {
      var input = document.querySelector('input.qty[data-article="' + item.article_id + '"]');
      if (!input) {
        var addForm = $(root.getAttribute('data-scan-add-form'));
        if (addForm) {
          toast(item.name + ' is not on today\'s sheet.', 'warn', {
            label: 'Add it', run: function () {
              addForm.querySelector('[name=article_id]').value = item.article_id;
              var q = addForm.querySelector('[name=qty]'); if (q) q.value = item.pack_qty;
              addForm.submit();
            }
          });
        } else {
          toast(item.name + ' is not on this sheet.', 'warn');
        }
        return;
      }
      if (input.readOnly) { toast('This sheet is confirmed. Reopen it to change quantities.', 'warn'); return; }
      var next = round((parseFloat(input.value) || 0) + item.pack_qty);
      input.value = fmt(next);
      input.dispatchEvent(new Event('input', { bubbles: true }));
      var row = input.closest('tr');
      if (row) {
        row.classList.remove('scan-hit'); void row.offsetWidth; row.classList.add('scan-hit');
        if (!dlg.open) row.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
      toast('+' + fmt(item.pack_qty) + ' ' + item.name + ' — now ' + fmt(next) + ' ' + item.unit, 'ok');
      return;
    }

    if (mode === 'pick') {
      var name = $(root.getAttribute('data-scan-name')),
          id   = $(root.getAttribute('data-scan-id')),
          qty  = $(root.getAttribute('data-scan-qty'));
      if (name) name.value = item.name;
      if (id) id.value = item.article_id;
      if (qty) { qty.value = fmt(item.pack_qty); }
      close();
      (qty || name).focus();
      if (qty && qty.select) qty.select();
      toast(item.name + ' selected' + (item.pack_qty !== 1 ? ' — ' + fmt(item.pack_qty) + ' per scan' : ''), 'ok');
      return;
    }

    if (mode === 'teach') {
      toast(item.code + ' is already linked to ' + item.name + '.', 'ok', {
        label: 'Change', run: function () { openTeach(item.code, item); }
      });
      return;
    }

    // lookup
    close();
    window.location.href = base + 'lookup.php?a=' + item.article_id;
  }

  /* ---------------- teaching a new barcode ---------------- */
  function openTeach(code, existing) {
    if (!dlg.open) open(false);
    stopCamera();
    teach.hidden = false;
    manual.hidden = true;
    dlg.querySelector('.scanner-view').hidden = true;
    teach.querySelector('[data-teach-code]').textContent = code;
    teach.elements.code.value = code;
    teach.elements.article_name.value = existing ? existing.name : '';
    teach.elements.article_id.value = existing ? existing.article_id : '';
    teach.elements.pack_qty.value = existing ? existing.pack_qty : 1;
    teach.querySelector('.teach-err').textContent = '';
    setTimeout(function () { teach.elements.article_name.focus(); }, 50);
  }
  function closeTeach() {
    if (teach.elements.code.value && !teach.hidden) seen[teach.elements.code.value] = Date.now() + AWAY_MS;
    teach.hidden = true;
    manual.hidden = false;
    dlg.querySelector('.scanner-view').hidden = false;
  }
  teach.elements.article_name.addEventListener('input', function () {
    var v = this.value, hit = '';
    document.querySelectorAll('#scan-articles option').forEach(function (o) {
      if (o.value === v) hit = o.getAttribute('data-id');
    });
    teach.elements.article_id.value = hit;
  });
  teach.addEventListener('submit', function (e) {
    e.preventDefault();
    var err = teach.querySelector('.teach-err');
    if (!teach.elements.article_id.value) { err.textContent = 'Choose an article from the list.'; return; }
    var fd = new FormData(teach);
    fd.append('csrf', csrf);
    fd.append('action', 'link');
    fetch(base + 'api/barcode.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { err.textContent = j.error || 'Could not save the link.'; return; }
        beep(true);
        closeTeach();
        toast('Linked. ' + j.code + ' will now be recognised as ' + j.name + '.', 'ok');
        seen[j.code] = seen[teach.elements.code.value] = Date.now() + AWAY_MS;   // still in view: wait for it to leave
        apply(j);
        if (mode === 'tally' || mode === 'teach') startCamera();
      })
      .catch(function () { err.textContent = 'No connection. Try again when you have signal.'; });
  });
  teach.querySelector('[data-teach-cancel]').addEventListener('click', function () {
    closeTeach(); startCamera();
  });

  /* ---------------- camera ---------------- */
  var FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'qr_code'];

  function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      hint.textContent = 'This browser cannot use the camera. Type the number below, or use a handheld scanner.';
      return;
    }
    if (!window.isSecureContext) {
      hint.textContent = 'The camera needs the site on HTTPS. Type the number below for now.';
      return;
    }
    hint.textContent = 'Starting camera…';
    var constraints = { video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false };

    if ('BarcodeDetector' in window) {
      navigator.mediaDevices.getUserMedia(constraints).then(function (s) {
        stream = s; video.srcObject = s; return video.play();
      }).then(function () {
        return window.BarcodeDetector.getSupportedFormats ? window.BarcodeDetector.getSupportedFormats() : FORMATS;
      }).then(function (supported) {
        var det = new window.BarcodeDetector({ formats: FORMATS.filter(function (f) { return supported.indexOf(f) > -1; }) });
        hint.textContent = 'Point the camera at the barcode';
        detectTimer = setInterval(function () {
          if (video.readyState < 2 || busy) return;
          det.detect(video).then(function (codes) { if (codes.length) fromCamera(codes[0].rawValue); }).catch(function () {});
        }, 180);
      }).catch(cameraFailed);
      return;
    }

    if (window.ZXing && window.ZXing.BrowserMultiFormatReader) {
      var hints = new Map();
      var F = window.ZXing.BarcodeFormat;
      hints.set(window.ZXing.DecodeHintType.POSSIBLE_FORMATS,
        [F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E, F.CODE_128, F.CODE_39, F.ITF, F.QR_CODE]);
      zxReader = new window.ZXing.BrowserMultiFormatReader(hints, 200);
      zxReader.decodeFromConstraints(constraints, video, function (result) {
        if (result) fromCamera(result.getText());
      }).then(function () {
        hint.textContent = 'Point the camera at the barcode';
      }).catch(cameraFailed);
      return;
    }
    hint.textContent = 'The scanner could not load. Type the number below instead.';
  }

  function cameraFailed(err) {
    var name = err && err.name;
    hint.textContent = name === 'NotAllowedError'
      ? 'Camera permission was refused. Allow it in the browser settings, or type the number below.'
      : name === 'NotFoundError'
        ? 'No camera found on this device. Type the number below, or use a handheld scanner.'
        : 'The camera would not start. Type the number below instead.';
  }

  function stopCamera() {
    if (detectTimer) { clearInterval(detectTimer); detectTimer = null; }
    if (zxReader) { try { zxReader.reset(); } catch (e) {} zxReader = null; }
    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    video.srcObject = null;
  }

  /* ---------------- dialog ---------------- */
  function open(withCamera) {
    closeTeach();
    if (!dlg.open) { dlg.showModal ? dlg.showModal() : dlg.setAttribute('open', ''); }
    if (withCamera !== false) startCamera();
    else hint.textContent = '';
  }
  function close() {
    stopCamera();
    if (dlg.open) dlg.close ? dlg.close() : dlg.removeAttribute('open');
  }
  dlg.addEventListener('close', stopCamera);
  dlg.addEventListener('cancel', stopCamera);
  document.querySelectorAll('[data-scan-open]').forEach(function (b) {
    b.addEventListener('click', function () { open(true); });
  });
  dlg.querySelectorAll('[data-scan-close]').forEach(function (b) { b.addEventListener('click', close); });
  manual.addEventListener('submit', function (e) {
    e.preventDefault();
    var v = manual.elements.code.value; manual.elements.code.value = '';
    lastCode = '';
    handle(v);
  });

  /* ---------------- handheld scanners (keyboard wedge) ---------------- */
  // A person types a character every 100ms or more; a scanner sends the whole code in a few
  // milliseconds and finishes with Enter. We watch timing, not focus, so it works even while
  // the cursor sits in a quantity box — and we put that box back the way it was.
  var buf = '', firstAt = 0, prevAt = 0, target = null, snapshot = null;
  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    if (teach.contains(e.target)) return;
    var now = performance.now();
    if (e.key === 'Enter') {
      var fast = buf.length >= 6 && (now - firstAt) / buf.length < 35;
      if (fast) {
        e.preventDefault(); e.stopPropagation();
        if (target && snapshot !== null && 'value' in target && target !== manual.elements.code) target.value = snapshot;
        var code = buf; buf = '';
        lastCode = '';
        handle(code);
      }
      buf = '';
      return;
    }
    if (e.key.length !== 1) return;
    if (now - prevAt > 60) {             // a pause means a new sequence
      buf = ''; firstAt = now;
      target = e.target;
      snapshot = ('value' in e.target) ? e.target.value : null;
    }
    buf += e.key; prevAt = now;
  }, true);

  window.MustrScan = { handle: handle, open: open, close: close };
})();
