<?php
/**
 * Free student subscription, step 3: "Compte et abonnement" box in the fiche.
 *
 * Accept / Refuse buttons (step 4) on requests confirmed by e-mail. A decided request
 * shows the outcome instead.
 */
defined( 'ABSPATH' ) || exit;

function on_gratuite_can_provision() {
    return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

function on_gratuite_add_subscription_metabox() {
    add_meta_box( 'on_gratuite_compte', __( 'Compte et abonnement', 'orgues-nouvelles' ), 'on_gratuite_render_subscription_metabox', on_gratuite_post_type(), 'side', 'default' );
}
add_action( 'add_meta_boxes', 'on_gratuite_add_subscription_metabox' );

function on_gratuite_render_subscription_metabox( $post ) {
    $sub_id   = (int) get_post_meta( $post->ID, 'abonnement_id', true );
    $order_id = (int) get_post_meta( $post->ID, 'commande_id', true );
    $client   = (int) get_post_meta( $post->ID, 'client_id', true );
    $status   = (string) get_post_meta( $post->ID, 'statut', true );

    if ( $client ) {
        printf( '<p>%s <a href="%s">#%d</a></p>', esc_html__( 'Compte :', 'orgues-nouvelles' ), esc_url( get_edit_user_link( $client ) ), $client );
    }
    if ( $sub_id ) {
        $sub = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $sub_id ) : null;
        printf( '<p>%s <a href="%s">#%d</a>%s</p>', esc_html__( 'Abonnement :', 'orgues-nouvelles' ), esc_url( get_edit_post_link( $sub_id ) ?: admin_url( 'admin.php?page=wc-orders--shop_subscription&action=edit&id=' . $sub_id ) ), $sub_id, $sub ? ' (' . esc_html( wcs_get_subscription_status_name( $sub->get_status() ) ) . ')' : '' );
    }
    if ( $order_id ) {
        $order = wc_get_order( $order_id );
        printf( '<p>%s <a href="%s">#%d</a>%s</p>', esc_html__( 'Commande :', 'orgues-nouvelles' ), esc_url( $order ? $order->get_edit_order_url() : '#' ), $order_id, $order ? ' (' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . ')' : '' );
    }
    $dups = get_post_meta( $post->ID, '_on_gratuite_doublons', true );
    if ( is_array( $dups ) && $dups ) {
        echo '<p><strong>' . esc_html__( 'À vérifier : cette personne a peut-être déjà un compte ou une demande :', 'orgues-nouvelles' ) . '</strong></p><ul style="list-style:disc;margin-left:1.2em">';
        foreach ( $dups as $line ) {
            echo '<li>' . esc_html( $line ) . '</li>';
        }
        echo '</ul>';
    }
    if ( on_gratuite_can_decide( $status ) && on_gratuite_can_provision() ) {
        $accept = wp_nonce_url( admin_url( 'admin-post.php?action=on_gratuite_accept&fiche=' . (int) $post->ID ), 'on_gratuite_decide_' . $post->ID );
        echo '<p><a class="button button-primary" href="' . esc_url( $accept ) . '">' . esc_html__( 'Accepter la demande', 'orgues-nouvelles' ) . '</a></p>';
        echo '<p class="description">' . esc_html__( 'Crée le compte si besoin, active l\'abonnement pour un an, supprime le justificatif et prévient la personne.', 'orgues-nouvelles' ) . '</p><hr>';
        echo '<p><label for="on_gratuite_motif">' . esc_html__( 'Motif du refus (facultatif, transmis à la personne)', 'orgues-nouvelles' ) . '</label>';
        echo '<textarea id="on_gratuite_motif" rows="3" style="width:100%"></textarea></p>';
        // The edit form already has a field named "action": the reason travels in the address instead.
        $refuse = wp_nonce_url( admin_url( 'admin-post.php?action=on_gratuite_refuse&fiche=' . (int) $post->ID ), 'on_gratuite_decide_' . $post->ID );
        echo '<p><a class="button" id="on_gratuite_refuse" href="' . esc_url( $refuse ) . '" onclick="this.href=this.href+\'&amp;motif=\'+encodeURIComponent(document.getElementById(\'on_gratuite_motif\').value);">' . esc_html__( 'Refuser la demande', 'orgues-nouvelles' ) . '</a></p>';
        echo '<p class="description">' . esc_html__( 'Supprime le justificatif et prévient la personne, qui pourra redéposer une demande tout de suite.', 'orgues-nouvelles' ) . '</p>';
    } elseif ( 'acceptee' === $status || 'refusee' === $status ) {
        $when = (string) get_post_meta( $post->ID, 'date_decision', true );
        echo '<p><strong>' . esc_html( 'acceptee' === $status ? __( 'Demande acceptée', 'orgues-nouvelles' ) : __( 'Demande refusée', 'orgues-nouvelles' ) ) . '</strong>' . ( $when ? ' (' . esc_html( $when ) . ')' : '' ) . '</p>';
        $motif = (string) get_post_meta( $post->ID, '_on_gratuite_motif', true );
        if ( '' !== $motif ) {
            echo '<p>' . esc_html__( 'Motif :', 'orgues-nouvelles' ) . ' ' . esc_html( $motif ) . '</p>';
        }
    } elseif ( ! $sub_id ) {
        echo '<p class="description">' . esc_html__( 'Rien n\'est créé tant que la demande n\'est pas confirmée par e-mail et acceptée.', 'orgues-nouvelles' ) . '</p>';
    }
}

/** Check capability and nonce of a decision; returns the fiche id or dies. */
function on_gratuite_decision_request( $nonce_value ) {
    $post_id = isset( $_GET['fiche'] ) ? absint( $_GET['fiche'] ) : 0;
    if ( ! $post_id || ! on_gratuite_can_provision() || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce_value ) ), 'on_gratuite_decide_' . $post_id ) ) {
        wp_die( esc_html__( 'Action non autorisée.', 'orgues-nouvelles' ), '', array( 'response' => 403 ) );
    }
    return $post_id;
}

function on_gratuite_decision_redirect( $post_id, $result, $ok_code ) {
    $args = is_wp_error( $result )
        ? array( 'on_gratuite_msg' => 'err', 'on_gratuite_err' => rawurlencode( $result->get_error_message() ) )
        : array( 'on_gratuite_msg' => $ok_code . ( empty( $result['mail_sent'] ) ? '_nomail' : '' ) );
    wp_safe_redirect( add_query_arg( $args, get_edit_post_link( $post_id, 'url' ) ) );
    exit;
}

function on_gratuite_handle_accept() {
    $post_id = on_gratuite_decision_request( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '' );
    on_gratuite_decision_redirect( $post_id, on_gratuite_accept( $post_id, get_current_user_id() ), 'accepted' );
}
add_action( 'admin_post_on_gratuite_accept', 'on_gratuite_handle_accept' );

function on_gratuite_handle_refuse() {
    $post_id = on_gratuite_decision_request( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '' );
    $reason  = isset( $_GET['motif'] ) ? sanitize_textarea_field( wp_unslash( $_GET['motif'] ) ) : '';
    on_gratuite_decision_redirect( $post_id, on_gratuite_refuse( $post_id, get_current_user_id(), $reason ), 'refused' );
}
add_action( 'admin_post_on_gratuite_refuse', 'on_gratuite_handle_refuse' );

function on_gratuite_provision_notice() {
    if ( empty( $_GET['on_gratuite_msg'] ) || ! on_gratuite_can_provision() ) {
        return;
    }
    $msg = sanitize_key( wp_unslash( $_GET['on_gratuite_msg'] ) );
    if ( 'err' === $msg ) {
        $text = isset( $_GET['on_gratuite_err'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['on_gratuite_err'] ) ) ) : 'Erreur.';
        echo '<div class="notice notice-error"><p>' . esc_html( $text ) . '</p></div>';
    } else {
        $texts = array(
            'accepted'        => 'Demande acceptée : abonnement activé, justificatif supprimé, e-mail envoyé.',
            'accepted_nomail' => 'Demande acceptée : abonnement activé, justificatif supprimé. ATTENTION : l\'e-mail n\'a pas pu être envoyé (e-mail désactivé dans WooCommerce > Réglages > E-mails ?).',
            'refused'         => 'Demande refusée : justificatif supprimé, e-mail envoyé.',
            'refused_nomail'  => 'Demande refusée : justificatif supprimé. ATTENTION : l\'e-mail n\'a pas pu être envoyé.',
        );
        if ( isset( $texts[ $msg ] ) ) {
            echo '<div class="notice ' . ( substr( $msg, -6 ) === 'nomail' ? 'notice-warning' : 'notice-success' ) . '"><p>' . esc_html( $texts[ $msg ] ) . '</p></div>';
        }
    }
}
add_action( 'admin_notices', 'on_gratuite_provision_notice' );
