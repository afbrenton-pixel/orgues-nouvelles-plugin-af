<?php
/**
 * Free student subscription: e-mail confirmation of the request.
 *
 * A request starts as "a_confirmer". The applicant receives a link; clicking it proves
 * the address is real and moves the request to "en_attente" (to be checked by the team).
 * An unconfirmed request (and its proof) is deleted: by hand for now (see
 * confirmation-admin.php), automatically if the setting `auto_purge_unconfirmed` is on.
 * The pure functions at the top take everything they need as arguments, so they are tested alone.
 */
defined( 'ABSPATH' ) || exit;

/** A new random confirmation token (32 hexadecimal characters). */
function on_gratuite_new_token() {
    return bin2hex( random_bytes( 16 ) );
}

/** What is stored for a token: a keyed hash, never the token itself. */
function on_gratuite_token_hash( $token, $post_id, $key ) {
    return hash_hmac( 'sha256', (int) $post_id . '|' . $token, $key );
}

/**
 * Judge a click on the confirmation link.
 *
 * @param array $in { status, stored_hash, token, post_id, key, created (timestamp), now (timestamp), days }
 * @return string 'ok', 'already' (already confirmed or decided), 'expired' or 'invalid'.
 */
function on_gratuite_check_confirmation( array $in ) {
    $status = isset( $in['status'] ) ? $in['status'] : '';
    if ( in_array( $status, array( 'en_attente', 'acceptee', 'refusee' ), true ) ) {
        return 'already';
    }
    $stored = isset( $in['stored_hash'] ) ? (string) $in['stored_hash'] : '';
    $token  = isset( $in['token'] ) ? (string) $in['token'] : '';
    if ( 'a_confirmer' !== $status || '' === $stored || 1 !== preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
        return 'invalid';
    }
    if ( ! hash_equals( $stored, on_gratuite_token_hash( $token, $in['post_id'], $in['key'] ) ) ) {
        return 'invalid';
    }
    if ( on_gratuite_is_stale( $in['created'], $in['now'], $in['days'] ) ) {
        return 'expired';
    }
    return 'ok';
}

/** Whether a request created at $created is older than $days days. */
function on_gratuite_is_stale( $created, $now, $days ) {
    return (int) $now - (int) $created > (int) $days * 86400;
}

/** Messages shown after a click on the link, by lang. */
function on_gratuite_confirmation_page_message( $result, $lang = 'fr' ) {
    $en = ( 'en' === $lang );
    $m  = array(
        'ok'      => array(
            array( 'Merci, votre demande est confirmée', 'Nous allons examiner votre justificatif et vous répondrons par e-mail.' ),
            array( 'Thank you, your request is confirmed', 'We will check your proof document and reply by e-mail.' ),
        ),
        'already' => array(
            array( 'Demande déjà confirmée', 'Votre demande a déjà été confirmée : nous vous répondrons par e-mail.' ),
            array( 'Request already confirmed', 'Your request has already been confirmed: we will reply by e-mail.' ),
        ),
        'expired' => array(
            array( 'Lien expiré', 'Ce lien n\'est plus valable. Merci de refaire votre demande depuis la page du formulaire.' ),
            array( 'Link expired', 'This link is no longer valid. Please submit your request again from the form page.' ),
        ),
        'invalid' => array(
            array( 'Lien non valable', 'Ce lien n\'est pas valable. Merci de refaire votre demande depuis la page du formulaire.' ),
            array( 'Invalid link', 'This link is not valid. Please submit your request again from the form page.' ),
        ),
    );
    $m = isset( $m[ $result ] ) ? $m[ $result ] : $m['invalid'];
    return $m[ $en ? 1 : 0 ];
}

/**
 * The e-mail sent to the applicant. Plain text, never mentions or attaches the proof.
 *
 * @return array { subject, body }
 */
function on_gratuite_confirmation_mail( $lang, $first_name, $url, $days, $for_other = false ) {
    if ( 'en' === $lang ) {
        return array(
            'subject' => 'Orgues Nouvelles: please confirm your request',
            'body'    => implode( "\n", array(
                'Hello ' . $first_name . ',',
                '',
                $for_other ? 'A request for the free digital subscription for people under 26 was made for you by a user of our website.' : 'We received your request for the free digital subscription for people under 26.',
                'To confirm that this e-mail address is yours, please click the link below:',
                '',
                $url,
                '',
                'The link is valid for ' . (int) $days . ' days. Without confirmation, your request and the document you sent are deleted.',
                'Once confirmed, we check your document and reply by e-mail.',
                '',
                'If you did not make this request, simply ignore this message.',
                '',
                'Orgues Nouvelles',
            ) ),
        );
    }
    return array(
        'subject' => 'Orgues Nouvelles : merci de confirmer votre demande',
        'body'    => implode( "\n", array(
            'Bonjour ' . $first_name . ',',
            '',
            $for_other ? 'Une demande d\'abonnement numérique gratuit pour les moins de 26 ans a été faite pour vous par un utilisateur de notre site.' : 'Nous avons bien reçu votre demande d\'abonnement numérique gratuit pour les moins de 26 ans.',
            'Pour confirmer que cette adresse e-mail est bien la vôtre, cliquez sur le lien ci-dessous :',
            '',
            $url,
            '',
            'Le lien est valable ' . (int) $days . ' jours. Sans confirmation, votre demande et le document envoyé sont supprimés.',
            'Une fois confirmée, nous examinons votre justificatif et nous vous répondons par e-mail.',
            '',
            'Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement ce message.',
            '',
            'Orgues Nouvelles',
        ) ),
    );
}

/** Key used for the token hashes. */
function on_gratuite_confirmation_key() {
    return wp_salt( 'auth' ) . '|on_gratuite_confirm';
}

/** Address of the confirmation link. */
function on_gratuite_confirmation_url( $post_id, $token ) {
    return add_query_arg( array( 'on_gratuite_confirm' => (int) $post_id, 'k' => $token ), home_url( '/' ) );
}

/**
 * Store a token for the request and e-mail the link to the applicant.
 *
 * @return bool Whether the e-mail was handed to wp_mail().
 */
function on_gratuite_send_confirmation( $post_id, array $d ) {
    $days  = (int) on_gratuite_settings()['confirm_days'];
    $token = on_gratuite_new_token();
    update_post_meta( $post_id, '_on_gratuite_token_hash', on_gratuite_token_hash( $token, $post_id, on_gratuite_confirmation_key() ) );
    update_post_meta( $post_id, '_on_gratuite_created', time() );
    $mail = on_gratuite_confirmation_mail( $d['langue'], $d['prenom'], on_gratuite_confirmation_url( $post_id, $token ), $days, ! empty( $d['pour_autre'] ) );
    return (bool) wp_mail( $d['email'], $mail['subject'], $mail['body'], array( 'Content-Type: text/plain; charset=UTF-8' ) );
}

/** The data of a stored request, in the shape used by on_gratuite_notify_new_request(). */
function on_gratuite_request_data( $post_id ) {
    $d = array();
    foreach ( array( 'prenom', 'nom', 'email', 'date_naissance', 'langue', 'type_demande', 'age_demande', 'demandeur_id', 'pour_autre' ) as $key ) {
        $d[ $key ] = (string) get_post_meta( $post_id, $key, true );
    }
    return $d;
}

/** Delete a request completely: proof file, then the record (no trace of personal data is kept). */
function on_gratuite_erase_request( $post_id ) {
    on_gratuite_delete_proof( $post_id );
    return (bool) wp_delete_post( $post_id, true );
}

/** Erase earlier unconfirmed requests of an address (a new request replaces them). */
function on_gratuite_erase_unconfirmed_for( $email ) {
    if ( '' === (string) $email ) {
        return 0;
    }
    $ids = get_posts( array(
        'post_type'   => on_gratuite_post_type(),
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_query'  => array(
            array( 'key' => 'email', 'value' => $email ),
            array( 'key' => 'statut', 'value' => 'a_confirmer' ),
        ),
    ) );
    foreach ( $ids as $id ) {
        on_gratuite_erase_request( $id );
    }
    return count( $ids );
}

/** IDs of the unconfirmed requests older than the confirmation delay. */
function on_gratuite_stale_unconfirmed_ids() {
    $cutoff = time() - (int) on_gratuite_settings()['confirm_days'] * 86400;
    return get_posts( array(
        'post_type'   => on_gratuite_post_type(),
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_query'  => array(
            array( 'key' => 'statut', 'value' => 'a_confirmer' ),
            array( 'key' => '_on_gratuite_created', 'value' => $cutoff, 'compare' => '<', 'type' => 'NUMERIC' ),
        ),
    ) );
}

/** Purge the stale unconfirmed requests. @return int Number erased. */
function on_gratuite_purge_unconfirmed() {
    $n = 0;
    foreach ( on_gratuite_stale_unconfirmed_ids() as $id ) {
        if ( on_gratuite_erase_request( $id ) ) {
            $n++;
        }
    }
    return $n;
}
add_action( 'on_gratuite_purge_unconfirmed_event', 'on_gratuite_purge_unconfirmed' );

/** The automatic purge is scheduled only when the setting is on (off by default). */
function on_gratuite_schedule_unconfirmed_purge() {
    $scheduled = wp_next_scheduled( 'on_gratuite_purge_unconfirmed_event' );
    if ( ! empty( on_gratuite_settings()['auto_purge_unconfirmed'] ) ) {
        if ( ! $scheduled ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'on_gratuite_purge_unconfirmed_event' );
        }
    } elseif ( $scheduled ) {
        wp_clear_scheduled_hook( 'on_gratuite_purge_unconfirmed_event' );
    }
}
add_action( 'init', 'on_gratuite_schedule_unconfirmed_purge' );

/** Handle a click on the confirmation link (address: /?on_gratuite_confirm=<id>&k=<token>). */
function on_gratuite_handle_confirmation_click() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token in the link is the proof.
    if ( ! isset( $_GET['on_gratuite_confirm'] ) ) {
        return;
    }
    $post_id = (int) $_GET['on_gratuite_confirm'];
    $token   = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : '';
    // phpcs:enable
    $post = $post_id ? get_post( $post_id ) : null;
    $lang = 'fr';
    $res  = 'invalid';
    if ( $post && on_gratuite_post_type() === $post->post_type ) {
        $lang = 'en' === get_post_meta( $post_id, 'langue', true ) ? 'en' : 'fr';
        $res  = on_gratuite_check_confirmation( array(
            'status'      => (string) get_post_meta( $post_id, 'statut', true ),
            'stored_hash' => (string) get_post_meta( $post_id, '_on_gratuite_token_hash', true ),
            'token'       => $token,
            'post_id'     => $post_id,
            'key'         => on_gratuite_confirmation_key(),
            'created'     => (int) get_post_meta( $post_id, '_on_gratuite_created', true ),
            'now'         => time(),
            'days'        => (int) on_gratuite_settings()['confirm_days'],
        ) );
        if ( 'ok' === $res ) {
            update_post_meta( $post_id, 'statut', 'en_attente' );
            update_post_meta( $post_id, '_on_gratuite_confirmed_at', time() );
            delete_post_meta( $post_id, '_on_gratuite_token_hash' );
            on_gratuite_notify_new_request( $post_id, on_gratuite_request_data( $post_id ) );
        }
    }
    list( $title, $text ) = on_gratuite_confirmation_page_message( $res, $lang );
    nocache_headers();
    wp_die(
        '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $text ) . '</p><p><a href="' . esc_url( home_url( '/' ) ) . '">Orgues Nouvelles</a></p>',
        esc_html( $title ),
        array( 'response' => 'ok' === $res || 'already' === $res ? 200 : 410 )
    );
}
add_action( 'template_redirect', 'on_gratuite_handle_confirmation_click', 1 );
