<?php
/**
 * Minimal stand-ins for the WordPress translation functions, for the unit tests of the gratuité module.
 *
 * switch_to_locale( 'en_GB' ) makes __() read the real languages/orgues-nouvelles-en_GB.mo of the plugin,
 * so the tests also check that the English texts exist in the compiled file.
 */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }

$GLOBALS['on_gratuite_test_locale_stack'] = array();
$GLOBALS['on_gratuite_test_mo']           = array();

/** Read a .mo file (little-endian, as written by msgfmt and polib) into an array. */
function on_gratuite_test_read_mo( $file ) {
    $d = file_get_contents( $file );
    $h = unpack( 'Vmagic/Vrev/Vn/Vo/Vt', $d );
    $out = array();
    for ( $i = 0; $i < $h['n']; $i++ ) {
        $oi = unpack( 'Vlen/Voff', substr( $d, $h['o'] + 8 * $i, 8 ) );
        $ti = unpack( 'Vlen/Voff', substr( $d, $h['t'] + 8 * $i, 8 ) );
        $out[ substr( $d, $oi['off'], $oi['len'] ) ] = substr( $d, $ti['off'], $ti['len'] );
    }
    return $out;
}

if ( ! function_exists( 'switch_to_locale' ) ) {
    function switch_to_locale( $locale ) {
        $GLOBALS['on_gratuite_test_locale_stack'][] = $locale;
        return true;
    }
}
if ( ! function_exists( 'restore_previous_locale' ) ) {
    function restore_previous_locale() {
        array_pop( $GLOBALS['on_gratuite_test_locale_stack'] );
        return true;
    }
}
if ( ! function_exists( 'unload_textdomain' ) ) {
    function unload_textdomain( $domain ) { $GLOBALS['on_gratuite_test_loaded'] = null; return true; }
}
if ( ! function_exists( 'load_textdomain' ) ) {
    function load_textdomain( $domain, $file ) {
        $GLOBALS['on_gratuite_test_mo'][ $file ] = on_gratuite_test_read_mo( $file );
        $GLOBALS['on_gratuite_test_loaded']      = $file;
        return true;
    }
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
    function load_plugin_textdomain() { $GLOBALS['on_gratuite_test_loaded'] = null; return true; }
}
if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
}
if ( ! function_exists( 'wp_date' ) ) {
    function wp_date( $format, $timestamp = null ) {
        // English month names are enough for the tests; French months come from the format only.
        return gmdate( $format, $timestamp );
    }
}
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        $file = $GLOBALS['on_gratuite_test_loaded'] ?? null;
        $mo   = $file ? ( $GLOBALS['on_gratuite_test_mo'][ $file ] ?? array() ) : array();
        return ( isset( $mo[ $text ] ) && '' !== $mo[ $text ] ) ? $mo[ $text ] : $text;
    }
}
