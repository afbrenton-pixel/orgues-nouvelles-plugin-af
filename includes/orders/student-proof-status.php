<?php
/**
 * Order-local student proof approval, independent of payment and email delivery.
 */
defined( 'ABSPATH' ) || exit;

/** Register a non-paid order status. */
function on_register_student_proof_status() {
    register_post_status( 'wc-awaiting-proof', array(
        'label' => __( 'Attente justificatif', 'orgues-nouvelles' ),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop( 'Attente justificatif <span class="count">(%s)</span>', 'Attente justificatif <span class="count">(%s)</span>', 'orgues-nouvelles' ),
    ) );
}
add_action( 'init', 'on_register_student_proof_status' );

/** Add the status to classic and HPOS order interfaces. */
function on_student_proof_order_statuses( $statuses ) {
    $statuses['wc-awaiting-proof'] = __( 'Attente justificatif', 'orgues-nouvelles' );
    return $statuses;
}
add_filter( 'wc_order_statuses', 'on_student_proof_order_statuses' );

/** Detect flagged variations using WooCommerce product metadata. */
function on_student_proof_items_require_proof( $order ) {
    foreach ( $order->get_items() as $item ) {
        if ( $item->get_variation_id() ) {
            $product = $item->get_product();
            if ( $product && 'yes' === $product->get_meta( '_demande_justificatif', true ) ) {
                return true;
            }
        }
    }
    return false;
}

/** Snapshot only orders created after this module was installed. */
function on_student_proof_snapshot( $order ) {
    if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
        return;
    }
    if ( ! $order->get_id() ) {
        $order->update_meta_data( '_on_student_proof_scope', 'yes' );
    }
    if ( 'yes' === $order->get_meta( '_on_student_proof_scope', true ) && '' === $order->get_meta( '_on_student_proof_required', true ) && $order->get_items() ) {
        $order->update_meta_data( '_on_student_proof_required', on_student_proof_items_require_proof( $order ) ? 'yes' : 'no' );
    }
}

/** Whether this order has an outstanding proof obligation. */
function on_student_proof_is_waiting( $order ) {
    return $order instanceof WC_Order && 'shop_order' === $order->get_type()
        && 'yes' === $order->get_meta( '_on_student_proof_required', true )
        && ! $order->get_meta( '_on_student_proof_approved_at', true );
}

/** Intercept status changes before WC_Order emits its private status transition. */
function on_student_proof_before_save( $order ) {
    on_student_proof_snapshot( $order );
    if ( ! on_student_proof_is_waiting( $order ) ) {
        return;
    }
    $status = $order->get_status();
    if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
        $order->update_meta_data( '_on_student_proof_unpaid_status', $status );
    } elseif ( in_array( $status, array( 'processing', 'completed' ), true ) ) {
        if ( ! $order->get_date_paid( 'edit' ) ) {
            $order->set_date_paid( time() );
        }
        $order->set_date_completed( null );
    } else {
        return;
    }
    $order->set_status( 'awaiting-proof' );
}
add_action( 'woocommerce_before_order_object_save', 'on_student_proof_before_save', 100, 1 );

/** Allow payment of unpaid proof orders and native activation after approval. */
function on_student_proof_payment_statuses( $statuses, $order ) {
    if ( $order instanceof WC_Order && 'yes' === $order->get_meta( '_on_student_proof_required', true )
        && ( ! $order->get_date_paid( 'edit' ) || ! on_student_proof_is_waiting( $order ) ) ) {
        $statuses[] = 'awaiting-proof';
    }
    return array_values( array_unique( $statuses ) );
}
add_filter( 'woocommerce_valid_order_statuses_for_payment', 'on_student_proof_payment_statuses', 10, 2 );
add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', 'on_student_proof_payment_statuses', 10, 2 );

/** A captured payment must never expose a second payment link. */
function on_student_proof_needs_payment( $needs_payment, $order ) {
    return $order->has_status( 'awaiting-proof' ) && $order->get_date_paid( 'edit' ) ? false : $needs_payment;
}
add_filter( 'woocommerce_order_needs_payment', 'on_student_proof_needs_payment', 10, 2 );

/** Approved proof orders remain processing, including payment after early approval. */
function on_student_proof_paid_status( $status, $order_id, $order ) {
    if ( $order instanceof WC_Order && 'yes' === $order->get_meta( '_on_student_proof_required', true ) && ! on_student_proof_is_waiting( $order ) ) {
        return 'processing';
    }
    return $status;
}
add_filter( 'woocommerce_payment_complete_order_status', 'on_student_proof_paid_status', 10, 3 );

/** Approve proof without fabricating payment or reviving cancelled orders. */
function on_student_proof_approve( $order, $user_id ) {
    if ( ! on_student_proof_is_waiting( $order ) || ! $order->has_status( array( 'awaiting-proof', 'pending', 'on-hold' ) ) ) {
        return false;
    }
    $order->update_meta_data( '_on_student_proof_approved_at', gmdate( 'Y-m-d H:i:s' ) );
    $order->update_meta_data( '_on_student_proof_approved_by', absint( $user_id ) );
    $status = $order->get_meta( '_on_student_proof_unpaid_status', true );
    $status = in_array( $status, array( 'pending', 'on-hold' ), true ) ? $status : 'pending';
    $order->set_status( $order->get_date_paid( 'edit' ) ? 'processing' : $status );
    $order->save();
    $order->add_order_note( __( 'Justificatif étudiant validé.', 'orgues-nouvelles' ) );
    return true;
}

/** Suspend only the related subscriptions, never subscriptions matched by buyer. */
function on_student_proof_suspend_subscriptions( $order ) {
    if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type()
        || 'yes' !== $order->get_meta( '_on_student_proof_required', true )
        || $order->has_status( array( 'cancelled', 'refunded' ) )
        || ( ! on_student_proof_is_waiting( $order ) && $order->get_date_paid( 'edit' ) )
        || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
        return;
    }
    foreach ( wcs_get_subscriptions_for_order( $order, array( 'order_type' => array( 'parent', 'renewal' ) ) ) as $subscription ) {
        if ( $subscription->has_status( 'active' ) ) {
            $subscription->update_status( 'on-hold', __( 'Paiement et justificatif étudiant requis pour la commande.', 'orgues-nouvelles' ) );
        }
    }
}
add_action( 'woocommerce_after_order_object_save', 'on_student_proof_suspend_subscriptions', 20, 1 );

/** A new renewal always needs its own proof, even after an approved parent order. */
function on_student_proof_renewal_created( $order, $subscription ) {
    if ( ! $order instanceof WC_Order ) {
        return $order;
    }
    foreach ( array( 'approved_at', 'approved_by', 'request_sent', 'unpaid_status' ) as $suffix ) {
        $order->delete_meta_data( '_on_student_proof_' . $suffix );
    }
    $order->update_meta_data( '_on_student_proof_scope', 'yes' );
    $order->update_meta_data( '_on_student_proof_required', on_student_proof_items_require_proof( $order ) ? 'yes' : 'no' );
    $order->save();
    on_student_proof_suspend_subscriptions( $order );
    on_student_proof_request_email( $order );
    return $order;
}
add_filter( 'wcs_renewal_order_created', 'on_student_proof_renewal_created', 20, 2 );

/** Prevent WCS's payment-after-cancellation special case for proof orders. */
function on_student_proof_subscription_order_completed( $completed, $new_status, $old_status, $subscriptions, $order ) {
    if ( 'yes' === $order->get_meta( '_on_student_proof_required', true ) ) {
        if ( on_student_proof_is_waiting( $order ) ) {
            return false;
        }
        foreach ( $subscriptions as $subscription ) {
            if ( $subscription->has_status( wcs_get_subscription_ended_statuses() ) ) {
                return false;
            }
        }
    }
    return $completed;
}
add_filter( 'wcs_is_subscription_order_completed', 'on_student_proof_subscription_order_completed', 10, 5 );

/** Initialize the WooCommerce mailer lazily, including scheduled renewals. */
function on_student_proof_request_email( $order ) {
    if ( ! on_student_proof_is_waiting( $order ) || $order->get_meta( '_on_student_proof_request_sent', true ) ) {
        return;
    }
    try {
        $emails = WC()->mailer()->get_emails();
        if ( isset( $emails['WC_Email_Justificatif_Etudiant'] ) ) {
            $emails['WC_Email_Justificatif_Etudiant']->trigger( $order );
        }
    } catch ( Exception $exception ) {
        wc_get_logger()->error( $exception->getMessage(), array( 'source' => 'on-student-proof', 'order_id' => $order->get_id() ) );
    }
}
add_action( 'woocommerce_checkout_order_created', 'on_student_proof_request_email', 20 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'on_student_proof_request_email', 20 );

/** Check order-specific edit capabilities for both storage engines. */
function on_student_proof_can_edit( $order ) {
    $post_type = get_post_type_object( 'shop_order' );
    return $post_type && current_user_can( $post_type->cap->edit_post, $order->get_id() );
}

/** Build the shared, nonce-protected admin action. */
function on_student_proof_admin_action( $order ) {
    if ( ! on_student_proof_is_waiting( $order ) || ! $order->has_status( array( 'awaiting-proof', 'pending', 'on-hold' ) ) || ! on_student_proof_can_edit( $order ) ) {
        return false;
    }
    return array(
        'url' => wp_nonce_url( add_query_arg( array( 'action' => 'on_approve_student_proof', 'order_id' => $order->get_id() ), admin_url( 'admin-post.php' ) ), 'on-approve-student-proof-' . $order->get_id() ),
        'name' => __( 'Valider le justificatif', 'orgues-nouvelles' ),
        'title' => __( 'Valider le justificatif', 'orgues-nouvelles' ),
        'action' => 'on-approve-proof',
    );
}

/** Add the approval command to order list rows. */
function on_student_proof_list_actions( $actions, $order ) {
    $action = on_student_proof_admin_action( $order );
    if ( $action ) {
        $actions['on-approve-proof'] = $action;
    }
    return $actions;
}
add_filter( 'woocommerce_admin_order_actions', 'on_student_proof_list_actions', 10, 2 );

/** Add the same command to quick previews (grouped WooCommerce format). */
function on_student_proof_preview_actions( $actions, $order ) {
    $action = on_student_proof_admin_action( $order );
    if ( $action ) {
        $actions['on-proof'] = array( 'group' => __( 'Justificatif :', 'orgues-nouvelles' ), 'actions' => array( 'on-approve-proof' => $action ) );
    }
    return $actions;
}
add_filter( 'woocommerce_admin_order_preview_actions', 'on_student_proof_preview_actions', 10, 2 );

/** Validate permission and order-bound nonce before any mutation. */
function on_student_proof_authorize_approval( $order, $nonce ) {
    return $order instanceof WC_Order && 'shop_order' === $order->get_type()
        && on_student_proof_can_edit( $order )
        && wp_verify_nonce( $nonce, 'on-approve-student-proof-' . $order->get_id() );
}

/** Handle the authenticated admin approval and safely return to the order. */
function on_student_proof_handle_approval() {
    $order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    $order = wc_get_order( $order_id );
    if ( ! on_student_proof_authorize_approval( $order, $nonce ) ) {
        wp_die( esc_html__( 'Validation du justificatif non autorisée.', 'orgues-nouvelles' ), '', array( 'response' => 403 ) );
    }
    on_student_proof_approve( $order, get_current_user_id() );
    wp_safe_redirect( $order->get_edit_order_url() );
    exit;
}
add_action( 'admin_post_on_approve_student_proof', 'on_student_proof_handle_approval' );

/** Load the approval icon only on order administration screens. */
function on_student_proof_admin_assets() {
    $screen = get_current_screen();
    if ( $screen && in_array( $screen->id, array( 'edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
        wp_enqueue_style( 'on-student-proof', plugins_url( '../../assets/css/student-proof-admin.css', __FILE__ ), array(), '1.0.0' );
    }
}
add_action( 'admin_enqueue_scripts', 'on_student_proof_admin_assets' );