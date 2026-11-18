<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }

require_once dirname( __DIR__ ) . '/includes/vignettes/migration-prefixe.php';

class VignettesPrefixMigrationTest extends TestCase {
	public function test_css_classes_get_the_on_prefix() {
		$this->assertSame( 'on-card on-has-audio', on_vignettes_rename_prefix( 'onv-card onv-has-audio' ) );
		$this->assertSame( '.on-pl li.on-pl-open', on_vignettes_rename_prefix( '.onv-pl li.onv-pl-open' ) );
		$this->assertSame( 'data-on-audio="1"', on_vignettes_rename_prefix( 'data-onv-audio="1"' ) );
	}

	public function test_the_playing_item_class_is_readable() {
		$this->assertSame( '.on-pl li.on-current { color: red; }', on_vignettes_rename_prefix( '.onv-pl li.onv-on { color: red; }' ) );
		$this->assertSame( 'on-on-air', on_vignettes_rename_prefix( 'onv-on-air' ), 'seul le nom exact onv-on change de forme' );
	}

	public function test_other_words_and_already_migrated_names_are_untouched() {
		$this->assertSame( 'conv-x on-fr on-sticky ponv-y', on_vignettes_rename_prefix( 'conv-x on-fr on-sticky ponv-y' ) );
		$this->assertSame( 'on-card', on_vignettes_rename_prefix( on_vignettes_rename_prefix( 'onv-card' ) ), 'deuxième passage sans effet' );
	}

	public function test_elementor_json_stays_valid() {
		$json = '{"settings":{"css_classes":"onv-col-centre on-fr","custom_css":".onv-sep-title{margin:0}\\n"}}';
		$new  = on_vignettes_rename_prefix( $json );
		$this->assertSame( '{"settings":{"css_classes":"on-col-centre on-fr","custom_css":".on-sep-title{margin:0}\\n"}}', $new );
		$this->assertNotNull( json_decode( $new, true ) );
	}

	public function test_arrays_are_renamed_in_keys_and_values() {
		$in  = array( 'custom_css' => '.onv-fixes-row{gap:0}', 'onv-x' => array( 'a' => 'onv-w-titre', 'n' => 3 ) );
		$out = on_vignettes_rename_prefix( $in );
		$this->assertSame( array( 'custom_css' => '.on-fixes-row{gap:0}', 'on-x' => array( 'a' => 'on-w-titre', 'n' => 3 ) ), $out );
	}
}
