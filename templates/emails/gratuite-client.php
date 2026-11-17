<?php
/**
 * Free student subscription: e-mail to the person (HTML). One template for the confirmation of the address and
 * for the decision (acceptance or refusal, copy to the requester): the texts come from the translation functions.
 *
 * @type string $email_heading      Heading
 * @type string $greeting           "Bonjour Prénom," (or its translation)
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
