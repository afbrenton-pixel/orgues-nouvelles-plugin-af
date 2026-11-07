<?php
/**
 * Free student subscription, step 3: "Compte et abonnement" box in the fiche.
 *
 * TEMPORARY button "Préparer le compte et l'abonnement": step 4 replaces it with
 * the Accept button, which calls the same on_gratuite_provision().
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
    if ( ! $sub_id && 'en_attente' === $status && on_gratuite_can_provision() ) {
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=on_gratuite_provision&fiche=' . (int) $post->ID ), 'on_gratuite_provision_' . $post->ID );
        echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Préparer le compte et l\'abonnement', 'orgues-nouvelles' ) . '</a></p>';
        echo '<p class="description">' . esc_html__( 'Crée le compte (si besoin), l\'abonnement Étudiant à 0 € et la commande « Attente justificatif ». Étape provisoire.', 'orgues-nouvelles' ) . '</p>';
    } elseif ( ! $sub_id ) {
        echo '<p class="description">' . esc_html__( 'Rien n\'est créé tant que la demande n\'est pas confirmée par e-mail et acceptée.', 'orgues-nouvelles' ) . '</p>';
    }
}

function on_gratuite_handle_provision() {
    $post_id = isset( $_GET['fiche'] ) ? absint( $_GET['fiche'] ) : 0;
    if ( ! $post_id || ! on_gratuite_can_provision() || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'on_gratuite_provision_' . $post_id ) ) {
        wp_die( esc_html__( 'Action non autorisée.', 'orgues-nouvelles' ), '', array( 'response' => 403 ) );
    }
    $result = on_gratuite_provision( $post_id );
    $args   = is_wp_error( $result )
        ? array( 'on_gratuite_msg' => 'err', 'on_gratuite_err' => rawurlencode( $result->get_error_message() ) )
        : array( 'on_gratuite_msg' => $result['account_created'] ? 'ok_new' : 'ok' );
    wp_safe_redirect( add_query_arg( $args, get_edit_post_link( $post_id, 'url' ) ) );
    exit;
}
add_action( 'admin_post_on_gratuite_provision', 'on_gratuite_handle_provision' );

function on_gratuite_provision_notice() {
    if ( empty( $_GET['on_gratuite_msg'] ) || ! on_gratuite_can_provision() ) {
        return;
    }
    $msg = sanitize_key( wp_unslash( $_GET['on_gratuite_msg'] ) );
    if ( 'err' === $msg ) {
        $text = isset( $_GET['on_gratuite_err'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['on_gratuite_err'] ) ) ) : 'Erreur.';
        echo '<div class="notice notice-error"><p>' . esc_html( $text ) . '</p></div>';
    } elseif ( in_array( $msg, array( 'ok', 'ok_new' ), true ) ) {
        $text = 'ok_new' === $msg ? 'Compte, abonnement et commande créés (e-mail « nouveau compte » envoyé).' : 'Abonnement et commande créés sur le compte existant.';
        echo '<div class="notice notice-success"><p>' . esc_html( $text ) . '</p></div>';
    }
}
add_action( 'admin_notices', 'on_gratuite_provision_notice' );
