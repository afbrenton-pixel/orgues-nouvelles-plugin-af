<?php
/**
 * Free student subscription, step 4: accept or refuse a confirmed request.
 *
 * - on_gratuite_accept(): creates account, subscription and order (step 3) if needed, validates the
 *   proof with the webmaster's on_student_proof_approve(), activates the subscription for one year
 *   from today, removes the -100 % line from the subscription, deletes the proof, e-mails the person.
 * - on_gratuite_refuse(): deletes the proof, e-mails the person (who may apply again at once).
 *   Nothing is created, except when the temporary step-3 button was used: then the objects are cancelled.
 */
defined( 'ABSPATH' ) || exit;

/** Page of the form in the person's language (Polylang translation when it exists). */
function on_gratuite_form_url( $lang ) {
    $id = (int) on_gratuite_settings()['form_page_id'];
    if ( $id && function_exists( 'pll_get_post' ) ) {
        $translated = pll_get_post( $id, 'en' === $lang ? 'en' : 'fr' );
        $id         = $translated ? (int) $translated : $id;
    }
    return $id ? (string) get_permalink( $id ) : home_url( '/' );
}

/** "My account" page in the person's language (found through Polylang, like the form page). */
function on_gratuite_account_url( $lang ) {
    // The raw option: WooCommerce's helper goes through Polylang, which answers nothing outside a page view.
    $id = (int) get_option( 'woocommerce_myaccount_page_id' );
    if ( $id > 0 && function_exists( 'pll_get_post' ) ) {
        $translated = pll_get_post( $id, 'en' === $lang ? 'en' : 'fr' );
        $id         = $translated ? (int) $translated : $id;
    }
    return $id > 0 ? (string) get_permalink( $id ) : home_url( '/' );
}

/** A date written in the language of the request (month names included). */
function on_gratuite_format_date( $timestamp, $lang ) {
    $switched = function_exists( 'switch_to_locale' ) ? switch_to_locale( 'en' === $lang ? 'en_US' : 'fr_FR' ) : false;
    $text     = wp_date( 'en' === $lang ? 'F j, Y' : 'j F Y', $timestamp );
    if ( $switched ) {
        restore_previous_locale();
    }
    return $text;
}

/** Send the acceptance or refusal e-mail. @return bool */
function on_gratuite_send_decision( $kind, $post_id, array $extra = array() ) {
    $class = 'accepted' === $kind ? 'ON_Gratuite_Email_Accepted' : 'ON_Gratuite_Email_Refused';
    $email = on_gratuite_wc_email( $class );
    if ( ! $email ) {
        return false;
    }
    $d    = on_gratuite_request_data( $post_id );
    $vars = array_merge( array(
        'account_url' => on_gratuite_account_url( $d['langue'] ),
        'form_url'    => on_gratuite_form_url( $d['langue'] ),
        'contact'     => on_gratuite_settings()['notify_email'],
    ), $extra );
    return (bool) $email->trigger( $post_id, $d, $vars );
}

/** Record the decision on the fiche. */
function on_gratuite_record_decision( $post_id, $status, $user_id ) {
    update_post_meta( $post_id, 'statut', $status );
    update_post_meta( $post_id, 'date_decision', current_time( 'mysql' ) );
    update_post_meta( $post_id, '_on_gratuite_decide_par', (int) $user_id );
}

/**
 * While a decision is applied, WooCommerce and its extensions must not send their own order e-mails
 * (processing, completed, new order, gift recipient, cancelled subscription): ours replaces them.
 * Nothing else is legitimately sent during that time, so every outgoing mail is held back.
 */
function on_gratuite_mute_order_emails( $mute ) {
    if ( $mute ) {
        add_filter( 'pre_wp_mail', '__return_true', 99 );
    } else {
        remove_filter( 'pre_wp_mail', '__return_true', 99 );
    }
}

/**
 * Accept a request. @return array|WP_Error { subscription_id, order_id, end_date, mail_sent }
 */
function on_gratuite_accept( $post_id, $user_id ) {
    $post_id = (int) $post_id;
    if ( on_gratuite_post_type() !== get_post_type( $post_id ) ) {
        return new WP_Error( 'on_gratuite_bad_post', 'Fiche introuvable.' );
    }
    if ( ! on_gratuite_can_decide( (string) get_post_meta( $post_id, 'statut', true ) ) ) {
        return new WP_Error( 'on_gratuite_status', 'La demande doit être « en attente » (confirmée par e-mail).' );
    }
    if ( ! function_exists( 'on_student_proof_approve' ) ) {
        return new WP_Error( 'on_gratuite_no_proof_module', 'Le module « justificatif étudiant » du plugin est absent.' );
    }
    $sub_id   = (int) get_post_meta( $post_id, 'abonnement_id', true );
    $order_id = (int) get_post_meta( $post_id, 'commande_id', true );
    if ( ! $sub_id ) {
        $created = on_gratuite_provision( $post_id );
        if ( is_wp_error( $created ) ) {
            return $created;
        }
        $sub_id   = $created['subscription_id'];
        $order_id = $created['order_id'];
    }
    $subscription = wcs_get_subscription( $sub_id );
    $order        = wc_get_order( $order_id );
    if ( ! $subscription || ! $order ) {
        return new WP_Error( 'on_gratuite_missing', 'Abonnement ou commande introuvable (n° ' . $sub_id . ' / ' . $order_id . ').' );
    }

    on_gratuite_mute_order_emails( true );
    try {
        // 1. Proof validated: the order leaves "Attente justificatif", then is paid (0 €) and completed.
        on_student_proof_approve( $order, $user_id );
        $order = wc_get_order( $order_id );
        if ( ! $order->get_date_paid( 'edit' ) ) {
            $order->payment_complete();
            $order = wc_get_order( $order_id );
        }
        if ( $order->has_status( 'processing' ) ) {
            $order->update_status( 'completed', 'Gratuité -26 ans acceptée.' );
        }

        // 2. One year from today, no next payment; magazine numbers recomputed for that start date.
        $start = time();
        $end   = on_gratuite_end_date( $start );
        $subscription = wcs_get_subscription( $sub_id );
        $subscription->update_dates( array( 'start' => gmdate( 'Y-m-d H:i:s', $start ), 'next_payment' => 0, 'end' => $end ) );
        $subscription->delete_meta_data( 'number-start' );
        $subscription->delete_meta_data( 'number-end' );
        $subscription->save();
        if ( function_exists( 'on_initialize_subscription_number_bounds' ) ) {
            on_initialize_subscription_number_bounds( $subscription );
        }

        // 3. The free line only concerns this year: removed from the subscription (kept on the order).
        foreach ( $subscription->get_fees() as $item_id => $fee ) {
            if ( $fee->get_meta( '_on_gratuite_fee' ) ) {
                $subscription->remove_item( $item_id );
            }
        }
        $subscription->calculate_totals( false );
        $subscription->save();

        // 4. Active, unless WooCommerce Subscriptions already did it when the order was paid.
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription->has_status( 'active' ) ) {
            $subscription->update_status( 'active', 'Gratuité -26 ans acceptée.' );
        }
    } finally {
        on_gratuite_mute_order_emails( false );
    }

    on_gratuite_delete_proof( $post_id );
    on_gratuite_record_decision( $post_id, 'acceptee', $user_id );
    $end_label = on_gratuite_format_date( strtotime( $end . ' UTC' ), (string) get_post_meta( $post_id, 'langue', true ) );
    $sent      = on_gratuite_send_decision( 'accepted', $post_id, array( 'end_date' => $end_label ) );

    return array( 'subscription_id' => $sub_id, 'order_id' => $order_id, 'end_date' => $end, 'mail_sent' => $sent );
}

/**
 * Refuse a request. @return array|WP_Error { mail_sent }
 */
function on_gratuite_refuse( $post_id, $user_id, $reason = '' ) {
    $post_id = (int) $post_id;
    if ( on_gratuite_post_type() !== get_post_type( $post_id ) ) {
        return new WP_Error( 'on_gratuite_bad_post', 'Fiche introuvable.' );
    }
    if ( ! on_gratuite_can_decide( (string) get_post_meta( $post_id, 'statut', true ) ) ) {
        return new WP_Error( 'on_gratuite_status', 'La demande doit être « en attente » (confirmée par e-mail).' );
    }
    $reason = trim( wp_strip_all_tags( (string) $reason ) );

    // Objects prepared by the temporary step-3 button are cancelled (never deleted).
    $sub_id   = (int) get_post_meta( $post_id, 'abonnement_id', true );
    $order_id = (int) get_post_meta( $post_id, 'commande_id', true );
    on_gratuite_mute_order_emails( true );
    try {
        if ( $sub_id && function_exists( 'wcs_get_subscription' ) && ( $sub = wcs_get_subscription( $sub_id ) ) ) {
            $sub->update_status( 'cancelled', 'Gratuité -26 ans refusée.' );
        }
        if ( $order_id && ( $order = wc_get_order( $order_id ) ) ) {
            $order->update_status( 'cancelled', 'Gratuité -26 ans refusée.' );
        }
    } finally {
        on_gratuite_mute_order_emails( false );
    }

    on_gratuite_delete_proof( $post_id );
    if ( '' !== $reason ) {
        update_post_meta( $post_id, '_on_gratuite_motif', $reason );
    }
    on_gratuite_record_decision( $post_id, 'refusee', $user_id );
    $sent = on_gratuite_send_decision( 'refused', $post_id, array( 'reason' => $reason ) );
    return array( 'mail_sent' => $sent );
}
