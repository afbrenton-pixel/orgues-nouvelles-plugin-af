<?php 

function on_complete_order($order_id, $from, $to, $order) {
    if ($to !== 'processing') return;
    if ('yes' === $order->get_meta('_on_student_proof_required', true)) return;
    $has_to_shipping = false;

    // Check if the order contains a physical product
    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();

        if ( ! $product->is_virtual() ) {
            $has_to_shipping = true;
            break;
        }
    }

    // If the order does not contain a physical product, mark it as completed
    if ( !$has_to_shipping ) {
        $order->update_status( 'completed' );
    }
}

add_action( 'woocommerce_order_status_changed', 'on_complete_order', 10, 4 );