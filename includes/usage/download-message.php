<?php

/**
 * Message affiché après un clic sur « Télécharger ».
 *
 * Le message est un conteneur Elementor ordinaire, auquel le designer donne la
 * classe CSS `on-dl-message` (onglet Avancé > Classes CSS). Il est masqué par
 * défaut sur le site (jamais dans l'éditeur Elementor) et apparaît au clic sur
 * un lien de téléchargement. Voir docs/comptage-usage.md.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'on_usage_enqueue_download_message');

function on_usage_enqueue_download_message()
{
    // Feuille de style propre, versionnée par la date du fichier : style.css est
    // chargé sans version et reste donc en cache dans les navigateurs.
    wp_enqueue_style(
        'on-download-message',
        plugin_dir_url(ORGUES_NOUVELLES_PLUGIN_DIR . 'orgues-nouvelles-plugin.php') . 'assets/css/download-message.css',
        array(),
        filemtime(ORGUES_NOUVELLES_PLUGIN_DIR . 'assets/css/download-message.css')
    );
    wp_enqueue_script(
        'on-download-message',
        plugin_dir_url(ORGUES_NOUVELLES_PLUGIN_DIR . 'orgues-nouvelles-plugin.php') . 'assets/js/download-message.js',
        array(),
        filemtime(ORGUES_NOUVELLES_PLUGIN_DIR . 'assets/js/download-message.js'),
        true
    );
}
