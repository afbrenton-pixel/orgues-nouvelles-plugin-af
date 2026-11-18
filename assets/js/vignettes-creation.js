/* Page « Créer les vignettes » : simulation puis création par lots (voir includes/vignettes/creation-en-masse.php).
   Les textes viennent de PHP (window.ON_BULK), rien n'est écrit en dur ici. */
(function () {
  var T = window.ON_BULK || {};
  var form = document.getElementById('on-bulk-form');
  if (!form) { return; }
  var btnSim = document.getElementById('on-bulk-simulate');
  var btnRun = document.getElementById('on-bulk-run');
  var result = document.getElementById('on-bulk-result');
  var box = document.getElementById('on-bulk-progress');
  var bar = document.getElementById('on-bulk-bar');
  var count = document.getElementById('on-bulk-count');
  var log = document.getElementById('on-bulk-log');
  var busy = false;

  function fmt(s) {
    var a = Array.prototype.slice.call(arguments, 1), i = 0;
    return s.replace(/%(\d)\$[ds]|%[ds]/g, function (m, n) { return a[n ? n - 1 : i++]; });
  }
  function el(tag, text, attrs) {
    var e = document.createElement(tag);
    if (text !== undefined && text !== null) { e.textContent = text; }
    for (var k in (attrs || {})) { e.setAttribute(k, attrs[k]); }
    return e;
  }
  function params(step) {
    var fd = new FormData(form);
    var data = new URLSearchParams();
    data.append('action', 'on_vignettes_bulk');
    data.append('nonce', T.nonce);
    data.append('step', step);
    fd.getAll('types[]').forEach(function (v) { data.append('types[]', v); });
    data.append('since', fd.get('since') || '');
    data.append('exclude', fd.get('exclude') || '');
    data.append('only_missing', fd.get('only_missing') ? '1' : '0');
    return data;
  }
  function post(step) {
    return fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: params(step) })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.success) { throw new Error((j && j.data && j.data.message) || ''); }
        return j.data;
      });
  }
  function showError(e) {
    result.textContent = '';
    var msg = e && e.message ? fmt(T.error, e.message) : T.network;
    result.appendChild(el('div', msg, { 'class': 'notice notice-error inline' }));
    busy = false;
    btnSim.disabled = false;
  }
  function table(rows) {
    var t = el('table', null, { 'class': 'widefat striped', style: 'max-width:640px' });
    var head = el('tr');
    [T.colType, T.colTotal, T.colHas, T.colTodo].forEach(function (h) { head.appendChild(el('th', h)); });
    t.appendChild(el('thead')).appendChild(head);
    var body = el('tbody');
    rows.forEach(function (r) {
      var tr = el('tr');
      [r.label, r.total, r.has, r.todo].forEach(function (c) { tr.appendChild(el('td', String(c))); });
      body.appendChild(tr);
    });
    t.appendChild(body);
    return t;
  }

  // Tout changement de réglage oblige à refaire la simulation.
  form.addEventListener('change', function () { if (!busy) { btnRun.disabled = true; } });
  form.addEventListener('input', function () { if (!busy) { btnRun.disabled = true; } });

  btnSim.addEventListener('click', function () {
    if (busy) { return; }
    busy = true;
    btnSim.disabled = true;
    btnRun.disabled = true;
    box.style.display = 'none';
    log.textContent = '';
    result.textContent = T.simulating;
    post('simulate').then(function (d) {
      result.textContent = '';
      result.appendChild(table(d.table));
      if (!d.todo) {
        result.appendChild(el('p', T.nothing));
      } else {
        result.appendChild(el('p', fmt(T.toDo, d.todo)));
        if (d.sample && d.sample.length) {
          result.appendChild(el('p', T.sampleTitle));
          var ul = el('ul', null, { style: 'list-style:disc;margin-left:20px' });
          d.sample.forEach(function (s) { ul.appendChild(el('li', s)); });
          result.appendChild(ul);
        }
        btnRun.disabled = false;
      }
      busy = false;
      btnSim.disabled = false;
    }).catch(showError);
  });

  btnRun.addEventListener('click', function () {
    if (busy) { return; }
    busy = true;
    btnSim.disabled = true;
    btnRun.disabled = true;
    log.textContent = '';
    result.textContent = T.running;
    var totals = { created: 0, updated: 0, draft: 0, skipped: 0, failed: 0 };
    post('start').then(function (d) {
      var total = d.total;
      box.style.display = 'block';
      bar.style.width = '0';
      count.textContent = fmt(T.progress, 0, total);
      function next() {
        return post('step').then(function (s) {
          for (var k in totals) { totals[k] += (s.stats[k] || 0); }
          (s.last || []).forEach(function (l) {
            if (l.result === 'failed' || l.result === 'draft') {
              log.appendChild(el('div', (l.result === 'failed' ? T.failed : T.draft) + ' : ' + l.title));
            }
          });
          var pct = total ? Math.round(100 * s.done / total) : 100;
          bar.style.width = pct + '%';
          count.textContent = fmt(T.progress, s.done, s.total);
          return s.done < s.total ? next() : null;
        });
      }
      return next();
    }).then(function () {
      return post('finish');
    }).then(function () {
      result.textContent = '';
      var parts = ['created', 'updated', 'draft', 'skipped', 'failed'].map(function (k) { return totals[k] + ' ' + T[k]; });
      result.appendChild(el('div', T.finished + ' ' + parts.join(', ') + '.', { 'class': 'notice notice-success inline' }));
      if (totals.draft) { result.appendChild(el('p', T.draftsNotice)); }
      busy = false;
      btnSim.disabled = false;
    }).catch(showError);
  });
})();
