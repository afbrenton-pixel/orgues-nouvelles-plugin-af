<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }

$GLOBALS['on_gratuite_test_options'] = array();
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) {
        return $GLOBALS['on_gratuite_test_options'][ $name ] ?? $default;
    }
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value ) { return $value; }
}

require_once dirname( __DIR__ ) . '/includes/gratuite/settings.php';

class GratuiteSettingsTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['on_gratuite_test_options'] = array();
    }

    private function set( $field, $value ) {
        $GLOBALS['on_gratuite_test_options'][ 'on_gratuite_reglages_' . $field ] = $value;
    }

    public function test_defaults_when_nothing_is_set() {
        $s = on_gratuite_settings();
        $this->assertSame( 0, $s['form_page_id'] );
        $this->assertSame( 257, $s['variation_fr'] );
        $this->assertSame( 30365, $s['variation_en'] );
        $this->assertSame( 'etudiant@orgues-nouvelles.org', $s['notify_email'] );
        $this->assertSame( 5, $s['rate_limit_per_hour'] );
    }

    public function test_values_of_the_settings_page_replace_the_defaults() {
        $this->set( 'variation_fr', '999' );
        $this->set( 'variation_en', '1000' );
        $this->set( 'limite_par_heure', '10' );
        $this->set( 'email_equipe', ' equipe@example.org ' );
        $s = on_gratuite_settings();
        $this->assertSame( 999, $s['variation_fr'] );
        $this->assertSame( 1000, $s['variation_en'] );
        $this->assertSame( 10, $s['rate_limit_per_hour'] );
        $this->assertSame( 'equipe@example.org', $s['notify_email'] );
    }

    public function test_invalid_values_keep_the_defaults() {
        $this->set( 'variation_fr', 'abc' );
        $this->set( 'variation_en', '0' );
        $this->set( 'limite_par_heure', '-3' );
        $this->set( 'email_equipe', 'pas une adresse' );
        $s = on_gratuite_settings();
        $this->assertSame( 257, $s['variation_fr'] );
        $this->assertSame( 30365, $s['variation_en'] );
        $this->assertSame( 5, $s['rate_limit_per_hour'] );
        $this->assertSame( 'etudiant@orgues-nouvelles.org', $s['notify_email'] );
    }

    public function test_form_page_is_read_whatever_shape_the_relationship_field_has() {
        foreach ( array( 282958, '282958', array( 282958 ), array( '282958' ), array( array( 'ID' => 282958 ) ), array( 5 => array( 'id' => '282958' ) ), array( (object) array( 'ID' => 282958 ) ) ) as $value ) {
            $this->set( 'page_formulaire', $value );
            $this->assertSame( 282958, on_gratuite_settings()['form_page_id'], print_r( $value, true ) );
        }
        foreach ( array( '', array(), 'x', 0, array( array() ) ) as $value ) {
            $this->set( 'page_formulaire', $value );
            $this->assertSame( 0, on_gratuite_settings()['form_page_id'], print_r( $value, true ) );
        }
    }

    public function test_promotional_dates_are_still_read() {
        $this->set( 'promo_debut', '2026-12-01 00:00:00' );
        $this->set( 'promo_fin', '2027-03-31' );
        $s = on_gratuite_settings();
        $this->assertSame( '2026-12-01', $s['promo_start'] );
        $this->assertSame( '2027-03-31', $s['promo_end'] );
    }
}
