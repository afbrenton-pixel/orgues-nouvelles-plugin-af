<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }

require_once __DIR__ . '/gratuite-i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/gratuite/i18n.php';
require_once dirname( __DIR__ ) . '/includes/gratuite/request-rules.php';
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'add_shortcode' ) ) { function add_shortcode() {} }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
require_once dirname( __DIR__ ) . '/includes/gratuite/period-notice.php';

class GratuitePeriodNoticeTest extends TestCase {
    public function test_nothing_while_the_offer_is_open() {
        $this->assertSame( '', on_gratuite_period_notice_html( 'open', '2027-03-31', 'fr' ) );
    }

    public function test_not_yet_open_in_both_languages() {
        $fr = on_gratuite_period_notice_html( 'not_yet', '2026-12-01', 'fr' );
        $en = on_gratuite_period_notice_html( 'not_yet', '2026-12-01', 'en' );
        $this->assertStringContainsString( 'on-gratuite-avis--not_yet', $fr );
        $this->assertStringContainsString( 'ouvriront le 01/12/2026', $fr );
        $this->assertStringContainsString( 'not open yet', $en );
        $this->assertStringContainsString( 'December 1, 2026', $en );
    }

    public function test_closed_in_both_languages() {
        $fr = on_gratuite_period_notice_html( 'closed', '2027-03-31', 'fr' );
        $en = on_gratuite_period_notice_html( 'closed', '2027-03-31', 'en' );
        $this->assertStringContainsString( 'terminée', $fr );
        $this->assertStringContainsString( '31/03/2027', $fr );
        $this->assertStringContainsString( 'March 31, 2027', $en );
    }
}
