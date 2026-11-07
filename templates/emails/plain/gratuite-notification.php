<?php
/**
 * Free student subscription: new confirmed request, to the team (plain text).
 */
defined( 'ABSPATH' ) || exit;

echo $email_heading . "\n\n";
echo "----------\n\n";
echo implode( "\n", $lines ) . "\n\n";
echo "----------\n\n";
if ( $additional_content ) {
    echo wptexturize( $additional_content ) . "\n\n";
}
echo (string) apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text', '' ) );
