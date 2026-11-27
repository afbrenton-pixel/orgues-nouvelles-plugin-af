<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/gratuite-i18n-stubs.php';

/**
 * Garde-fous des textes du comptage des lectures et téléchargements : chaque texte écrit en français dans le code a
 * son texte anglais dans le fichier compilé, et le code ne choisit aucun texte selon la langue.
 */
class UsageTranslationsTest extends TestCase {
    private function files() {
        $root = dirname( __DIR__ );
        return array(
            $root . '/includes/usage/dynamic-tags.php',
            $root . '/includes/usage/rest-api.php',
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
            $this->assertDoesNotMatchRegularExpression( "/===\s*'en'\s*\)/", $code, basename( $file ) );
        }
    }
}
