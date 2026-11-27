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

/** Adresse de connexion qui revient sur la page du numéro. */
function on_usage_login_url()
{
    return wp_login_url(get_permalink());
}

add_filter('on_visibility_condition_options', 'on_usage_situation_add_choices');
add_filter('elementor/frontend/widget/should_render', 'on_usage_situation_should_render', 5, 2);
add_filter('elementor/frontend/section/should_render', 'on_usage_situation_should_render', 5, 2);
add_filter('elementor/frontend/container/should_render', 'on_usage_situation_should_render', 5, 2);
