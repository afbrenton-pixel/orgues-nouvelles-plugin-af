<?php
/**
 * Orgues-Nouvelles Plugin
 *
 * @link              https://github.com/patou/orgues-nouvelles-plugin/
 * @since             1.0.0
 * @package           Orgues-Nouvelles Plugin
 *
 * @wordpress-plugin
 * Plugin Name:       Orgues-Nouvelles Plugin
 * Plugin URI:        https://github.com/patou/orgues-nouvelles-plugin/
 * Description:       Plugin pour ajouter des fonctionnalités au site orgues nouvelles.
 * Version:           1.0.0
 * Author:            Patrice de Saint Steban
 * Author URI:        https://patou.dev
 * License:           Apache-2.0
 * License URI:       http://www.apache.org/licenses/LICENSE-2.0
 * Text Domain:       orgues-nouvelles
 * Domain Path:       /languages
 */

define( 'ORGUES_NOUVELLES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

function on_load_plugin() {
    load_plugin_textdomain( 'orgues-nouvelles', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Charger le fichier core (PRIORITÉ 1 - nécessaire pour tous les autres fichiers)
    require_once(__DIR__ . '/includes/core/orgues-nouvelles.php');
    require_once(__DIR__ . '/includes/core/subscription-formule-choices.php');
    
    // Charger les modules WooCommerce Subscriptions (si le plugin est actif)
    if (is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php')) {
        require_once(__DIR__ . '/includes/subscriptions/bootstrap.php');
        require_once(__DIR__ . '/includes/subscriptions/update-subscriptions-price.php');
        if (defined('WP_CLI') && WP_CLI) {
            require_once(__DIR__ . '/includes/subscriptions/wp-cli-sync-memberships.php');
        }
    }

    // Charger l'intégration AcyMailing (déclencheurs WooCommerce Subscriptions)
    if (is_plugin_active('acymailing/index.php') && is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php')) {
        require_once(__DIR__ . '/includes/acymailing/bootstrap.php');
    }
    
    // Charger les modules de gestion des commandes WooCommerce
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        require_once(__DIR__ . '/includes/orders/complete-order.php');
        require_once(__DIR__ . '/includes/orders/check-abonnement-france.php');
        require_once(__DIR__ . '/includes/orders/require-shipping-formule-on.php');
        require_once(__DIR__ . '/includes/orders/student-proof-status.php');
        require_once(__DIR__ . '/includes/orders/justificatif-etudiant.php');
        require_once(__DIR__ . '/includes/orders/phone-orders.php');
        require_once(__DIR__ . '/includes/orders/email-subscription-info.php');
        require_once(__DIR__ . '/includes/orders/custom-export-type-filter.php');
        require_once(__DIR__ . '/includes/orders/subscription-link-button.php');
    }

    // Charger le module gratuité -26 ans (stockage privé des justificatifs, autorisations)
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        require_once(__DIR__ . '/includes/gratuite/bootstrap.php');
    }

    // Charger les modules de gestion des produits (WooCommerce seul)
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        require_once(__DIR__ . '/includes/products/check-download-product.php');
        require_once(__DIR__ . '/includes/products/direct-download-free.php');
    }
    
    // Charger les modules de gestion des produits (WooCommerce + Pods)
    if (is_plugin_active('woocommerce/woocommerce.php') && is_plugin_active('pods/init.php')) {
        require_once(__DIR__ . '/includes/products/product-free-for-plans.php');
        require_once(__DIR__ . '/includes/products/restrict-magazines.php');
    }

    // Charger les modules Pods transverses.
    if (is_plugin_active('pods/init.php')) {
        require_once(__DIR__ . '/includes/core/pods-pick-field-labels.php');
    }
    
    // Charger les modules frontend
    require_once(__DIR__ . '/includes/frontend/shortcode.php');
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        require_once(__DIR__ . '/includes/frontend/form-login.php');
    }
    if (is_plugin_active('woocommerce/woocommerce.php') && is_plugin_active('pods/init.php')) {
        require_once(__DIR__ . '/includes/frontend/mon-compte.php');
        require_once(__DIR__ . '/includes/frontend/subscription-shipping.php');
        require_once(__DIR__ . '/includes/frontend/subscription-address-sync.php');
    }
    if (is_plugin_active('polylang-pro/polylang.php') || is_plugin_active('polylang/polylang.php')) {
        require_once(__DIR__ . '/includes/frontend/search.php');
        require_once(__DIR__ . '/includes/frontend/url-ressources.php');
    }
    
    // Charger les modules admin
    require_once(__DIR__ . '/includes/admin/on-admin.php');
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        require_once(__DIR__ . '/includes/admin/edit-compte-gestionnaire.php');
    }
    
    // Charger les modules de contenu
    require_once(__DIR__ . '/includes/content/on-last-magazine.php');
    require_once(__DIR__ . '/includes/content/last-post-menu-item.php');
    require_once(__DIR__ . '/includes/content/video-thumbnail.php');

    add_action('admin_enqueue_scripts', 'on_admin_script');
    add_action('wp_enqueue_scripts', 'on_css_script');
    add_action( 'elementor/widgets/register', 'on_register_elementor_widget' );   
}

function on_admin_script($hook)
{
    wp_enqueue_style('on-style', plugin_dir_url(__FILE__) . 'assets/css/admin.css');
    /**if ($hook == 'user-edit.php') {
        wp_enqueue_style('on-edit-user-style', plugin_dir_url(__FILE__) . 'assets/css/edit-user.css');

        // Enqueue le script
        wp_enqueue_script('on-edit-user-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', array(), null, true);
    }*/
}

function on_css_script($hook)
{
    wp_enqueue_style('on-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
}


function on_register_elementor_widget( $widgets_manager ) {
    require_once(__DIR__ . '/includes/elementor/widgets/search-form-url.php');
    require_once(__DIR__ . '/includes/elementor/widgets/search-result-content.php');
	$widgets_manager->register( new \Search_Form_URL() );
    $widgets_manager->register( new \Search_Result_Content());
}

add_action( 'plugins_loaded', 'on_load_plugin' );
