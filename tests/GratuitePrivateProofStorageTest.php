<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
if ( ! defined( 'HOUR_IN_SECONDS' ) ) { define( 'HOUR_IN_SECONDS', 3600 ); }
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $code;
        public function __construct( $code = '', $message = '' ) { $this->code = $code; }
        public function get_error_code() { return $this->code; }
    }
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
    function wp_upload_dir( $time = null, $create = true ) { return array( 'basedir' => $GLOBALS['on_gratuite_test_base'] ); }
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
    function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value ) { return $GLOBALS['on_gratuite_test_filters'][ $tag ] ?? $value; }
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
    function sanitize_file_name( $name ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', $name ); }
}
if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['on_gratuite_test_meta'][ $id ][ $key ] ?? ''; }
}
if ( ! function_exists( 'update_post_meta' ) ) {
    function update_post_meta( $id, $key, $value ) { $GLOBALS['on_gratuite_test_meta'][ $id ][ $key ] = $value; return true; }
}
if ( ! function_exists( 'delete_post_meta' ) ) {
    function delete_post_meta( $id, $key ) { unset( $GLOBALS['on_gratuite_test_meta'][ $id ][ $key ] ); return true; }
}
if ( ! function_exists( 'get_posts' ) ) {
    function get_posts( $args ) {
        $GLOBALS['on_gratuite_test_query'] = $args;
        $ids = array();
        foreach ( $GLOBALS['on_gratuite_test_meta'] as $id => $meta ) {
            if ( isset( $meta[ $args['meta_key'] ] ) ) { $ids[] = $id; }
        }
        return $ids;
    }
}
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = '' ) { return $text; }
}
if ( ! function_exists( 'wp_salt' ) ) {
    function wp_salt( $scheme = 'auth' ) { return $GLOBALS['on_gratuite_test_salt'] ?? 'salt-de-test-' . $scheme; }
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
    function add_filter() {}
}

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }
require_once dirname( __DIR__ ) . '/includes/gratuite/private-proof-storage.php';
require_once dirname( __DIR__ ) . '/includes/gratuite/authorization.php';

class GratuitePrivateProofStorageTest extends TestCase {
    private $base;

    protected function setUp(): void {
        $this->base = sys_get_temp_dir() . '/on-gratuite-test-' . bin2hex( random_bytes( 4 ) );
        mkdir( $this->base, 0777, true );
        $GLOBALS['on_gratuite_test_base']    = $this->base;
        $GLOBALS['on_gratuite_test_meta']    = array();
        $GLOBALS['on_gratuite_test_filters'] = array();
        unset( $GLOBALS['on_gratuite_test_salt'] );
    }

    protected function tearDown(): void {
        foreach ( glob( $this->base . '/on-gratuite-prive/{,.}*', GLOB_BRACE ) ?: array() as $f ) {
            if ( is_file( $f ) ) { unlink( $f ); }
        }
        @rmdir( $this->base . '/on-gratuite-prive' );
        @rmdir( $this->base );
    }

    private function make_upload( $content, $name = 'upload.tmp' ) {
        $path = $this->base . '/' . $name;
        file_put_contents( $path, $content );
        return $path;
    }

    private function png() {
        return base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
    }

    public function test_valid_proof_is_moved_to_private_folder_under_a_random_name() {
        $source = $this->make_upload( $this->png() );
        $result = on_gratuite_store_proof( $source, 'Carte étudiant.PNG', 10 );
        $this->assertIsArray( $result );
        $this->assertMatchesRegularExpression( ON_GRATUITE_FILE_PATTERN, $result['file'] );
        $this->assertFileDoesNotExist( $source );
        $this->assertFileExists( $this->base . '/on-gratuite-prive/' . $result['file'] );
        $this->assertSame( $result['file'], get_post_meta( 10, ON_GRATUITE_META_FILE, true ) );
        $this->assertStringNotContainsString( 'Carte', $result['file'] );
        $this->assertStringEndsWith( '.enc', $result['file'] );
    }

    public function test_private_folder_gets_deny_rules() {
        on_gratuite_store_proof( $this->make_upload( $this->png() ), 'a.png', 1 );
        $this->assertStringContainsString( 'Require all denied', file_get_contents( $this->base . '/on-gratuite-prive/.htaccess' ) );
        $this->assertFileExists( $this->base . '/on-gratuite-prive/index.php' );
    }

    public function test_wrong_extension_is_refused_and_nothing_is_stored() {
        $result = on_gratuite_store_proof( $this->make_upload( '<?php echo 1;' ), 'x.php', 2 );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'on_gratuite_bad_type', $result->get_error_code() );
        $this->assertSame( '', get_post_meta( 2, ON_GRATUITE_META_FILE, true ) );
    }

    public function test_content_must_match_the_extension() {
        $result = on_gratuite_store_proof( $this->make_upload( '<?php echo 1;' ), 'fake.pdf', 3 );
        $this->assertSame( 'on_gratuite_bad_mime', $result->get_error_code() );
    }

    public function test_too_large_file_is_refused() {
        $GLOBALS['on_gratuite_test_filters']['on_gratuite_proof_max_bytes'] = 10;
        $result = on_gratuite_store_proof( $this->make_upload( $this->png() ), 'a.png', 4 );
        $this->assertSame( 'on_gratuite_bad_size', $result->get_error_code() );
    }

    public function test_delete_removes_file_and_data_and_keeps_a_deletion_date() {
        $result = on_gratuite_store_proof( $this->make_upload( $this->png() ), 'a.png', 5 );
        $this->assertTrue( on_gratuite_delete_proof( 5 ) );
        $this->assertFileDoesNotExist( $this->base . '/on-gratuite-prive/' . $result['file'] );
        $this->assertFalse( on_gratuite_proof_path( 5 ) );
        $this->assertNotEmpty( get_post_meta( 5, ON_GRATUITE_META_DELETED, true ) );
        $this->assertFalse( on_gratuite_delete_proof( 5 ) );
    }

    public function test_second_proof_replaces_the_first() {
        $first  = on_gratuite_store_proof( $this->make_upload( $this->png(), 'u1.tmp' ), 'a.png', 6 );
        $second = on_gratuite_store_proof( $this->make_upload( $this->png(), 'u2.tmp' ), 'b.png', 6 );
        $this->assertFileDoesNotExist( $this->base . '/on-gratuite-prive/' . $first['file'] );
        $this->assertFileExists( $this->base . '/on-gratuite-prive/' . $second['file'] );
    }

    public function test_path_tricks_in_stored_name_are_ignored() {
        update_post_meta( 7, ON_GRATUITE_META_FILE, '../../wp-config.php' );
        $this->assertFalse( on_gratuite_proof_path( 7 ) );
        $this->assertFalse( on_gratuite_is_valid_proof_name( '../a.png' ) );
        $this->assertTrue( on_gratuite_is_valid_proof_name( str_repeat( 'a', 32 ) . '.enc' ) );
    }

    public function test_purge_deletes_only_expired_proofs() {
        $old = on_gratuite_store_proof( $this->make_upload( $this->png(), 'u1.tmp' ), 'a.png', 8 );
        $new = on_gratuite_store_proof( $this->make_upload( $this->png(), 'u2.tmp' ), 'b.png', 9 );
        update_post_meta( 8, ON_GRATUITE_META_STORED_AT, time() - 61 * DAY_IN_SECONDS );
        $count = on_gratuite_purge_expired_proofs();
        $this->assertSame( 1, $count );
        $this->assertFileDoesNotExist( $this->base . '/on-gratuite-prive/' . $old['file'] );
        $this->assertFileExists( $this->base . '/on-gratuite-prive/' . $new['file'] );
    }

    public function test_purge_removes_orphan_old_files() {
        $dir = on_gratuite_prepare_proof_dir();
        $orphan = $dir . '/' . str_repeat( 'b', 32 ) . '.enc';
        file_put_contents( $orphan, 'x' );
        touch( $orphan, time() - 90 * DAY_IN_SECONDS );
        $this->assertSame( 1, on_gratuite_purge_expired_proofs() );
        $this->assertFileDoesNotExist( $orphan );
    }

    public function test_stored_file_is_encrypted_and_not_readable_as_an_image() {
        $png    = $this->png();
        $result = on_gratuite_store_proof( $this->make_upload( $png ), 'a.png', 20 );
        $stored = file_get_contents( $this->base . '/on-gratuite-prive/' . $result['file'] );
        $this->assertNotSame( $png, $stored );
        $this->assertStringNotContainsString( 'PNG', $stored );
        $this->assertStringNotContainsString( $png, $stored );
    }

    public function test_admin_read_gives_back_the_original_content() {
        $png = $this->png();
        on_gratuite_store_proof( $this->make_upload( $png ), 'a.png', 21 );
        $this->assertSame( $png, on_gratuite_read_proof( 21 ) );
    }

    public function test_altered_file_is_refused() {
        $result = on_gratuite_store_proof( $this->make_upload( $this->png() ), 'a.png', 22 );
        $path   = $this->base . '/on-gratuite-prive/' . $result['file'];
        $blob   = file_get_contents( $path );
        file_put_contents( $path, substr( $blob, 0, -1 ) . chr( ord( substr( $blob, -1 ) ) ^ 1 ) );
        $this->assertFalse( on_gratuite_read_proof( 22 ) );
    }

    public function test_changed_site_key_makes_the_file_unreadable() {
        on_gratuite_store_proof( $this->make_upload( $this->png() ), 'a.png', 23 );
        $GLOBALS['on_gratuite_test_salt'] = 'un-autre-sel';
        $this->assertFalse( on_gratuite_read_proof( 23 ) );
    }

    public function test_two_encryptions_of_the_same_data_differ() {
        $this->assertNotSame( on_gratuite_encrypt( 'abc' ), on_gratuite_encrypt( 'abc' ) );
        $this->assertSame( 'abc', on_gratuite_decrypt( on_gratuite_encrypt( 'abc' ) ) );
        $this->assertFalse( on_gratuite_decrypt( 'pas chiffré du tout, mais assez long pour passer la taille minimale' ) );
    }

    public function test_purge_queries_the_authorization_type_explicitly() {
        // 'any' skips the types excluded from search, which is the case of the authorizations.
        on_gratuite_purge_expired_proofs();
        $this->assertSame( 'on_abon_autorisation', $GLOBALS['on_gratuite_test_query']['post_type'] );
    }

    public function test_purge_clears_the_proof_data_kept_on_the_record() {
        on_gratuite_store_proof( $this->make_upload( $this->png(), 'u3.tmp' ), 'carte-identite.png', 30 );
        update_post_meta( 30, ON_GRATUITE_META_STORED_AT, time() - 61 * DAY_IN_SECONDS );
        on_gratuite_purge_expired_proofs();
        $this->assertSame( '', get_post_meta( 30, ON_GRATUITE_META_NAME, true ), 'Original file name erased.' );
        $this->assertNotSame( '', get_post_meta( 30, ON_GRATUITE_META_DELETED, true ), 'Deletion date recorded.' );
    }

    public function test_every_capability_of_the_authorization_type_is_the_decision_one() {
        $args = on_gratuite_post_type_args( array( 'public' => false ), 'on_abon_autorisation' );
        $this->assertSame( 'manage_woocommerce', $args['capabilities']['edit_posts'] );
        $this->assertSame( 'manage_woocommerce', $args['capabilities']['read_private_posts'] );
        $this->assertArrayNotHasKey( 'edit_post', $args['capabilities'], 'Meta capabilities are left to WordPress.' );
        $this->assertArrayNotHasKey( 'read_post', $args['capabilities'] );
        $this->assertArrayNotHasKey( 'delete_post', $args['capabilities'] );
        $this->assertTrue( $args['map_meta_cap'] );
        $this->assertSame( array( 'manage_woocommerce' ), array_values( array_unique( $args['capabilities'] ) ) );
        $this->assertSame( array( 'public' => false ), on_gratuite_post_type_args( array( 'public' => false ), 'post' ), 'Other types untouched.' );
    }
}
