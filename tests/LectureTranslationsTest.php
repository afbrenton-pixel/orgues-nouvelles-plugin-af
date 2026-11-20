<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/gratuite-i18n-stubs.php';

/**
 * Garde-fous des textes de la lecture en ligne et du comptage : chaque texte écrit en français dans le code a
 * son texte anglais dans le fichier compilé, et le code ne choisit aucun texte selon la langue.
 */
class LectureTranslationsTest extends TestCase {
    private function files() {
        $root = dirname( __DIR__ );
        return array(
            $root . '/includes/elementor/widgets/online-reader.php',
            $root . '/includes/products/read-online.php',
        );
    }

    private function unescape( $raw ) {
        return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $raw );
    }

    public function test_every_text_has_an_english_translation() {
        $mo      = on_gratuite_test_read_mo( dirname( __DIR__ ) . '/languages/orgues-nouvelles-en_GB.mo' );
        $missing = array();
        foreach ( $this->files() as $file ) {
            $code = file_get_contents( $file );
            preg_match_all( "/(?:esc_html__|esc_attr__|__)\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'orgues-nouvelles'\s*\)/", $code, $m );
            foreach ( $m[1] as $raw ) {
                $text = $this->unescape( $raw );
                if ( empty( $mo[ $text ] ) ) {
                    $missing[] = $text;
                }
            }
            preg_match_all( "/_x\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'orgues-nouvelles'\s*\)/", $code, $x );
            foreach ( $x[1] as $i => $raw ) {
                $key = $this->unescape( $x[2][ $i ] ) . "\x04" . $this->unescape( $raw );
                if ( empty( $mo[ $key ] ) ) {
                    $missing[] = $key;
                }
            }
        }
        $this->assertSame( array(), $missing, 'Textes sans traduction anglaise dans languages/orgues-nouvelles-en_GB.po (puis recompiler le .mo).' );
    }

    public function test_texts_are_not_chosen_by_language_in_the_code() {
        foreach ( $this->files() as $file ) {
            $code = file_get_contents( $file );
            $this->assertStringNotContainsString( "'Read online'", $code, basename( $file ) );
            $this->assertDoesNotMatchRegularExpression( "/===\s*'en'\s*\)/", $code, basename( $file ) );
        }
    }

    public function test_reader_does_not_read_the_page_language() {
        $this->assertStringNotContainsString( 'pll_current_language', file_get_contents( dirname( __DIR__ ) . '/includes/elementor/widgets/online-reader.php' ) );
    }

    public function test_zoom_texts_do_not_use_the_unrelated_translations() {
        $mo = on_gratuite_test_read_mo( dirname( __DIR__ ) . '/languages/orgues-nouvelles-en_GB.mo' );
        $this->assertSame( 'Zoom out', $mo["zoom du lecteur\x04Réduire"] );
        $this->assertSame( 'Zoom in', $mo["zoom du lecteur\x04Agrandir"] );
    }
}
