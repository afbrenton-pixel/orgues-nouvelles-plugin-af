<?php

/**
 * Compteur de lectures et de téléchargements : table dédiée `on_usage`.
 *
 * Une ligne par client, par produit et par type (`read` ou `download`).
 * Voir docs/comptage-usage.md.
 *
 * La table est créée (ou mise à jour) automatiquement quand la version de
 * structure change, sans passer par l'activation du plugin : les mises à jour
 * par WP Pusher ne déclenchent pas d'activation.
 */

if (!defined('ON_USAGE_DB_VERSION')) {
    define('ON_USAGE_DB_VERSION', '1');
}

/**
 * Nom complet de la table (avec le préfixe WordPress).
 */
function on_usage_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'on_usage';
}

/**
 * Crée la table si elle n'existe pas ou si sa structure a changé.
 */
function on_usage_maybe_install()
{
    if (get_option('on_usage_db_version') === ON_USAGE_DB_VERSION) {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = on_usage_table_name();
    $charset = $wpdb->get_charset_collate();

    // dbDelta est exigeant sur le format : deux espaces après PRIMARY KEY,
    // un champ par ligne.
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        product_id bigint(20) unsigned NOT NULL,
        numero varchar(32) NOT NULL DEFAULT '',
        magazine varchar(64) NOT NULL DEFAULT '',
        type varchar(16) NOT NULL,
        count int(10) unsigned NOT NULL DEFAULT 0,
        first_at datetime NOT NULL,
        last_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_product_type (user_id,product_id,type),
        KEY numero (numero),
        KEY last_at (last_at)
    ) {$charset};";

    dbDelta($sql);
    update_option('on_usage_db_version', ON_USAGE_DB_VERSION);
}
add_action('init', 'on_usage_maybe_install', 1);
