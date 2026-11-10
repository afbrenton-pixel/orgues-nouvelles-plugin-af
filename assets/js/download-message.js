/**
 * Affiche le message « téléchargement lancé » après un clic sur un lien de
 * téléchargement gratuit (adresse contenant on_action=download_free).
 *
 * Le message est tout élément portant la classe CSS `on-dl-message`
 * (conteneur Elementor). Le style (couleurs, polices) reste dans Elementor ;
 * seule l'apparition est gérée ici.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest('a[href*="on_action=download_free"]') : null;
        if (!link) {
            return;
        }
        var messages = document.querySelectorAll('.on-dl-message');
        for (var i = 0; i < messages.length; i++) {
            messages[i].classList.add('is-visible');
            messages[i].setAttribute('role', 'status');
        }
    });
})();
