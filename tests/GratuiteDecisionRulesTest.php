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

    public function test_copy_for_the_requester_names_the_person_and_gives_the_end_date() {
        $vars = array( 'name' => 'Jules DUPONT', 'email' => 'j@x.org', 'end_date' => '7 novembre 2027' );
        foreach ( array( 'fr', 'en' ) as $lang ) {
            $texts = on_gratuite_copy_texts( 'accepted', $lang );
            $body  = on_gratuite_fill_copy_text( $texts['body'], $vars, $lang );
            $this->assertStringContainsString( 'Jules DUPONT (j@x.org)', $body, $lang );
            $this->assertStringContainsString( '7 novembre 2027', $body, $lang );
            $this->assertStringContainsString( 'Jules DUPONT', on_gratuite_fill_copy_text( $texts['subject'], $vars, $lang ), $lang );
            $this->assertStringNotContainsString( '{', $body, $lang );
        }
        $this->assertStringContainsString( 'Pour information', on_gratuite_copy_texts( 'accepted', 'fr' )['body'] );
        $this->assertStringContainsString( 'For your information', on_gratuite_copy_texts( 'accepted', 'en' )['body'] );
    }

    public function test_copy_for_the_requester_says_nothing_about_reading_the_account_or_renewal() {
        foreach ( array( 'accepted', 'refused' ) as $kind ) {
            foreach ( array( 'fr', 'en' ) as $lang ) {
                $t    = on_gratuite_copy_texts( $kind, $lang );
                $text = $t['subject'] . ' ' . $t['heading'] . ' ' . $t['body'];
                foreach ( array( '{account_url}', '{form_url}', 'Mon compte', 'My account', 'lire', 'read', 'conserver', 'keep it', 'supprimé', 'deleted' ) as $forbidden ) {
                    $this->assertStringNotContainsString( $forbidden, $text, "$kind $lang $forbidden" );
                }
            }
        }
    }

    public function test_copy_of_a_refusal_carries_the_reason_only_when_there_is_one() {
        $fr   = on_gratuite_copy_texts( 'refused', 'fr' )['body'];
        $with = on_gratuite_fill_copy_text( $fr, array( 'name' => 'A B', 'email' => 'a@b.c', 'reason' => ' Document illisible ' ), 'fr' );
        $this->assertStringContainsString( 'Motif : Document illisible', $with );
        $this->assertStringNotContainsString( 'Motif', on_gratuite_fill_copy_text( $fr, array( 'name' => 'A B', 'email' => 'a@b.c' ), 'fr' ) );
        $en = on_gratuite_fill_copy_text( on_gratuite_copy_texts( 'refused', 'en' )['body'], array( 'name' => 'A B', 'email' => 'a@b.c', 'reason' => 'Unreadable' ), 'en' );
        $this->assertStringContainsString( 'Reason: Unreadable', $en );
    }

    public function test_a_decision_lock_is_abandoned_only_after_its_delay() {
        $this->assertFalse( on_gratuite_lock_is_stale( 1000, 1000 + 600 ) );
        $this->assertTrue( on_gratuite_lock_is_stale( 1000, 1000 + 601 ) );
        $this->assertFalse( on_gratuite_lock_is_stale( 0, 99999 ), 'An unreadable lock is never taken over.' );
    }
}
