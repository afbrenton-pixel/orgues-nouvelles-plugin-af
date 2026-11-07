<?php
/**
 * Free student subscription: Elementor Pro form integration.
 *
 * The form is a normal Elementor "Form" widget (built in Elementor, no shortcode).
 * Field IDs expected (Advanced tab of each field):
 *   prenom, nom, email, date_naissance, justificatif (Upload, 1 file), newsletter (optional),
 *   pour_autre (Acceptance, optional: request for another person), consentement (Acceptance, required),
 *   langue (Hidden, value "fr" or "en").
 * In "Actions After Submit", choose only "Gratuité -26 ans (autorisation)" (and Redirect if wanted).
 * Other actions are removed by on_gratuite_filter_submit_actions(): they would copy
 * personal data or the proof's address.
 */
defined( 'ABSPATH' ) || exit;

/** Register the action with Elementor Pro. */
function on_gratuite_register_form_action( $registrar ) {
    require_once __DIR__ . '/class-form-action.php';
    $registrar->register( new ON_Gratuite_Form_Action() );
}
add_action( 'elementor_pro/forms/actions/register', 'on_gratuite_register_form_action' );

add_filter( 'elementor_pro/forms/submit_actions', 'on_gratuite_filter_submit_actions', 10, 1 );

/** Whether this form record uses our action. */
function on_gratuite_record_uses_action( $record ) {
    $actions = $record->get_form_settings( 'submit_actions' );
    return is_array( $actions ) && in_array( 'on_gratuite', $actions, true );
}

/** Values of the form fields, by field ID. */
function on_gratuite_record_values( $record ) {
    $values = array();
    foreach ( (array) $record->get( 'fields' ) as $id => $field ) {
        $values[ $id ] = isset( $field['value'] ) ? trim( (string) $field['value'] ) : '';
    }
    return $values;
}

/** Name, size and error code of the first uploaded proof ($_FILES is read directly, before Elementor stores it). */
function on_gratuite_uploaded_proof() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Elementor verifies the request.
    $f = isset( $_FILES['form_fields'] ) ? $_FILES['form_fields'] : null;
    if ( ! is_array( $f ) ) {
        return null;
    }
    // Elementor Pro reshapes $_FILES to ['justificatif'][0]['name'] before validation; PHP's raw shape is ['name']['justificatif'].
    if ( isset( $f['justificatif'] ) && is_array( $f['justificatif'] ) ) {
        $e = $f['justificatif'];
        $e = isset( $e[0] ) && is_array( $e[0] ) ? $e[0] : $e;
        if ( isset( $e['name'] ) ) {
            return array( 'name' => (string) $e['name'], 'size' => (int) ( $e['size'] ?? 0 ), 'error' => (int) ( $e['error'] ?? UPLOAD_ERR_NO_FILE ) );
        }
    }
    if ( isset( $f['name']['justificatif'] ) ) {
        $pick = function ( $key ) use ( $f ) {
            $v = $f[ $key ]['justificatif'];
            return is_array( $v ) ? reset( $v ) : $v;
        };
        return array( 'name' => (string) $pick( 'name' ), 'size' => (int) $pick( 'size' ), 'error' => (int) $pick( 'error' ) );
    }
    return null;
}

/** Rate limit per IP (only a hash is kept, for one hour). */
function on_gratuite_rate_key() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    return 'on_gratuite_rl_' . substr( wp_hash( $ip ), 0, 20 );
}

/**
 * Build the data given to on_gratuite_evaluate_request().
 *
 * "email" is the address the request is for: the beneficiary's. For oneself it is the account's
 * address when logged in; for another person it is the typed address (requester must be logged in).
 */
function on_gratuite_request_input( array $values ) {
    $user      = wp_get_current_user();
    $typed     = strtolower( $values['email'] ?? '' );
    $for_other = ! empty( $values['pour_autre'] );
    $account   = $user->exists() ? strtolower( $user->user_email ) : '';
    $email     = ( $for_other || ! $user->exists() ) ? $typed : $account;
    $target_id = $for_other ? ( '' !== $typed ? (int) email_exists( $typed ) : 0 ) : ( $user->exists() ? (int) $user->ID : 0 );
    return array(
        'dob'               => $values['date_naissance'] ?? '',
        'lang'              => ( $values['langue'] ?? 'fr' ) === 'en' ? 'en' : 'fr',
        'logged_in'         => $user->exists(),
        'for_other'         => $for_other,
        'typed_email'       => $typed,
        'account_email'     => $account,
        'email_has_account' => '' !== $typed && (bool) email_exists( $typed ),
        'has_accepted'      => $target_id > 0 && on_gratuite_customer_has_accepted( $target_id ),
        'has_pending'       => on_gratuite_email_has_pending( $email ),
        'email'             => $email,
        'target_user_id'    => $target_id,
        'requester_id'      => $user->exists() ? (int) $user->ID : 0,
    );
}

/** Validation, before Elementor stores any file. */
function on_gratuite_validate_form( $record, $ajax_handler ) {
    if ( ! on_gratuite_record_uses_action( $record ) ) {
        return;
    }
    $values = on_gratuite_record_values( $record );
    $input  = on_gratuite_request_input( $values );
    $lang   = $input['lang'];

    foreach ( on_gratuite_evaluate_request( $input )['errors'] as $field => $message ) {
        $ajax_handler->add_error( $field, $message );
    }

    $proof = on_gratuite_uploaded_proof();
    if ( ! $proof || UPLOAD_ERR_NO_FILE === $proof['error'] || '' === $proof['name'] ) {
        $ajax_handler->add_error( 'justificatif', on_gratuite_message( 'no_proof', $lang ) );
    } elseif ( UPLOAD_ERR_OK !== $proof['error'] || ! on_gratuite_proof_is_acceptable( $proof['name'], $proof['size'] ) ) {
        $ajax_handler->add_error( 'justificatif', on_gratuite_message( 'bad_proof', $lang ) );
    }

    $settings = on_gratuite_settings();
    if ( (int) get_transient( on_gratuite_rate_key() ) >= (int) $settings['rate_limit_per_hour'] ) {
        $ajax_handler->add_error_message( on_gratuite_message( 'rate_limit', $lang ) );
        $ajax_handler->add_error( 'email', on_gratuite_message( 'rate_limit', $lang ) );
    }
}
add_action( 'elementor_pro/forms/validation', 'on_gratuite_validate_form', 10, 2 );

/** Delete the files Elementor stored in its public folder. */
function on_gratuite_remove_public_files( $record ) {
    $files = $record->get( 'files' );
    foreach ( (array) $files as $entry ) {
        foreach ( (array) ( $entry['path'] ?? array() ) as $path ) {
            if ( is_string( $path ) && is_file( $path ) ) {
                @unlink( $path );
            }
        }
    }
}

/**
 * Action: create the authorization, move the proof to the private encrypted storage, notify.
 * Returns the authorization ID, or false after adding an error to the form.
 */
function on_gratuite_process_submission( $record, $ajax_handler ) {
    $values = on_gratuite_record_values( $record );
    $input  = on_gratuite_request_input( $values );
    $lang   = $input['lang'];
    $result = on_gratuite_evaluate_request( $input );

    $fail = function ( $key ) use ( $record, $ajax_handler, $lang ) {
        on_gratuite_remove_public_files( $record );
        $ajax_handler->add_error_message( on_gratuite_message( $key, $lang ) );
        return false;
    };
    if ( $result['errors'] ) {
        return $fail( 'technical' );
    }
    $files = $record->get( 'files' );
    $path  = isset( $files['justificatif']['path'] ) ? reset( $files['justificatif']['path'] ) : '';
    if ( ! is_string( $path ) || ! is_file( $path ) ) {
        return $fail( 'no_proof' );
    }
    $uploaded = on_gratuite_uploaded_proof();
    $data     = array(
        'prenom'         => sanitize_text_field( $values['prenom'] ?? '' ),
        'nom'            => sanitize_text_field( $values['nom'] ?? '' ),
        'email'          => sanitize_email( $input['email'] ),
        'date_naissance' => $values['date_naissance'],
        'langue'         => $lang,
        'type_demande'   => $result['type'],
        'age_demande'    => $result['age'],
        'client_id'      => (int) $input['target_user_id'],
        'demandeur_id'   => (int) $input['requester_id'],
        'pour_autre'     => ! empty( $input['for_other'] ),
        'newsletter'     => ! empty( $values['newsletter'] ),
    );
    on_gratuite_erase_unconfirmed_for( $data['email'] ); // A new request replaces an earlier unconfirmed one.
    $post_id = on_gratuite_create_authorization( $data );
    if ( is_wp_error( $post_id ) ) {
        return $fail( 'technical' );
    }
    $stored = on_gratuite_store_proof( $path, $uploaded ? $uploaded['name'] : basename( $path ), $post_id );
    if ( is_wp_error( $stored ) ) {
        wp_delete_post( $post_id, true );
        return $fail( 'bad_proof' );
    }
    on_gratuite_remove_public_files( $record ); // Extra files, if any.
    $count = (int) get_transient( on_gratuite_rate_key() );
    set_transient( on_gratuite_rate_key(), $count + 1, HOUR_IN_SECONDS );
    // The team is notified when the applicant confirms the address (confirmation.php).
    if ( ! on_gratuite_send_confirmation( $post_id, $data ) ) {
        on_gratuite_erase_request( $post_id );
        return $fail( 'technical' );
    }
    return $post_id;
}
