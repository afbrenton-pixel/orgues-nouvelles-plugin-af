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
    return apply_filters( 'on_gratuite_settings', array(
        'age_first'          => 26,   // First request: strictly under this age.
        'age_renew'          => 27,   // Yearly renewal: strictly under this age.
        'renew_window_days'  => 60,   // Renewal allowed this many days before the end (enforced in step 5).
        'variation_fr'       => 257,  // "1 an / Étudiant" (test site IDs, to confirm on production).
        'variation_en'       => 30365, // "1 year / Student".
        'discount_percent'   => 100,
        'fee_label'          => 'Gratuité -26 ans',
        'notify_email'       => 'etudiant@orgues-nouvelles.org',
        'form_page_id'       => 49840, // French page with the form (the English one is found through Polylang).
        'rate_limit_per_hour' => 5,   // Requests per IP and per hour.
        'confirm_days'       => 7,    // The e-mail confirmation link is valid this many days.
        'auto_purge_unconfirmed' => false, // false = manual purge only (decision of 7 November; automatic purge to check with the webmaster).
    ) );
}
