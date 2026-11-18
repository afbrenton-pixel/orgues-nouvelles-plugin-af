<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $hook, $value ) { return $value; } }

require_once dirname( __DIR__ ) . '/includes/vignettes/creation-en-masse.php';

class VignettesCreationEnMasseTest extends TestCase {
	public function test_default_exclusion_keeps_real_books_and_drops_the_rest() {
		$p = on_vignettes_bulk_default_exclusion();
		foreach ( array( 'Orgues Nouvelles n°73', 'ONED12 Dossier', 'CahPart 61', 'Test produit', 'Abonnement numérique', 'Download Jehan Alain', 'Booklet', 'Miscellanées 1 (in English)', 'Calendrier 2027', 'Calendar 2027' ) as $title ) {
			$this->assertTrue( on_vignettes_bulk_title_matches( $title, $p ), $title . ' doit être écarté' );
		}
		foreach ( array( 'Miscellanées 1', 'Jehan Alain', 'Itinéraires', 'Louis Thiry' ) as $title ) {
			$this->assertFalse( on_vignettes_bulk_title_matches( $title, $p ), $title . ' doit être gardé' );
		}
	}

	public function test_titles_are_compared_without_tags_entities_or_case() {
		$this->assertTrue( on_vignettes_bulk_title_matches( '<em>CALENDRIER</em> 2027', 'calendrier' ) );
		$this->assertTrue( on_vignettes_bulk_title_matches( 'L&#8217;orgue &amp; moi', 'L’orgue & moi' ) );
	}

	public function test_empty_or_broken_patterns_exclude_nothing() {
		$this->assertTrue( on_vignettes_bulk_valid_pattern( '' ) );
		$this->assertFalse( on_vignettes_bulk_title_matches( 'Calendrier', '' ) );
		$this->assertFalse( on_vignettes_bulk_valid_pattern( '(' ) );
		$this->assertFalse( on_vignettes_bulk_title_matches( 'Calendrier', '(' ) );
	}

	public function test_a_hash_in_the_pattern_does_not_break_the_delimiters() {
		$this->assertTrue( on_vignettes_bulk_valid_pattern( 'N#1' ) );
		$this->assertTrue( on_vignettes_bulk_title_matches( 'Cahier N#1', 'N#1' ) );
	}

	public function test_params_keep_only_allowed_types_and_valid_values() {
		$allowed = array( 'post', 'ressource', 'cd', 'product' );
		$p       = on_vignettes_bulk_clean_params( array( 'types' => array( 'post', 'cd', 'cd', 'page', 'attachment' ), 'since' => '2025-11-01', 'exclude' => 'Test', 'only_missing' => '1' ), $allowed );
		$this->assertSame( array( 'post', 'cd' ), $p['types'] );
		$this->assertSame( '2025-11-01', $p['since'] );
		$this->assertTrue( $p['only_missing'] );
		$this->assertSame( '', $p['error'] );
	}

	public function test_params_report_the_first_problem() {
		$allowed = array( 'post', 'cd' );
		$this->assertSame( 'types', on_vignettes_bulk_clean_params( array( 'types' => array( 'page' ) ), $allowed )['error'] );
		$this->assertSame( 'date', on_vignettes_bulk_clean_params( array( 'types' => array( 'post' ), 'since' => '2025-02-31' ), $allowed )['error'] );
		$this->assertSame( 'date', on_vignettes_bulk_clean_params( array( 'types' => array( 'post' ), 'since' => '01/11/2025' ), $allowed )['error'] );
		$this->assertSame( 'motif', on_vignettes_bulk_clean_params( array( 'types' => array( 'post' ), 'exclude' => '(' ), $allowed )['error'] );
		$this->assertFalse( on_vignettes_bulk_clean_params( array( 'types' => array( 'post' ), 'only_missing' => '0' ), $allowed )['only_missing'] );
	}

	public function test_queue_is_cut_into_batches_without_losing_anything() {
		$queue = range( 1, 12 );
		$seen  = array();
		while ( $queue ) {
			$t     = on_vignettes_bulk_take( $queue, 5 );
			$seen  = array_merge( $seen, $t['batch'] );
			$queue = $t['rest'];
		}
		$this->assertSame( range( 1, 12 ), $seen );
		$this->assertSame( array( 'batch' => array(), 'rest' => array() ), on_vignettes_bulk_take( array(), 5 ) );
	}
}
