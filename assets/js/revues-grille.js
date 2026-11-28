/* Choix d'achat des revues : choix exclusifs papier / numérique, total, ajout au panier.
   Fonctionne avec les éléments « Choix d'achat du numéro » et « Total du choix » et
   un bouton Elementor portant la classe « onr-add ». Écoute le document entier :
   rien à relancer quand la grille se recharge. */
(function () {
  function cfg() {
    var t = document.querySelector('.onr-total');
    return t ? t.dataset : {};
  }

  function update() {
    var c = cfg();
    var on = document.querySelectorAll('.onr-choice input:checked');
    var total = 0;
    Array.prototype.forEach.call(on, function (i) { total += Number(i.dataset.p) || 0; });
    var text = on.length
      ? on.length + ' ' + (on.length > 1 ? c.many : c.one) + ' · ' + (Math.round(total * 100) / 100) + ' ' + (c.currency || '€')
      : (c.empty || '');
    Array.prototype.forEach.call(document.querySelectorAll('.onr-total'), function (t) {
      t.querySelector('.onr-rec').textContent = text;
      t.querySelector('.onr-msg').textContent = '';
    });
  }

  function message(text) {
    Array.prototype.forEach.call(document.querySelectorAll('.onr-total .onr-msg'), function (m) { m.textContent = text; });
  }

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input.matches || !input.matches('.onr-choice input')) { return; }
    if (input.checked) {
      var k = input.dataset.k;
      var other = k === 'pn' ? 'n' : (k === 'n' ? 'pn' : null);
      if (other) {
        var x = document.querySelector('.onr-choice input[data-g="' + input.dataset.g + '"][data-k="' + other + '"]');
        if (x) { x.checked = false; }
      }
    }
    update();
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.onr-add') : null;
    if (!btn) { return; }
    e.preventDefault();
    var c = cfg();
    var on = Array.prototype.slice.call(document.querySelectorAll('.onr-choice input:checked'))
      .filter(function (i) { return Number(i.dataset.id) > 0; });
    if (!on.length) { message(c.choose || ''); return; }
    btn.classList.add('onr-busy');
    var chain = Promise.resolve();
    on.forEach(function (i) {
      chain = chain.then(function () {
        var f = new FormData();
        f.append('product_id', i.dataset.id);
        f.append('quantity', '1');
        return fetch(c.ajax || '/?wc-ajax=add_to_cart', { method: 'POST', body: f, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) { if (!j || j.error) { throw new Error('add'); } });
      });
    });
    chain.then(function () { window.location.href = c.cart || '/'; })
      .catch(function () { btn.classList.remove('onr-busy'); message(c.error || ''); });
  });
})();
