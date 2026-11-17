<?php
/**
 * Free student subscription: rules applied to a request (pure functions).
 *
 * Nothing here reads the database or the form: data is given in, errors come out,
 * so the rules can be tested alone.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Messages shown to the visitor, in the language of the request.
 * Written in French; the English text is in languages/orgues-nouvelles-en_GB.po.
 */
function on_gratuite_message( $key, $lang = 'fr', $limit = 0, $extra = '' ) {
    return on_gratuite_in_lang( $lang, static function () use ( $key, $limit, $extra ) {
        switch ( $key ) {
            case 'bad_dob':
                return __( 'Date de naissance non valide.', 'orgues-nouvelles' );
            case 'too_old':
                /* translators: 1: last age allowed, 2: age at which the offer stops. */
                return sprintf( __( 'Cette offre est réservée aux jeunes jusqu\'à %1$d ans (avant leur %2$de anniversaire).', 'orgues-nouvelles' ), $limit - 1, $limit );
            case 'promo_not_yet':
                /* translators: %s: date. */
                return sprintf( __( 'Cette offre n\'est pas encore ouverte : les demandes ouvriront le %s.', 'orgues-nouvelles' ), $extra );
            case 'promo_closed':
                /* translators: %s: date. */
                return sprintf( __( 'Cette offre est terminée : les demandes étaient ouvertes jusqu\'au %s.', 'orgues-nouvelles' ), $extra );
            case 'login_first':
                return __( 'Un compte existe déjà avec cette adresse. Connectez-vous d\'abord à votre compte, puis refaites votre demande depuis cette page.', 'orgues-nouvelles' );
            case 'email_mismatch':
                /* translators: %s: e-mail address of the account. */
                return sprintf( __( 'Vous êtes connecté(e) : la demande se fait avec l\'adresse de votre compte (%s). Pour demander pour une autre personne, cochez la case prévue.', 'orgues-nouvelles' ), $extra );
            case 'login_for_other':
                return __( 'Pour faire une demande pour une autre personne, connectez-vous d\'abord à votre compte.', 'orgues-nouvelles' );
            case 'same_email':
                return __( 'Cette adresse est celle de votre compte. Décochez la case « pour une autre personne », ou indiquez l\'adresse de la personne concernée.', 'orgues-nouvelles' );
            case 'pending':
                return __( 'Une demande est déjà en cours d\'examen pour cette adresse.', 'orgues-nouvelles' );
            case 'renewal_unavailable':
                /* translators: 1: number of days, 2: contact e-mail address. */
                return sprintf( __( 'Un abonnement gratuit a déjà été obtenu avec cette adresse au titre de cette offre. Le renouvellement en ligne n\'est pas encore disponible ; il sera ouvert %1$d jours avant la date d\'expiration de l\'abonnement. Pour toute question sur un abonnement, écrivez-nous à %2$s.', 'orgues-nouvelles' ), $limit, $extra );
            case 'no_proof':
                return __( 'Veuillez joindre un justificatif.', 'orgues-nouvelles' );
            case 'bad_proof':
                return __( 'Justificatif non accepté : PDF, JPG ou PNG, 5 Mo au maximum.', 'orgues-nouvelles' );
            case 'rate_limit':
                return __( 'Trop de demandes depuis cette connexion. Réessayez plus tard.', 'orgues-nouvelles' );
            case 'technical':
                return __( 'Votre demande n\'a pas pu être enregistrée. Réessayez plus tard ou écrivez-nous.', 'orgues-nouvelles' );
        }
        return '';
    } );
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
 * State of the promotional period for first requests: 'open', 'not_yet' or 'closed'.
 * The end date is included. An empty date means no limit on that side.
 *
 * @param string            $start YYYY-MM-DD or ''.
 * @param string            $end   YYYY-MM-DD or ''.
 * @param DateTimeImmutable $today
 */
function on_gratuite_promo_state( $start, $end, DateTimeImmutable $today ) {
    $from = on_gratuite_parse_date( (string) $start );
    $to   = on_gratuite_parse_date( (string) $end );
    if ( $from && $today < $from ) {
        return 'not_yet';
    }
    if ( $to && $today > $to ) {
        return 'closed';
    }
    return 'open';
}

/**
 * Today's date in the site's time zone (not the server's UTC day), as a date-only value built like the
 * parsed birth and promotional dates, so that comparisons are between the same representations.
 */
function on_gratuite_today() {
    $ymd   = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );
    $today = on_gratuite_parse_date( (string) $ymd );
    return $today ? $today : new DateTimeImmutable( 'today' );
}

/** A date for a message: 09/11/2026 in French, 9 November 2026 in English (format translated like the texts). */
function on_gratuite_promo_date_text( $value, $lang ) {
    $date = on_gratuite_parse_date( (string) $value );
    if ( ! $date ) {
        return '';
    }
    return on_gratuite_in_lang( $lang, static function () use ( $date ) {
        /* translators: date format (PHP date() syntax) for a day in a message. */
        return $date->format( __( 'd/m/Y', 'orgues-nouvelles' ) );
    } );
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
 *     @type DateTimeImmutable|null $today             For tests (default: today in the site's time zone).
 * }
 * @return array { type: 'premiere'|'renouvellement', age: int|null, errors: array field id => message }
 */
function on_gratuite_evaluate_request( array $in ) {
    $settings = on_gratuite_settings();
    $lang     = ( isset( $in['lang'] ) && 'en' === $in['lang'] ) ? 'en' : 'fr';
    $today    = isset( $in['today'] ) ? $in['today'] : on_gratuite_today();
    $type     = ! empty( $in['has_accepted'] ) ? 'renouvellement' : 'premiere';
    $errors   = array();
    $age      = null;

    // Someone already accepted would be a renewal, which has no provisioning path yet (step 5): accepting it
    // would create a second, overlapping free subscription. Refused until the renewal workflow exists.
    if ( 'renouvellement' === $type && empty( $settings['renew_enabled'] ) ) {
        $errors['renouvellement'] = on_gratuite_message( 'renewal_unavailable', $lang, (int) $settings['renew_window_days'], (string) $settings['notify_email'] );
        return array( 'type' => $type, 'age' => null, 'errors' => $errors );
    }

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
    // The promotional period only limits first requests: renewals go on until the 27th birthday.
    if ( 'premiere' === $type ) {
        $state = on_gratuite_promo_state( (string) ( $settings['promo_start'] ?? '' ), (string) ( $settings['promo_end'] ?? '' ), $today );
        if ( 'open' !== $state ) {
            $date = on_gratuite_promo_date_text( 'not_yet' === $state ? $settings['promo_start'] : $settings['promo_end'], $lang );
            $errors['promo'] = on_gratuite_message( 'promo_' . $state, $lang, 0, $date );
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
    if ( ! empty( $in['has_pending'] ) && ! isset( $errors['email'] ) ) {
        $errors['email'] = on_gratuite_message( 'pending', $lang ); // Never hides a more precise message on the address.
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
