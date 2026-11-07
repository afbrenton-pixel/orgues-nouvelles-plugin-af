<?php
/**
 * Free student subscription: WooCommerce e-mails (same mechanism as the "Demande de justificatif
 * étudiant" e-mail): site logo, colours and footer, texts editable in WooCommerce > Settings > Emails.
 *
 * - ON_Gratuite_Email_Confirmation : to the applicant (French or English, by the request's language);
 * - ON_Gratuite_Email_Notification : to the team when a request is confirmed.
 * The classes are defined only when WooCommerce's e-mail system is loaded (class-emails.php).
 */
defined( 'ABSPATH' ) || exit;

/** Register the e-mail classes. */
function on_gratuite_register_emails( $classes ) {
    require_once __DIR__ . '/class-emails.php';
    $classes['ON_Gratuite_Email_Confirmation'] = new ON_Gratuite_Email_Confirmation();
    $classes['ON_Gratuite_Email_Notification'] = new ON_Gratuite_Email_Notification();
    return $classes;
}
add_filter( 'woocommerce_email_classes', 'on_gratuite_register_emails' );

/** One of our e-mail objects, or null if WooCommerce e-mails are not available. */
function on_gratuite_wc_email( $class ) {
    if ( ! function_exists( 'WC' ) || ! WC() || ! method_exists( WC(), 'mailer' ) ) {
        return null;
    }
    $emails = WC()->mailer()->get_emails();
    return isset( $emails[ $class ] ) ? $emails[ $class ] : null;
}
