<?php
/**
 * Free student subscription: settings.
 *
 * TEMPORARY: values are hard-coded on purpose (decision of 7 November 2026, the
 * operation is not meant to be permanent). Everything reads them through
 * on_gratuite_settings(), so a Pods settings page can replace this function
 * later without touching the rest. The filter `on_gratuite_settings` allows tests.
 */
defined( 'ABSPATH' ) || exit;

function on_gratuite_settings() {
    $settings = array(
        'age_first'          => 27,   // First request: strictly under this age, i.e. until the 27th birthday (decision of 9 November 2026: same rule as the renewal).
        'age_renew'          => 27,   // Yearly renewal: strictly under this age, i.e. until the 27th birthday.
        'renew_window_days'  => 60,   // Renewal allowed this many days before the end (enforced in step 5).
        'renew_enabled'      => false, // Renewal workflow (step 5) not built yet: a request from someone already accepted is refused. Step 5 switches this on.
        'variation_fr'       => 257,  // "1 an / Étudiant" (test site IDs, to confirm on production).
        'variation_en'       => 30365, // "1 year / Student".
        'discount_percent'   => 100,
        'fee_label'          => 'Gratuité -26 ans',
        'notify_email'       => 'etudiant@orgues-nouvelles.org',
        'form_page_id'       => 49840, // French page with the form (the English one is found through Polylang).
        'rate_limit_per_hour' => 5,   // Requests per IP and per hour.
        'confirm_days'       => 7,    // The e-mail confirmation link is valid this many days.
        'auto_purge_unconfirmed' => true,  // Daily purge of the requests never confirmed within `confirm_days` (decision of 10 November: the site uses WP-Cron, confirmed by the webmaster). The manual purge link stays available.
        'promo_start'        => '',   // First requests open from this date (YYYY-MM-DD, included). Empty = no start limit.
        'promo_end'          => '',   // First requests open until this date (YYYY-MM-DD, included). Empty = no end limit. Renewals are never limited by these dates.
    );
    // The promotional period is edited by the team on the Pods settings page "Gratuité -26 ans : réglages"
    // (settings pod `on_gratuite_reglages`, date fields `promo_debut` and `promo_fin`, stored as options).
    if ( function_exists( 'get_option' ) ) {
        foreach ( array( 'promo_start' => 'promo_debut', 'promo_end' => 'promo_fin' ) as $key => $field ) {
            $value = get_option( 'on_gratuite_reglages_' . $field, '' );
            if ( is_string( $value ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}/', $value ) ) {
                $settings[ $key ] = substr( $value, 0, 10 );
            }
        }
    }
    return apply_filters( 'on_gratuite_settings', $settings );
}
