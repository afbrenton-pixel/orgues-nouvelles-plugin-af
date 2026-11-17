<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/gratuite-i18n-stubs.php';

/**
 * Guards for the translation files: every text of the module written in French in the code has an English
 * text in the compiled file, and the code holds no "if English then ..., else ..." branch on texts.
 */
class GratuiteTranslationsTest extends TestCase {
    private function module_files() {
        return glob( dirname( __DIR__ ) . '/includes/gratuite/*.php' );
    }

    public function test_every_text_of_the_module_has_an_english_translation() {
        $mo      = on_gratuite_test_read_mo( dirname( __DIR__ ) . '/languages/orgues-nouvelles-en_GB.mo' );
        $missing = array();
        foreach ( $this->module_files() as $file ) {
            preg_match_all( "/__\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'orgues-nouvelles'\s*\)/", file_get_contents( $file ), $m );
            foreach ( $m[1] as $raw ) {
                $text = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $raw );
                if ( empty( $mo[ $text ] ) ) {
                    $missing[] = $text;
                }
            }
        }
        $this->assertSame( array(), $missing, 'Texts without English translation in languages/orgues-nouvelles-en_GB.po (then recompile the .mo).' );
    }

    public function test_texts_are_not_chosen_by_language_in_the_code() {
        foreach ( $this->module_files() as $file ) {
            $code = file_get_contents( $file );
            $this->assertDoesNotMatchRegularExpression( "/'en'\s*===\s*\\\$\w+\s*\?\s*'[^']*[a-z]{3}[^']*'\s*:\s*'/", $code, basename( $file ) );
            $this->assertStringNotContainsString( "'Hello '", $code, basename( $file ) );
        }
    }
}
