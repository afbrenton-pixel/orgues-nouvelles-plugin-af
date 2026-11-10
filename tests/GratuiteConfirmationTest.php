<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }
if ( ! function_exists( 'add_action' ) ) {
    function add_action() {}
}

require_once dirname( __DIR__ ) . '/includes/gratuite/confirmation.php';

class GratuiteConfirmationTest extends TestCase {
    private const KEY = 'test-key';

    private function click( array $over = array() ) {
        $token = str_repeat( 'ab', 16 );
        return on_gratuite_check_confirmation( array_merge( array(
            'status'      => 'a_confirmer',
            'stored_hash' => on_gratuite_token_hash( $token, 12, self::KEY ),
            'token'       => $token,
            'post_id'     => 12,
            'key'         => self::KEY,
            'created'     => 1000000,
            'now'         => 1000000 + 3 * 86400,
            'days'        => 7,
        ), $over ) );
    }

    public function test_tokens_are_random_hexadecimal() {
        $a = on_gratuite_new_token();
        $this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $a );
        $this->assertNotSame( $a, on_gratuite_new_token() );
    }

    public function test_the_stored_hash_depends_on_token_request_and_key() {
        $h = on_gratuite_token_hash( 'aa', 1, 'k' );
        $this->assertNotSame( $h, on_gratuite_token_hash( 'ab', 1, 'k' ) );
        $this->assertNotSame( $h, on_gratuite_token_hash( 'aa', 2, 'k' ) );
        $this->assertNotSame( $h, on_gratuite_token_hash( 'aa', 1, 'other' ) );
        $this->assertSame( 64, strlen( $h ) );
        $this->assertStringNotContainsString( 'secret-token-value', on_gratuite_token_hash( 'secret-token-value', 1, 'k' ) );
    }

    public function test_a_correct_click_within_the_delay_confirms() {
        $this->assertSame( 'ok', $this->click() );
    }

    public function test_a_click_on_the_last_day_still_works_but_not_after() {
        $this->assertSame( 'ok', $this->click( array( 'now' => 1000000 + 7 * 86400 ) ) );
        $this->assertSame( 'expired', $this->click( array( 'now' => 1000000 + 7 * 86400 + 1 ) ) );
    }

    public function test_a_wrong_or_malformed_token_is_invalid() {
        $this->assertSame( 'invalid', $this->click( array( 'token' => str_repeat( 'cd', 16 ) ) ) );
        $this->assertSame( 'invalid', $this->click( array( 'token' => 'short' ) ) );
        $this->assertSame( 'invalid', $this->click( array( 'token' => '' ) ) );
    }

    public function test_a_token_does_not_work_for_another_request() {
        $this->assertSame( 'invalid', $this->click( array( 'post_id' => 13 ) ) );
    }

    public function test_a_missing_hash_or_other_status_is_invalid_or_already_done() {
        $this->assertSame( 'invalid', $this->click( array( 'stored_hash' => '' ) ) );
        $this->assertSame( 'already', $this->click( array( 'status' => 'en_attente' ) ) );
        $this->assertSame( 'already', $this->click( array( 'status' => 'acceptee' ) ) );
        $this->assertSame( 'already', $this->click( array( 'status' => 'refusee' ) ) );
        $this->assertSame( 'invalid', $this->click( array( 'status' => '' ) ) );
    }

    public function test_staleness_uses_whole_days() {
        $this->assertFalse( on_gratuite_is_stale( 0, 7 * 86400, 7 ) );
        $this->assertTrue( on_gratuite_is_stale( 0, 7 * 86400 + 1, 7 ) );
    }

    public function test_mail_is_in_the_applicants_language_with_the_link_and_the_delay() {
        $fr = on_gratuite_confirmation_mail( 'fr', 'Léa', 'https://x.test/?k=1', 7 );
        $en = on_gratuite_confirmation_mail( 'en', 'Lea', 'https://x.test/?k=1', 7 );
        $this->assertStringContainsString( 'Bonjour Léa', $fr['body'] );
        $this->assertStringContainsString( 'Hello Lea', $en['body'] );
        foreach ( array( $fr, $en ) as $m ) {
            $this->assertStringContainsString( 'https://x.test/?k=1', $m['body'] );
            $this->assertStringContainsString( '7', $m['body'] );
            $this->assertNotSame( '', $m['subject'] );
        }
    }

    public function test_page_messages_exist_for_every_result_in_both_languages() {
        foreach ( array( 'ok', 'already', 'expired', 'invalid' ) as $r ) {
            foreach ( array( 'fr', 'en' ) as $lang ) {
                list( $title, $text ) = on_gratuite_confirmation_page_message( $r, $lang );
                $this->assertNotSame( '', $title );
                $this->assertNotSame( '', $text );
            }
        }
        $this->assertSame( on_gratuite_confirmation_page_message( 'invalid' ), on_gratuite_confirmation_page_message( 'unknown' ) );
    }
    public function test_mail_for_another_person_says_so() {
        $fr = on_gratuite_confirmation_mail( 'fr', 'Léa', 'https://x.test/?k=1', 7, true );
        $en = on_gratuite_confirmation_mail( 'en', 'Lea', 'https://x.test/?k=1', 7, true );
        $this->assertStringContainsString( 'faite pour vous', $fr['body'] );
        $this->assertStringContainsString( 'made for you', $en['body'] );
        $this->assertStringNotContainsString( 'faite pour vous', on_gratuite_confirmation_mail( 'fr', 'Léa', 'u', 7 )['body'] );
    }
    public function test_default_texts_exist_in_both_languages_with_placeholders() {
        foreach ( array( 'fr', 'en' ) as $lang ) {
            foreach ( array( false, true ) as $other ) {
                $t = on_gratuite_confirmation_texts( $lang, $other );
                foreach ( array( 'subject', 'heading', 'body' ) as $k ) {
                    $this->assertNotSame( '', $t[ $k ] );
                }
                $this->assertStringContainsString( '{link}', $t['body'] );
                $this->assertStringContainsString( '{days}', $t['body'] );
            }
        }
        $this->assertNotSame( on_gratuite_confirmation_texts( 'fr' )['body'], on_gratuite_confirmation_texts( 'fr', true )['body'] );
    }

    public function test_placeholders_are_filled() {
        $out = on_gratuite_fill_confirmation_text( 'Salut {first_name}, {link} ({days} jours)', 'Léa', 'https://x.test/?k=1', 7 );
        $this->assertSame( 'Salut Léa, https://x.test/?k=1 (7 jours)', $out );
        $this->assertStringNotContainsString( '{', $out );
    }
}
