<?php

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {}
}

require_once dirname( __DIR__ ) . '/includes/orders/student-proof-status.php';
require_once dirname( __DIR__ ) . '/includes/orders/complete-order.php';

if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
    function wcs_get_subscriptions_for_order( $order, $args = array() ) {
        $GLOBALS['on_proof_relation_query'] = $args;
        return $GLOBALS['on_proof_related_subscriptions'] ?? array();
    }
}
if ( ! function_exists( 'wcs_get_subscription_ended_statuses' ) ) {
    function wcs_get_subscription_ended_statuses() { return array( 'cancelled', 'expired', 'switched', 'trash' ); }
}
if ( ! function_exists( 'get_post_type_object' ) ) {
    function get_post_type_object( $type ) { return (object) array( 'cap' => (object) array( 'edit_post' => 'edit_shop_order' ) ); }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability, $order_id = 0 ) { return $GLOBALS['on_proof_test_can_edit'] ?? false; }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, $action ) { return $nonce === 'nonce:' . $action; }
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
}
if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
    function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=nonce:' . $action; }
}
if ( ! function_exists( 'WC' ) ) {
    function WC() { return new ON_Student_Proof_Test_Mailer(); }
}

class StudentProofStatusTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['on_proof_related_subscriptions'] = array();
        $GLOBALS['on_proof_test_can_edit'] = true;
        $GLOBALS['on_proof_test_email_attempts'] = 0;
        $GLOBALS['on_proof_test_email_enabled'] = true;
        $GLOBALS['on_proof_test_email_success'] = true;
    }

    public function test_paid_order_is_blocked_without_losing_payment() {
        $order = new ON_Student_Proof_Test_Order();
        $order->status = 'completed';
        on_student_proof_before_save( $order );
        $this->assertSame( 'awaiting-proof', $order->status );
        $this->assertNotNull( $order->paid );
        $this->assertNull( $order->completed );
        $this->assertFalse( on_student_proof_needs_payment( true, $order ) );
        $this->assertNotContains( 'awaiting-proof', on_student_proof_payment_statuses( array( 'pending' ), $order ) );
        $this->assertTrue( on_student_proof_approve( $order, 12 ) );
        $this->assertSame( 'processing', $order->status );
        $this->assertContains( 'awaiting-proof', on_student_proof_payment_statuses( array( 'pending' ), $order ) );
        $this->assertFalse( on_student_proof_approve( $order, 12 ) );
        $this->assertSame( 1, $order->saves );
    }

    public function test_unpaid_approval_restores_gateway_status() {
        $order = new ON_Student_Proof_Test_Order();
        $order->status = 'on-hold';
        on_student_proof_before_save( $order );
        $this->assertSame( 'awaiting-proof', $order->status );
        $this->assertNull( $order->paid );
        $this->assertContains( 'awaiting-proof', on_student_proof_payment_statuses( array(), $order ) );
        on_student_proof_approve( $order, 12 );
        $this->assertSame( 'on-hold', $order->status );
        $this->assertNull( $order->paid );
        $this->assertSame( 'processing', on_student_proof_paid_status( 'completed', 123, $order ) );
        $order->status = 'processing';
        on_student_proof_before_save( $order );
        $this->assertSame( 'processing', $order->status );
    }

    public function test_existing_orders_are_not_migrated() {
        $order = new ON_Student_Proof_Test_Order();
        $order->id = 123;
        $order->status = 'processing';
        on_student_proof_before_save( $order );
        $this->assertSame( 'processing', $order->status );
        $this->assertSame( '', $order->get_meta( '_on_student_proof_required', true ) );
    }

    public function test_snapshot_and_terminal_statuses_are_preserved() {
        $order = new ON_Student_Proof_Test_Order();
        on_student_proof_before_save( $order );
        $order->eligible = false;
        $order->id = 123;
        $order->status = 'cancelled';
        on_student_proof_before_save( $order );
        $this->assertSame( 'yes', $order->get_meta( '_on_student_proof_required', true ) );
        $this->assertSame( 'cancelled', $order->status );
        $this->assertFalse( on_student_proof_approve( $order, 12 ) );
    }

    public function test_noneligible_orders_and_subscriptions_are_unchanged() {
        $order = new ON_Student_Proof_Test_Order();
        $order->eligible = false;
        on_student_proof_before_save( $order );
        $this->assertSame( 'pending', $order->status );
        $this->assertSame( 'completed', on_student_proof_paid_status( 'completed', 123, $order ) );
        $order->type = 'shop_subscription';
        $order->eligible = true;
        on_student_proof_before_save( $order );
        $this->assertSame( 'no', $order->get_meta( '_on_student_proof_required', true ) );
    }

    public function test_manual_processing_and_completed_cannot_bypass_proof() {
        foreach ( array( 'processing', 'completed' ) as $status ) {
            $order = new ON_Student_Proof_Test_Order();
            on_student_proof_before_save( $order );
            $order->id = 123;
            $order->status = $status;
            on_student_proof_before_save( $order );
            $this->assertSame( 'awaiting-proof', $order->status );
            $this->assertNotNull( $order->paid );
        }
    }

    public function test_renewal_suspends_only_active_related_subscriptions_including_gifts() {
        $order = new ON_Student_Proof_Test_Order();
        on_student_proof_before_save( $order );
        $active = new ON_Student_Proof_Test_Order();
        $active->status = 'active';
        $active->meta['_recipient_user'] = 22;
        $cancelled = new ON_Student_Proof_Test_Order();
        $cancelled->status = 'cancelled';
        $unrelated = new ON_Student_Proof_Test_Order();
        $unrelated->status = 'active';
        $GLOBALS['on_proof_related_subscriptions'] = array( $active, $cancelled );
        on_student_proof_suspend_subscriptions( $order );
        $this->assertSame( 'on-hold', $active->status );
        $this->assertSame( 'cancelled', $cancelled->status );
        $this->assertSame( 'active', $unrelated->status );
        $this->assertSame( array( 'order_type' => array( 'parent', 'renewal' ) ), $GLOBALS['on_proof_relation_query'] );
        on_student_proof_approve( $order, 12 );
        $active->status = 'active';
        on_student_proof_suspend_subscriptions( $order );
        $this->assertSame( 'on-hold', $active->status );
    }

    public function test_native_parent_payment_does_not_revive_ended_subscriptions() {
        $order = new ON_Student_Proof_Test_Order();
        on_student_proof_before_save( $order );
        $subscription = new ON_Student_Proof_Test_Order();
        $subscription->status = 'cancelled';
        $this->assertFalse( on_student_proof_subscription_order_completed( true, 'processing', 'awaiting-proof', array( $subscription ), $order ) );
        on_student_proof_approve( $order, 12 );
        $this->assertFalse( on_student_proof_subscription_order_completed( true, 'processing', 'pending', array( $subscription ), $order ) );
        $subscription->status = 'on-hold';
        $this->assertTrue( on_student_proof_subscription_order_completed( true, 'processing', 'pending', array( $subscription ), $order ) );
    }

    public function test_admin_actions_share_url_and_require_permission() {
        $order = new ON_Student_Proof_Test_Order();
        on_student_proof_before_save( $order );
        $order->id = 123;
        $list = on_student_proof_list_actions( array(), $order );
        $preview = on_student_proof_preview_actions( array(), $order );
        $this->assertSame( $list['on-approve-proof'], $preview['on-proof']['actions']['on-approve-proof'] );
        $this->assertStringContainsString( 'order_id=123', $list['on-approve-proof']['url'] );
        $this->assertFalse( on_student_proof_authorize_approval( $order, 'wrong' ) );
        $this->assertFalse( on_student_proof_authorize_approval( $order, 'nonce:on-approve-student-proof-124' ) );
        $this->assertTrue( on_student_proof_authorize_approval( $order, 'nonce:on-approve-student-proof-123' ) );
        $GLOBALS['on_proof_test_can_edit'] = false;
        $this->assertFalse( on_student_proof_authorize_approval( $order, 'nonce:on-approve-student-proof-123' ) );
        $this->assertSame( array(), on_student_proof_list_actions( array(), $order ) );
        $GLOBALS['on_proof_test_can_edit'] = true;
        on_student_proof_approve( $order, 12 );
        $this->assertSame( array(), on_student_proof_preview_actions( array(), $order ) );
        $this->assertFalse( on_student_proof_authorize_approval( false, 'anything' ) );
    }

    public function test_renewal_metadata_excludes_all_proof_bookkeeping() {
        $keys = array( 'scope', 'required', 'approved_at', 'approved_by', 'request_sent', 'unpaid_status' );
        $meta = array( array( 'meta_key' => 'ordinary', 'meta_value' => 'keep' ) );
        foreach ( $keys as $key ) {
            $meta[] = array( 'meta_key' => '_on_student_proof_' . $key, 'meta_value' => 'old' );
        }
        $this->assertSame( array( $meta[0] ), on_exclude_custom_meta_from_renewal_order_copy( $meta ) );
    }

    public function test_renewal_resets_old_approval_and_sends_one_request() {
        $order = new ON_Student_Proof_Test_Order();
        $order->meta['_on_student_proof_approved_at'] = 'old';
        $order->meta['_on_student_proof_request_sent'] = 'old';
        $this->assertSame( $order, on_student_proof_renewal_created( $order, null ) );
        $this->assertTrue( on_student_proof_is_waiting( $order ) );
        $this->assertSame( 'awaiting-proof', $order->status );
        on_student_proof_request_email( $order );
        $this->assertSame( 1, $GLOBALS['on_proof_test_email_attempts'] );
    }

    public function test_disabled_or_failed_mail_does_not_remove_proof_gate() {
        $order = new ON_Student_Proof_Test_Order();
        $GLOBALS['on_proof_test_email_enabled'] = false;
        on_student_proof_renewal_created( $order, null );
        $this->assertSame( 'awaiting-proof', $order->status );
        $this->assertSame( '', $order->get_meta( '_on_student_proof_request_sent', true ) );
        $GLOBALS['on_proof_test_email_enabled'] = true;
        $GLOBALS['on_proof_test_email_success'] = false;
        on_student_proof_request_email( $order );
        $this->assertTrue( on_student_proof_is_waiting( $order ) );
        $this->assertSame( '', $order->get_meta( '_on_student_proof_request_sent', true ) );
    }

    public function test_virtual_proof_order_is_not_autocompleted() {
        $order = new ON_Student_Proof_Test_Order();
        on_student_proof_before_save( $order );
        $order->paid = 123;
        on_student_proof_approve( $order, 12 );
        on_complete_order( 123, 'awaiting-proof', 'processing', $order );
        $this->assertSame( 'processing', $order->status );
        $ordinary = new ON_Student_Proof_Test_Order();
        $ordinary->status = 'processing';
        on_complete_order( 123, 'pending', 'processing', $ordinary );
        $this->assertSame( 'completed', $ordinary->status );
    }
}

class ON_Student_Proof_Test_Order extends WC_Order {
    public $id = 0;
    public $type = 'shop_order';
    public $status = 'pending';
    public $eligible = true;
    public $meta = array();
    public $paid = null;
    public $completed = 1;
    public $saves = 0;
    public $notes = array();

    public function get_id() { return $this->id; }
    public function get_type() { return $this->type; }
    public function get_items( $types = 'line_item' ) { return array( $this ); }
    public function get_variation_id() { return 123; }
    public function get_product() { return $this; }
    public function get_meta( $key = '', $single = true, $context = 'view' ) {
        return '_demande_justificatif' === $key ? ( $this->eligible ? 'yes' : 'no' ) : ( $this->meta[ $key ] ?? '' );
    }
    public function update_meta_data( $key, $value, $meta_id = 0 ) { $this->meta[ $key ] = $value; }
    public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
    public function get_status( $context = 'view' ) { return $this->status; }
    public function has_status( $statuses ) { return in_array( $this->status, (array) $statuses, true ); }
    public function set_status( $status, $note = '', $manual_update = false ) { $this->status = $status; }
    public function get_date_paid( $context = 'view' ) { return $this->paid; }
    public function set_date_paid( $value = null ) { $this->paid = $value; }
    public function set_date_completed( $value = null ) { $this->completed = $value; }
    public function save() { ++$this->saves; on_student_proof_before_save( $this ); }
    public function update_status( $status, $note = '', $manual = false ) { $this->status = $status; }
    public function is_virtual() { return true; }
    public function add_order_note( $note, $is_customer_note = 0, $added_by_user = false, $meta_data = array() ) { $this->notes[] = $note; }
}

class ON_Student_Proof_Test_Mailer {
    public function mailer() { return $this; }
    public function get_emails() { return array( 'WC_Email_Justificatif_Etudiant' => $this ); }
    public function trigger( $order ) {
        if ( ! $GLOBALS['on_proof_test_email_enabled'] ) {
            return;
        }
        ++$GLOBALS['on_proof_test_email_attempts'];
        if ( $GLOBALS['on_proof_test_email_success'] ) {
            $order->update_meta_data( '_on_student_proof_request_sent', 'sent' );
        }
    }
}