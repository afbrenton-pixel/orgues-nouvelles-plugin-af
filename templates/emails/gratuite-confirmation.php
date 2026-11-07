<?php
/**
 * Free student subscription: confirmation of the address (HTML).
 *
 * @type string $email_heading      Heading
 * @type string $greeting           "Bonjour Prénom," or "Hello Name,"
 * @type string $email_body         Body, already formatted (HTML)
 * @type string $additional_content Additional content (WooCommerce setting)
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p><?php echo esc_html( $greeting ); ?></p>
<?php
echo wp_kses_post( $email_body );
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
do_action( 'woocommerce_email_footer', $email );
