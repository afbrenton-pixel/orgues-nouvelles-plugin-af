<?php
/**
 * Free student subscription: notice about the promotional period, shown on the form page.
 *
 * - Shortcode [on_gratuite_periode]: prints a message when the offer is not open yet or is over
 *   (nothing while it is open). The texts are the ones the form already gives when a request is
 *   refused for that reason (in French, English in the .po file), the dates come from the settings page.
 * - When the offer is closed, the form widgets that carry the CSS class "on-gratuite-formulaire"
 *   (Elementor, Advanced > CSS classes) are hidden. A form without that class stays visible.
 * - Administrators can preview the three states on the page: add ?gratuite_apercu=avant, apres or ouvert.
 */
defined( 'ABSPATH' ) || exit;

/** Language of the page: 'en' or 'fr'. */
function on_gratuite_period_page_lang() {
    $lang = function_exists( 'pll_current_language' ) ? pll_current_language() : substr( (string) get_locale(), 0, 2 );
    return 'en' === $lang ? 'en' : 'fr';
}

/**
 * State of the offer today: array( 'open'|'not_yet'|'closed', date of the limit YYYY-MM-DD or '' ).
 * An administrator can force a state with ?gratuite_apercu= (display only, nothing is saved).
 */
function on_gratuite_period_view() {
    $s     = on_gratuite_settings();
    $start = (string) ( $s['promo_start'] ?? '' );
    $end   = (string) ( $s['promo_end'] ?? '' );
    $state = on_gratuite_promo_state( $start, $end, on_gratuite_today() );
    if ( isset( $_GET['gratuite_apercu'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
        $map = array( 'avant' => 'not_yet', 'apres' => 'closed', 'ouvert' => 'open' );
        $key = sanitize_key( wp_unslash( $_GET['gratuite_apercu'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $map[ $key ] ) ) {
            $state = $map[ $key ];
        }
    }
    return array( $state, 'not_yet' === $state ? $start : $end );
}

/** The notice for a state (empty while the offer is open). */
function on_gratuite_period_notice_html( $state, $date, $lang ) {
    if ( 'not_yet' !== $state && 'closed' !== $state ) {
        return '';
    }
    $text = on_gratuite_message( 'promo_' . $state, $lang, 0, on_gratuite_promo_date_text( $date, $lang ) );
    return '<p class="on-gratuite-avis on-gratuite-avis--' . esc_attr( $state ) . '" role="status">' . esc_html( $text ) . '</p>';
}

function on_gratuite_period_shortcode() {
    list( $state, $date ) = on_gratuite_period_view();
    return on_gratuite_period_notice_html( $state, $date, on_gratuite_period_page_lang() );
}
add_shortcode( 'on_gratuite_periode', 'on_gratuite_period_shortcode' );

/** Hides the marked form while the offer is closed, and styles the notice. */
function on_gratuite_period_head_css() {
    if ( ! is_singular( 'page' ) ) {
        return;
    }
    list( $state ) = on_gratuite_period_view();
    echo '<style id="on-gratuite-periode">.on-gratuite-avis{padding:1em 1.25em;border-left:4px solid #b32d2e;background:#f6f7f7;font-weight:600;margin:0 0 1em}';
    if ( 'open' !== $state ) {
        echo '.on-gratuite-formulaire{display:none!important}';
    }
    echo '</style>' . "\n";
}
add_action( 'wp_head', 'on_gratuite_period_head_css' );
