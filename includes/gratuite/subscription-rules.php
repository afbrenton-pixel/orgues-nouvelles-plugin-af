<?php
/**
 * Free student subscription, step 3: pure rules (no WordPress call, unit tested).
 */
defined( 'ABSPATH' ) || exit;

/**
 * What must be created for an accepted request.
 *
 * @param array $d        { langue, email, pour_autre, demandeur_id, client_id }
 * @param array $settings on_gratuite_settings()
 * @return array { variation_id, gift, buyer_id (0 = the beneficiary himself), beneficiary_id (0 = to create) }
 */
function on_gratuite_subscription_plan( array $d, array $settings ) {
    $gift = ! empty( $d['pour_autre'] ) && ! empty( $d['demandeur_id'] );
    return array(
        'variation_id'   => (int) ( 'en' === ( $d['langue'] ?? 'fr' ) ? $settings['variation_en'] : $settings['variation_fr'] ),
        'gift'           => $gift,
        'buyer_id'       => $gift ? (int) $d['demandeur_id'] : 0,
        'beneficiary_id' => (int) ( $d['client_id'] ?? 0 ),
    );
}

/** Amount of the (negative) fee line cancelling the price, rounded to the cent. */
function on_gratuite_fee_amount( $price, $percent ) {
    $price   = max( 0.0, (float) $price );
    $percent = min( 100.0, max( 0.0, (float) $percent ) );
    return -1 * round( $price * $percent / 100, 2 );
}

/** End of the one-year subscription, from a Unix timestamp (UTC), as 'Y-m-d H:i:s'. */
function on_gratuite_end_date( $start_ts ) {
    return gmdate( 'Y-m-d H:i:s', strtotime( '+1 year', (int) $start_ts ) );
}

/** Username derived from an e-mail address (before the @), made unique with a counter. */
function on_gratuite_username_from_email( $email, callable $exists ) {
    $parts = explode( '@', (string) $email );
    $base  = preg_replace( '/[^a-z0-9._-]/', '', strtolower( $parts[0] ) );
    $base  = '' === $base ? 'abonne' : $base;
    $name  = $base;
    $i     = 1;
    while ( $exists( $name ) ) {
        $name = $base . $i;
        ++$i;
    }
    return $name;
}

/** Same person if first name and last name are equal, ignoring case, accents and spaces. */
function on_gratuite_same_name( $first_a, $last_a, $first_b, $last_b ) {
    $norm = static function ( $s ) {
        $s = mb_strtolower( trim( (string) $s ) );
        $s = strtr( $s, array( 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c' ) );
        return preg_replace( '/[^a-z0-9]/', '', $s );
    };
    return '' !== $norm( $last_a ) && '' !== $norm( $first_a )
        && $norm( $first_a ) === $norm( $first_b ) && $norm( $last_a ) === $norm( $last_b );
}

/** Lines shown in the fiche when other accounts or fiches look like the same person. */
function on_gratuite_duplicate_lines( array $hints ) {
    $lines = array();
    foreach ( $hints as $h ) {
        $lines[] = sprintf( '%s n° %d (%s)', $h['kind'], (int) $h['id'], $h['label'] );
    }
    return $lines;
}
