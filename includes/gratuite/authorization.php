<?php
/**
 * Free student subscription: authorization records (Pods post type
 * `on_abon_autorisation`, see docs/gratuite.md for the fields).
 */
defined( 'ABSPATH' ) || exit;

/** Whether this customer already had an accepted authorization (=> yearly renewal). */
function on_gratuite_customer_has_accepted( $user_id ) {
    if ( ! $user_id ) {
        return false;
    }
    return (bool) get_posts( array(
        'post_type'   => on_gratuite_post_type(),
        'post_status' => 'any',
        'numberposts' => 1,
        'fields'      => 'ids',
        'meta_query'  => array(
            array( 'key' => 'client_id', 'value' => (string) (int) $user_id ),
            array( 'key' => 'statut', 'value' => 'acceptee' ),
        ),
    ) );
}

/** Whether a request is already waiting for this address. */
function on_gratuite_email_has_pending( $email ) {
    if ( '' === (string) $email ) {
        return false;
    }
    return (bool) get_posts( array(
        'post_type'   => on_gratuite_post_type(),
        'post_status' => 'any',
        'numberposts' => 1,
        'fields'      => 'ids',
        'meta_query'  => array(
            array( 'key' => 'email', 'value' => $email ),
            array( 'key' => 'statut', 'value' => 'en_attente' ),
        ),
    ) );
}

/**
 * Create the authorization record, status "to confirm" (the applicant must click the e-mail link first).
 *
 * @param array $d { prenom, nom, email, date_naissance, langue, type_demande, age_demande, client_id, newsletter }
 * @return int|WP_Error Post id.
 */
function on_gratuite_create_authorization( array $d ) {
    $title = trim( mb_strtoupper( $d['nom'] ) . ' ' . $d['prenom'] ) . ' - ' . wp_date( 'd/m/Y' );
    $id    = wp_insert_post( array(
        'post_type'   => on_gratuite_post_type(),
        'post_status' => 'publish',
        'post_title'  => $title,
    ), true );
    if ( is_wp_error( $id ) || ! $id ) {
        return is_wp_error( $id ) ? $id : new WP_Error( 'on_gratuite_insert', 'insert failed' );
    }
    $meta = array(
        'prenom'        => $d['prenom'],
        'nom'           => $d['nom'],
        'email'         => $d['email'],
        'date_naissance' => $d['date_naissance'],
        'langue'        => $d['langue'],
        'type_demande'  => $d['type_demande'],
        'statut'        => 'a_confirmer',
        'age_demande'   => (int) $d['age_demande'],
        'client_id'     => (int) $d['client_id'],
        'newsletter'    => empty( $d['newsletter'] ) ? '0' : '1',
    );
    foreach ( $meta as $key => $value ) {
        update_post_meta( $id, $key, $value );
    }
    return (int) $id;
}

/** Notify the team. Never attaches or links the proof. */
function on_gratuite_notify_new_request( $post_id, array $d ) {
    $settings = on_gratuite_settings();
    $subject  = sprintf( '[Gratuité -26 ans] Nouvelle demande : %s %s', mb_strtoupper( $d['nom'] ), $d['prenom'] );
    $lines    = array(
        'Une nouvelle demande de gratuité est en attente de décision.',
        '',
        'Nom : ' . mb_strtoupper( $d['nom'] ) . ' ' . $d['prenom'],
        'E-mail : ' . $d['email'],
        'Date de naissance : ' . $d['date_naissance'] . ' (' . (int) $d['age_demande'] . ' ans)',
        'Type : ' . ( 'renouvellement' === $d['type_demande'] ? 'renouvellement annuel' : 'première demande' ),
        'Langue : ' . ( 'en' === $d['langue'] ? 'anglais' : 'français' ),
        '',
        'Fiche (le justificatif se consulte uniquement depuis cette fiche, une fois connecté) :',
        admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ),
    );
    return wp_mail( $settings['notify_email'], $subject, implode( "\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
}
