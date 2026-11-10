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

    public function test_first_request_is_accepted_at_26_until_the_day_before_the_27th_birthday() {
        $r = $this->request( array( 'dob' => '1999-11-08' ) ); // Turns 27 tomorrow.
        $this->assertSame( 26, $r['age'] );
        $this->assertSame( array(), $r['errors'] );
        $r = $this->request( array( 'dob' => '2000-11-07' ) ); // Turned 26 today.
        $this->assertSame( 26, $r['age'] );
        $this->assertSame( array(), $r['errors'] );
    }

    public function test_first_request_on_the_27th_birthday_is_refused() {
        $r = $this->request( array( 'dob' => '1999-11-07' ) );
        $this->assertSame( 27, $r['age'] );
        $this->assertArrayHasKey( 'date_naissance', $r['errors'] );
        $this->assertStringContainsString( 'jusqu\'à 26 ans', $r['errors']['date_naissance'] );
        $this->assertStringContainsString( '27e anniversaire', $r['errors']['date_naissance'] );
    }

    public function test_renewal_follows_the_same_rule_as_the_first_request() {
        $this->assertSame( array(), $this->request( array( 'dob' => '2000-01-01', 'has_accepted' => true, 'logged_in' => true ) )['errors'] );
        $this->assertSame( array(), $this->request( array( 'dob' => '1999-11-08', 'has_accepted' => true, 'logged_in' => true ) )['errors'] );
        $r = $this->request( array( 'dob' => '1999-11-07', 'has_accepted' => true, 'logged_in' => true ) );
        $this->assertSame( 'renouvellement', $r['type'] );
        $this->assertArrayHasKey( 'date_naissance', $r['errors'] );
        $this->assertSame( on_gratuite_settings()['age_first'], on_gratuite_settings()['age_renew'] );
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
        $this->assertStringContainsString( '26 or under', $r['errors']['date_naissance'] );
        $this->assertStringContainsString( 'log in', $r['errors']['email'] );
        foreach ( array( 'bad_dob', 'login_first', 'pending', 'no_proof', 'bad_proof', 'rate_limit', 'technical' ) as $key ) {
            $this->assertNotSame( '', on_gratuite_message( $key, 'fr' ), $key );
            $this->assertNotSame( on_gratuite_message( $key, 'fr' ), on_gratuite_message( $key, 'en' ), $key );
        }
    }

    public function test_unconfirmed_requests_are_purged_automatically() {
        $this->assertTrue( on_gratuite_settings()['auto_purge_unconfirmed'] );
        $this->assertSame( 7, on_gratuite_settings()['confirm_days'] );
    }

    public function test_ages_come_from_the_settings() {
        $GLOBALS['on_gratuite_test_filters']['on_gratuite_settings'] = array_merge( on_gratuite_settings(), array( 'age_first' => 30 ) );
        $this->assertSame( array(), $this->request( array( 'dob' => '1997-01-01' ) )['errors'] );
    }

    private function with_promo( $start, $end ) {
        $GLOBALS['on_gratuite_test_filters']['on_gratuite_settings'] = array_merge( on_gratuite_settings(), array( 'promo_start' => $start, 'promo_end' => $end ) );
    }

    public function test_promo_period_without_dates_is_always_open() {
        $this->assertSame( 'open', on_gratuite_promo_state( '', '', new DateTimeImmutable( '2026-11-07' ) ) );
        $this->assertArrayNotHasKey( 'promo', $this->request()['errors'] );
    }

    public function test_promo_period_includes_the_first_and_the_last_day() {
        $today = new DateTimeImmutable( '2026-11-07' );
        $this->assertSame( 'open', on_gratuite_promo_state( '2026-11-07', '2026-11-30', $today ) );
        $this->assertSame( 'open', on_gratuite_promo_state( '2026-11-01', '2026-11-07', $today ) );
        $this->assertSame( 'not_yet', on_gratuite_promo_state( '2026-11-08', '2026-11-30', $today ) );
        $this->assertSame( 'closed', on_gratuite_promo_state( '2026-11-01', '2026-11-06', $today ) );
        $this->assertSame( 'open', on_gratuite_promo_state( '', '2026-11-30', $today ) );
        $this->assertSame( 'not_yet', on_gratuite_promo_state( '2026-12-01', '', $today ) );
        $this->assertSame( 'open', on_gratuite_promo_state( 'pas une date', 'xx', $today ) );
    }

    public function test_first_request_is_refused_outside_the_promo_period_with_the_date_in_the_message() {
        $this->with_promo( '2026-12-01', '2026-12-31' );
        $r = $this->request();
        $this->assertStringContainsString( '01/12/2026', $r['errors']['promo'] );
        $this->assertStringContainsString( 'pas encore ouverte', $r['errors']['promo'] );
        $this->assertStringContainsString( 'December 1, 2026', $this->request( array( 'lang' => 'en' ) )['errors']['promo'] );

        $this->with_promo( '2026-10-01', '2026-11-06' );
        $r = $this->request();
        $this->assertStringContainsString( 'terminée', $r['errors']['promo'] );
        $this->assertStringContainsString( '06/11/2026', $r['errors']['promo'] );
        $this->assertStringContainsString( 'has ended', $this->request( array( 'lang' => 'en' ) )['errors']['promo'] );
    }

    public function test_first_request_is_accepted_inside_the_promo_period() {
        $this->with_promo( '2026-11-06', '2026-11-30' );
        $this->assertSame( array(), $this->request()['errors'] );
    }

    public function test_renewal_is_never_limited_by_the_promo_period() {
        $this->with_promo( '2026-12-01', '2026-12-31' );
        $this->assertSame( array(), $this->request( array( 'has_accepted' => true, 'logged_in' => true ) )['errors'] );
        $this->with_promo( '2026-01-01', '2026-01-31' );
        $this->assertSame( array(), $this->request( array( 'has_accepted' => true, 'logged_in' => true ) )['errors'] );
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
    public function test_logged_in_person_cannot_type_another_address_for_herself() {
        $base = array( 'logged_in' => true, 'account_email' => 'moi@example.org' );
        $r    = $this->request( array_merge( $base, array( 'typed_email' => 'autre@example.org' ) ) );
        $this->assertArrayHasKey( 'email', $r['errors'] );
        $this->assertStringContainsString( 'moi@example.org', $r['errors']['email'] );
        $this->assertSame( array(), $this->request( array_merge( $base, array( 'typed_email' => 'MOI@example.org' ) ) )['errors'] );
        $this->assertSame( array(), $this->request( array_merge( $base, array( 'typed_email' => '' ) ) )['errors'] );
    }

    public function test_request_for_another_person_needs_a_logged_in_requester() {
        $r = $this->request( array( 'for_other' => true, 'typed_email' => 'jeune@example.org' ) );
        $this->assertArrayHasKey( 'pour_autre', $r['errors'] );
        $this->assertArrayNotHasKey( 'email', $r['errors'] );
    }

    public function test_request_for_another_person_needs_a_different_address() {
        $r = $this->request( array( 'for_other' => true, 'logged_in' => true, 'account_email' => 'moi@example.org', 'typed_email' => 'Moi@example.org' ) );
        $this->assertArrayHasKey( 'email', $r['errors'] );
        $this->assertSame( array(), $this->request( array( 'for_other' => true, 'logged_in' => true, 'account_email' => 'moi@example.org', 'typed_email' => 'jeune@example.org' ) )['errors'] );
    }

    public function test_beneficiary_with_an_existing_account_is_accepted_for_a_gift() {
        $r = $this->request( array( 'for_other' => true, 'logged_in' => true, 'account_email' => 'moi@example.org', 'typed_email' => 'jeune@example.org', 'email_has_account' => true ) );
        $this->assertSame( array(), $r['errors'] );
    }

    public function test_age_is_checked_on_the_beneficiary_date_of_birth() {
        $r = $this->request( array( 'for_other' => true, 'logged_in' => true, 'account_email' => 'moi@example.org', 'typed_email' => 'adulte@example.org', 'dob' => '1990-01-01' ) );
        $this->assertArrayHasKey( 'date_naissance', $r['errors'] );
    }

    public function test_new_messages_exist_in_both_languages() {
        foreach ( array( 'email_mismatch', 'login_for_other', 'same_email' ) as $key ) {
            $this->assertNotSame( '', on_gratuite_message( $key, 'fr', 0, 'a@b.c' ) );
            $this->assertNotSame( on_gratuite_message( $key, 'fr', 0, 'a@b.c' ), on_gratuite_message( $key, 'en', 0, 'a@b.c' ) );
        }
    }
    public function test_pending_message_does_not_hide_a_more_precise_address_error() {
        $r = $this->request( array( 'logged_in' => true, 'account_email' => 'moi@example.org', 'typed_email' => 'autre@example.org', 'has_pending' => true ) );
        $this->assertStringContainsString( 'moi@example.org', $r['errors']['email'] );
    }
}
