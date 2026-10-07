// Filter any table that follows a [data-filter] search box.
document.querySelectorAll('[data-filter]').forEach(function (box) {
  box.addEventListener('input', function () {
    var term = box.value.trim().toLowerCase();
    document.querySelectorAll('[data-row]').forEach(function (tr) {
      tr.style.display = !term || tr.getAttribute('data-row').toLowerCase().indexOf(term) > -1 ? '' : 'none';
    });
    document.querySelectorAll('[data-group]').forEach(function (g) {
      var id = g.getAttribute('data-group');
      var vis = Array.prototype.some.call(
        document.querySelectorAll('[data-in="' + id + '"]'),
        function (r) { return r.style.display !== 'none'; });
      g.style.display = vis ? '' : 'none';
    });
  });
});
// Warn before leaving a half-finished sheet.
(function () {
  var f = document.querySelector('form[data-dirty]');
  if (!f) return;
  var dirty = false;
  f.addEventListener('input', function () { dirty = true; });
  f.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();

// Article picker: keep the hidden id in step with the typed name.
document.querySelectorAll('[data-article-input]').forEach(function (input) {
  var hidden = document.getElementById(input.getAttribute('data-article-input'));
  input.addEventListener('input', function () {
    var match = null;
    document.querySelectorAll('#articles option').forEach(function (o) {
      if (o.value === input.value) match = o;
    });
    if (hidden) hidden.value = match ? match.dataset.id : '';
  });
});

/* ------------------------------------------------------------------
   Installable app, offline safety net, and drafts kept on the device
   ------------------------------------------------------------------ */
(function () {
  var me = document.querySelector('script[src$="assets/app.js"]');
  var base = me ? me.getAttribute('src').replace(/assets\/app\.js.*$/, '') : '';

  // Service worker: fast start-up and a proper offline screen.
  if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(base + 'sw.php').catch(function () {});
    });
  }

  // "You're offline" strip.
  var strip = null;
  function setOffline(off) {
    if (off && !strip) {
      strip = document.createElement('div');
      strip.className = 'offline-strip';
      strip.setAttribute('role', 'status');
      strip.textContent = 'No connection. Keep going — numbers you type are kept on this device until you can save.';
      document.body.insertBefore(strip, document.body.firstChild.nextSibling);
    } else if (!off && strip) { strip.remove(); strip = null; }
  }
  window.addEventListener('offline', function () { setOffline(true); });
  window.addEventListener('online', function () { setOffline(false); });
  if (navigator.onLine === false) setOffline(true);

  // Drafts: every number typed on a count or waste sheet is copied to this device,
  // so a dropped connection or a closed tab never loses a count.
  var form = document.querySelector('form[data-draft]');
  if (form) {
    var key = 'mustr-draft:' + form.getAttribute('data-draft');
    var fields = function () { return form.querySelectorAll('input.qty[name], input[type=checkbox][name]'); };
    if (form.getAttribute('data-locked') === '1') {
      try { localStorage.removeItem(key); } catch (e) {}
    } else {
      // Only forget the device copy once the server has confirmed the save.
      try {
        if (sessionStorage.getItem('mustr-pending') === key) {
          sessionStorage.removeItem('mustr-pending');
          if (document.querySelector('.msg.ok')) localStorage.removeItem(key);
        }
      } catch (e) {}
      var saved = null;
      try { saved = JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) {}
      if (saved && saved.v) {
        var n = 0;
        fields().forEach(function (el) {
          if (!(el.name in saved.v) || el.readOnly) return;
          var val = saved.v[el.name];
          if (el.type === 'checkbox') { if (el.checked !== val) { el.checked = val; n++; } }
          else if (val !== '' && el.value !== val) { el.value = val; n++; }
        });
        if (n) {
          var note = document.createElement('div');
          note.className = 'msg warn';
          note.setAttribute('role', 'status');
          note.innerHTML = '<span><strong>' + n + (n === 1 ? ' number' : ' numbers') +
            ' restored</strong> from this device — typed earlier but not saved yet. Check them, then save.</span>';
          var discard = document.createElement('button');
          discard.type = 'button'; discard.className = 'btn sm'; discard.textContent = 'Discard';
          discard.style.marginLeft = 'auto';
          discard.addEventListener('click', function () { localStorage.removeItem(key); location.reload(); });
          note.appendChild(discard);
          form.parentNode.insertBefore(note, form);
        }
      }
      var timer = null;
      var store = function () {
        var v = {};
        fields().forEach(function (el) { v[el.name] = el.type === 'checkbox' ? el.checked : el.value; });
        try { localStorage.setItem(key, JSON.stringify({ v: v, at: Date.now() })); } catch (e) {}
      };
      form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(store, 250); });
      form.addEventListener('change', store);
      form.addEventListener('submit', function (e) {
        if (navigator.onLine === false) {
          e.preventDefault();
          store();
          setOffline(true);
          alert('No connection right now. Your numbers are kept on this device — press Save again once you have signal.');
          return;
        }
        store();                                     // keep a copy until the server confirms it
        try { sessionStorage.setItem('mustr-pending', key); } catch (e) {}
      });
    }
  }

  // "Install app" card on the home screen.
  var card = document.querySelector('[data-install]');
  if (card) {
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var dismissed = false;
    try { dismissed = localStorage.getItem('mustr-install-dismissed') === '1'; } catch (e) {}
    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (!standalone && !dismissed) {
      var deferred = null, btn = card.querySelector('[data-install-btn]');
      window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault(); deferred = e; card.hidden = false; btn.hidden = false;
      });
      btn.addEventListener('click', function () {
        if (!deferred) return;
        deferred.prompt();
        deferred.userChoice.then(function () { card.hidden = true; deferred = null; });
      });
      if (ios) {
        card.querySelector('[data-install-text]').innerHTML =
          'On iPhone or iPad: open this site in <strong>Safari</strong>, tap <strong>Share</strong>, then ' +
          '<strong>Add to Home Screen</strong>. It opens full-screen like any other app.';
        card.hidden = false;
      }
      window.addEventListener('appinstalled', function () { card.hidden = true; });
    }
    card.querySelector('[data-install-dismiss]').addEventListener('click', function () {
      card.hidden = true;
      try { localStorage.setItem('mustr-install-dismissed', '1'); } catch (e) {}
    });
  }
})();

/* Count sheet: "3 of 10 counted" keeps up as you type or scan. */
(function () {
  var out = document.querySelector('[data-progress]');
  var form = out && out.closest('form');
  if (!form) return;
  var update = function () {
    var boxes = form.querySelectorAll('input.qty[name]'), done = 0;
    boxes.forEach(function (b) { if (b.value.trim() !== '') done++; });
    out.textContent = done + ' of ' + boxes.length + ' counted';
  };
  form.addEventListener('input', update);
  update();
})();
