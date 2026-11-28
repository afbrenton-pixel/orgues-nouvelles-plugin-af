/* Grille des revues : total, choix exclusifs papier / numérique, ajout au panier. */
(function () {
  function init(root) {
    if (root.dataset.onrReady) { return; }
    root.dataset.onrReady = '1';
    var rec = root.querySelector('.onr-rec');
    var msg = root.querySelector('.onr-msg');
    var btn = root.querySelector('.onr-btn');
    var sym = root.dataset.currency || '€';

    function update(e) {
      if (e && e.target.checked) {
        var k = e.target.dataset.k;
        var other = k === 'pn' ? 'n' : (k === 'n' ? 'pn' : null);
        if (other) {
          var x = root.querySelector('input[data-g="' + e.target.dataset.g + '"][data-k="' + other + '"]');
          if (x) { x.checked = false; }
        }
      }
      var on = root.querySelectorAll('input:checked');
      var total = 0;
      Array.prototype.forEach.call(on, function (i) { total += Number(i.dataset.p) || 0; });
      rec.textContent = on.length
        ? on.length + ' ' + (on.length > 1 ? root.dataset.many : root.dataset.one) + ' · ' + (Math.round(total * 100) / 100) + ' ' + sym
        : root.dataset.empty;
      msg.textContent = '';
    }

    root.addEventListener('change', update);

    btn.addEventListener('click', function () {
      var on = Array.prototype.slice.call(root.querySelectorAll('input:checked'));
      if (!on.length) { msg.textContent = root.dataset.choose; return; }
      btn.disabled = true;
      var chain = Promise.resolve();
      on.forEach(function (i) {
        chain = chain.then(function () {
          var f = new FormData();
          f.append('product_id', i.dataset.id);
          f.append('quantity', '1');
          return fetch(root.dataset.ajax, { method: 'POST', body: f, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (!j || j.error) { throw new Error('add'); } });
        });
      });
      chain.then(function () { window.location.href = root.dataset.cart; })
        .catch(function () { btn.disabled = false; msg.textContent = root.dataset.error; });
    });
  }

  function boot() { Array.prototype.forEach.call(document.querySelectorAll('.onr'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
  if (window.jQuery && window.elementorFrontend) {
    window.jQuery(window).on('elementor/frontend/init', function () {
      window.elementorFrontend.hooks.addAction('frontend/element_ready/on-revues-grille.default', function ($el) { boot(); });
    });
  }
})();
