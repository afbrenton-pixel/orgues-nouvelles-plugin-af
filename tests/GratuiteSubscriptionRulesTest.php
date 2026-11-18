<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }

require_once dirname( __DIR__ ) . '/includes/gratuite/subscription-rules.php';

class GratuiteSubscriptionRulesTest extends TestCase {
    private $settings = array( 'variation_fr' => 257, 'variation_en' => 30365 );

    public function test_french_request_for_oneself() {
        $p = on_gratuite_subscription_plan( array( 'langue' => 'fr', 'client_id' => 5 ), $this->settings );
        $this->assertSame( 257, $p['variation_id'] );
        $this->assertFalse( $p['gift'] );
        $this->assertSame( 0, $p['buyer_id'] );
        $this->assertSame( 5, $p['beneficiary_id'] );
    }

    public function test_english_request_uses_the_english_variation() {
        $p = on_gratuite_subscription_plan( array( 'langue' => 'en' ), $this->settings );
        $this->assertSame( 30365, $p['variation_id'] );
    }

    public function test_request_for_another_person_is_a_gift_bought_by_the_requester() {
        $p = on_gratuite_subscription_plan( array( 'langue' => 'fr', 'pour_autre' => '1', 'demandeur_id' => 9 ), $this->settings );
        $this->assertTrue( $p['gift'] );
        $this->assertSame( 9, $p['buyer_id'] );
    }

    public function test_for_other_without_requester_is_not_a_gift() {
        $p = on_gratuite_subscription_plan( array( 'pour_autre' => '1', 'demandeur_id' => 0 ), $this->settings );
        $this->assertFalse( $p['gift'] );
    }

    public function test_fee_cancels_the_price() {
        $this->assertSame( -29.0, on_gratuite_fee_amount( '29.00', 100 ) );
        $this->assertSame( -7.5, on_gratuite_fee_amount( 15, 50 ) );
        $this->assertEquals( 0, on_gratuite_fee_amount( 29, 0 ) );
        $this->assertSame( -15.0, on_gratuite_fee_amount( 15, 250 ) );
    }

    public function test_end_date_is_one_year_later() {
        $this->assertSame( '2027-11-07 10:00:00', on_gratuite_end_date( strtotime( '2026-11-07 10:00:00 UTC' ) ) );
    }

    public function test_username_is_made_unique() {
        $taken = array( 'lea', 'lea1' );
        $exists = function ( $n ) use ( $taken ) { return in_array( $n, $taken, true ); };
        $this->assertSame( 'lea2', on_gratuite_username_from_email( 'Lea@example.org', $exists ) );
        $this->assertSame( 'abonne', on_gratuite_username_from_email( '@x.org', $exists ) );
    }

    public function test_same_name_ignores_case_accents_and_spaces() {
        $this->assertTrue( on_gratuite_same_name( 'Léa', 'Dupont', 'lea', ' DUPONT ' ) );
        $this->assertFalse( on_gratuite_same_name( 'Léa', 'Dupont', 'Léo', 'Dupont' ) );
        $this->assertFalse( on_gratuite_same_name( '', '', '', '' ) );
    }

    public function test_duplicate_lines() {
        $this->assertSame( array( 'Compte n° 12 (a@b.c)' ), on_gratuite_duplicate_lines( array( array( 'kind' => 'Compte', 'id' => 12, 'label' => 'a@b.c' ) ) ) );
    }
}
