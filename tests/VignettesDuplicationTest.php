<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }
if ( ! defined( 'ON_VIGNETTES_CPT' ) ) { define( 'ON_VIGNETTES_CPT', 'vignette_accueil' ); }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( '__' ) ) { function __( $text ) { return $text; } }
if ( ! function_exists( 'wp_slash' ) ) { function wp_slash( $value ) { return $value; } }
if ( ! function_exists( 'maybe_unserialize' ) ) { function maybe_unserialize( $value ) { return is_string( $value ) && @unserialize( $value ) !== false ? unserialize( $value ) : $value; } }
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id ) { return $GLOBALS['on_test_meta'][ $id ] ?? array(); }
}
if ( ! function_exists( 'add_post_meta' ) ) {
	function add_post_meta( $id, $key, $value ) { $GLOBALS['on_test_added'][] = array( $id, $key, $value ); return true; }
}

require_once dirname( __DIR__ ) . '/includes/vignettes/duplication.php';

class VignettesDuplicationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['on_test_meta']  = array();
		$GLOBALS['on_test_added'] = array();
	}

	public function test_internal_import_values_are_not_copied_but_pods_fields_are() {
		foreach ( array( '_on_source_id', '_on_locked', '_on_autodraft', '_on_sans_image', '_on_source_type', '_edit_lock', '_edit_last', '_wp_old_slug' ) as $key ) {
			$this->assertTrue( on_vignettes_duplicate_skips_meta( $key ), $key );
		}
		foreach ( array( 'pistes', '_pods_pistes', 'lien', 'format', 'cadrage', 'masquer_titre', 'rang', 'image_cote', '_thumbnail_id' ) as $key ) {
			$this->assertFalse( on_vignettes_duplicate_skips_meta( $key ), $key );
		}
	}

	public function test_polylang_taxonomies_are_not_copied_but_labels_and_zones_are() {
		$this->assertTrue( on_vignettes_duplicate_skips_taxonomy( 'language' ) );
		$this->assertTrue( on_vignettes_duplicate_skips_taxonomy( 'post_translations' ) );
		$this->assertFalse( on_vignettes_duplicate_skips_taxonomy( 'zone_vignette' ) );
		$this->assertFalse( on_vignettes_duplicate_skips_taxonomy( 'etiquette_vignette' ) );
	}

	public function test_title_of_the_copy_says_it_is_a_copy() {
		$this->assertSame( 'Splendeur de l\'orgue ancien (copie)', on_vignettes_duplicate_title( 'Splendeur de l\'orgue ancien' ) );
	}

	public function test_a_field_with_several_values_keeps_its_order_and_skips_internal_values() {
		$GLOBALS['on_test_meta'][10] = array(
			'pistes'        => array( '3070', '1142', '3066' ), // playlist : l'ordre des pistes compte.
			'lien'          => array( 'https://exemple.org/page' ),
			'_on_source_id' => array( '55' ),
			'_on_locked'    => array( '1' ),
			'_edit_lock'    => array( '1700000000:3' ),
			'_pods_pistes'  => array( serialize( array( 3070, 1142, 3066 ) ) ),
		);
		on_vignettes_duplicate_meta( 10, 99 );
		$added = $GLOBALS['on_test_added'];
		$this->assertSame(
			array(
				array( 99, 'pistes', '3070' ),
				array( 99, 'pistes', '1142' ),
				array( 99, 'pistes', '3066' ),
				array( 99, 'lien', 'https://exemple.org/page' ),
				array( 99, '_pods_pistes', array( 3070, 1142, 3066 ) ),
			),
			$added
		);
	}

	public function test_no_meta_means_nothing_to_copy() {
		on_vignettes_duplicate_meta( 11, 100 );
		$this->assertSame( array(), $GLOBALS['on_test_added'] );
	}

	public function test_file_ids_are_normalised_in_order_without_duplicates() {
		$this->assertSame( array( 5, 3, 9 ), on_vignettes_duplicate_ids( array( array( 'ID' => '5' ), 3, '9', 5, 0, '' ) ) );
		$this->assertSame( array( 7 ), on_vignettes_duplicate_ids( 7 ) );
		$this->assertSame( array(), on_vignettes_duplicate_ids( false ) );
	}
}
