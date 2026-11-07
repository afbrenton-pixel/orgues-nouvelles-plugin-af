<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }

require_once dirname( __DIR__ ) . '/includes/gratuite/decision-rules.php';

class GratuiteDecisionRulesTest extends TestCase {
    public function test_only_a_confirmed_waiting_request_can_be_decided() {
        $this->assertTrue( on_gratuite_can_decide( 'en_attente' ) );
        foreach ( array( 'a_confirmer', 'acceptee', 'refusee', '' ) as $s ) {
            $this->assertFalse( on_gratuite_can_decide( $s ), $s );
        }
    }

    public function test_every_text_exists_in_both_languages() {
        foreach ( array( 'accepted', 'refused' ) as $kind ) {
            foreach ( array( 'fr', 'en' ) as $lang ) {
                $t = on_gratuite_decision_texts( $kind, $lang );
                $this->assertNotSame( '', $t['subject'] );
                $this->assertNotSame( '', $t['heading'] );
                $this->assertNotSame( '', $t['body'] );
            }
        }
        $this->assertNotSame( on_gratuite_decision_texts( 'accepted', 'fr' )['subject'], on_gratuite_decision_texts( 'accepted', 'en' )['subject'] );
    }

    public function test_acceptance_text_gives_end_date_and_account_link() {
        $body = on_gratuite_fill_decision_text( on_gratuite_decision_texts( 'accepted', 'fr' )['body'], array( 'end_date' => '7 novembre 2027', 'account_url' => 'https://x.org/mon-compte/' ) );
        $this->assertStringContainsString( '7 novembre 2027', $body );
        $this->assertStringContainsString( 'https://x.org/mon-compte/', $body );
        $this->assertStringNotContainsString( '{', $body );
    }

    public function test_refusal_text_offers_to_apply_again_and_shows_the_reason_only_when_given() {
        $vars = array( 'form_url' => 'https://x.org/gratuite/', 'contact' => 'etudiant@x.org' );
        $raw  = on_gratuite_decision_texts( 'refused', 'fr' )['body'];
        $without = on_gratuite_fill_decision_text( $raw, $vars );
        $this->assertStringContainsString( 'https://x.org/gratuite/', $without );
        $this->assertStringNotContainsString( 'Motif', $without );
        $this->assertStringNotContainsString( '{', $without );
        $with = on_gratuite_fill_decision_text( $raw, $vars + array( 'reason' => ' Document illisible ' ) );
        $this->assertStringContainsString( 'Motif : Document illisible', $with );
        $en = on_gratuite_fill_decision_text( on_gratuite_decision_texts( 'refused', 'en' )['body'], $vars + array( 'reason' => 'Unreadable' ), 'en' );
        $this->assertStringContainsString( 'Reason: Unreadable', $en );
    }

    public function test_copy_intro_names_the_beneficiary_in_both_languages() {
        $fr = on_gratuite_copy_intro( 'fr', 'Jules DUPONT', 'j@x.org' );
        $this->assertStringContainsString( 'Jules DUPONT (j@x.org)', $fr );
        $this->assertStringContainsString( 'Copie', $fr );
        $this->assertStringContainsString( 'Copy for your information', on_gratuite_copy_intro( 'en', 'A B', 'a@b.c' ) );
    }
}
