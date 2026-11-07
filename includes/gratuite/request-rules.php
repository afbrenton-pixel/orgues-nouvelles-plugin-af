<?php
/**
 * Free student subscription: rules applied to a request (pure functions).
 *
 * Nothing here reads the database or the form: data is given in, errors come out,
 * so the rules can be tested alone.
 */
defined( 'ABSPATH' ) || exit;

/** Messages shown to the visitor, in French and English. */
function on_gratuite_message( $key, $lang = 'fr', $limit = 0, $extra = '' ) {
    $en = ( 'en' === $lang );
    $messages = array(
        'bad_dob'     => array( 'Date de naissance non valide.', 'Invalid date of birth.' ),
        'too_old'     => array(
            sprintf( 'Cette offre est réservée aux personnes de moins de %d ans.', $limit ),
            sprintf( 'This offer is reserved for people under %d.', $limit ),
        ),
        'login_first' => array(
            'Un compte existe déjà avec cette adresse. Connectez-vous d\'abord à votre compte, puis refaites votre demande depuis cette page.',
            'An account already exists with this address. Please log in to your account first, then submit your request again from this page.',
        ),
        'email_mismatch' => array(
            sprintf( 'Vous êtes connecté(e) : la demande se fait avec l\'adresse de votre compte (%s). Pour demander pour une autre personne, cochez la case prévue.', $extra ),
            sprintf( 'You are logged in: the request is made with the address of your account (%s). To apply for someone else, tick the box provided.', $extra ),
        ),
        'login_for_other' => array(
            'Pour faire une demande pour une autre personne, connectez-vous d\'abord à votre compte.',
            'To apply for another person, please log in to your account first.',
        ),
        'same_email'  => array(
            'Cette adresse est celle de votre compte. Décochez la case « pour une autre personne », ou indiquez l\'adresse de la personne concernée.',
            'This is the address of your account. Untick the "for another person" box, or enter the address of the person concerned.',
        ),
        'pending'     => array(
            'Une demande est déjà en cours d\'examen pour cette adresse.',
            'A request is already being reviewed for this address.',
        ),
        'no_proof'    => array( 'Veuillez joindre un justificatif.', 'Please attach a proof document.' ),
        'bad_proof'   => array(
            'Justificatif non accepté : PDF, JPG ou PNG, 5 Mo au maximum.',
            'Proof not accepted: PDF, JPG or PNG, 5 MB at most.',
        ),
        'rate_limit'  => array(
            'Trop de demandes depuis cette connexion. Réessayez plus tard.',
            'Too many requests from this connection. Please try again later.',
        ),
        'technical'   => array(
            'Votre demande n\'a pas pu être enregistrée. Réessayez plus tard ou écrivez-nous.',
            'Your request could not be saved. Please try again later or write to us.',
        ),
    );
    if ( ! isset( $messages[ $key ] ) ) {
        return '';
    }
    return $messages[ $key ][ $en ? 1 : 0 ];
}

/** Parse a YYYY-MM-DD date at midnight, or null if invalid. */
function on_gratuite_parse_date( $value ) {
    if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( $value ) ) ) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat( '!Y-m-d', trim( $value ) );
    $err  = DateTimeImmutable::getLastErrors();
    if ( ! $date || ( $err && ( $err['warning_count'] || $err['error_count'] ) ) ) {
        return null;
    }
    return $date;
}

/**
 * Evaluate a request.
 *
 * @param array $in {
 *     @type string                 $dob               Date of birth, YYYY-MM-DD.
 *     @type string                 $lang              'fr' or 'en'.
 *     @type bool                   $logged_in         The visitor is logged in.
 *     @type bool                   $email_has_account An account exists for the typed address.
 *     @type bool                   $for_other         The request is made for another person (the typed address is theirs).
 *     @type string                 $typed_email       Address typed in the form.
 *     @type string                 $account_email     Address of the logged-in account.
 *     @type bool                   $has_accepted      The customer already had an accepted authorization (=> renewal).
 *     @type bool                   $has_pending       An authorization is already waiting for this address.
 *     @type DateTimeImmutable|null $today             For tests.
 * }
 * @return array { type: 'premiere'|'renouvellement', age: int|null, errors: array field id => message }
 */
function on_gratuite_evaluate_request( array $in ) {
    $settings = on_gratuite_settings();
    $lang     = ( isset( $in['lang'] ) && 'en' === $in['lang'] ) ? 'en' : 'fr';
    $today    = isset( $in['today'] ) ? $in['today'] : new DateTimeImmutable( 'today' );
    $type     = ! empty( $in['has_accepted'] ) ? 'renouvellement' : 'premiere';
    $errors   = array();
    $age      = null;

    $dob = on_gratuite_parse_date( isset( $in['dob'] ) ? $in['dob'] : '' );
    if ( ! $dob || $dob > $today || (int) $dob->format( 'Y' ) < 1900 ) {
        $errors['date_naissance'] = on_gratuite_message( 'bad_dob', $lang );
    } else {
        $age   = $dob->diff( $today )->y;
        $limit = ( 'premiere' === $type ) ? (int) $settings['age_first'] : (int) $settings['age_renew'];
        if ( $age >= $limit ) {
            $errors['date_naissance'] = on_gratuite_message( 'too_old', $lang, $limit );
        }
    }
    $typed   = strtolower( trim( isset( $in['typed_email'] ) ? (string) $in['typed_email'] : '' ) );
    $account = strtolower( trim( isset( $in['account_email'] ) ? (string) $in['account_email'] : '' ) );
    if ( ! empty( $in['for_other'] ) ) {
        // Request for someone else: the typed address is the beneficiary's. Requires a logged-in requester.
        if ( empty( $in['logged_in'] ) ) {
            $errors['pour_autre'] = on_gratuite_message( 'login_for_other', $lang );
        } elseif ( '' !== $account && $typed === $account ) {
            $errors['email'] = on_gratuite_message( 'same_email', $lang );
        }
    } elseif ( ! empty( $in['logged_in'] ) ) {
        // For oneself: the account's address is always used, so a different typed address is refused (never ignored silently).
        if ( '' !== $account && '' !== $typed && $typed !== $account ) {
            $errors['email'] = on_gratuite_message( 'email_mismatch', $lang, 0, $account );
        }
    } elseif ( ! empty( $in['email_has_account'] ) ) {
        $errors['email'] = on_gratuite_message( 'login_first', $lang );
    }
    if ( ! empty( $in['has_pending'] ) ) {
        $errors['email'] = on_gratuite_message( 'pending', $lang );
    }
    return array( 'type' => $type, 'age' => $age, 'errors' => $errors );
}

/** Whether a proof file passes the early checks (extension and size, before Elementor stores it). */
function on_gratuite_proof_is_acceptable( $name, $size ) {
    $extension = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
    $allowed   = function_exists( 'on_gratuite_proof_allowed_types' ) ? on_gratuite_proof_allowed_types() : array( 'pdf' => 1, 'jpg' => 1, 'jpeg' => 1, 'png' => 1 );
    $max       = function_exists( 'on_gratuite_proof_max_bytes' ) ? on_gratuite_proof_max_bytes() : 5 * 1024 * 1024;
    return isset( $allowed[ $extension ] ) && (int) $size > 0 && (int) $size <= $max;
}

/** Keep only the actions that cannot leak personal data (the proof or the form fields). */
function on_gratuite_filter_submit_actions( $actions ) {
    if ( ! is_array( $actions ) || ! in_array( 'on_gratuite', $actions, true ) ) {
        return $actions;
    }
    return array_values( array_intersect( $actions, array( 'on_gratuite', 'redirect' ) ) );
}
