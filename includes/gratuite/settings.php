<?php
/**
 * Free student subscription: settings.
 *
 * Everything reads the settings through on_gratuite_settings(). The values that differ from one
 * environment to another (form page, subscription variations, team address, rate limit) are edited by
 * the team on the Pods settings page "Gratuité -26 ans : réglages" (options `on_gratuite_reglages_<field>`);
 * the values below are only the defaults used when a field is empty. The other values (ages, delays, labels)
 * stay in the code on purpose (decision of 7 November 2026: the operation is not meant to be permanent).
 * The filter `on_gratuite_settings` allows tests.
 */
defined( 'ABSPATH' ) || exit;

function on_gratuite_settings() {
    $settings = array(
        'age_first'          => 27,   // First request: strictly under this age, i.e. until the 27th birthday (decision of 9 November 2026: same rule as the renewal).
        'age_renew'          => 27,   // Yearly renewal: strictly under this age, i.e. until the 27th birthday.
        'renew_window_days'  => 60,   // Renewal allowed this many days before the end (enforced in step 5).
        'renew_enabled'      => false, // Renewal workflow (step 5) not built yet: a request from someone already accepted is refused. Step 5 switches this on.
        'variation_fr'       => 257,  // Default: variation "1 an / Étudiant". Edited on the settings page (a variation ID can differ between sites).
        'variation_en'       => 30365, // Default: variation "1 year / Student". Edited on the settings page.
        'discount_percent'   => 100,
        'fee_label'          => 'Gratuité -26 ans',
        'notify_email'       => 'etudiant@orgues-nouvelles.org', // Default. Edited on the settings page.
        'form_page_id'       => 0,    // Page with the form, chosen on the settings page (0 = not set: the refusal e-mail then links to the home page). The page in the other language is found through Polylang.
        'rate_limit_per_hour' => 5,   // Default: requests per IP and per hour. Edited on the settings page.
        'confirm_days'       => 7,    // The e-mail confirmation link is valid this many days.
        'auto_purge_unconfirmed' => true,  // Daily purge of the requests never confirmed within `confirm_days` (decision of 10 November: the site uses WP-Cron, confirmed by the webmaster). The manual purge link stays available.
        'promo_start'        => '',   // First requests open from this date (YYYY-MM-DD, included). Empty = no start limit.
        'promo_end'          => '',   // First requests open until this date (YYYY-MM-DD, included). Empty = no end limit. Renewals are never limited by these dates.
    );
    // Values edited by the team on the Pods settings page "Gratuité -26 ans : réglages"
    // (settings pod `on_gratuite_reglages`, stored as options `on_gratuite_reglages_<field>`).
    if ( function_exists( 'get_option' ) ) {
        // Promotional period (dates YYYY-MM-DD).
        foreach ( array( 'promo_start' => 'promo_debut', 'promo_end' => 'promo_fin' ) as $key => $field ) {
            $value = get_option( 'on_gratuite_reglages_' . $field, '' );
            if ( is_string( $value ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}/', $value ) ) {
                $settings[ $key ] = substr( $value, 0, 10 );
            }
        }
        // Numbers: an empty or invalid field keeps the default above.
        foreach ( array( 'variation_fr' => 'variation_fr', 'variation_en' => 'variation_en', 'rate_limit_per_hour' => 'limite_par_heure' ) as $key => $field ) {
            $id = on_gratuite_option_id( get_option( 'on_gratuite_reglages_' . $field, '' ) );
            if ( $id > 0 ) {
                $settings[ $key ] = $id;
            }
        }
        // Page of the form (relationship field).
        $settings['form_page_id'] = on_gratuite_option_id( get_option( 'on_gratuite_reglages_page_formulaire', '' ) );
        // Team address.
        $email = get_option( 'on_gratuite_reglages_email_equipe', '' );
        if ( is_string( $email ) && '' !== trim( $email ) && false !== strpos( $email, '@' ) ) {
            $settings['notify_email'] = trim( $email );
        }
    }
    return apply_filters( 'on_gratuite_settings', $settings );
}

/**
 * A positive whole number read from a Pods option: a number field gives "257", a relationship field
 * gives an ID, a list of IDs or a list of posts (the first one is used). Anything else gives 0.
 */
function on_gratuite_option_id( $value ) {
    if ( is_array( $value ) ) {
        $value = reset( $value );
        if ( is_array( $value ) ) {
            $value = $value['ID'] ?? ( $value['id'] ?? 0 );
        } elseif ( is_object( $value ) ) {
            $value = $value->ID ?? 0;
        }
    }
    return ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) && (int) $value > 0 ? (int) $value : 0;
}
