<?php

/**
 * Balises dynamiques Elementor pour les boutons de la fiche d'un numéro :
 *
 *  - « Adresse de lecture »        -> /?on_action=read_pdf&product_id=…
 *  - « Adresse de téléchargement » -> /?on_action=download_free&product_id=…&_wpnonce=…
 *
 * Le designer les choisit dans le champ « lien » d'un bouton Elementor
 * ordinaire (icône de balise dynamique), comme « Lien de la vignette ».
 *
 * Le produit est retrouvé automatiquement : même numéro (champ Pods `numero`)
 * que la page, dans la langue de la page, téléchargeable, et gratuit pour le
 * client connecté (la règle de product-free-for-plans.php). Quand la page
 * propose plusieurs produits (magazine, cahier de musique…), le champ
 * « Le nom du produit contient » de la balise choisit le bon (ex. CahPart).
 * Pas de produit gratuit pour ce client : la balise reste vide.
 *
 * Voir docs/comptage-usage.md.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Produit à lire ou télécharger pour la page courante et le client connecté.
 *
 * @param string $motif Texte que le nom du produit doit contenir (facultatif).
 * @return WC_Product|null
 */
function on_usage_page_product($motif = '')
{
    if (!is_user_logged_in() || !function_exists('wc_get_product')) {
        return null;
    }

    $post_id = get_the_ID();
    if (!$post_id) {
        return null;
    }

    // Numéro de la page : champ Pods `numero` du contenu courant.
    $numero = function_exists('pods_field') ? pods_field(get_post_type($post_id), $post_id, 'numero', true) : get_post_meta($post_id, 'numero', true);
    if (!is_scalar($numero) || $numero === '') {
        return null;
    }

    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 30,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
            array('key' => 'numero', 'value' => (string) $numero),
        ),
    );
    // Polylang : les produits français et anglais sont des produits distincts.
    if (function_exists('pll_current_language')) {
        $args['lang'] = pll_current_language();
    }

    $motif = trim((string) $motif);
    foreach (get_posts($args) as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_downloadable() || (float) $product->get_price() > 0) {
            continue;
        }
        if ($motif !== '' && stripos($product->get_name(), $motif) === false) {
            continue;
        }
        return $product;
    }

    return null;
}

/** Adresse de lecture du produit, ou ''. */
function on_usage_read_url($motif = '')
{
    $product = on_usage_page_product($motif);
    if (!$product) {
        return '';
    }
    return add_query_arg(array(
        'on_action'  => 'read_pdf',
        'product_id' => $product->get_id(),
    ), home_url('/'));
}

/** Adresse de téléchargement du produit, ou ''. */
function on_usage_download_url($motif = '')
{
    $product = on_usage_page_product($motif);
    if (!$product) {
        return '';
    }
    return add_query_arg(array(
        'on_action'  => 'download_free',
        'product_id' => $product->get_id(),
        '_wpnonce'   => wp_create_nonce('on_download_free_' . $product->get_id()),
    ), home_url('/'));
}

add_action(
    'elementor/dynamic_tags/register',
    function ($dynamic_tags) {
        if (!class_exists('\Elementor\Core\DynamicTags\Data_Tag') || !defined('ELEMENTOR_VERSION')) {
            return;
        }

        if (!class_exists('ON_Usage_Url_Tag_Base')) {
            /** Commun aux deux balises : le champ « Le nom du produit contient ». */
            abstract class ON_Usage_Url_Tag_Base extends \Elementor\Core\DynamicTags\Data_Tag
            {
                public function get_group()
                {
                    return 'site';
                }

                public function get_categories()
                {
                    return array(\Elementor\Modules\DynamicTags\Module::URL_CATEGORY);
                }

                protected function register_controls()
                {
                    $this->add_control(
                        'motif',
                        array(
                            'label'       => 'Le nom du produit contient',
                            'type'        => \Elementor\Controls_Manager::TEXT,
                            'default'     => '',
                            'description' => 'Laisser vide s\'il n\'y a qu\'un produit pour ce numéro. Sinon, un morceau du nom, par exemple CahPart ou ONED.',
                        )
                    );
                }
            }
        }

        if (!class_exists('ON_Usage_Read_Url_Tag')) {
            class ON_Usage_Read_Url_Tag extends ON_Usage_Url_Tag_Base
            {
                public function get_name()
                {
                    return 'on-usage-lecture';
                }

                public function get_title()
                {
                    return 'Adresse de lecture';
                }

                public function get_value(array $options = array())
                {
                    return esc_url_raw(on_usage_read_url($this->get_settings('motif')));
                }
            }
        }

        if (!class_exists('ON_Usage_Download_Url_Tag')) {
            class ON_Usage_Download_Url_Tag extends ON_Usage_Url_Tag_Base
            {
                public function get_name()
                {
                    return 'on-usage-telechargement';
                }

                public function get_title()
                {
                    return 'Adresse de téléchargement';
                }

                public function get_value(array $options = array())
                {
                    return esc_url_raw(on_usage_download_url($this->get_settings('motif')));
                }
            }
        }

        $dynamic_tags->register(new ON_Usage_Read_Url_Tag());
        $dynamic_tags->register(new ON_Usage_Download_Url_Tag());
    }
);
