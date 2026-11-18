<?php
/**
 * Free student subscription: administration of unconfirmed requests.
 *
 * - "À confirmer" filter in the authorizations list, with a counter;
 * - row action "Annuler la demande" (erases the record and its proof);
 * - link "Supprimer les demandes non confirmées de plus de N jours" (manual purge).
 */
defined( 'ABSPATH' ) || exit;

/** Number of unconfirmed requests, and how many are older than the delay. */
function on_gratuite_unconfirmed_counts() {
    $ids = get_posts( array(
        'post_type'   => on_gratuite_post_type(),
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_query'  => array( array( 'key' => 'statut', 'value' => 'a_confirmer' ) ),
    ) );
    return array( 'all' => count( $ids ), 'stale' => count( on_gratuite_stale_unconfirmed_ids() ) );
}

function on_gratuite_admin_views( $views ) {
    if ( ! current_user_can( on_gratuite_capability() ) ) {
        return $views;
    }
    $counts  = on_gratuite_unconfirmed_counts();
    // "Tous" and "Publié" do not count the unconfirmed requests (they are hidden from the normal list).
    foreach ( array( 'all', 'publish' ) as $key ) {
        if ( isset( $views[ $key ] ) ) {
            $views[ $key ] = preg_replace_callback( '/\((\d+)\)/', function ( $m ) use ( $counts ) {
                return '(' . max( 0, (int) $m[1] - $counts['all'] ) . ')';
            }, $views[ $key ], 1 );
        }
    }
    $current = isset( $_GET['on_gratuite_statut'] ) && 'a_confirmer' === $_GET['on_gratuite_statut']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $url     = add_query_arg( array( 'post_type' => on_gratuite_post_type(), 'on_gratuite_statut' => 'a_confirmer' ), admin_url( 'edit.php' ) );
    $views['on_gratuite_a_confirmer'] = sprintf(
        '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
        esc_url( $url ),
        $current ? ' class="current" aria-current="page"' : '',
        esc_html__( 'À confirmer', 'orgues-nouvelles-plugin' ),
        $counts['all']
    );
    if ( $counts['stale'] > 0 ) {
        $purge = wp_nonce_url( admin_url( 'admin-post.php?action=on_gratuite_purger_non_confirmees' ), 'on_gratuite_purger' );
        $views['on_gratuite_purger'] = sprintf(
            '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
            esc_url( $purge ),
            esc_js( sprintf( 'Supprimer définitivement %d demande(s) non confirmée(s) et leur justificatif ?', $counts['stale'] ) ),
            esc_html( sprintf( 'Supprimer les demandes non confirmées de plus de %d jours (%d)', (int) on_gratuite_settings()['confirm_days'], $counts['stale'] ) )
        );
    }
    return $views;
}

/** Hide the unconfirmed requests from the normal list; show them only under "À confirmer". */
function on_gratuite_admin_filter_list( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || ! function_exists( 'get_current_screen' ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || 'edit-' . on_gratuite_post_type() !== $screen->id ) {
        return;
    }
    $only = isset( $_GET['on_gratuite_statut'] ) && 'a_confirmer' === $_GET['on_gratuite_statut']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $meta = (array) $query->get( 'meta_query' );
    $meta[] = array( 'key' => 'statut', 'value' => 'a_confirmer', 'compare' => $only ? '=' : '!=' );
    $query->set( 'meta_query', $meta );
}

function on_gratuite_admin_row_actions( $actions, $post ) {
    if ( on_gratuite_post_type() !== $post->post_type || 'a_confirmer' !== get_post_meta( $post->ID, 'statut', true ) || ! current_user_can( on_gratuite_capability() ) ) {
        return $actions;
    }
    $url = wp_nonce_url( admin_url( 'admin-post.php?action=on_gratuite_annuler_demande&post_id=' . (int) $post->ID ), 'on_gratuite_annuler_' . (int) $post->ID );
    $actions['on_gratuite_annuler'] = sprintf(
        '<a href="%s" onclick="return confirm(\'%s\');" class="submitdelete">Annuler la demande</a>',
        esc_url( $url ),
        esc_js( 'Supprimer définitivement cette demande non confirmée et son justificatif ?' )
    );
    return $actions;
}

function on_gratuite_admin_back( $message ) {
    wp_safe_redirect( add_query_arg( array( 'post_type' => on_gratuite_post_type(), 'on_gratuite_msg' => rawurlencode( $message ) ), admin_url( 'edit.php' ) ) );
    exit;
}

function on_gratuite_handle_cancel() {
    $id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
    check_admin_referer( 'on_gratuite_annuler_' . $id );
    if ( ! current_user_can( on_gratuite_capability() ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'orgues-nouvelles-plugin' ), '', array( 'response' => 403 ) );
    }
    $post = $id ? get_post( $id ) : null;
    if ( ! $post || on_gratuite_post_type() !== $post->post_type || 'a_confirmer' !== get_post_meta( $id, 'statut', true ) ) {
        on_gratuite_admin_back( 'Demande introuvable ou déjà confirmée : rien n\'a été supprimé.' );
    }
    on_gratuite_erase_request( $id );
    on_gratuite_admin_back( 'Demande non confirmée supprimée, avec son justificatif.' );
}

function on_gratuite_handle_purge() {
    check_admin_referer( 'on_gratuite_purger' );
    if ( ! current_user_can( on_gratuite_capability() ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'orgues-nouvelles-plugin' ), '', array( 'response' => 403 ) );
    }
    $n = on_gratuite_purge_unconfirmed();
    on_gratuite_admin_back( sprintf( '%d demande(s) non confirmée(s) supprimée(s), avec leur justificatif.', $n ) );
}

function on_gratuite_admin_notice() {
    if ( empty( $_GET['on_gratuite_msg'] ) || ! current_user_can( on_gratuite_capability() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( rawurldecode( wp_unslash( $_GET['on_gratuite_msg'] ) ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

function on_gratuite_confirmation_admin_init() {
    add_filter( 'views_edit-' . on_gratuite_post_type(), 'on_gratuite_admin_views' );
    add_action( 'pre_get_posts', 'on_gratuite_admin_filter_list' );
    add_filter( 'post_row_actions', 'on_gratuite_admin_row_actions', 10, 2 );
    add_action( 'admin_notices', 'on_gratuite_admin_notice' );
}
add_action( 'admin_init', 'on_gratuite_confirmation_admin_init' );
add_action( 'admin_post_on_gratuite_annuler_demande', 'on_gratuite_handle_cancel' );
add_action( 'admin_post_on_gratuite_purger_non_confirmees', 'on_gratuite_handle_purge' );
