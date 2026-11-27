<?php

/**
 * Affichage d'un élément Elementor selon la situation du visiteur sur la page d'un numéro
 * (magazine ou cahier de partitions).
 *
 * Quatre choix s'ajoutent au réglage « Abonnés Magazines » (onglet Avancé de tout
 * élément, liste « Visibilité aux magazines »), pour poser les mêmes boutons dans tous
 * les modèles de page :
 *
 *  - pas connecté ;
 *  - connecté, sans droit de lire ce numéro ;
 *  - connecté, avec droit de lire ce numéro (abonné, acheteur, numéro gratuit) ;
 *  - peut télécharger le PDF gratuitement.
 *
 * Le droit est celui de l'élément « Lecteur PDF en ligne » et de la lecture en ligne
 * (on_usage_page_product), donc le même pour tous les boutons. Dans l'éditeur
 * Elementor, tout reste visible.
 *
 * Voir docs/lecture-en-ligne.md.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Situations proposées dans la liste « Visibilité aux magazines » : clé => texte. */
function on_usage_situation_choices()
{
    return array(
        'on_situation_anonyme'        => __('Seulement si le visiteur n\'est pas connecté', 'orgues-nouvelles'),
        'on_situation_sans_droit'     => __('Seulement si connecté, sans droit de lire ce numéro', 'orgues-nouvelles'),
        'on_situation_papier'         => __('Seulement si connecté, sans droit, et si la version papier est en vente', 'orgues-nouvelles'),
        'on_situation_sans_lecture'   => __('Seulement si le visiteur ne peut pas lire ce numéro (non connecté ou sans droit)', 'orgues-nouvelles'),
        'on_situation_lecture'        => __('Seulement si connecté, avec droit de lire ce numéro', 'orgues-nouvelles'),
        'on_situation_telechargement' => __('Seulement si le PDF est téléchargeable gratuitement', 'orgues-nouvelles'),
    );
}

/** Ajoute les situations à la liste existante. */
function on_usage_situation_add_choices($options)
{
    return array_merge((array) $options, on_usage_situation_choices());
}

/**
 * La page affichée est-elle visible dans cette situation ?
 *
 * @param string $situation Clé de on_usage_situation_choices().
 * @return bool
 */
function on_usage_situation_matches($situation)
{
    if ($situation === 'on_situation_anonyme') {
        return !is_user_logged_in();
    }
    if ($situation === 'on_situation_sans_lecture') {
        return !is_user_logged_in() || !on_usage_page_product('', true);
    }
    if (!is_user_logged_in()) {
        return false;
    }
    if ($situation === 'on_situation_lecture') {
        return (bool) on_usage_page_product('', true);
    }
    if ($situation === 'on_situation_sans_droit') {
        // Une page sans produit relié n'a rien à acheter : on ne propose pas le bouton.
        return on_usage_page_product_id(get_the_ID()) > 0 && !on_usage_page_product('', true);
    }
    if ($situation === 'on_situation_papier') {
        return !on_usage_page_product('', true) && on_usage_paper_product_url() !== '';
    }
    if ($situation === 'on_situation_telechargement') {
        return on_usage_download_url() !== '';
    }
    return false;
}

/** Filtre elementor/frontend/.../should_render : n'agit que sur les quatre situations. */
function on_usage_situation_should_render($should_render, $element)
{
    $condition = $element->get_settings(ON_VISIBILITY_CONDITION);
    if (!is_string($condition) || strpos($condition, 'on_situation_') !== 0) {
        return $should_render;
    }
    if (is_preview() || \Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return $should_render;
    }
    return $should_render && on_usage_situation_matches($condition);
}

/** Adresse de la fiche produit du numéro (page de la boutique), ou ''. */
function on_usage_product_page_url()
{
    $product_id = on_usage_page_product_id(get_the_ID());
    if (!$product_id) {
        return '';
    }
    $product = wc_get_product($product_id);
    if (!$product || $product->get_status() !== 'publish') {
        return '';
    }
    return (string) get_permalink($product_id);
}

/**
 * Adresse de la fiche de la version papier du numéro, ou ''.
 *
 * Le produit papier est un produit publié, non téléchargeable, dont le champ
 * Pods « Magazine » désigne la page affichée. Ce même champ donne au client
 * l'accès en ligne après l'achat (on_ajouter_numero_apres_achat).
 */
function on_usage_paper_product_url()
{
    $page_id = get_the_ID();
    if (!$page_id || !function_exists('wc_get_product')) {
        return '';
    }
    $ids = get_posts(array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'fields'         => 'ids',
        'meta_query'     => array(array('key' => 'magazine', 'value' => (string) $page_id)),
        'suppress_filters' => true,
    ));
    foreach ($ids as $id) {
        $product = wc_get_product($id);
        if ($product && !$product->is_downloadable() && !$product->is_virtual()) {
            return (string) get_permalink($id);
        }
    }
    return '';
}

/**
 * Adresse du PDF d'extrait du numéro (champ Pods « pdf_sommaire » de la page), ou ''.
 *
 * L'adresse est celle du site courant (fichier de la médiathèque), pour que le lecteur
 * puisse l'ouvrir sans changer de domaine. L'extrait se lit sans compte ni droit.
 */
function on_usage_extract_url()
{
    $page_id = get_the_ID();
    if (!$page_id) {
        return '';
    }
    $value = function_exists('pods_field')
        ? pods_field(get_post_type($page_id), $page_id, 'pdf_sommaire', true)
        : get_post_meta($page_id, 'pdf_sommaire', true);
    if (is_array($value)) {
        $value = isset($value['ID']) ? $value['ID'] : reset($value);
    } elseif (is_object($value)) {
        $value = isset($value->ID) ? $value->ID : 0;
    }
    $attachment_id = is_numeric($value) ? (int) $value : 0;
    if (!$attachment_id || get_post_type($attachment_id) !== 'attachment') {
        return '';
    }
    $url = wp_get_attachment_url($attachment_id);
    return $url ? (string) $url : '';
}

/**
 * Adresse d'une page reliée à celle du numéro par un champ Pods (« page_cd », « page_partitions »), ou ''.
 *
 * @param string $field Nom du champ de la page du numéro.
 * @return string
 */
function on_usage_related_page_url($field)
{
    $page_id = get_the_ID();
    if (!$page_id) {
        return '';
    }
    $value = function_exists('pods_field')
        ? pods_field(get_post_type($page_id), $page_id, $field, true)
        : get_post_meta($page_id, $field, true);
    if (is_array($value)) {
        $value = isset($value['ID']) ? $value['ID'] : reset($value);
        if (is_array($value)) {
            $value = isset($value['ID']) ? $value['ID'] : 0;
        }
    } elseif (is_object($value)) {
        $value = isset($value->ID) ? $value->ID : 0;
    }
    $id = is_numeric($value) ? (int) $value : 0;
    if (!$id || get_post_status($id) !== 'publish') {
        return '';
    }
    return (string) get_permalink($id);
}

/**
 * Image à la une d'une page reliée à celle du numéro (« page_cd », « page_partitions »), pour une balise d'image.
 *
 * @param string $field Nom du champ de la page du numéro.
 * @return array{id:int,url:string}|array
 */
function on_usage_related_page_image($field)
{
    $url = on_usage_related_page_url($field);
    $id  = $url ? url_to_postid($url) : 0;
    if (!$id) {
        return array();
    }
    $thumb = (int) get_post_thumbnail_id($id);
    if (!$thumb) {
        return array();
    }
    $src = wp_get_attachment_url($thumb);
    return $src ? array('id' => $thumb, 'url' => $src) : array();
}

/** Adresse de connexion qui revient sur la page du numéro. */
function on_usage_login_url()
{
    return wp_login_url(get_permalink());
}

add_filter('on_visibility_condition_options', 'on_usage_situation_add_choices');
add_filter('elementor/frontend/widget/should_render', 'on_usage_situation_should_render', 5, 2);
add_filter('elementor/frontend/section/should_render', 'on_usage_situation_should_render', 5, 2);
add_filter('elementor/frontend/container/should_render', 'on_usage_situation_should_render', 5, 2);

/**
 * Boutons du bloc « Actions du numéro » (classe CSS on-actions sur le conteneur) :
 * même largeur, même hauteur, même police pour tous, quel que soit le bouton affiché.
 */
function on_usage_actions_css()
{
    echo '<style id="on-actions-css">'
        . '.on-actions{align-items:stretch}'
        . '.on-actions>.elementor-widget{flex:1 1 190px;max-width:260px;width:auto;margin:0}'
        . '.on-actions .elementor-button,.on-actions .on-rd-open{box-sizing:border-box;width:100%;min-height:44px;justify-content:center;text-align:center;border-radius:4px;padding:12px 20px;font:600 16px/1.2 "Open Sans",system-ui,sans-serif}'
        . '.on-actions .elementor-button-text,.on-actions .elementor-button-content-wrapper{justify-content:center}'
        . '.on-actions .on-reader{width:100%}'
        . '</style>';
}
add_action('wp_head', 'on_usage_actions_css', 99);
