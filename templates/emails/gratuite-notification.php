<?php
/**
 * Free student subscription: new confirmed request, to the team (HTML).
 *
 * @type string   $email_heading Heading
 * @type string[] $lines         Lines of the message (the last one is the address of the record)
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

foreach ( $lines as $line ) {
    if ( '' === $line ) {
        continue;
    }
    if ( 0 === strpos( $line, 'http' ) ) {
        echo '<p><a href="' . esc_url( $line ) . '">' . esc_html( $line ) . '</a></p>';
    } else {
        echo '<p>' . esc_html( $line ) . '</p>';
    }
}
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
do_action( 'woocommerce_email_footer', $email );
