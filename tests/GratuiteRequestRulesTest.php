<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value ) { return $GLOBALS['on_gratuite_test_filters'][ $tag ] ?? $value; }
}

require_once dirname( __DIR__ ) . '/includes/gratuite/settings.php';
require_once dirname( __DIR__ ) . '/includes/gratuite/request-rules.php';

class GratuiteRequestRulesTest extends TestCase {
    private function request( array $over = array() ) {
        return on_gratuite_evaluate_request( array_merge( array(
            'dob'               => '2005-06-15',
            'lang'              => 'fr',
            'logged_in'         => false,
            'email_has_account' => false,
            'has_accepted'      => false,
            'has_pending'       => false,
            'today'             => new DateTimeImmutable( '2026-11-07' ),
        ), $over ) );
    }

    protected function setUp(): void {
        $GLOBALS['on_gratuite_test_filters'] = array();
    }

    public function test_person_aged_21_is_accepted_for_a_first_request() {
        $r = $this->request();
        $this->assertSame( array(), $r['errors'] );
        $this->assertSame( 'premiere', $r['type'] );
        $this->assertSame( 21, $r['age'] );
    }

    public function test_first_request_the_day_before_the_26th_birthday_is_accepted() {
        $r = $this->request( array( 'dob' => '2000-11-08' ) );
        $this->assertSame( 25, $r['age'] );
        $this->assertSame( array(), $r['errors'] );
    }

    public function test_first_request_on_the_26th_birthday_is_refused() {
        $r = $this->request( array( 'dob' => '2000-11-07' ) );
        $this->assertSame( 26, $r['age'] );
        $this->assertArrayHasKey( 'date_naissance', $r['errors'] );
        $this->assertStringContainsString( '26', $r['errors']['date_naissance'] );
    }

    public function test_renewal_accepts_26_but_refuses_27() {
        $this->assertSame( array(), $this->request( array( 'dob' => '2000-01-01', 'has_accepted' => true, 'logged_in' => true ) )['errors'] );
        $r = $this->request( array( 'dob' => '1999-11-07', 'has_accepted' => true, 'logged_in' => true ) );
        $this->assertSame( 'renouvellement', $r['type'] );
        $this->assertArrayHasKey( 'date_naissance', $r['errors'] );
    }

    public function test_invalid_future_or_impossible_dates_are_refused() {
        foreach ( array( '', 'abc', '2030-01-01', '2005-02-30', '1850-01-01', '15/06/2005' ) as $dob ) {
            $this->assertArrayHasKey( 'date_naissance', $this->request( array( 'dob' => $dob ) )['errors'], $dob );
        }
    }

    public function test_existing_account_must_log_in_first() {
        $r = $this->request( array( 'email_has_account' => true ) );
        $this->assertArrayHasKey( 'email', $r['errors'] );
        $this->assertStringContainsString( 'Connectez-vous', $r['errors']['email'] );
        $this->assertSame( array(), $this->request( array( 'email_has_account' => true, 'logged_in' => true ) )['errors'] );
    }

    public function test_pending_request_blocks_a_second_one() {
        $this->assertArrayHasKey( 'email', $this->request( array( 'has_pending' => true ) )['errors'] );
    }

    public function test_messages_exist_in_english() {
        $r = $this->request( array( 'lang' => 'en', 'dob' => '1990-01-01', 'email_has_account' => true ) );
        $this->assertStringContainsString( 'under 26', $r['errors']['date_naissance'] );
        $this->assertStringContainsString( 'log in', $r['errors']['email'] );
        foreach ( array( 'bad_dob', 'login_first', 'pending', 'no_proof', 'bad_proof', 'rate_limit', 'technical' ) as $key ) {
            $this->assertNotSame( '', on_gratuite_message( $key, 'fr' ), $key );
            $this->assertNotSame( on_gratuite_message( $key, 'fr' ), on_gratuite_message( $key, 'en' ), $key );
        }
    }

    public function test_ages_come_from_the_settings() {
        $GLOBALS['on_gratuite_test_filters']['on_gratuite_settings'] = array_merge( on_gratuite_settings(), array( 'age_first' => 30 ) );
        $this->assertSame( array(), $this->request( array( 'dob' => '1997-01-01' ) )['errors'] );
    }

    public function test_early_proof_check_on_extension_and_size() {
        $this->assertTrue( on_gratuite_proof_is_acceptable( 'carte.PDF', 1000 ) );
        $this->assertTrue( on_gratuite_proof_is_acceptable( 'photo.jpeg', 5 * 1024 * 1024 ) );
        $this->assertFalse( on_gratuite_proof_is_acceptable( 'virus.php', 1000 ) );
        $this->assertFalse( on_gratuite_proof_is_acceptable( 'carte.pdf', 0 ) );
        $this->assertFalse( on_gratuite_proof_is_acceptable( 'carte.pdf', 6 * 1024 * 1024 ) );
        $this->assertFalse( on_gratuite_proof_is_acceptable( 'sans-extension', 1000 ) );
    }

    public function test_only_safe_actions_survive_when_the_free_action_is_used() {
        $this->assertSame( array( 'on_gratuite', 'redirect' ), on_gratuite_filter_submit_actions( array( 'save-to-database', 'email', 'on_gratuite', 'webhook', 'redirect' ) ) );
        $this->assertSame( array( 'email', 'redirect' ), on_gratuite_filter_submit_actions( array( 'email', 'redirect' ) ) );
    }
}
