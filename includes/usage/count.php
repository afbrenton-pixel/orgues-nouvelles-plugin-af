<?php

/**
 * Compteur de lectures et de téléchargements : fonction d'incrément.
 *
 * Voir docs/comptage-usage.md.
 */

/**
 * Types d'usage comptés.
 *
 * @return string[]
 */
function on_usage_types()
{
    return array('read', 'download');
}

/**
 * Numéro et magazine du produit, copiés dans la ligne de comptage pour
 * pouvoir interroger sans jointure.
 *
 * - numero   : champ Pods `numero` du produit (ex. 73)
 * - magazine : identifiant (slug) du contenu `magazine` lié au produit
 *
 * @param int $product_id
 * @return array{numero:string,magazine:string}
 */
function on_usage_product_info($product_id)
{
    $info = array('numero' => '', 'magazine' => '');

    if (!function_exists('pods_field')) {
        return $info;
    }

    $numero = pods_field('product', $product_id, 'numero', true);
    if (is_scalar($numero)) {
        $info['numero'] = (string) $numero;
    }

    $magazine = pods_field('product', $product_id, 'magazine', true);
    if (is_array($magazine) && !empty($magazine['post_name'])) {
        $info['magazine'] = (string) $magazine['post_name'];
    }

    return $info;
}

/**
 * Compte un usage : ajoute 1 à la ligne (client, produit, type), qu'elle
 * soit nouvelle ou déjà existante.
 *
 * Cette fonction ne vérifie PAS le droit d'accès : l'appelant (adresse de
 * lecture ou de téléchargement) l'a déjà fait. Elle ne compte jamais un
 * client non identifié.
 *
 * @param int    $user_id    Le client (compte WordPress).
 * @param int    $product_id Le produit WooCommerce lu ou téléchargé.
 * @param string $type       `read` ou `download`.
 * @return bool true si l'usage a été compté.
 */
function on_usage_record($user_id, $product_id, $type)
{
    $user_id = absint($user_id);
    $product_id = absint($product_id);

    if ($user_id <= 0 || $product_id <= 0 || !in_array($type, on_usage_types(), true)) {
        return false;
    }

    on_usage_maybe_install();

    global $wpdb;
    $table = on_usage_table_name();
    $info = on_usage_product_info($product_id);
    $now = gmdate('Y-m-d H:i:s'); // dates en UTC

    // Un seul ordre SQL : atomique, même si deux clics arrivent ensemble.
    $sql = $wpdb->prepare(
        "INSERT INTO {$table} (user_id, product_id, numero, magazine, type, count, first_at, last_at)
         VALUES (%d, %d, %s, %s, %s, 1, %s, %s)
         ON DUPLICATE KEY UPDATE count = count + 1, last_at = VALUES(last_at),
             numero = VALUES(numero), magazine = VALUES(magazine)",
        $user_id,
        $product_id,
        $info['numero'],
        $info['magazine'],
        $type,
        $now,
        $now
    );

    $result = $wpdb->query($sql);
    if ($result === false) {
        error_log(sprintf('on_usage_record: échec (user %d, produit %d, type %s) : %s', $user_id, $product_id, $type, $wpdb->last_error));
        return false;
    }

    do_action('on_usage_recorded', $user_id, $product_id, $type);
    return true;
}
