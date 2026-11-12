<?php
/**
 * Free student subscription: private storage of proof documents (GDPR).
 *
 * A proof file is taken out of Elementor's public upload folder, ENCRYPTED
 * (libsodium secretbox) and stored in a private folder under a random name.
 * Encryption keeps the file unreadable even if the web server serves the
 * folder (Nginx ignores .htaccess). It is only readable, decrypted, through an
 * authenticated admin request, and is deleted on decision and after a
 * retention delay. No filename or URL of a proof is ever put in an e-mail.
 */
defined( 'ABSPATH' ) || exit;

const ON_GRATUITE_META_FILE      = '_on_gratuite_justificatif';
const ON_GRATUITE_META_NAME      = '_on_gratuite_justificatif_nom';
const ON_GRATUITE_META_MIME      = '_on_gratuite_justificatif_mime';
const ON_GRATUITE_META_STORED_AT = '_on_gratuite_justificatif_date';
const ON_GRATUITE_META_DELETED   = '_on_gratuite_justificatif_supprime';
const ON_GRATUITE_META_ACCESS    = '_on_gratuite_justificatif_acces';
const ON_GRATUITE_PURGE_HOOK     = 'on_gratuite_purge_justificatifs';
const ON_GRATUITE_FILE_PATTERN   = '/^[a-f0-9]{32}\.enc$/';
const ON_GRATUITE_MAGIC          = 'ONG1';

/** Post type holding the authorizations (created with Pods). */
function on_gratuite_post_type() {
    return apply_filters( 'on_gratuite_post_type', 'on_abon_autorisation' );
}

/** Capability required to read or delete a proof. */
function on_gratuite_capability() {
    return apply_filters( 'on_gratuite_capability', 'manage_woocommerce' );
}

/** Retention delay in days, at most. */
function on_gratuite_retention_days() {
    return max( 1, (int) apply_filters( 'on_gratuite_retention_days', 60 ) );
}

/** Accepted extensions and their real MIME type. */
function on_gratuite_proof_allowed_types() {
    return apply_filters( 'on_gratuite_proof_types', array(
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ) );
}

/** Maximum size of a proof, in bytes. */
function on_gratuite_proof_max_bytes() {
    return (int) apply_filters( 'on_gratuite_proof_max_bytes', 5 * 1024 * 1024 );
}

/** Absolute path of the private folder (not created). */
function on_gratuite_proof_dir() {
    $upload = wp_upload_dir( null, false );
    return rtrim( $upload['basedir'], '/\\' ) . '/on-gratuite-prive';
}

/**
 * Create the private folder with deny rules for Apache and an empty index.
 * These rules are not enough on every server (see docs/gratuite.md).
 *
 * @return string|false Folder path, or false if it cannot be created.
 */
function on_gratuite_prepare_proof_dir() {
    $dir = on_gratuite_proof_dir();
    if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
        return false;
    }
    $rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";
    if ( ! file_exists( $dir . '/.htaccess' ) ) {
        file_put_contents( $dir . '/.htaccess', $rules );
    }
    if ( ! file_exists( $dir . '/index.php' ) ) {
        file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
    }
    return $dir;
}

/**
 * Encryption key (32 bytes). Derived from the site salts, unless a constant
 * ON_GRATUITE_PROOF_KEY (64 hex characters) is set in wp-config.php.
 * If the salts change, stored proofs become unreadable (retention is 60 days).
 */
function on_gratuite_proof_key() {
    if ( defined( 'ON_GRATUITE_PROOF_KEY' ) && 1 === preg_match( '/^[a-f0-9]{64}$/', (string) ON_GRATUITE_PROOF_KEY ) ) {
        return hex2bin( ON_GRATUITE_PROOF_KEY );
    }
    return hash_hkdf( 'sha256', wp_salt( 'secure_auth' ), SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'on-gratuite-proof-v1' );
}

/** Encrypt data: magic + nonce + authenticated ciphertext. */
function on_gratuite_encrypt( $plain ) {
    $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
    return ON_GRATUITE_MAGIC . $nonce . sodium_crypto_secretbox( $plain, $nonce, on_gratuite_proof_key() );
}

/** Decrypt data from on_gratuite_encrypt(); false if altered or wrong key. */
function on_gratuite_decrypt( $blob ) {
    $head = strlen( ON_GRATUITE_MAGIC );
    $n    = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
    if ( ! is_string( $blob ) || strlen( $blob ) < $head + $n + SODIUM_CRYPTO_SECRETBOX_MACBYTES || ON_GRATUITE_MAGIC !== substr( $blob, 0, $head ) ) {
        return false;
    }
    return sodium_crypto_secretbox_open( substr( $blob, $head + $n ), substr( $blob, $head, $n ), on_gratuite_proof_key() );
}

/** Whether a stored name is a name we generated (blocks any path trick). */
function on_gratuite_is_valid_proof_name( $name ) {
    return is_string( $name ) && 1 === preg_match( ON_GRATUITE_FILE_PATTERN, $name );
}

/**
 * Move a file into the private folder and link it to an authorization.
 * An earlier proof of the same authorization is deleted first.
 *
 * @param string $source        Path of the uploaded file.
 * @param string $original_name Name given by the visitor (shown to admins only).
 * @param int    $post_id       Authorization id.
 * @return array|WP_Error Stored data, or an error with a message for the visitor.
 */
function on_gratuite_store_proof( $source, $original_name, $post_id ) {
    $extension = strtolower( pathinfo( (string) $original_name, PATHINFO_EXTENSION ) );
    $allowed   = on_gratuite_proof_allowed_types();
    if ( ! is_string( $source ) || ! is_file( $source ) ) {
        return new WP_Error( 'on_gratuite_no_file', __( 'Le justificatif est introuvable.', 'orgues-nouvelles' ) );
    }
    if ( ! isset( $allowed[ $extension ] ) ) {
        return new WP_Error( 'on_gratuite_bad_type', __( 'Format de justificatif non accepté (PDF, JPG ou PNG).', 'orgues-nouvelles' ) );
    }
    $size = filesize( $source );
    if ( $size < 1 || $size > on_gratuite_proof_max_bytes() ) {
        return new WP_Error( 'on_gratuite_bad_size', __( 'Le justificatif est vide ou trop volumineux.', 'orgues-nouvelles' ) );
    }
    $finfo = finfo_open( FILEINFO_MIME_TYPE );
    $mime  = $finfo ? finfo_file( $finfo, $source ) : false;
    if ( $finfo ) {
        finfo_close( $finfo );
    }
    if ( $mime !== $allowed[ $extension ] ) {
        return new WP_Error( 'on_gratuite_bad_mime', __( 'Le contenu du fichier ne correspond pas à son format.', 'orgues-nouvelles' ) );
    }
    $dir = on_gratuite_prepare_proof_dir();
    if ( ! $dir ) {
        return new WP_Error( 'on_gratuite_no_dir', __( 'Le justificatif ne peut pas être enregistré pour le moment.', 'orgues-nouvelles' ) );
    }
    $plain = file_get_contents( $source );
    if ( false === $plain || '' === $plain ) {
        return new WP_Error( 'on_gratuite_no_file', __( 'Le justificatif est introuvable.', 'orgues-nouvelles' ) );
    }
    on_gratuite_delete_proof( $post_id );
    $name = bin2hex( random_bytes( 16 ) ) . '.enc';
    if ( false === file_put_contents( $dir . '/' . $name, on_gratuite_encrypt( $plain ), LOCK_EX ) ) {
        return new WP_Error( 'on_gratuite_move_failed', __( 'Le justificatif ne peut pas être enregistré pour le moment.', 'orgues-nouvelles' ) );
    }
    @chmod( $dir . '/' . $name, 0600 );
    @unlink( $source );
    update_post_meta( $post_id, ON_GRATUITE_META_FILE, $name );
    update_post_meta( $post_id, ON_GRATUITE_META_NAME, sanitize_file_name( (string) $original_name ) );
    update_post_meta( $post_id, ON_GRATUITE_META_MIME, $allowed[ $extension ] );
    update_post_meta( $post_id, ON_GRATUITE_META_STORED_AT, time() );
    delete_post_meta( $post_id, ON_GRATUITE_META_DELETED );
    return array( 'file' => $name, 'mime' => $allowed[ $extension ], 'size' => $size );
}

/** Full path of the proof of an authorization, or false. */
function on_gratuite_proof_path( $post_id ) {
    $name = get_post_meta( $post_id, ON_GRATUITE_META_FILE, true );
    if ( ! on_gratuite_is_valid_proof_name( $name ) ) {
        return false;
    }
    $path = on_gratuite_proof_dir() . '/' . $name;
    return is_file( $path ) ? $path : false;
}

/** Decrypted content of the proof of an authorization, or false. */
function on_gratuite_read_proof( $post_id ) {
    $path = on_gratuite_proof_path( $post_id );
    if ( ! $path ) {
        return false;
    }
    return on_gratuite_decrypt( file_get_contents( $path ) );
}

/**
 * Delete the proof file and its data. Called on accept, refuse and purge.
 *
 * @return bool True if a proof existed.
 */
function on_gratuite_delete_proof( $post_id ) {
    $name = get_post_meta( $post_id, ON_GRATUITE_META_FILE, true );
    if ( '' === $name || false === $name ) {
        return false;
    }
    if ( on_gratuite_is_valid_proof_name( $name ) ) {
        $path = on_gratuite_proof_dir() . '/' . $name;
        if ( is_file( $path ) ) {
            @unlink( $path );
        }
    }
    delete_post_meta( $post_id, ON_GRATUITE_META_FILE );
    delete_post_meta( $post_id, ON_GRATUITE_META_NAME );
    delete_post_meta( $post_id, ON_GRATUITE_META_MIME );
    delete_post_meta( $post_id, ON_GRATUITE_META_STORED_AT );
    update_post_meta( $post_id, ON_GRATUITE_META_DELETED, time() );
    return true;
}

/** Send the proof to an administrator, with a trace of the access. */
function on_gratuite_serve_proof() {
    $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
    if ( ! $post_id || ! current_user_can( on_gratuite_capability() ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'orgues-nouvelles' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'on_gratuite_voir_' . $post_id );
    $content = on_gratuite_read_proof( $post_id );
    if ( false === $content ) {
        wp_die( esc_html__( 'Aucun justificatif lisible pour cette demande.', 'orgues-nouvelles' ), '', array( 'response' => 404 ) );
    }
    add_post_meta( $post_id, ON_GRATUITE_META_ACCESS, time() . '|' . get_current_user_id() );
    $mime  = get_post_meta( $post_id, ON_GRATUITE_META_MIME, true );
    $exts  = array_flip( array_unique( on_gratuite_proof_allowed_types() ) );
    $ext   = isset( $exts[ $mime ] ) ? $exts[ $mime ] : 'bin';
    nocache_headers();
    header( 'Content-Type: ' . ( isset( $exts[ $mime ] ) ? $mime : 'application/octet-stream' ) );
    header( 'Content-Disposition: inline; filename="justificatif.' . $ext . '"' );
    header( 'Content-Length: ' . strlen( $content ) );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Robots-Tag: noindex, nofollow' );
    echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- binary file for an authenticated admin.
    exit;
}
add_action( 'admin_post_on_gratuite_voir_justificatif', 'on_gratuite_serve_proof' );

/** Manual deletion from the authorization screen. */
function on_gratuite_handle_delete_proof() {
    $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
    if ( ! $post_id || ! current_user_can( on_gratuite_capability() ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'orgues-nouvelles' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'on_gratuite_suppr_' . $post_id );
    on_gratuite_delete_proof( $post_id );
    wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
    exit;
}
add_action( 'admin_post_on_gratuite_supprimer_justificatif', 'on_gratuite_handle_delete_proof' );

/** Box on the authorization edit screen. */
function on_gratuite_add_proof_metabox() {
    add_meta_box( 'on_gratuite_justificatif', __( 'Justificatif', 'orgues-nouvelles' ), 'on_gratuite_render_proof_metabox', on_gratuite_post_type(), 'side', 'high' );
}
add_action( 'add_meta_boxes', 'on_gratuite_add_proof_metabox' );

function on_gratuite_render_proof_metabox( $post ) {
    if ( ! current_user_can( on_gratuite_capability() ) ) {
        return;
    }
    $path = on_gratuite_proof_path( $post->ID );
    if ( ! $path ) {
        $deleted = (int) get_post_meta( $post->ID, ON_GRATUITE_META_DELETED, true );
        echo '<p>' . esc_html( $deleted
            ? sprintf( /* translators: %s: date */ __( 'Justificatif supprimé le %s.', 'orgues-nouvelles' ), wp_date( 'd/m/Y', $deleted ) )
            : __( 'Aucun justificatif conservé.', 'orgues-nouvelles' ) ) . '</p>';
        return;
    }
    $stored = (int) get_post_meta( $post->ID, ON_GRATUITE_META_STORED_AT, true );
    $view   = wp_nonce_url( admin_url( 'admin-post.php?action=on_gratuite_voir_justificatif&post_id=' . $post->ID ), 'on_gratuite_voir_' . $post->ID );
    $delete = wp_nonce_url( admin_url( 'admin-post.php?action=on_gratuite_supprimer_justificatif&post_id=' . $post->ID ), 'on_gratuite_suppr_' . $post->ID );
    echo '<p><a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url( $view ) . '">' . esc_html__( 'Voir le justificatif', 'orgues-nouvelles' ) . '</a></p>';
    if ( $stored ) {
        echo '<p>' . esc_html( sprintf( /* translators: %s: date */ __( 'Suppression automatique au plus tard le %s.', 'orgues-nouvelles' ), wp_date( 'd/m/Y', $stored + on_gratuite_retention_days() * DAY_IN_SECONDS ) ) ) . '</p>';
    }
    echo '<p><a href="' . esc_url( $delete ) . '">' . esc_html__( 'Supprimer maintenant', 'orgues-nouvelles' ) . '</a></p>';
}

/**
 * Daily purge: proofs older than the retention delay, and orphan files.
 *
 * @return int Number of files deleted.
 */
function on_gratuite_purge_expired_proofs() {
    $cutoff  = time() - on_gratuite_retention_days() * DAY_IN_SECONDS;
    $deleted = 0;
    $ids     = get_posts( array(
        // Explicit type: 'any' skips the types excluded from search, which is the case of the authorizations.
        'post_type'   => on_gratuite_post_type(),
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_key'    => ON_GRATUITE_META_FILE,
    ) );
    foreach ( $ids as $post_id ) {
        $stored = (int) get_post_meta( $post_id, ON_GRATUITE_META_STORED_AT, true );
        if ( ! $stored || $stored < $cutoff ) {
            $deleted += on_gratuite_delete_proof( $post_id ) ? 1 : 0;
        }
    }
    $dir = on_gratuite_proof_dir();
    if ( is_dir( $dir ) ) {
        foreach ( (array) scandir( $dir ) as $file ) {
            if ( on_gratuite_is_valid_proof_name( $file ) && filemtime( $dir . '/' . $file ) < $cutoff ) {
                @unlink( $dir . '/' . $file );
                $deleted++;
            }
        }
    }
    return $deleted;
}
add_action( ON_GRATUITE_PURGE_HOOK, 'on_gratuite_purge_expired_proofs' );

/** Schedule the daily purge once. */
function on_gratuite_schedule_purge() {
    if ( ! wp_next_scheduled( ON_GRATUITE_PURGE_HOOK ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', ON_GRATUITE_PURGE_HOOK );
    }
}
add_action( 'init', 'on_gratuite_schedule_purge' );
