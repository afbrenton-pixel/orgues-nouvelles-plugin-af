<?php
/**
 * Free student subscription: Elementor Pro form integration.
 *
 * The form is a normal Elementor "Form" widget (built in Elementor, no shortcode).
 * Field IDs expected (Advanced tab of each field):
 *   prenom, nom, email, date_naissance, justificatif (Upload, 1 file), newsletter (optional),
 *   consentement (Acceptance, required), langue (Hidden, value "fr" or "en").
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
    if ( ! $f || ! isset( $f['name']['justificatif'] ) ) {
        return null;
    }
    $pick = function ( $key ) use ( $f ) {
        $v = $f[ $key ]['justificatif'];
        return is_array( $v ) ? reset( $v ) : $v;
    };
    return array( 'name' => (string) $pick( 'name' ), 'size' => (int) $pick( 'size' ), 'error' => (int) $pick( 'error' ) );
}

/** Rate limit per IP (only a hash is kept, for one hour). */
function on_gratuite_rate_key() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    return 'on_gratuite_rl_' . substr( wp_hash( $ip ), 0, 20 );
}

/** Build the data given to on_gratuite_evaluate_request(). */
function on_gratuite_request_input( array $values ) {
    $user  = wp_get_current_user();
    $email = $user->exists() ? $user->user_email : strtolower( $values['email'] ?? '' );
    return array(
        'dob'               => $values['date_naissance'] ?? '',
        'lang'              => ( $values['langue'] ?? 'fr' ) === 'en' ? 'en' : 'fr',
        'logged_in'         => $user->exists(),
        'email_has_account' => '' !== $email && (bool) email_exists( $email ),
        'has_accepted'      => $user->exists() && on_gratuite_customer_has_accepted( $user->ID ),
        'has_pending'       => on_gratuite_email_has_pending( $email ),
        'email'             => $email,
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
        'client_id'      => get_current_user_id(),
        'newsletter'     => ! empty( $values['newsletter'] ),
    );
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
    on_gratuite_notify_new_request( $post_id, $data );
    return $post_id;
}
