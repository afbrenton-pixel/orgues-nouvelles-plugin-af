<?php
/**
 * Free student subscription: confirmation of the address (plain text).
 */
defined( 'ABSPATH' ) || exit;

echo $email_heading . "\n\n";
echo "----------\n\n";
echo $greeting . "\n\n";
echo wptexturize( $email_body ) . "\n\n";
echo "----------\n\n";
if ( $additional_content ) {
    echo wptexturize( $additional_content ) . "\n\n";
}
echo (string) apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text', '' ) );
