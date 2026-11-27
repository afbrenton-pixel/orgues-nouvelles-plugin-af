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
 * Le produit est celui du champ Pods « Téléchargement » de la page (magazine ou
 * cahier) : un champ qui désigne le produit WooCommerce du bon numéro et de la
 * bonne langue. Aucune règle sur le titre ou le numéro du produit. La balise
 * reste vide si le client n'a pas le droit (produit non gratuit pour lui) ou si
 * la page n'a pas de produit.
 *
 * Voir docs/comptage-usage.md.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Le client connecté a-t-il déjà acheté ce produit (commande payée) ?
 *
 * Sert à lire en ligne un cahier vendu seul : il n'est pas « gratuit » pour le
 * client (le champ Magazine du produit est vide, voir on_ajouter_numero_apres_achat),
 * mais il l'a payé.
 *
 * @param WC_Product $product Produit.
 * @return bool
 */
function on_usage_customer_owns($product)
{
    $user = wp_get_current_user();
    if (!$user || !$user->ID || !$product || !function_exists('wc_customer_bought_product')) {
        return false;
    }
    return (bool) wc_customer_bought_product($user->user_email, $user->ID, $product->get_id());
}

/**
 * Identifiant du produit du champ « Téléchargement » d'une page, ou 0.
 *
 * Le champ Pods est une relation vers un produit ; selon la façon de le lire,
 * Pods rend un identifiant, un tableau (clé « ID ») ou un objet.
 *
 * @param int $post_id Page (magazine ou cahier de partitions).
 * @return int
 */
function on_usage_page_product_id($post_id)
{
    $value = function_exists('pods_field')
        ? pods_field(get_post_type($post_id), $post_id, 'telechargement', true)
        : get_post_meta($post_id, 'telechargement', true);
    if (empty($value)) {
        $value = get_post_meta($post_id, 'telechargement', true);
    }
    if (is_object($value)) {
        $value = isset($value->ID) ? $value->ID : 0;
    } elseif (is_array($value)) {
        $value = isset($value['ID']) ? $value['ID'] : reset($value);
        if (is_array($value)) {
            $value = isset($value['ID']) ? $value['ID'] : 0;
        }
    }
    return is_numeric($value) ? (int) $value : 0;
}

/**
 * Produit à lire ou télécharger pour la page courante et le client connecté.
 *
 * C'est le produit du champ « Téléchargement » de la page, à condition qu'il
 * soit publié, téléchargeable et gratuit pour ce client (la règle de
 * product-free-for-plans.php), ou déjà acheté quand $allow_owned est vrai.
 *
 * @param string $unused      Ancien paramètre (« le nom du produit contient »), ignoré : le produit ne se choisit plus par son nom.
 * @param bool   $allow_owned Accepter aussi un produit payant déjà acheté par ce client
 *                            (lecture en ligne ; pas pour le téléchargement gratuit, qui refuse un produit payant).
 * @return WC_Product|null
 */
function on_usage_page_product($unused = '', $allow_owned = false)
{
    if (!is_user_logged_in() || !function_exists('wc_get_product')) {
        return null;
    }

    $post_id = get_the_ID();
    if (!$post_id) {
        return null;
    }

    $product_id = on_usage_page_product_id($post_id);
    if (!$product_id) {
        return null;
    }

    $product = wc_get_product($product_id);
    if (!$product || $product->get_status() !== 'publish' || !$product->is_downloadable()) {
        return null;
    }
    if ((float) $product->get_price() > 0 && !($allow_owned && on_usage_customer_owns($product))) {
        return null;
    }
    return $product;
}

/** Adresse de lecture du produit, ou ''. */
function on_usage_read_url()
{
    $product = on_usage_page_product('', true);
    if (!$product) {
        return '';
    }
    return add_query_arg(array(
        'on_action'  => 'read_pdf',
        'product_id' => $product->get_id(),
    ), home_url('/'));
}

/** Adresse de téléchargement du produit, ou ''. */
function on_usage_download_url()
{
    $product = on_usage_page_product();
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
            /** Commun aux deux balises : groupe et catégorie (adresse). */
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
                    return __('Adresse de lecture', 'orgues-nouvelles');
                }

                public function get_value(array $options = array())
                {
                    return esc_url_raw(on_usage_read_url());
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
                    return __('Adresse de téléchargement', 'orgues-nouvelles');
                }

                public function get_value(array $options = array())
                {
                    return esc_url_raw(on_usage_download_url());
                }
            }
        }

        if (!class_exists('ON_Usage_Product_Page_Url_Tag')) {
            class ON_Usage_Product_Page_Url_Tag extends ON_Usage_Url_Tag_Base
            {
                public function get_name()
                {
                    return 'on-usage-fiche';
                }

                public function get_title()
                {
                    return __('Fiche du numéro (boutique)', 'orgues-nouvelles');
                }

                public function get_value(array $options = array())
                {
                    return esc_url_raw(on_usage_product_page_url());
                }
            }
        }

        if (!class_exists('ON_Usage_Paper_Url_Tag')) {
            class ON_Usage_Paper_Url_Tag extends ON_Usage_Url_Tag_Base
            {
                public function get_name()
                {
                    return 'on-usage-papier';
                }

                public function get_title()
                {
                    return __('Fiche de la version papier', 'orgues-nouvelles');
                }

                public function get_value(array $options = array())
                {
                    return esc_url_raw(on_usage_paper_product_url());
                }
            }
        }

        if (!class_exists('ON_Usage_Extract_Url_Tag')) {
            class ON_Usage_Extract_Url_Tag extends ON_Usage_Url_Tag_Base
            {
                public function get_name()
                {
                    return 'on-usage-extrait';
                }

                public function get_title()
                {
                    return __('Extrait PDF du numéro', 'orgues-nouvelles');
                }

                public function get_value(array $options = array())
                {
                    return esc_url_raw(on_usage_extract_url());
                }
            }
        }

        if (!class_exists('ON_Usage_Page_Cd_Url_Tag')) {
            class ON_Usage_Page_Cd_Url_Tag extends ON_Usage_Url_Tag_Base
            {
                public function get_name()
                {
                    return 'on-usage-page-cd';
                }

                public function get_title()
                {
                    return __('Page du CD du numéro', 'orgues-nouvelles');
                }

                public function get_value(array $options = array())
                {
                    return esc_url_raw(on_usage_related_page_url('page_cd'));
                }
            }
        }

        if (!class_exists('ON_Usage_Page_Partitions_Url_Tag')) {
            class ON_Usage_Page_Partitions_Url_Tag extends ON_Usage_Url_Tag_Base
            {
                public function get_name()
                {
                    return 'on-usage-page-partitions';
                }

                public function get_title()
                {
                    return __('Page du cahier de partitions du numéro', 'orgues-nouvelles');
                }

                public function get_value(array $options = array())
                {
                    return esc_url_raw(on_usage_related_page_url('page_partitions'));
                }
            }
        }

        if (!class_exists('ON_Usage_Image_Tag_Base')) {
            /** Image à la une de la page du CD ou du cahier du numéro. */
            abstract class ON_Usage_Image_Tag_Base extends \Elementor\Core\DynamicTags\Data_Tag
            {
                public function get_group()
                {
                    return 'site';
                }

                public function get_categories()
                {
                    return array(\Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY);
                }

                abstract protected function field();

                public function get_value(array $options = array())
                {
                    return on_usage_related_page_image($this->field());
                }
            }
        }

        if (!class_exists('ON_Usage_Image_Cd_Tag')) {
            class ON_Usage_Image_Cd_Tag extends ON_Usage_Image_Tag_Base
            {
                public function get_name()
                {
                    return 'on-usage-image-cd';
                }

                public function get_title()
                {
                    return __('Image du CD du numéro', 'orgues-nouvelles');
                }

                protected function field()
                {
                    return 'page_cd';
                }
            }
        }

        if (!class_exists('ON_Usage_Image_Partitions_Tag')) {
            class ON_Usage_Image_Partitions_Tag extends ON_Usage_Image_Tag_Base
            {
                public function get_name()
                {
                    return 'on-usage-image-partitions';
                }

                public function get_title()
                {
                    return __('Image du cahier de partitions du numéro', 'orgues-nouvelles');
                }

                protected function field()
                {
                    return 'page_partitions';
                }
            }
        }

        if (!class_exists('ON_Usage_Login_Url_Tag')) {
            class ON_Usage_Login_Url_Tag extends ON_Usage_Url_Tag_Base
            {
                public function get_name()
                {
                    return 'on-usage-connexion';
                }

                public function get_title()
                {
                    return __('Adresse de connexion (retour sur cette page)', 'orgues-nouvelles');
                }

                public function get_value(array $options = array())
                {
                    return esc_url_raw(on_usage_login_url());
                }
            }
        }

        $dynamic_tags->register(new ON_Usage_Read_Url_Tag());
        $dynamic_tags->register(new ON_Usage_Download_Url_Tag());
        $dynamic_tags->register(new ON_Usage_Product_Page_Url_Tag());
        $dynamic_tags->register(new ON_Usage_Paper_Url_Tag());
        $dynamic_tags->register(new ON_Usage_Extract_Url_Tag());
        $dynamic_tags->register(new ON_Usage_Page_Cd_Url_Tag());
        $dynamic_tags->register(new ON_Usage_Page_Partitions_Url_Tag());
        $dynamic_tags->register(new ON_Usage_Image_Cd_Tag());
        $dynamic_tags->register(new ON_Usage_Image_Partitions_Tag());
        $dynamic_tags->register(new ON_Usage_Login_Url_Tag());
    }
);
