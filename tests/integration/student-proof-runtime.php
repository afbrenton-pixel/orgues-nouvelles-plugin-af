<?php
/**
 * Local-only integration probe: wp eval-file <this file> [cleanup].
 * Uses real WC_Order transitions and WCS, but no gateway or outbound mail.
 */
defined( 'WP_CLI' ) && WP_CLI || exit;
if ( ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
    WP_CLI::error( 'Run only in a local/development environment.' );
}

/** Track explicitly created fixtures so cleanup never touches shared orders. */
function on_student_proof_runtime_track( $type, $id ) {
    $fixtures = get_option( 'on_student_proof_runtime_fixtures', array() );
    $fixtures[ $type ][] = $id;
    update_option( 'on_student_proof_runtime_fixtures', $fixtures, false );
}

/** Fail on the first behavioral mismatch. */
function on_student_proof_runtime_expect( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    WP_CLI::log( 'PASS: ' . $message );
}

/** Create a real parent order and related subscription with isolated users. */
function on_student_proof_runtime_pair( $product, $customer_id, $recipient_id = 0 ) {
    $order = wc_create_order( array( 'customer_id' => $customer_id, 'created_via' => 'on-student-proof-runtime' ) );
    on_student_proof_runtime_track( 'orders', $order->get_id() );
    $order->set_billing_email( 'student-proof@example.test' );
    $order->set_payment_method( 'cheque' );
    $order->add_product( $product, 1 );
    $order->calculate_totals();
    $order->save();
    $subscription = wcs_create_subscription( array(
        'order_id' => $order->get_id(),
        'customer_id' => $customer_id,
        'billing_period' => 'year',
        'billing_interval' => 1,
        'status' => 'pending',
        'start_date' => gmdate( 'Y-m-d H:i:s' ),
    ) );
    if ( is_wp_error( $subscription ) ) {
        throw new RuntimeException( $subscription->get_error_message() );
    }
    on_student_proof_runtime_track( 'subscriptions', $subscription->get_id() );
    $subscription->add_product( $product, 1 );
    $subscription->set_billing_email( 'student-proof@example.test' );
    $subscription->set_requires_manual_renewal( true );
    $subscription->update_meta_data( 'number-start', 100 );
    $subscription->update_meta_data( 'number-end', 103 );
    $subscription->update_meta_data( 'on_formule', 'ONED' );
    if ( $recipient_id ) {
        $subscription->update_meta_data( '_recipient_user', $recipient_id );
    }
    $subscription->calculate_totals();
    $subscription->save();
    return array( $order, $subscription );
}

$fixtures = get_option( 'on_student_proof_runtime_fixtures', array() );
if ( isset( $args[0] ) && 'cleanup' === $args[0] ) {
    foreach ( array( 'subscriptions', 'orders', 'products' ) as $type ) {
        foreach ( $fixtures[ $type ] ?? array() as $fixture_id ) {
            $fixture = 'products' === $type ? wc_get_product( $fixture_id ) : wc_get_order( $fixture_id );
            if ( $fixture ) {
                $fixture->delete( true );
            }
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ( $fixtures['users'] ?? array() as $fixture_id ) {
        wp_delete_user( $fixture_id );
    }
    delete_option( 'on_student_proof_runtime_fixtures' );
    WP_CLI::success( 'Only tracked student-proof fixtures deleted.' );
    return;
}
if ( $fixtures ) {
    WP_CLI::error( 'Clean up the previous fixtures before running again.' );
}

$mail_success = true;
$proof_sent = 0;
$payments = array();
$renewal_payments = array();
add_filter( 'pre_wp_mail', function () use ( &$mail_success ) { return $mail_success; } );
add_action( 'woocommerce_email_sent', function ( $sent, $email_id ) use ( &$proof_sent ) {
    if ( $sent && 'justificatif_etudiant' === $email_id ) {
        ++$proof_sent;
    }
}, 10, 2 );
add_action( 'woocommerce_subscription_payment_complete', function ( $subscription ) use ( &$payments ) {
    $payments[ $subscription->get_id() ] = ( $payments[ $subscription->get_id() ] ?? 0 ) + 1;
}, 100 );
add_action( 'woocommerce_subscription_renewal_payment_complete', function ( $subscription ) use ( &$renewal_payments ) {
    $renewal_payments[ $subscription->get_id() ] = ( $renewal_payments[ $subscription->get_id() ] ?? 0 ) + 1;
}, 100 );

$email = WC()->mailer()->get_emails()['WC_Email_Justificatif_Etudiant'];
$original_enabled = $email->enabled;
try {
    $token = strtolower( wp_generate_password( 10, false ) );
    $customer_id = wp_create_user( 'proof-donor-' . $token, wp_generate_password(), 'proof-donor-' . $token . '@example.test' );
    $recipient_id = wp_create_user( 'proof-recipient-' . $token, wp_generate_password(), 'proof-recipient-' . $token . '@example.test' );
    if ( is_wp_error( $customer_id ) || is_wp_error( $recipient_id ) ) {
        throw new RuntimeException( 'Could not create isolated test users.' );
    }
    on_student_proof_runtime_track( 'users', $customer_id );
    on_student_proof_runtime_track( 'users', $recipient_id );
    $parent = new WC_Product_Variable_Subscription();
    $parent->set_name( 'Student proof runtime fixture ' . $token );
    $parent->set_status( 'private' );
    $parent->set_catalog_visibility( 'hidden' );
    $parent->save();
    on_student_proof_runtime_track( 'products', $parent->get_id() );
    $product = new WC_Product_Subscription_Variation();
    $product->set_parent_id( $parent->get_id() );
    $product->set_status( 'publish' );
    $product->set_virtual( true );
    $product->set_regular_price( '10' );
    $product->set_price( '10' );
    $product->update_meta_data( '_demande_justificatif', 'yes' );
    $product->update_meta_data( '_subscription_price', '10' );
    $product->update_meta_data( '_subscription_period', 'year' );
    $product->update_meta_data( '_subscription_period_interval', 1 );
    $product->save();
    on_student_proof_runtime_track( 'products', $product->get_id() );

    list( $order, $subscription ) = on_student_proof_runtime_pair( $product, $customer_id, $recipient_id );
    $email->enabled = 'no';
    do_action( 'woocommerce_checkout_order_created', $order );
    on_student_proof_runtime_expect( on_student_proof_is_waiting( $order ) && ! $order->get_meta( '_on_student_proof_request_sent', true ), 'Disabled email leaves proof required.' );
    $email->enabled = 'yes';
    $mail_success = false;
    on_student_proof_request_email( $order );
    on_student_proof_runtime_expect( ! $order->get_meta( '_on_student_proof_request_sent', true ), 'Failed email is not marked sent.' );
    $mail_success = true;
    on_student_proof_request_email( $order );
    on_student_proof_request_email( $order );
    on_student_proof_runtime_expect( 1 === $proof_sent && (bool) $order->get_meta( '_on_student_proof_request_sent', true ), 'Checkout proof email succeeds once.' );
    $order->payment_complete( 'on-proof-runtime' );
    $order = wc_get_order( $order->get_id() );
    $subscription = wcs_get_subscription( $subscription->get_id() );
    on_student_proof_runtime_expect( $order->has_status( 'awaiting-proof' ) && $order->get_date_paid() && ! $order->is_paid() && ! $order->needs_payment(), 'Real payment is captured but awaiting-proof is not paid and cannot be paid twice.' );
    on_student_proof_runtime_expect( $subscription->has_status( 'pending' ) && empty( $payments[ $subscription->get_id() ] ), 'Payment alone does not activate native WCS (gift recipient fixture).' );
    $order->payment_complete( 'repeat-callback' );
    on_student_proof_approve( $order, 1 );
    $subscription = wcs_get_subscription( $subscription->get_id() );
    on_student_proof_runtime_expect( wc_get_order( $order->get_id() )->has_status( 'processing' ) && $subscription->has_status( 'active' ) && 1 === $payments[ $subscription->get_id() ], 'Approval releases virtual order as processing and activates WCS exactly once.' );
    on_student_proof_runtime_expect( ! on_student_proof_approve( $order, 1 ), 'Repeated approval is inert.' );

    $before_end = (int) $subscription->get_meta( 'number-end', true );
    $renewal = wcs_create_renewal_order( $subscription );
    on_student_proof_runtime_track( 'orders', $renewal->get_id() );
    $subscription = wcs_get_subscription( $subscription->get_id() );
    on_student_proof_runtime_expect( on_student_proof_is_waiting( $renewal ) && $subscription->has_status( 'on-hold' ) && 2 === $proof_sent, 'Renewal has fresh proof, sends one email and suspends related subscription.' );
    $renewal->payment_complete();
    $subscription = wcs_get_subscription( $subscription->get_id() );
    on_student_proof_runtime_expect( $subscription->has_status( 'on-hold' ) && $before_end === (int) $subscription->get_meta( 'number-end', true ) && empty( $renewal_payments[ $subscription->get_id() ] ), 'Paid renewal waits without activation or issue-number extension.' );
    on_student_proof_approve( $renewal, 1 );
    $subscription = wcs_get_subscription( $subscription->get_id() );
    on_student_proof_runtime_expect( $subscription->has_status( 'active' ) && 1 === $renewal_payments[ $subscription->get_id() ] && (int) $subscription->get_meta( 'number-end', true ) > $before_end, 'Approved paid renewal resumes and extends issue numbers once through native WCS.' );
    $after_end = (int) $subscription->get_meta( 'number-end', true );
    on_student_proof_approve( $renewal, 1 );
    $renewal->payment_complete();
    on_student_proof_runtime_expect( $after_end === (int) wcs_get_subscription( $subscription->get_id() )->get_meta( 'number-end', true ), 'Repeated renewal callbacks do not extend again.' );

    list( $unpaid, $unpaid_subscription ) = on_student_proof_runtime_pair( $product, $customer_id );
    $unpaid->update_status( 'on-hold' );
    on_student_proof_approve( $unpaid, 1 );
    on_student_proof_runtime_expect( $unpaid->has_status( 'on-hold' ) && ! $unpaid->get_date_paid() && wcs_get_subscription( $unpaid_subscription->get_id() )->has_status( 'pending' ), 'Unpaid approval restores on-hold without activation.' );
    $unpaid->payment_complete();
    on_student_proof_runtime_expect( $unpaid->has_status( 'processing' ) && wcs_get_subscription( $unpaid_subscription->get_id() )->has_status( 'active' ), 'Later native payment activates and keeps processing.' );

    $subscription->update_status( 'cancelled' );
    $ended_renewal = wcs_create_renewal_order( $subscription );
    on_student_proof_runtime_track( 'orders', $ended_renewal->get_id() );
    $ended_renewal->payment_complete();
    on_student_proof_approve( $ended_renewal, 1 );
    on_student_proof_runtime_expect( wcs_get_subscription( $subscription->get_id() )->has_status( 'cancelled' ), 'Cancelled subscription is not revived by approval/payment.' );

    list( $preview_order, $preview_subscription ) = on_student_proof_runtime_pair( $product, $customer_id );
    $preview_order->payment_complete();
    list( $unpaid_preview, $unpaid_preview_subscription ) = on_student_proof_runtime_pair( $product, $customer_id );
    WP_CLI::log( 'BROWSER_FIXTURES=' . wp_json_encode( array( 'paid' => $preview_order->get_id(), 'paid_subscription' => $preview_subscription->get_id(), 'unpaid' => $unpaid_preview->get_id(), 'unpaid_subscription' => $unpaid_preview_subscription->get_id() ) ) );
    WP_CLI::success( 'Runtime integration passed; tracked fixtures retained for browser checks. Run again with cleanup afterwards.' );
} finally {
    $email->enabled = $original_enabled;
}